<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/asaas.php';
require_once __DIR__.'/includes/single_offer.php';
require_once __DIR__.'/includes/subscription_cancellation.php';
$user=require_family_access();
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);exit('Método não permitido.');}
verify_csrf();$pdo=db();$tid=(int)$user['tenant_id'];$changeId=0;
try{
 if(!bemora_single_offer_enabled($pdo))throw new RuntimeException('Os adicionais ainda não estão disponíveis.');
 if(bemora_asaas_mode()==='production' && (string)config('asaas','addon_upgrades_production_enabled','0')!=='1')throw new RuntimeException('Inclusão de adicionais após a contratação ainda em homologação. Contate o suporte.');
 if(bemora_cancellation_for_family($pdo,$tid))throw new RuntimeException('Não é possível alterar uma assinatura em cancelamento.');
 $role=$pdo->prepare('SELECT role FROM tenant_users WHERE tenant_id=? AND user_id=?');$role->execute([$tid,(int)$user['id']]);
 if($role->fetchColumn()!=='family_admin')throw new RuntimeException('Somente o administrador familiar pode contratar adicionais.');
 $members=filter_var($_POST['members']??null,FILTER_VALIDATE_INT);
 if($members===false||$members===null)throw new RuntimeException('Selecione uma quantidade válida de pessoas.');
 $shopping=($_POST['shopping_addon']??'')==='1';
 $offer=bemora_single_offer($pdo);
 $pdo->beginTransaction();
 $q=$pdo->prepare("SELECT * FROM subscription_orders WHERE activated_tenant_id=? AND single_offer=1 AND status='completed' AND asaas_subscription_id IS NOT NULL ORDER BY id DESC LIMIT 1 FOR UPDATE");
 $q->execute([$tid]);$order=$q->fetch(PDO::FETCH_ASSOC);
 if(!$order)throw new RuntimeException('Assinatura única paga não encontrada.');
 if(!empty($order['coupon_free_until']) && $order['coupon_free_until']>date('Y-m-d'))throw new RuntimeException('Alterações durante cortesia precisam de atendimento.');
 $oldMembers=(int)$order['max_members'];$oldShopping=(int)$order['shopping_addon']===1;
 if($members<$oldMembers||($oldShopping&&!$shopping))throw new RuntimeException('Reduções de adicionais ainda não estão disponíveis nesta etapa.');
 if($members===$oldMembers&&$shopping===$oldShopping)throw new RuntimeException('Nenhum adicional novo foi selecionado.');
 $q=$pdo->prepare('SELECT COUNT(*) FROM tenant_users WHERE tenant_id=?');$q->execute([$tid]);
 if($members<(int)$q->fetchColumn())throw new RuntimeException('A quantidade escolhida não comporta os membros da família.');
 $oldQuote=bemora_single_quote($offer,(string)$order['billing_cycle'],$oldMembers,$oldShopping);
 $newQuote=bemora_single_quote($offer,(string)$order['billing_cycle'],$members,$shopping);
 $oldPrice=(float)$order['recurring_amount'];
 $increment=round($newQuote['total']-$oldQuote['total'],2);
 $newPrice=round($oldPrice+$increment,2);
 if($increment<0.01||$newPrice>99999999)throw new RuntimeException('Adicional sem valor positivo ou fora dos limites.');
 $q=$pdo->prepare("SELECT id FROM bemora_addon_changes WHERE tenant_id=? AND status IN ('processing','awaiting_payment','payment_confirmed','reconciliation_required') LIMIT 1 FOR UPDATE");
 $q->execute([$tid]);if($q->fetchColumn())throw new RuntimeException('Já existe uma solicitação aguardando pagamento ou conciliação.');
 $q=$pdo->prepare("INSERT INTO bemora_addon_changes(tenant_id,order_id,requested_by,old_members,new_members,old_shopping,new_shopping,old_recurring,new_recurring,payment_amount,status) VALUES(?,?,?,?,?,?,?,?,?,?,'processing')");
 $q->execute([$tid,(int)$order['id'],(int)$user['id'],$oldMembers,$members,$oldShopping?1:0,$shopping?1:0,$oldPrice,$newPrice,$increment]);$changeId=(int)$pdo->lastInsertId();
 $pdo->commit();
 $sub=bemora_asaas_request('GET','/subscriptions/'.rawurlencode((string)$order['asaas_subscription_id']));
 if((string)($sub['id']??'')!==(string)$order['asaas_subscription_id'] || (string)($sub['customer']??'')!==(string)$order['asaas_customer_id'] || (string)($sub['status']??'')!=='ACTIVE' || (string)($sub['cycle']??'')!==bemora_asaas_cycle((string)$order['billing_cycle']) || abs((float)($sub['value']??-1)-$oldPrice)>0.009)throw new RuntimeException('Assinatura Asaas divergente. Necessária conciliação.');
 $due=(string)($sub['nextDueDate']??'');
 if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$due))throw new RuntimeException('Data de renovação inválida.');
 $remaining=(int)(new DateTimeImmutable('today'))->diff(new DateTimeImmutable($due))->format('%r%a');
 if($remaining<=0)throw new RuntimeException('Renovação em andamento. Tente após o próximo vencimento.');
 $period=['monthly'=>31,'semiannual'=>184,'annual'=>366][$order['billing_cycle']];
 $prorated=round($increment*min($remaining,$period)/$period,2);
 if($prorated<0.01)throw new RuntimeException('Valor proporcional insuficiente para cobrança.');
 $pdo->prepare('UPDATE bemora_addon_changes SET payment_amount=? WHERE id=?')->execute([$prorated,$changeId]);
 $payment=bemora_asaas_request('POST','/payments',[
  'customer'=>(string)$order['asaas_customer_id'],'billingType'=>'UNDEFINED','value'=>$prorated,
  'dueDate'=>(new DateTimeImmutable('tomorrow'))->format('Y-m-d'),
  'description'=>'Bemora - adicionais proporcionais para assinatura',
  'externalReference'=>'bemora-addon-'.$changeId
 ]);
 $paymentId=(string)($payment['id']??'');$url=bemora_asaas_invoice_url($payment);
 if(!preg_match('/^[A-Za-z0-9_-]{3,100}$/D',$paymentId)||!$url||abs((float)($payment['value']??-1)-$prorated)>0.009)throw new RuntimeException('Resposta de cobrança exige conciliação.');
 $pdo->prepare("UPDATE bemora_addon_changes SET asaas_payment_id=?,invoice_url=?,status='awaiting_payment' WHERE id=? AND status='processing'")->execute([$paymentId,$url,$changeId]);
 flash('success','Adicional solicitado. Conclua o pagamento para ativar os novos recursos.');
}catch(Throwable $e){
 if($pdo->inTransaction())$pdo->rollBack();
 if($changeId){$pdo->prepare("UPDATE bemora_addon_changes SET status='reconciliation_required' WHERE id=? AND status='processing'")->execute([$changeId]);error_log('Bemora addon change '.$changeId.' requires reconciliation: '.$e->getMessage());flash('error','Sua solicitação precisa de conciliação. Nenhum adicional foi liberado.');}
 else flash('error',$e->getMessage());
}
redirect_to('/minha-assinatura/adicionais');

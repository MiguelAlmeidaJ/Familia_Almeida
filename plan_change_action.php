<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/asaas.php';
require_once __DIR__.'/includes/subscription_cancellation.php';
$user=require_family_access();
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);exit;}
verify_csrf();
$pdo=db();$tid=(int)$user['tenant_id'];$changeId=0;
try{
 if(!bemora_asaas_enabled()||bemora_asaas_mode()!=='sandbox')throw new RuntimeException('Alterações pagas disponíveis apenas no Sandbox.');
 $targetId=filter_var($_POST['plan_id']??null,FILTER_VALIDATE_INT);
 $mode=(string)($_POST['change_mode']??'');
 if(!$targetId||!in_array($mode,['immediate','renewal'],true))throw new RuntimeException('Selecione um plano e quando deseja mudar.');
 if($mode!=='immediate')throw new RuntimeException('Troca na renovação ainda indisponível até a confirmação de pagamento do próximo ciclo.');
 if(bemora_cancellation_for_family($pdo,$tid))throw new RuntimeException('Esta assinatura possui cancelamento solicitado ou confirmado. Para contratar novamente, contate o suporte.');
 $pdo->beginTransaction();
 $role=$pdo->prepare("SELECT role FROM tenant_users WHERE tenant_id=? AND user_id=? FOR UPDATE");$role->execute([$tid,(int)$user['id']]);
 if($role->fetchColumn()!=='family_admin')throw new RuntimeException('Somente o administrador familiar pode mudar o plano.');
 $q=$pdo->prepare("SELECT * FROM subscription_orders WHERE activated_tenant_id=? AND status='completed' ORDER BY id DESC LIMIT 1 FOR UPDATE");$q->execute([$tid]);$order=$q->fetch(PDO::FETCH_ASSOC);
 if(!$order||!$order['asaas_subscription_id']||!$order['asaas_customer_id'])throw new RuntimeException('Assinatura não disponível.');
 $trialActive=!empty($order['coupon_free_until']) && $order['coupon_free_until']>date('Y-m-d');
 $q=$pdo->prepare("SELECT * FROM subscription_plans WHERE id=? AND active=1");$q->execute([$targetId]);$target=$q->fetch(PDO::FETCH_ASSOC);
 if(!$target||(int)$target['id']===(int)$order['plan_id'])throw new RuntimeException('Selecione outro plano.');
 if((string)$target['billing_cycle']!==(string)$order['billing_cycle'])throw new RuntimeException('Mudança de periodicidade será habilitada após validação da renovação.');
 $q=$pdo->prepare("SELECT COUNT(*) FROM tenant_users WHERE tenant_id=?");$q->execute([$tid]);if((int)$target['max_members']<(int)$q->fetchColumn())throw new RuntimeException('Limite de membros insuficiente.');
 $q=$pdo->prepare("SELECT id FROM subscription_change_requests WHERE tenant_id=? AND status IN ('scheduled','processing','reconciliation_required','awaiting_payment','payment_confirmed') LIMIT 1 FOR UPDATE");$q->execute([$tid]);
 if($q->fetchColumn())throw new RuntimeException('Existe uma mudança pendente ou exigindo conciliação.');
 $oldPrice=(float)($order['recurring_amount']??$order['amount']);$newPrice=bemora_plan_promo_active($target)?(float)$target['promo_price']:(float)$target['price'];
 if($newPrice<=$oldPrice+0.009)throw new RuntimeException('Downgrades e mudanças sem acréscimo ficam disponíveis no próximo ciclo, após implementar a confirmação da renovação.');
 $pdo->prepare("UPDATE subscription_change_requests SET status='superseded' WHERE tenant_id=? AND status='pending'")->execute([$tid]);
 $q=$pdo->prepare("INSERT INTO subscription_change_requests(tenant_id,order_id,requested_by,from_plan_id,to_plan_id,quoted_amount,old_recurring_amount,payment_amount,status,change_mode) VALUES(?,?,?,?,?,?,?,?,'processing','immediate')");
 // Temporary amount is positive; updated with validated prorated quote before payment creation.
 $q->execute([$tid,(int)$order['id'],(int)$user['id'],(int)$order['plan_id'],$targetId,$newPrice,$oldPrice,round($newPrice-$oldPrice,2)]);
 $changeId=(int)$pdo->lastInsertId();$pdo->commit();
 $sub=bemora_asaas_request('GET','/subscriptions/'.rawurlencode((string)$order['asaas_subscription_id']));
 if((string)($sub['id']??'')!==(string)$order['asaas_subscription_id']||(string)($sub['customer']??'')!==(string)$order['asaas_customer_id']||(string)($sub['status']??'')!=='ACTIVE'||(string)($sub['cycle']??'')!==bemora_asaas_cycle($order['billing_cycle']))throw new RuntimeException('Assinatura Asaas divergente.');
 $due=(string)($sub['nextDueDate']??'');if(!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/D',$due))throw new RuntimeException('Vencimento não disponível.');
 if($trialActive && $due<(string)$order['coupon_free_until'])throw new RuntimeException('Data da cortesia divergente no Asaas. Solicite conciliação antes de trocar.');
 if(abs((float)($sub['value']??-1)-$oldPrice)>0.009)throw new RuntimeException('Valor do Asaas diferente do plano atual. Solicite conciliação.');
 $today=new DateTimeImmutable('today');$next=new DateTimeImmutable($due);
 $days=(int)$today->diff($next)->format('%r%a');if($days<=0)throw new RuntimeException('Renovação em andamento. Tente após o próximo vencimento.');
 $period=['monthly'=>31,'semiannual'=>184,'annual'=>366][$order['billing_cycle']];
 $prorated=round(($newPrice-$oldPrice)*min($days,$period)/$period,2);
 if($prorated<0.01)throw new RuntimeException('Diferença proporcional insuficiente para cobrança.');
 $pdo->prepare('UPDATE subscription_change_requests SET payment_amount=? WHERE id=?')->execute([$prorated,$changeId]);
 $payment=bemora_asaas_request('POST','/payments',[
  'customer'=>(string)$order['asaas_customer_id'],'billingType'=>'UNDEFINED','value'=>$prorated,
  'dueDate'=>$today->modify('+1 day')->format('Y-m-d'),
  'description'=>'Bemora - diferenca proporcional de upgrade para '.$target['name'],
  'externalReference'=>'bemora-change-'.$changeId
 ]);
 $paymentId=(string)($payment['id']??'');$url=bemora_asaas_invoice_url($payment);
 if(!preg_match('/^[A-Za-z0-9_-]{3,100}$/D',$paymentId)||!$url||abs((float)($payment['value']??-1)-$prorated)>0.009)throw new RuntimeException('Cobrança criada com resposta não confirmada. Requer conciliação.');
 $pdo->prepare("UPDATE subscription_change_requests SET asaas_payment_id=?,invoice_url=?,status='awaiting_payment' WHERE id=? AND status='processing'")->execute([$paymentId,$url,$changeId]);
 flash('success','Cobrança de upgrade criada. O plano será liberado somente após o Asaas confirmar o pagamento.');
}catch(Throwable $e){
 if($pdo->inTransaction())$pdo->rollBack();
 if($changeId){$pdo->prepare("UPDATE subscription_change_requests SET status='reconciliation_required' WHERE id=? AND status='processing'")->execute([$changeId]);error_log('Bemora: upgrade request '.$changeId.' needs reconciliation: '.$e->getMessage());flash('error','A solicitação precisa de conciliação no Asaas. Nenhuma mudança de plano foi liberada.');}
 else flash('error',$e->getMessage());
}
redirect_to('/evoluir-plano');

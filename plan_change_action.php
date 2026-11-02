<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/asaas.php';
$user=require_family_access();
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);exit;}
verify_csrf();
$pdo=db();$tid=(int)$user['tenant_id'];$requestId=0;
try {
 if(!bemora_asaas_enabled()||bemora_asaas_mode()!=='sandbox')throw new RuntimeException('Trocas automáticas estão disponíveis somente no Asaas Sandbox nesta etapa.');
 // Safety gate: do not grant paid plan benefits before confirmed payment.
 // Full paid-change orchestration will be implemented separately.
 $targetId=filter_var($_POST['plan_id']??null,FILTER_VALIDATE_INT);
 $mode=(string)($_POST['change_mode']??'');
 if(!in_array($mode,['immediate','renewal'],true))throw new RuntimeException('Escolha quando deseja mudar seu plano.');
 if(!$targetId||$targetId<1)throw new RuntimeException('Escolha um plano válido.');
 throw new RuntimeException('Troca automática temporariamente suspensa: o novo plano exige confirmação de pagamento pelo Asaas antes da ativação. Sua assinatura atual permanece inalterada.');
 $pdo->beginTransaction();
 $role=$pdo->prepare("SELECT role FROM tenant_users WHERE tenant_id=? AND user_id=? FOR UPDATE");
 $role->execute([$tid,(int)$user['id']]);
 if($role->fetchColumn()!=='family_admin')throw new RuntimeException('Somente o administrador familiar pode mudar o plano.');
 $orderQuery=$pdo->prepare("SELECT * FROM subscription_orders WHERE activated_tenant_id=? AND status='completed' ORDER BY id DESC LIMIT 1 FOR UPDATE");
 $orderQuery->execute([$tid]);$order=$orderQuery->fetch(PDO::FETCH_ASSOC);
 if(!$order||!$order['asaas_subscription_id'])throw new RuntimeException('Assinatura atual indisponível.');
 $trialActive=!empty($order['coupon_free_until']) && $order['coupon_free_until']>date('Y-m-d');
 $targetQuery=$pdo->prepare("SELECT * FROM subscription_plans WHERE id=? AND active=1");
 $targetQuery->execute([$targetId]);$target=$targetQuery->fetch(PDO::FETCH_ASSOC);
 if(!$target||(int)$target['id']===(int)$order['plan_id'])throw new RuntimeException('Selecione um plano diferente do atual.');
 $countQuery=$pdo->prepare('SELECT COUNT(*) FROM tenant_users WHERE tenant_id=?');$countQuery->execute([$tid]);
 if((int)$target['max_members']<(int)$countQuery->fetchColumn())throw new RuntimeException('Este plano não comporta os membros atuais da família.');
 $pending=$pdo->prepare("SELECT id FROM subscription_change_requests WHERE tenant_id=? AND status IN ('scheduled','processing','reconciliation_required') LIMIT 1 FOR UPDATE");
 $pending->execute([$tid]);if($pending->fetchColumn())throw new RuntimeException('Já existe uma alteração em andamento. Aguarde a conciliação.');
 // Legacy 'pending' requests never called Asaas; they may be superseded safely.
 $pdo->prepare("UPDATE subscription_change_requests SET status='superseded' WHERE tenant_id=? AND status='pending'")->execute([$tid]);
 $amount=bemora_plan_promo_active($target)?(float)$target['promo_price']:(float)$target['price'];
 $renewalAt=null;
 if($mode==='renewal'){
  $current=bemora_asaas_request('GET','/subscriptions/'.rawurlencode((string)$order['asaas_subscription_id']));
  if((string)($current['status']??'')!=='ACTIVE'||(string)($current['customer']??'')!==(string)$order['asaas_customer_id'])throw new RuntimeException('Não foi possível confirmar a assinatura no Asaas.');
  $next=(string)($current['nextDueDate']??'');
  if(!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/D',$next)||$next<=date('Y-m-d'))throw new RuntimeException('Próxima renovação indisponível. Escolha a troca imediata ou tente mais tarde.');
  $renewalAt=$next;
 }

 $insert=$pdo->prepare("INSERT INTO subscription_change_requests(tenant_id,order_id,requested_by,from_plan_id,to_plan_id,quoted_amount,status,change_mode,effective_at) VALUES(?,?,?,?,?,?,?,?,?)");
 $insert->execute([$tid,(int)$order['id'],(int)$user['id'],(int)$order['plan_id'],(int)$target['id'],$amount,$mode==='renewal'?'scheduled':'processing',$mode,$renewalAt]);
 $requestId=(int)$pdo->lastInsertId();
 $pdo->commit();
 if($mode==='renewal'){
  flash('success','Troca agendada para '.date('d/m/Y',strtotime($renewalAt)).'. Seu plano atual permanece ativo até a execução do agendamento.');
  redirect_to('/evoluir-plano');
 }
 // Do not hold a database transaction while calling an external provider.
 // A timeout is ambiguous: retain reconciliation_required to prevent duplicate updates.
 $subscriptionId=(string)$order['asaas_subscription_id'];
 $current=bemora_asaas_request('GET','/subscriptions/'.rawurlencode($subscriptionId));
 if((string)($current['id']??'')!==$subscriptionId||(string)($current['status']??'')!=='ACTIVE')throw new RuntimeException('Assinatura não está ativa no Asaas.');
 if((string)($current['customer']??'')!==(string)$order['asaas_customer_id'])throw new RuntimeException('Cliente Asaas não corresponde à assinatura.');
 $expectedCycle=bemora_asaas_cycle((string)$order['billing_cycle']);
 $originalDue=(string)($current['nextDueDate']??'');
 if($trialActive && (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/D',$originalDue)||$originalDue<(string)$order['coupon_free_until']))throw new RuntimeException('Data de cortesia divergente no Asaas; alteração requer conciliação.');
 if((string)($current['cycle']??'')!==$expectedCycle)throw new RuntimeException('Periodicidade divergente. Concilie a assinatura antes de alterar.');
 // Do not modify an outstanding invoice or payment due date; changes apply to future ungenerated invoices only.
 $updated=bemora_asaas_request('PUT','/subscriptions/'.rawurlencode($subscriptionId),[
  'value'=>$amount,'cycle'=>bemora_asaas_cycle((string)$target['billing_cycle']),
  'description'=>'Bemora - '.$target['name'],'updatePendingPayments'=>false
 ]);
 if($trialActive && (string)($updated['nextDueDate']??'')!==$originalDue)throw new RuntimeException('Data de cortesia alterada. Conciliação manual necessária.');
 if((string)($updated['id']??'')!==$subscriptionId
    ||(string)($updated['cycle']??'')!==bemora_asaas_cycle((string)$target['billing_cycle'])
    ||abs((float)($updated['value']??-1)-$amount)>0.009)
    throw new RuntimeException('Resposta de alteração divergente. Conciliação manual necessária.');
 $pdo->beginTransaction();
 $lock=$pdo->prepare("SELECT status FROM subscription_change_requests WHERE id=? AND tenant_id=? FOR UPDATE");$lock->execute([$requestId,$tid]);
 if($lock->fetchColumn()!=='processing')throw new RuntimeException('Solicitação alterada durante a confirmação.');
 $pdo->prepare("UPDATE subscription_orders SET plan_id=?,plan_name=?,billing_cycle=?,max_members=?,recurring_amount=?,amount=? WHERE id=?")->execute([(int)$target['id'],$target['name'],$target['billing_cycle'],(int)$target['max_members'],$amount,$amount,(int)$order['id']]);
 $pdo->prepare("UPDATE tenants SET max_members=? WHERE id=?")->execute([(int)$target['max_members'],$tid]);
 $pdo->prepare("UPDATE subscription_change_requests SET status='completed' WHERE id=?")->execute([$requestId]);
 $pdo->commit();
 flash('success','Seu plano foi atualizado no Asaas. As faturas já emitidas permanecem com seus valores originais.');
}catch(Throwable $e){
 if($pdo->inTransaction())$pdo->rollBack();
 if($requestId>0){
  try{$pdo->prepare("UPDATE subscription_change_requests SET status='reconciliation_required' WHERE id=? AND status='processing'")->execute([$requestId]);}catch(Throwable $ignored){error_log('Bemora: falha ao registrar conciliação de plano.');}
  error_log('Bemora: troca de plano requer conciliação. Request '.$requestId);
  flash('error','A atualização precisa ser conferida no Asaas antes de tentar novamente. Nenhuma nova solicitação será aceita até a conciliação.');
 }else flash('error',$e instanceof PDOException?'Verifique se a migration 028 foi executada.':$e->getMessage());
}
redirect_to('/evoluir-plano');

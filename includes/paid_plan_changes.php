<?php
declare(strict_types=1);
require_once __DIR__.'/asaas.php';

/** Payment identity and amount must be independently verified through Asaas, never trusted from webhook JSON. */
function bemora_apply_paid_plan_change(PDO $pdo,string $paymentId):bool {
 if(!preg_match('/^[A-Za-z0-9_-]{3,100}$/D',$paymentId))return false;
 $lookup=$pdo->prepare("SELECT id FROM subscription_change_requests WHERE asaas_payment_id=? AND status IN ('awaiting_payment','payment_confirmed','reconciliation_required') LIMIT 1");
 $lookup->execute([$paymentId]);$requestId=(int)$lookup->fetchColumn();
 if(!$requestId)return false;
 $paid=bemora_asaas_request('GET','/payments/'.rawurlencode($paymentId));
 if(!in_array((string)($paid['status']??''),['CONFIRMED','RECEIVED'],true))return false;
 $pdo->beginTransaction();
 try{
  $q=$pdo->prepare("SELECT c.*,o.asaas_subscription_id,o.asaas_customer_id,o.activated_tenant_id,o.plan_id current_plan_id,o.status order_status,o.coupon_free_until trial_until,p.name target_name,p.billing_cycle target_cycle,p.max_members target_members FROM subscription_change_requests c JOIN subscription_orders o ON o.id=c.order_id JOIN subscription_plans p ON p.id=c.to_plan_id WHERE c.id=? FOR UPDATE");
  $q->execute([$requestId]);$c=$q->fetch(PDO::FETCH_ASSOC);
  if(!$c){$pdo->rollBack();return false;}
  if($c['status']==='completed'){$pdo->commit();return true;}
  if((string)$paid['id']!==(string)$c['asaas_payment_id']
   ||(string)($paid['customer']??'')!==(string)$c['asaas_customer_id']
   ||(string)($paid['externalReference']??'')!=='bemora-change-'.$requestId
   ||abs((float)($paid['value']??-1)-(float)$c['payment_amount'])>0.009
   ||!empty($paid['subscription']))throw new RuntimeException('Cobrança de upgrade divergente.');
  if((int)$c['current_plan_id']!==(int)$c['from_plan_id']||$c['order_status']!=='completed')throw new RuntimeException('Plano atual foi modificado.');
  $count=$pdo->prepare('SELECT COUNT(*) FROM tenant_users WHERE tenant_id=?');$count->execute([(int)$c['tenant_id']]);
  if((int)$count->fetchColumn()>(int)$c['target_members'])throw new RuntimeException('Limite de membros incompatível.');
  // Remote state is validated immediately before mutation. If remote update succeeds
  // but DB commit fails, next webhook attempt can reconcile the same subscription.
  $subId=(string)$c['asaas_subscription_id'];
  $current=bemora_asaas_request('GET','/subscriptions/'.rawurlencode($subId));
  if((string)($current['id']??'')!==$subId||(string)($current['customer']??'')!==(string)$c['asaas_customer_id']||(string)($current['status']??'')!=='ACTIVE')throw new RuntimeException('Assinatura Asaas divergente.');
  $trialActive=!empty($c['trial_until']) && $c['trial_until']>date('Y-m-d');
  $beforeDue=(string)($current['nextDueDate']??'');
  if($trialActive && (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/D',$beforeDue)||$beforeDue<(string)$c['trial_until']))throw new RuntimeException('Data de cortesia divergente.');
  $value=(float)$c['quoted_amount'];$cycle=bemora_asaas_cycle((string)$c['target_cycle']);
  $already=abs((float)($current['value']??-1)-$value)<0.009 && (string)($current['cycle']??'')===$cycle;
  if(!$already){
   $updated=bemora_asaas_request('PUT','/subscriptions/'.rawurlencode($subId),['value'=>$value,'cycle'=>$cycle,'description'=>'Bemora - '.$c['target_name'],'updatePendingPayments'=>false]);
   if((string)($updated['id']??'')!==$subId||(string)($updated['cycle']??'')!==$cycle||abs((float)($updated['value']??-1)-$value)>0.009)throw new RuntimeException('Atualização Asaas não confirmada.');
  }
  if($trialActive){
   $check=bemora_asaas_request('GET','/subscriptions/'.rawurlencode($subId));
   if((string)($check['nextDueDate']??'')!==$beforeDue)throw new RuntimeException('Data de cobrança da cortesia modificada, conciliação necessária.');
  }
  $pdo->prepare("UPDATE subscription_orders SET plan_id=?,plan_name=?,billing_cycle=?,max_members=?,recurring_amount=?,amount=? WHERE id=?")->execute([(int)$c['to_plan_id'],$c['target_name'],$c['target_cycle'],(int)$c['target_members'],$value,$value,(int)$c['order_id']]);
  $pdo->prepare('UPDATE tenants SET max_members=? WHERE id=?')->execute([(int)$c['target_members'],(int)$c['tenant_id']]);
  $pdo->prepare("UPDATE subscription_change_requests SET status='completed',payment_confirmed_at=COALESCE(payment_confirmed_at,NOW()),activated_at=NOW() WHERE id=?")->execute([$requestId]);
  $pdo->commit();return true;
 }catch(Throwable $e){
  if($pdo->inTransaction())$pdo->rollBack();
  $pdo->prepare("UPDATE subscription_change_requests SET status='reconciliation_required' WHERE id=? AND status IN ('awaiting_payment','payment_confirmed','reconciliation_required')")->execute([$requestId]);
  error_log('Bemora: paid plan change '.$requestId.' requires reconciliation: '.$e->getMessage());
  throw $e;
 }
}

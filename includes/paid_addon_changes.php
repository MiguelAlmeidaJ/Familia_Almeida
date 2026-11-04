<?php
declare(strict_types=1);
require_once __DIR__.'/asaas.php';
/**
 * Apply extra seats or shopping only after verifying the standalone payment
 * with Asaas. Update the same Asaas subscription for future renewals.
 */
function bemora_apply_paid_addon_change(PDO $pdo,string $paymentId):bool {
 if(!preg_match('/^[A-Za-z0-9_-]{3,100}$/D',$paymentId))return false;
 $check=$pdo->prepare("SELECT id FROM bemora_addon_changes WHERE asaas_payment_id=? AND status IN ('awaiting_payment','payment_confirmed','reconciliation_required') LIMIT 1");
 $check->execute([$paymentId]);$requestId=(int)$check->fetchColumn();
 if(!$requestId)return false;
 $paid=bemora_asaas_request('GET','/payments/'.rawurlencode($paymentId));
 if(!in_array((string)($paid['status']??''),['CONFIRMED','RECEIVED'],true))return false;
 $pdo->beginTransaction();
 try{
  $q=$pdo->prepare('SELECT c.*,o.asaas_subscription_id,o.asaas_customer_id,o.status AS order_status,o.max_members AS current_members,o.shopping_addon AS current_shopping,o.recurring_amount AS current_recurring,o.billing_cycle,o.activated_tenant_id FROM bemora_addon_changes c JOIN subscription_orders o ON o.id=c.order_id WHERE c.id=? FOR UPDATE');
  $q->execute([$requestId]);$c=$q->fetch(PDO::FETCH_ASSOC);
  if(!$c){$pdo->rollBack();return false;}
  if((string)$paid['id']!==(string)$c['asaas_payment_id']
   ||(string)($paid['customer']??'')!==(string)$c['asaas_customer_id']
   ||(string)($paid['externalReference']??'')!=='bemora-addon-'.$requestId
   ||abs((float)($paid['value']??-1)-(float)$c['payment_amount'])>0.009
   ||!empty($paid['subscription']))throw new RuntimeException('Pagamento de adicional divergente.');
  if((int)$c['tenant_id']!==(int)$c['activated_tenant_id']||$c['order_status']!=='completed'
   ||(int)$c['old_members']!==(int)$c['current_members']
   ||(int)$c['old_shopping']!==(int)$c['current_shopping']
   ||abs((float)$c['current_recurring']-(float)$c['old_recurring'])>0.009)throw new RuntimeException('Assinatura local foi modificada.');
  $subId=(string)$c['asaas_subscription_id'];
  $current=bemora_asaas_request('GET','/subscriptions/'.rawurlencode($subId));
  if((string)($current['id']??'')!==$subId
   ||(string)($current['customer']??'')!==(string)$c['asaas_customer_id']
   ||(string)($current['status']??'')!=='ACTIVE'
   ||(string)($current['cycle']??'')!==bemora_asaas_cycle((string)$c['billing_cycle']))throw new RuntimeException('Assinatura Asaas divergente.');
  $newValue=(float)$c['new_recurring'];$oldValue=(float)$c['old_recurring'];
  $remoteValue=(float)($current['value']??-1);
  if(abs($remoteValue-$oldValue)>0.009 && abs($remoteValue-$newValue)>0.009)throw new RuntimeException('Preço da assinatura Asaas inesperado.');
  if(abs($remoteValue-$newValue)>0.009){
   $updated=bemora_asaas_request('PUT','/subscriptions/'.rawurlencode($subId),[
    'value'=>$newValue,'description'=>'Bemora - assinatura com adicionais',
    'updatePendingPayments'=>false
   ]);
   if((string)($updated['id']??'')!==$subId||abs((float)($updated['value']??-1)-$newValue)>0.009)throw new RuntimeException('Renovação com adicionais não confirmada.');
  }
  // Subscription updates and the local entitlement are coupled by this
  // transaction. On a network/DB failure, reconciliation is required.
  $pdo->prepare("UPDATE subscription_orders SET max_members=?,additional_members=?,shopping_addon=?,recurring_amount=?,amount=? WHERE id=?")
   ->execute([(int)$c['new_members'],(int)$c['new_members']-1,(int)$c['new_shopping'],$newValue,$newValue,(int)$c['order_id']]);
  $pdo->prepare('UPDATE tenants SET max_members=? WHERE id=?')->execute([(int)$c['new_members'],(int)$c['tenant_id']]);
  $pdo->prepare("UPDATE bemora_addon_changes SET status='completed',activated_at=NOW() WHERE id=?")->execute([$requestId]);
  $pdo->commit();
  return true;
 }catch(Throwable $e){
  if($pdo->inTransaction())$pdo->rollBack();
  $pdo->prepare("UPDATE bemora_addon_changes SET status='reconciliation_required' WHERE id=? AND status IN ('awaiting_payment','payment_confirmed','reconciliation_required')")->execute([$requestId]);
  error_log('Bemora add-on '.$requestId.' needs reconciliation: '.$e->getMessage());
  throw $e;
 }
}

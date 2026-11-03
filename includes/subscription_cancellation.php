<?php
declare(strict_types=1);
require_once __DIR__.'/asaas.php';

function bemora_cancellation_ready(PDO $pdo):bool {
 return (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='subscription_cancellations'")->fetchColumn()===1;
}

function bemora_cancellation_for_family(PDO $pdo,int $tenantId):?array {
 if(!bemora_cancellation_ready($pdo))return null;
 $q=$pdo->prepare('SELECT * FROM subscription_cancellations WHERE tenant_id=? LIMIT 1');
 $q->execute([$tenantId]);return $q->fetch(PDO::FETCH_ASSOC)?:null;
}

/**
 * Asaas nextDueDate is the next recurrence billing date, not proof of payment.
 * Preserve the interval only with a recent confirmed charge or an active free coupon.
 * The returned date is inclusive; null means no prepaid period was demonstrated.
 */
function bemora_cancellation_entitlement(array $subscription,array $payments,string $cycle,string $couponUntil,?DateTimeImmutable $today=null):?string {
 $today=$today??new DateTimeImmutable('today');
 $due=(string)($subscription['nextDueDate']??'');
 if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$due)||$due<=$today->format('Y-m-d'))return null;
 $next=new DateTimeImmutable($due);
 $maxAge=['monthly'=>35,'semiannual'=>190,'annual'=>375][$cycle]??0;
 if(!$maxAge)throw new RuntimeException('Periodicidade não reconhecida.');
 $paid=false;
 foreach($payments as $p){
  $status=(string)($p['status']??'');$date=(string)($p['dueDate']??'');
  if(!in_array($status,['CONFIRMED','RECEIVED','RECEIVED_IN_CASH'],true)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date))continue;
  $dateObj=new DateTimeImmutable($date);
  if($dateObj<=$today&&$dateObj>=$today->modify('-'.$maxAge.' days')&&$dateObj<$next)$paid=true;
 }
 $free=preg_match('/^\d{4}-\d{2}-\d{2}$/D',$couponUntil) && $couponUntil>=$today->format('Y-m-d');
 if(!$paid && !$free)return null;
 $end=$next->modify('-1 day');
 if(!$paid && $free){
  $couponEnd=(new DateTimeImmutable($couponUntil))->modify('-1 day');
  if($couponEnd<$end)$end=$couponEnd;
 }
 return $end>=$today?$end->format('Y-m-d'):null;
}

function bemora_cancellation_payment_list(string $subscriptionId,string $customerId):array {
 $payments=[];$offset=0;
 do{
  $page=bemora_asaas_request('GET','/subscriptions/'.rawurlencode($subscriptionId).'/payments?limit=100&offset='.$offset);
  $items=$page['data']??null;if(!is_array($items))throw new RuntimeException('Faturas não puderam ser verificadas.');
  foreach($items as $payment){
   if(!is_array($payment)||(string)($payment['subscription']??'')!==$subscriptionId||(string)($payment['customer']??'')!==$customerId)
    throw new RuntimeException('Fatura não corresponde à assinatura.');
   $payments[]=$payment;
  }
  $offset+=count($items);
  if($offset>10000)throw new RuntimeException('Limite de faturas excedido.');
  $more=($page['hasMore']??false)===true;
  if($more&&!$items)throw new RuntimeException('Paginação de faturas incompleta.');
 }while($more);
 return $payments;
}

function bemora_cancellation_confirm(PDO $pdo,int $id,?string $accessUntil):void {
 $q=$pdo->prepare("UPDATE subscription_cancellations SET status='confirmed',access_until=?,confirmed_at=COALESCE(confirmed_at,NOW()),reviewed_at=NOW() WHERE id=? AND status IN ('processing','reconciliation_required')");
 $q->execute([$accessUntil,$id]);
}

/** Verify remote status after a network timeout or interrupted DB update. */
function bemora_cancellation_recover(PDO $pdo,array $row):bool {
 if(!in_array((string)$row['status'],['processing','reconciliation_required'],true))return false;
 $order=$pdo->prepare('SELECT asaas_customer_id,coupon_free_until,billing_cycle FROM subscription_orders WHERE id=? AND activated_tenant_id=?');
 $order->execute([(int)$row['order_id'],(int)$row['tenant_id']]);$o=$order->fetch(PDO::FETCH_ASSOC);
 if(!$o)return false;
 $sid=(string)$row['asaas_subscription_id'];
 $remote=bemora_asaas_request('GET','/subscriptions/'.rawurlencode($sid));
 if((string)($remote['id']??'')!==$sid||(string)($remote['customer']??'')!==(string)$o['asaas_customer_id'])throw new RuntimeException('Assinatura divergente na recuperação do cancelamento.');
 $status=(string)($remote['status']??'');
 if($status==='ACTIVE'){
  $age=time()-strtotime((string)$row['requested_at']);
  if($age<600)return false; // Do not race the original request.
 }
 if(!in_array($status,['ACTIVE','INACTIVE'],true))throw new RuntimeException('Assinatura não pode ser conciliada automaticamente.');
 $payments=bemora_cancellation_payment_list($sid,(string)$o['asaas_customer_id']);
 $until=!empty($row['access_until'])?(string)$row['access_until']:bemora_cancellation_entitlement($remote,$payments,(string)$o['billing_cycle'],(string)($o['coupon_free_until']??''));
 if($status==='ACTIVE'){
  $updated=bemora_asaas_request('PUT','/subscriptions/'.rawurlencode($sid),['status'=>'INACTIVE']);
  if((string)($updated['id']??'')!==$sid||(string)($updated['status']??'')!=='INACTIVE')
   throw new RuntimeException('Cancelamento ainda não confirmado no Asaas.');
 }
 bemora_cancellation_confirm($pdo,(int)$row['id'],$until);
 return true;
}

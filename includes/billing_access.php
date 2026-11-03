<?php
declare(strict_types=1);
require_once __DIR__.'/asaas.php';

/** Grace period after the due date; operational policy can be revised later. */
const BEMORA_BILLING_GRACE_DAYS = 3;
// Non-active subscriptions cannot retain unrestricted access.

/**
 * Pure decision logic; cancellation, chargebacks and refunds take precedence
 * over a promotional grace period. Used by production reconciler and tests.
 */
function bemora_billing_decision(string $subscriptionStatus,array $payments,string $trialUntil='',?DateTimeImmutable $today=null):array {
 $today=$today??new DateTimeImmutable('today');
 $status=strtoupper($subscriptionStatus);
 if(!in_array($status,['ACTIVE','INACTIVE','EXPIRED'],true))throw new RuntimeException('Status da assinatura Asaas não reconhecido.');
 if($status!=='ACTIVE')return ['state'=>'suspended','reason'=>'subscription_inactive','payment_id'=>null,'overdue_since'=>null,'days_overdue'=>0];
 $oldest=null;$risk=null;$lastSettled=null;
 // Evaluate only the most recent billing window for reversals, not an ancient refunded charge.
 $windowStart=$today->modify('-370 days');
 foreach($payments as $p){
  $date=(string)($p['dueDate']??'');
  if(!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/D',$date))throw new RuntimeException('Data de cobrança inválida.');
  $due=new DateTimeImmutable($date);$state=strtoupper((string)($p['status']??''));
  if($state==='')throw new RuntimeException('Cobrança sem status.');
  if(in_array($state,['CONFIRMED','RECEIVED','RECEIVED_IN_CASH'],true) && $due<=$today){
   if($lastSettled===null||$date>$lastSettled)$lastSettled=$date;
  }
  if(in_array($state,['REFUNDED','REFUND_IN_PROGRESS','PARTIALLY_REFUNDED','CHARGEBACK_REQUESTED','CHARGEBACK_DISPUTE','AWAITING_CHARGEBACK_REVERSAL'],true) && $due>=$windowStart && $due<=$today){
   if($risk===null||$date>$risk['date'])$risk=['date'=>$date,'id'=>(string)($p['id']??'')];
  }
  if(in_array($state,['PENDING','OVERDUE'],true)&&$due<$today){
   if($oldest===null||$date<$oldest['date'])$oldest=['date'=>$date,'id'=>(string)($p['id']??'')];
  }
 }
 if($risk && ($lastSettled===null || $risk['date']>=$lastSettled))return ['state'=>'suspended','reason'=>'payment_reversed','payment_id'=>$risk['id'],'overdue_since'=>$risk['date'],'days_overdue'=>0];
 $trialActive=preg_match('/^\\d{4}-\\d{2}-\\d{2}$/D',$trialUntil)&&$trialUntil>=$today->format('Y-m-d');
 $days=$oldest?(int)(new DateTimeImmutable($oldest['date']))->diff($today)->format('%a'):0;
 $state=$trialActive?'active':($oldest&&$days>BEMORA_BILLING_GRACE_DAYS?'suspended':($oldest?'grace':'active'));
 return ['state'=>$state,'reason'=>$oldest?'overdue':null,'payment_id'=>$oldest['id']??null,'overdue_since'=>$oldest['date']??null,'days_overdue'=>$days];
}

function bemora_billing_access_ready(PDO $pdo):bool {
 static $ready=null;
 if($ready===null){$ready=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='tenant_billing_access'")->fetchColumn()===1;}
 return $ready;
}

function bemora_billing_access(PDO $pdo,int $tenantId):array {
 if(!bemora_billing_access_ready($pdo))return ['state'=>'active','overdue_since'=>null,'overdue_payment_id'=>null];
 $q=$pdo->prepare('SELECT * FROM tenant_billing_access WHERE tenant_id=? LIMIT 1');
 $q->execute([$tenantId]);return $q->fetch(PDO::FETCH_ASSOC)?:['state'=>'active','overdue_since'=>null,'overdue_payment_id'=>null];
}

/**
 * Reconcile the actual invoices of an Asaas subscription, not webhook payload or local order status.
 * Fail open on provider errors: do not suspend a family based on incomplete evidence.
 * Billing suspension does NOT change tenants.active or tenants.status.
 */
function bemora_reconcile_billing_access(PDO $pdo,int $tenantId):array {
 if(!bemora_billing_access_ready($pdo))throw new RuntimeException('Execute a migration de acesso financeiro 034.');
 $q=$pdo->prepare("SELECT o.asaas_subscription_id,o.asaas_customer_id,o.coupon_free_until FROM subscription_orders o WHERE o.activated_tenant_id=? AND o.asaas_subscription_id IS NOT NULL AND o.status IN ('completed','paid','payment_attention') ORDER BY o.id DESC LIMIT 1");
 $q->execute([$tenantId]);$order=$q->fetch(PDO::FETCH_ASSOC);
 if(!$order)return bemora_billing_access($pdo,$tenantId); // legacy/internals are never auto-suspended
 $today=new DateTimeImmutable('today');
 $subscriptionId=(string)$order['asaas_subscription_id'];
 $sub=bemora_asaas_request('GET','/subscriptions/'.rawurlencode($subscriptionId));
 if((string)($sub['id']??'')!==$subscriptionId||(string)($sub['customer']??'')!==(string)$order['asaas_customer_id'])throw new RuntimeException('Assinatura ou cliente divergente.');
 $payments=[];$offset=0;$pageSize=100;
 do {
  $page=bemora_asaas_request('GET','/subscriptions/'.rawurlencode($subscriptionId).'/payments?limit='.$pageSize.'&offset='.$offset);
  $items=$page['data']??null;if(!is_array($items))throw new RuntimeException('Lista de cobranças inválida.');
  foreach($items as $payment){
   if(!is_array($payment))throw new RuntimeException('Item de cobrança inválido.');
   if((string)($payment['subscription']??'')!==$subscriptionId||(string)($payment['customer']??'')!==(string)$order['asaas_customer_id'])throw new RuntimeException('Cobrança vinculada a outra assinatura ou cliente.');
   $payments[]=$payment;
  }
  $offset+=count($items);
  $hasMore=($page['hasMore']??false)===true;
  if($hasMore&&count($items)===0)throw new RuntimeException('Paginação de cobrança inconsistente.');
  if($offset>10000)throw new RuntimeException('Limite de conciliação excedido.');
 }while($hasMore);
 $decision=bemora_billing_decision((string)($sub['status']??''),$payments,(string)($order['coupon_free_until']??''),$today);
 $upgradeReview=false;
 $review=$pdo->prepare("SELECT COUNT(*) FROM subscription_change_requests WHERE tenant_id=? AND status='reconciliation_required' AND activated_at IS NOT NULL AND failure_reason='upgrade_payment_reversed'");
 $review->execute([$tenantId]);
 $upgradeReview=(int)$review->fetchColumn()>0;
 if($upgradeReview){
  $decision=['state'=>'suspended','reason'=>'upgrade_payment_reversed','payment_id'=>null,'overdue_since'=>null,'days_overdue'=>0];
 }
 $state=$decision['state'];
 $oldest=$decision['payment_id']!==null?['id'=>$decision['payment_id'],'date'=>$decision['overdue_since']]:null;
 $daysOverdue=$decision['days_overdue'];
 // In a race, serialize updates for this tenant and preserve the first suspension timestamp.
 $pdo->beginTransaction();
 try {
  $q=$pdo->prepare('SELECT state FROM tenant_billing_access WHERE tenant_id=? FOR UPDATE');$q->execute([$tenantId]);$prior=$q->fetchColumn();
  $reasonReady=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='tenant_billing_access' AND column_name='reason'")->fetchColumn()===1;
  $reasonColumn=$reasonReady?',reason':'';
  $reasonValue=$reasonReady?',?':'';
  $reasonUpdate=$reasonReady?',reason=VALUES(reason)':'';
  $sql="INSERT INTO tenant_billing_access(tenant_id,state,overdue_payment_id,overdue_since,checked_at,suspended_at".$reasonColumn.")
   VALUES(?,?,?,?,NOW(),IF(?='suspended',NOW(),NULL)".$reasonValue.")
   ON DUPLICATE KEY UPDATE
    resumed_at=IF(state='suspended' AND VALUES(state)<>'suspended',NOW(),resumed_at),
    suspended_at=IF(VALUES(state)='suspended',COALESCE(suspended_at,NOW()),NULL),
    overdue_payment_id=VALUES(overdue_payment_id),overdue_since=VALUES(overdue_since),checked_at=NOW(),state=VALUES(state)".$reasonUpdate;
  $values=[$tenantId,$state,$oldest['id']??null,$oldest['date']??null,$state];
  if($reasonReady)$values[]=$decision['reason'];
  $pdo->prepare($sql)->execute($values);
  $pdo->commit();
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
 return ['state'=>$state,'reason'=>$decision['reason'],'overdue_since'=>$oldest['date']??null,'overdue_payment_id'=>$oldest['id']??null,'days_overdue'=>$daysOverdue];
}

/** Manual suspension is independent of automatic billing state. */
function bemora_billing_is_blocked(array $access):bool {
 return ($access['state']??'active')==='suspended'||(int)($access['manual_suspended']??0)===1;
}
function bemora_billing_manual_suspend(PDO $pdo,int $tenantId,int $adminId,bool $suspend):void {
 if(!bemora_billing_access_ready($pdo))throw new RuntimeException('Execute a migration 034 e 035.');
 $hasColumn=$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='tenant_billing_access' AND column_name='manual_suspended'")->fetchColumn();
 if(!(int)$hasColumn)throw new RuntimeException('Execute a migration 035 antes de testar a suspensão.');
 $pdo->beginTransaction();
 try{
  $q=$pdo->prepare('SELECT id FROM tenants WHERE id=? FOR UPDATE');$q->execute([$tenantId]);
  if(!$q->fetchColumn())throw new RuntimeException('Família não encontrada.');
  $pdo->prepare("INSERT INTO tenant_billing_access (tenant_id,manual_suspended,manual_changed_at,manual_changed_by) VALUES (?,?,NOW(),?)
   ON DUPLICATE KEY UPDATE manual_suspended=VALUES(manual_suspended),manual_changed_at=NOW(),manual_changed_by=VALUES(manual_changed_by)")
    ->execute([$tenantId,$suspend?1:0,$adminId]);
  $pdo->commit();
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

// Subscription deletion is reconciled from authenticated Asaas lifecycle events.

function bemora_mark_asaas_subscription_removed(PDO $pdo,string $subscriptionId,string $customerId):void {
 if(!bemora_billing_access_ready($pdo)||$subscriptionId===''||$customerId==='')return;
 $q=$pdo->prepare("SELECT activated_tenant_id FROM subscription_orders WHERE asaas_subscription_id=? AND asaas_customer_id=? AND activated_tenant_id IS NOT NULL ORDER BY id DESC LIMIT 1");
 $q->execute([$subscriptionId,$customerId]);
 $tid=(int)$q->fetchColumn();if($tid<1)return;
 $pdo->prepare("INSERT INTO tenant_billing_access(tenant_id,state,suspended_at,checked_at) VALUES(?,'suspended',NOW(),NOW()) ON DUPLICATE KEY UPDATE state='suspended',suspended_at=COALESCE(suspended_at,NOW()),checked_at=NOW()")->execute([$tid]);
 $ready=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='tenant_billing_access' AND column_name='reason'")->fetchColumn();
 if($ready)$pdo->prepare("UPDATE tenant_billing_access SET reason='subscription_deleted' WHERE tenant_id=?")->execute([$tid]);
}

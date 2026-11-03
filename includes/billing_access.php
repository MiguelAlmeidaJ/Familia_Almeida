<?php
declare(strict_types=1);
require_once __DIR__.'/asaas.php';

/** Grace period after the due date; operational policy can be revised later. */
const BEMORA_BILLING_GRACE_DAYS = 3;
// Non-active subscriptions cannot retain unrestricted access.

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
 $oldest=null;$offset=0;$pageSize=100;
 do {
  $page=bemora_asaas_request('GET','/subscriptions/'.rawurlencode($subscriptionId).'/payments?limit='.$pageSize.'&offset='.$offset);
  $items=$page['data']??null;if(!is_array($items))throw new RuntimeException('Lista de cobranças inválida.');
  foreach($items as $payment){
   if(!is_array($payment)||(string)($payment['subscription']??'')!==$subscriptionId||(string)($payment['customer']??'')!==(string)$order['asaas_customer_id'])continue;
   $status=(string)($payment['status']??'');$date=(string)($payment['dueDate']??'');
   if(!in_array($status,['OVERDUE','PENDING'],true)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date))continue;
   $due=new DateTimeImmutable($date);
   if($due >= $today)continue;
   if($oldest===null||$date<$oldest['date'])$oldest=['date'=>$date,'id'=>(string)($payment['id']??'')];
  }
  $offset+=count($items);
  $hasMore=($page['hasMore']??false)===true;
  if($hasMore&&count($items)===0)throw new RuntimeException('Paginação de cobrança inconsistente.');
  if($offset>10000)throw new RuntimeException('Limite de conciliação excedido.');
 }while($hasMore);
 $trialUntil=(string)($order['coupon_free_until']??'');
 $trialActive=preg_match('/^\d{4}-\d{2}-\d{2}$/D',$trialUntil)&&$trialUntil>=$today->format('Y-m-d');
 $daysOverdue=$oldest?(int)(new DateTimeImmutable($oldest['date']))->diff($today)->format('%a'):0;
 $state=$trialActive?'active':($oldest&&$daysOverdue>BEMORA_BILLING_GRACE_DAYS?'suspended':($oldest?'grace':'active'));
 // In a race, serialize updates for this tenant and preserve the first suspension timestamp.
 $pdo->beginTransaction();
 try {
  $q=$pdo->prepare('SELECT state FROM tenant_billing_access WHERE tenant_id=? FOR UPDATE');$q->execute([$tenantId]);$prior=$q->fetchColumn();
  $pdo->prepare("INSERT INTO tenant_billing_access(tenant_id,state,overdue_payment_id,overdue_since,checked_at,suspended_at)
   VALUES(?,?,?,?,NOW(),IF(?='suspended',NOW(),NULL))
   ON DUPLICATE KEY UPDATE
    resumed_at=IF(state='suspended' AND VALUES(state)<>'suspended',NOW(),resumed_at),
    suspended_at=IF(VALUES(state)='suspended',COALESCE(suspended_at,NOW()),NULL),
    overdue_payment_id=VALUES(overdue_payment_id),overdue_since=VALUES(overdue_since),checked_at=NOW(),state=VALUES(state)")->execute([
    $tenantId,$state,$oldest['id']??null,$oldest['date']??null,$state
   ]);
  $pdo->commit();
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
 return ['state'=>$state,'overdue_since'=>$oldest['date']??null,'overdue_payment_id'=>$oldest['id']??null,'days_overdue'=>$daysOverdue];
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

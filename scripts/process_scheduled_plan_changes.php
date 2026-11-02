<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../includes/asaas.php';
$pdo=db();
if(!bemora_asaas_enabled()||bemora_asaas_mode()!=='sandbox'){fwrite(STDERR,"Scheduled changes only available in Sandbox.\n");exit(1);}
// Disable legacy schedule execution until payment-first renewals are implemented.
// Existing scheduled requests are preserved for manual reconciliation.
fwrite(STDOUT,"Scheduled plan changes paused: waiting for payment-confirmed renewal flow.\n");
exit(0);
$rows=$pdo->query("SELECT id FROM subscription_change_requests WHERE status='scheduled' AND effective_at<=CURDATE() ORDER BY id LIMIT 20")->fetchAll(PDO::FETCH_COLUMN);
foreach($rows as $id){
 $id=(int)$id;
 try{
  $pdo->beginTransaction();
  $q=$pdo->prepare("SELECT r.*,o.asaas_subscription_id,o.asaas_customer_id,o.plan_id AS current_plan_id,o.activated_tenant_id,o.status AS order_status,p.name AS target_name,p.billing_cycle AS target_cycle,p.max_members AS target_members FROM subscription_change_requests r JOIN subscription_orders o ON o.id=r.order_id JOIN subscription_plans p ON p.id=r.to_plan_id WHERE r.id=? FOR UPDATE");
  $q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);
  if(!$r||$r['status']!=='scheduled'){$pdo->rollBack();continue;}
  $pdo->prepare("UPDATE subscription_change_requests SET status='processing' WHERE id=?")->execute([$id]);
  $pdo->commit();
  if((int)$r['current_plan_id']!==(int)$r['from_plan_id']||$r['order_status']!=='completed')throw new RuntimeException('Local subscription changed');
  $memberCount=$pdo->prepare('SELECT COUNT(*) FROM tenant_users WHERE tenant_id=?');
  $memberCount->execute([(int)$r['tenant_id']]);
  if((int)$memberCount->fetchColumn()>(int)$r['target_members'])throw new RuntimeException('Plan no longer supports existing members');
  $subId=(string)$r['asaas_subscription_id'];
  $current=bemora_asaas_request('GET','/subscriptions/'.rawurlencode($subId));
  if((string)($current['id']??'')!==$subId||(string)($current['customer']??'')!==(string)$r['asaas_customer_id']||(string)($current['status']??'')!=='ACTIVE')throw new RuntimeException('Subscription could not be validated');
  $updated=bemora_asaas_request('PUT','/subscriptions/'.rawurlencode($subId),['value'=>(float)$r['quoted_amount'],'cycle'=>bemora_asaas_cycle($r['target_cycle']),'description'=>'Bemora - '.$r['target_name'],'updatePendingPayments'=>false]);
  if((string)($updated['id']??'')!==$subId||(string)($updated['cycle']??'')!==bemora_asaas_cycle($r['target_cycle'])||abs((float)($updated['value']??-1)-(float)$r['quoted_amount'])>0.009)throw new RuntimeException('Subscription response mismatch');
  $pdo->beginTransaction();
  $pdo->prepare("UPDATE subscription_orders SET plan_id=?,plan_name=?,billing_cycle=?,max_members=?,recurring_amount=?,amount=?,coupon_id=NULL,coupon_kind=NULL,coupon_free_until=NULL WHERE id=?")->execute([(int)$r['to_plan_id'],$r['target_name'],$r['target_cycle'],(int)$r['target_members'],(float)$r['quoted_amount'],(float)$r['quoted_amount'],(int)$r['order_id']]);
  $pdo->prepare('UPDATE tenants SET max_members=? WHERE id=?')->execute([(int)$r['target_members'],(int)$r['tenant_id']]);
  $pdo->prepare("UPDATE subscription_change_requests SET status='completed' WHERE id=?")->execute([$id]);
  $pdo->commit();
  echo "Completed scheduled change #$id\n";
 }catch(Throwable $e){
  if($pdo->inTransaction())$pdo->rollBack();
  try{$pdo->prepare("UPDATE subscription_change_requests SET status='reconciliation_required' WHERE id=? AND status='processing'")->execute([$id]);}catch(Throwable $ignored){}
  error_log("Bemora scheduled plan change $id requires reconciliation: ".$e->getMessage());
  fwrite(STDERR,"Scheduled change #$id requires reconciliation\n");
 }
}

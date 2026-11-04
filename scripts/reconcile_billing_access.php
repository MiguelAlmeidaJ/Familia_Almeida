<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../includes/billing_access.php';
require_once __DIR__.'/../includes/billing_notifications.php';
require_once __DIR__.'/../includes/paid_addon_changes.php';
$pdo=db();
if(!bemora_asaas_enabled()){fwrite(STDERR,"Asaas desabilitado.\n");exit(1);}
if(!bemora_billing_access_ready($pdo)){fwrite(STDERR,"Execute a migration 034.\n");exit(1);}
$q=$pdo->query("SELECT DISTINCT activated_tenant_id FROM subscription_orders WHERE activated_tenant_id IS NOT NULL AND asaas_subscription_id IS NOT NULL AND status IN ('completed','paid','payment_attention') ORDER BY activated_tenant_id");
$ids=$q->fetchAll(PDO::FETCH_COLUMN);$failures=0;
foreach($ids as $id){
 try{$result=bemora_reconcile_billing_access($pdo,(int)$id);echo "Tenant ".(int)$id.": ".$result['state']."\n";
  try{$sent=bemora_send_billing_reminders($pdo,(int)$id);echo "Notificações enviadas: ".$sent."\n";}catch(Throwable $mailError){$failures++;error_log("Bemora: billing email process failed for tenant ".(int)$id);fwrite(STDERR,"Falha ao verificar notificações para família ".(int)$id."\n");}
  try{bemora_cancellation_notice($pdo,(int)$id);}catch(Throwable $cancelMailError){$failures++;error_log("Bemora: cancellation notice delivery failed for tenant ".(int)$id);}}
 catch(Throwable $e){$failures++;error_log('Bemora billing reconciliation tenant '.(int)$id.': '.$e->getMessage());fwrite(STDERR,"Erro ao conciliar família ".(int)$id."\n");}
}
// Also reconcile standalone add-on charges when webhook delivery was delayed.
$ready=$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='bemora_addon_changes'");
if((int)$ready->fetchColumn()===1){
 $q=$pdo->query("SELECT asaas_payment_id FROM bemora_addon_changes WHERE status IN ('awaiting_payment','payment_confirmed','reconciliation_required') AND asaas_payment_id IS NOT NULL ORDER BY id LIMIT 100");
 foreach($q->fetchAll(PDO::FETCH_COLUMN) as $paymentId){
  try{bemora_apply_paid_addon_change($pdo,(string)$paymentId);}
  catch(Throwable $e){$failures++;error_log('Bemora addon reconciliation failed: '.$e->getMessage());}
 }
}
exit($failures?1:0);

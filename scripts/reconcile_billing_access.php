<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../includes/billing_access.php';
require_once __DIR__.'/../includes/billing_notifications.php';
$pdo=db();
if(!bemora_asaas_enabled()){fwrite(STDERR,"Asaas desabilitado.\n");exit(1);}
if(!bemora_billing_access_ready($pdo)){fwrite(STDERR,"Execute a migration 034.\n");exit(1);}
$q=$pdo->query("SELECT DISTINCT activated_tenant_id FROM subscription_orders WHERE activated_tenant_id IS NOT NULL AND asaas_subscription_id IS NOT NULL AND status IN ('completed','paid','payment_attention') ORDER BY activated_tenant_id");
$ids=$q->fetchAll(PDO::FETCH_COLUMN);$failures=0;
foreach($ids as $id){
 try{$result=bemora_reconcile_billing_access($pdo,(int)$id);echo "Tenant ".(int)$id.": ".$result['state']."\n";
  try{$sent=bemora_send_billing_reminders($pdo,(int)$id);echo "Notificações enviadas: ".$sent."\n";bemora_cancellation_notice($pdo,(int)$id);}catch(Throwable $mailError){$failures++;error_log("Bemora: billing email process failed for tenant ".(int)$id);fwrite(STDERR,"Falha ao verificar notificações para família ".(int)$id."\n");}}
 catch(Throwable $e){$failures++;error_log('Bemora billing reconciliation tenant '.(int)$id.': '.$e->getMessage());fwrite(STDERR,"Erro ao conciliar família ".(int)$id."\n");}
}
exit($failures?1:0);

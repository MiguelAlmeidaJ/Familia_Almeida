<?php
declare(strict_types=1);
return [
 'description'=>'Suspensao administrativa separada e notificacoes de cobranca por fatura',
 'up'=>static function(PDO $pdo):void {
  $exists=$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='tenant_billing_access'")->fetchColumn();
  if(!(int)$exists)throw new RuntimeException('Execute a migration 034 antes da 035.');
  $columns=$pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='tenant_billing_access'")->fetchAll(PDO::FETCH_COLUMN);
  foreach([
   'manual_suspended'=>'TINYINT(1) NOT NULL DEFAULT 0',
   'manual_changed_at'=>'DATETIME NULL',
   'manual_changed_by'=>'BIGINT UNSIGNED NULL'
  ] as $column=>$type){
   if(!in_array($column,$columns,true))$pdo->exec("ALTER TABLE tenant_billing_access ADD COLUMN `$column` $type");
  }
  $pdo->exec("CREATE TABLE IF NOT EXISTS billing_notification_log (
   id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
   tenant_id BIGINT UNSIGNED NOT NULL,
   payment_id VARCHAR(100) NOT NULL,
   notification_type VARCHAR(32) NOT NULL,
   recipient VARCHAR(254) NOT NULL,
   status VARCHAR(16) NOT NULL DEFAULT 'sending',
   attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   sent_at DATETIME NULL,
   UNIQUE KEY billing_notice_unique (tenant_id,payment_id,notification_type),
   INDEX billing_notice_status_idx (status,attempted_at),
   CONSTRAINT billing_notice_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 }
];
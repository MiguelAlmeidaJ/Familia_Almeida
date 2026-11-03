<?php
declare(strict_types=1);
return [
 'description'=>'Atendimento LGPD e prova de aceite das condições de contratação',
 'up'=>static function(PDO $pdo):void {
  $pdo->exec("CREATE TABLE IF NOT EXISTS privacy_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    request_type ENUM('access','correction','deletion','portability','information','objection') NOT NULL,
    description TEXT NOT NULL,
    status ENUM('received','identity_verification','in_progress','completed','denied') NOT NULL DEFAULT 'received',
    response_note TEXT NULL,
    resolved_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    KEY privacy_requests_owner (user_id,created_at),
    KEY privacy_requests_status (status,created_at),
    CONSTRAINT privacy_requests_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT privacy_requests_user_fk FOREIGN KEY (user_id) REFERENCES users(id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $cols=$pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='subscription_orders'")->fetchAll(PDO::FETCH_COLUMN);
  if(!in_array('terms_version',$cols,true))$pdo->exec("ALTER TABLE subscription_orders ADD COLUMN terms_version VARCHAR(40) NULL");
  if(!in_array('privacy_version',$cols,true))$pdo->exec("ALTER TABLE subscription_orders ADD COLUMN privacy_version VARCHAR(40) NULL");
  if(!in_array('terms_accepted_at',$cols,true))$pdo->exec("ALTER TABLE subscription_orders ADD COLUMN terms_accepted_at DATETIME NULL");
 }
];
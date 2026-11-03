<?php
declare(strict_types=1);
return [
 'description'=>'Controle independente de suspensao financeira de familias sem remover seus dados',
 'up'=>static function(PDO $pdo):void {
  $pdo->exec("CREATE TABLE IF NOT EXISTS tenant_billing_access (
   tenant_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
   state VARCHAR(24) NOT NULL DEFAULT 'active',
   overdue_payment_id VARCHAR(100) NULL,
   overdue_since DATE NULL,
   checked_at DATETIME NULL,
   suspended_at DATETIME NULL,
   resumed_at DATETIME NULL,
   last_error_at DATETIME NULL,
   CONSTRAINT fk_billing_access_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 }
];

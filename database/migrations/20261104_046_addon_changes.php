<?php
declare(strict_types=1);
return [
 'description'=>'Solicitações auditáveis de adicionais pagos após a assinatura, com liberação somente após pagamento validado',
 'up'=>static function(PDO $pdo):void {
  $pdo->exec("CREATE TABLE IF NOT EXISTS bemora_addon_changes (
   id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
   tenant_id BIGINT UNSIGNED NOT NULL,
   order_id BIGINT UNSIGNED NOT NULL,
   requested_by BIGINT UNSIGNED NOT NULL,
   old_members INT UNSIGNED NOT NULL,
   new_members INT UNSIGNED NOT NULL,
   old_shopping TINYINT(1) NOT NULL,
   new_shopping TINYINT(1) NOT NULL,
   old_recurring DECIMAL(12,2) NOT NULL,
   new_recurring DECIMAL(12,2) NOT NULL,
   payment_amount DECIMAL(12,2) NOT NULL,
   status VARCHAR(32) NOT NULL DEFAULT 'processing',
   asaas_payment_id VARCHAR(100) NULL UNIQUE,
   invoice_url VARCHAR(1500) NULL,
   activated_at DATETIME NULL,
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   INDEX idx_addon_tenant_status(tenant_id,status),
   CONSTRAINT fk_addon_order FOREIGN KEY(order_id) REFERENCES subscription_orders(id),
   CONSTRAINT fk_addon_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id),
   CONSTRAINT fk_addon_user FOREIGN KEY(requested_by) REFERENCES users(id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 }
];
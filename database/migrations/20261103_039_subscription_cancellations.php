<?php
declare(strict_types=1);
return [
 'description'=>'Solicitacoes de cancelamento de recorrencia com acesso ate o periodo pago',
 'up'=>static function(PDO $pdo):void {
  $pdo->exec("CREATE TABLE IF NOT EXISTS subscription_cancellations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NOT NULL,
    requested_by BIGINT UNSIGNED NOT NULL,
    asaas_subscription_id VARCHAR(100) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'processing',
    access_until DATE NULL,
    reason VARCHAR(500) NULL,
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    confirmed_at DATETIME NULL,
    reviewed_at DATETIME NULL,
    notice_sent_at DATETIME NULL,
    UNIQUE KEY cancellation_tenant_unique(tenant_id),
    KEY cancellation_status_idx(status,requested_at),
    CONSTRAINT cancellation_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id),
    CONSTRAINT cancellation_order_fk FOREIGN KEY(order_id) REFERENCES subscription_orders(id),
    CONSTRAINT cancellation_user_fk FOREIGN KEY(requested_by) REFERENCES users(id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 }
];
<?php
declare(strict_types=1);
return ['description'=>'Solicitacoes autenticadas de evolucao de plano sem criar assinaturas duplicadas',
'up'=>static function(PDO $pdo):void {
 $pdo->exec("CREATE TABLE IF NOT EXISTS subscription_change_requests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 tenant_id BIGINT UNSIGNED NOT NULL,
 order_id BIGINT UNSIGNED NOT NULL,
 requested_by BIGINT UNSIGNED NOT NULL,
 from_plan_id BIGINT UNSIGNED NOT NULL,
 to_plan_id BIGINT UNSIGNED NOT NULL,
 quoted_amount DECIMAL(12,2) NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'pending',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_change_tenant (tenant_id,status),
 KEY idx_change_order (order_id),
 CONSTRAINT change_order_fk FOREIGN KEY(order_id) REFERENCES subscription_orders(id),
 CONSTRAINT change_from_fk FOREIGN KEY(from_plan_id) REFERENCES subscription_plans(id),
 CONSTRAINT change_to_fk FOREIGN KEY(to_plan_id) REFERENCES subscription_plans(id)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}];

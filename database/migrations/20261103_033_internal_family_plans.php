<?php
declare(strict_types=1);
return [
 'description'=>'Vinculo administrativo de planos internos as familias, separado de cobrancas Asaas',
 'up'=>static function(PDO $pdo):void {
  $pdo->exec("CREATE TABLE IF NOT EXISTS tenant_internal_plans (
   tenant_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
   plan_id BIGINT UNSIGNED NOT NULL,
   assigned_by BIGINT UNSIGNED NOT NULL,
   assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   CONSTRAINT fk_internal_plan_tenant FOREIGN KEY(tenant_id) REFERENCES tenants(id),
   CONSTRAINT fk_internal_plan_plan FOREIGN KEY(plan_id) REFERENCES subscription_plans(id),
   CONSTRAINT fk_internal_plan_admin FOREIGN KEY(assigned_by) REFERENCES users(id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 }
];

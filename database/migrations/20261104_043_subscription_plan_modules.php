<?php
declare(strict_types=1);
return [
 'description'=>'Permissões de módulos configuráveis por plano, preservando os recursos dos planos atuais',
 'up'=>static function(PDO $pdo):void {
  $pdo->exec("CREATE TABLE IF NOT EXISTS subscription_plan_modules (
   plan_id BIGINT UNSIGNED NOT NULL,
   module_key VARCHAR(50) NOT NULL,
   enabled TINYINT(1) NOT NULL DEFAULT 1,
   PRIMARY KEY(plan_id,module_key),
   CONSTRAINT fk_subscription_plan_modules_plan FOREIGN KEY(plan_id) REFERENCES subscription_plans(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  // Backfill makes sure existing paying and internal plans keep their
  // functionality until an administrator intentionally changes access.
  $pdo->exec("INSERT IGNORE INTO subscription_plan_modules(plan_id,module_key,enabled)
   SELECT p.id,m.module_key,1 FROM subscription_plans p
   CROSS JOIN (SELECT 'reports' module_key UNION ALL SELECT 'shopping' UNION ALL SELECT 'scanner') m");
 }
];

<?php
declare(strict_types=1);
return [
 'description'=>'Oferta comercial unica Bemora: tabela de precos e adicionais por assinatura, sem apagar contratos antigos',
 'up'=>static function(PDO $pdo):void {
  $pdo->exec("CREATE TABLE IF NOT EXISTS bemora_single_offer (
   id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
   enabled TINYINT(1) NOT NULL DEFAULT 0,
   name VARCHAR(100) NOT NULL DEFAULT 'Bemora',
   base_monthly DECIMAL(12,2) NOT NULL DEFAULT 0,
   extra_member_monthly DECIMAL(12,2) NOT NULL DEFAULT 0,
   shopping_monthly DECIMAL(12,2) NOT NULL DEFAULT 0,
   max_members INT UNSIGNED NOT NULL DEFAULT 6,
   semi_discount DECIMAL(5,2) NOT NULL DEFAULT 0,
   annual_discount DECIMAL(5,2) NOT NULL DEFAULT 0,
   monthly_plan_id BIGINT UNSIGNED NULL,
   semiannual_plan_id BIGINT UNSIGNED NULL,
   annual_plan_id BIGINT UNSIGNED NULL,
   updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $pdo->exec("INSERT IGNORE INTO bemora_single_offer(id) VALUES(1)");
  $cols=$pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='subscription_orders'")->fetchAll(PDO::FETCH_COLUMN);
  if(!in_array('additional_members',$cols,true))
   $pdo->exec("ALTER TABLE subscription_orders ADD COLUMN additional_members INT UNSIGNED NOT NULL DEFAULT 0");
  if(!in_array('shopping_addon',$cols,true))
   $pdo->exec("ALTER TABLE subscription_orders ADD COLUMN shopping_addon TINYINT(1) NOT NULL DEFAULT 0");
  if(!in_array('single_offer',$cols,true))
   $pdo->exec("ALTER TABLE subscription_orders ADD COLUMN single_offer TINYINT(1) NOT NULL DEFAULT 0");
 }
];
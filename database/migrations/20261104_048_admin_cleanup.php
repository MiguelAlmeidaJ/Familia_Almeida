<?php
declare(strict_types=1);
return [
 'description'=>'Marcar remoções remotas confirmadas e registrar limpezas administrativas sem dados pessoais',
 'up'=>static function(PDO $pdo):void {
  $cols=$pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='subscription_orders'")->fetchAll(PDO::FETCH_COLUMN);
  if(!in_array('admin_subscription_removed_at',$cols,true)){
   $pdo->exec("ALTER TABLE subscription_orders ADD COLUMN admin_subscription_removed_at DATETIME NULL");
  }
  $pdo->exec("CREATE TABLE IF NOT EXISTS admin_cleanup_audit (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    admin_user_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(35) NOT NULL,
    target_id BIGINT UNSIGNED NOT NULL,
    reference_type VARCHAR(30) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX admin_cleanup_audit_created(created_at)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 }
];
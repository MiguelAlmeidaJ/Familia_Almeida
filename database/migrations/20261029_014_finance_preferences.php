<?php
declare(strict_types=1);
return ['description'=>'Preferencias financeiras familiares e responsabilidades planejadas',
'up'=>static function(PDO $pdo):void {
 $exists=$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='tenants' AND column_name='finance_mode'");
 if(!(int)$exists->fetchColumn())$pdo->exec("ALTER TABLE tenants ADD COLUMN finance_mode ENUM('shared','separate') NOT NULL DEFAULT 'shared'");
 $pdo->exec("CREATE TABLE IF NOT EXISTS family_bill_responsibilities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  fixed_bill_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_family_bill (tenant_id,fixed_bill_id),
  KEY responsibility_family_user (tenant_id,user_id),
  CONSTRAINT fbr_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  CONSTRAINT fbr_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}];
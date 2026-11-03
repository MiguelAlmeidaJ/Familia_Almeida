<?php
declare(strict_types=1);
return [
 'description'=>'Relatos de suporte vinculados a família e usuário com entrega por e-mail',
 'up'=>static function(PDO $pdo):void {
  $pdo->exec("CREATE TABLE IF NOT EXISTS support_tickets (
   id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
   tenant_id BIGINT UNSIGNED NOT NULL,
   user_id BIGINT UNSIGNED NOT NULL,
   description TEXT NOT NULL,
   contact_method ENUM('whatsapp','email') NOT NULL,
   contact_value VARCHAR(254) NOT NULL,
   status ENUM('open','in_progress','resolved') NOT NULL DEFAULT 'open',
   email_status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   INDEX support_tickets_tenant_idx(tenant_id,created_at),
   INDEX support_tickets_status_idx(status,created_at),
   CONSTRAINT support_tickets_tenant_fk FOREIGN KEY(tenant_id) REFERENCES tenants(id),
   CONSTRAINT support_tickets_user_fk FOREIGN KEY(user_id) REFERENCES users(id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 }
];
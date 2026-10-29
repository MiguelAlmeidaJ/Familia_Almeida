<?php
declare(strict_types=1);
return [
 'description'=>'Convites de famílias, perfis e estados de gestão',
 'up'=>static function(PDO $pdo):void {
  $cols=$pdo->query("SELECT COLUMN_NAME FROM information_schema.columns WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tenants'")->fetchAll(PDO::FETCH_COLUMN);
  if(!in_array('status',$cols,true))$pdo->exec("ALTER TABLE tenants ADD COLUMN status ENUM('active','archived','maintenance','pending') NOT NULL DEFAULT 'active'");
  if(!in_array('name_key',$cols,true)) {
    $pdo->exec("ALTER TABLE tenants ADD COLUMN name_key VARCHAR(160) NULL");
    $pdo->exec("UPDATE tenants SET name_key=LOWER(TRIM(name)) WHERE name_key IS NULL");
    $pdo->exec("ALTER TABLE tenants ADD UNIQUE KEY tenants_name_key_unique (name_key)");
  }
  foreach(['sex'=>"ENUM('M','F') NULL",'birth_date'=>'DATE NULL'] as $field=>$type) {
   $q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='users' AND column_name=?");
   $q->execute([$field]);if(!(int)$q->fetchColumn())$pdo->exec("ALTER TABLE users ADD COLUMN `$field` $type");
  }
  $pdo->exec("CREATE TABLE IF NOT EXISTS family_invitations(
   id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
   token_hash CHAR(64) NOT NULL UNIQUE,
   max_members TINYINT UNSIGNED NOT NULL,
   expires_at DATETIME NOT NULL,
   used_at DATETIME NULL,
   revoked_at DATETIME NULL,
   created_by BIGINT UNSIGNED NOT NULL,
   created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
   KEY (expires_at),
   CONSTRAINT family_invitations_admin_fk FOREIGN KEY(created_by) REFERENCES users(id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $pdo->exec("CREATE TABLE IF NOT EXISTS family_registrations(
   id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
   invitation_id BIGINT UNSIGNED NOT NULL UNIQUE,
   family_name VARCHAR(160) NOT NULL,
   family_name_key VARCHAR(160) NOT NULL UNIQUE,
   member_count TINYINT UNSIGNED NOT NULL,
   admin_member SMALLINT UNSIGNED NOT NULL,
   members_json MEDIUMTEXT NOT NULL,
   state ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
   submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
   CONSTRAINT family_registrations_invitation_fk FOREIGN KEY(invitation_id) REFERENCES family_invitations(id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 }
];
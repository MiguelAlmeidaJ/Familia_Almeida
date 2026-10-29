<?php
declare(strict_types=1);
return [
 'description'=>'Perfil pessoal com telefone, foto e multiplas fontes de renda',
 'up'=>static function(PDO $pdo):void {
  foreach(['phone'=>'VARCHAR(25) NULL','avatar_path'=>'VARCHAR(255) NULL'] as $column=>$definition){
   $q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='users' AND column_name=?");
   $q->execute([$column]);
   if(!(int)$q->fetchColumn())$pdo->exec("ALTER TABLE users ADD COLUMN `{$column}` {$definition}");
  }
  $pdo->exec("CREATE TABLE IF NOT EXISTS user_income_sources (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    job_title VARCHAR(120) NOT NULL,
    salary DECIMAL(12,2) NOT NULL DEFAULT 0,
    payday TINYINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY income_source_user_idx(user_id),
    CONSTRAINT income_source_user_fk FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 }
];
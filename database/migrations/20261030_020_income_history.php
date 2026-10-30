<?php
declare(strict_types=1);
return ['description'=>'Histórico de vigência para fontes de renda e receitas recorrentes',
 'up'=>static function(PDO $pdo):void {
  $pdo->exec("CREATE TABLE IF NOT EXISTS user_income_source_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    valid_from CHAR(7) NOT NULL,
    job_title VARCHAR(120) NOT NULL,
    salary DECIMAL(12,2) NOT NULL,
    payday TINYINT UNSIGNED NOT NULL,
    KEY income_version_lookup(source_id,valid_from),
    UNIQUE KEY income_source_month_unique(source_id,valid_from),
    CONSTRAINT income_version_source_fk FOREIGN KEY(source_id) REFERENCES user_income_sources(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $pdo->exec("ALTER TABLE user_income_sources ADD COLUMN ended_month CHAR(7) NULL");
  $pdo->exec("INSERT INTO user_income_source_versions(source_id,user_id,valid_from,job_title,salary,payday)
    SELECT s.id,s.user_id,DATE_FORMAT(s.created_at,'%Y-%m'),s.job_title,s.salary,s.payday
    FROM user_income_sources s
    WHERE NOT EXISTS(SELECT 1 FROM user_income_source_versions v WHERE v.source_id=s.id)");
 }
];
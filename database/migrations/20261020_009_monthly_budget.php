<?php
declare(strict_types=1);
return [
 'description' => 'Previsão de receitas recorrentes e distribuição mensal por categoria.',
 'up' => static function (PDO $pdo): void {
   $pdo->exec("CREATE TABLE IF NOT EXISTS recurring_incomes (
     id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
     name VARCHAR(160) NOT NULL,
     amount DECIMAL(12,2) NOT NULL,
     active TINYINT(1) NOT NULL DEFAULT 1,
     created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
   $pdo->exec("CREATE TABLE IF NOT EXISTS monthly_allocations (
     month CHAR(7) NOT NULL,
     category VARCHAR(100) NOT NULL,
     amount DECIMAL(12,2) NOT NULL DEFAULT 0,
     PRIMARY KEY(month, category)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 },
];

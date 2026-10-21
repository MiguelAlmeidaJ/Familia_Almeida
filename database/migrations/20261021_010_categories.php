<?php
declare(strict_types=1);
return ['description'=>'Cadastro central de categorias de ganhos e gastos','up'=>static function(PDO $pdo):void {
 $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  type ENUM('income','expense') NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY categories_type_name_unique(type,name)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 $pdo->exec("INSERT IGNORE INTO categories(name,type)
 SELECT DISTINCT TRIM(category), CASE WHEN type='income' THEN 'income' ELSE 'expense' END
 FROM transactions WHERE TRIM(category)<>''");
 $pdo->exec("INSERT IGNORE INTO categories(name,type)
 SELECT DISTINCT TRIM(category), 'expense' FROM monthly_allocations WHERE TRIM(category)<>''");
 foreach(['Mercado','Farmácia','Lazer','Transporte','Pet','Outros'] as $name) {
  $stmt=$pdo->prepare("INSERT IGNORE INTO categories(name,type) VALUES(?,'expense')");
  $stmt->execute([$name]);
 }
}];
<?php
declare(strict_types=1);
return [
 'description'=>'Catálogo comercial de planos Bemora e promoções por período',
 'up'=>static function(PDO $pdo):void {
  $pdo->exec("CREATE TABLE IF NOT EXISTS subscription_plans (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    billing_cycle ENUM('monthly','semiannual','annual') NOT NULL,
    details TEXT NOT NULL,
    max_members INT UNSIGNED NOT NULL,
    price DECIMAL(12,2) NOT NULL,
    promo_price DECIMAL(12,2) NULL,
    promo_duration ENUM('24h','1w','1m','3m') NULL,
    promo_starts_at DATETIME NULL,
    promo_ends_at DATETIME NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_subscription_plans_active (active,billing_cycle),
    CONSTRAINT chk_plan_members CHECK (max_members BETWEEN 1 AND 30),
    CONSTRAINT chk_plan_price CHECK (price > 0),
    CONSTRAINT chk_plan_promo_price CHECK (promo_price IS NULL OR (promo_price > 0 AND promo_price < price))
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 }
];
<?php
declare(strict_types=1);
return ['description'=>'Cupons promocionais para assinaturas Bemora',
'up'=>static function(PDO $pdo):void {
 $pdo->exec("CREATE TABLE IF NOT EXISTS subscription_coupons (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(32) NOT NULL,
 title VARCHAR(100) NOT NULL,
 description VARCHAR(500) NULL,
 kind ENUM('free_months','first_payment_discount','annual_discount') NOT NULL,
 free_months SMALLINT UNSIGNED NULL,
 discount_type ENUM('percent','fixed') NULL,
 discount_value DECIMAL(12,2) NULL,
 cycle_scope ENUM('all','monthly','semiannual','annual') NOT NULL DEFAULT 'all',
 plan_id BIGINT UNSIGNED NULL,
 max_redemptions INT UNSIGNED NULL,
 redeemed_count INT UNSIGNED NOT NULL DEFAULT 0,
 valid_from DATETIME NULL,
 valid_until DATETIME NULL,
 enabled TINYINT(1) NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY coupon_code_unique(code),
 KEY coupon_active_dates(enabled,valid_from,valid_until),
 CONSTRAINT coupon_plan_fk FOREIGN KEY(plan_id) REFERENCES subscription_plans(id) ON DELETE SET NULL
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}];
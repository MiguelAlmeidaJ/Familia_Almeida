<?php
declare(strict_types=1);
return ['description'=>'Vincular cupom e desconto calculado aos pedidos de assinatura',
'up'=>static function(PDO $pdo):void {
 $cols=$pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='subscription_orders'")->fetchAll(PDO::FETCH_COLUMN);
 if(!in_array('coupon_id',$cols,true))$pdo->exec("ALTER TABLE subscription_orders ADD COLUMN coupon_id BIGINT UNSIGNED NULL, ADD COLUMN list_amount DECIMAL(12,2) NULL, ADD COLUMN coupon_kind VARCHAR(40) NULL");
 $pdo->exec("CREATE TABLE IF NOT EXISTS subscription_coupon_redemptions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 coupon_id BIGINT UNSIGNED NOT NULL,
 order_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY unique_coupon_order (order_id),
 KEY coupon_usage (coupon_id),
 CONSTRAINT coupon_redemption_coupon FOREIGN KEY(coupon_id) REFERENCES subscription_coupons(id),
 CONSTRAINT coupon_redemption_order FOREIGN KEY(order_id) REFERENCES subscription_orders(id)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}];
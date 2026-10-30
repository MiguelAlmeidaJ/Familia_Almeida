<?php
declare(strict_types=1);
return ['description'=>'Pedidos de assinatura Asaas, vínculo do cliente e eventos idempotentes',
'up'=>static function(PDO $pdo):void {
 $pdo->exec("CREATE TABLE IF NOT EXISTS subscription_orders (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 public_token_hash CHAR(64) NOT NULL UNIQUE,
 plan_id BIGINT UNSIGNED NOT NULL,
 plan_name VARCHAR(100) NOT NULL,
 billing_cycle VARCHAR(20) NOT NULL,
 max_members INT UNSIGNED NOT NULL,
 amount DECIMAL(12,2) NOT NULL,
 buyer_name VARCHAR(120) NOT NULL,
 buyer_email VARCHAR(254) NOT NULL,
 buyer_cpf_cnpj VARCHAR(14) NOT NULL,
 buyer_phone VARCHAR(20) NULL,
 status VARCHAR(25) NOT NULL DEFAULT 'initiated',
 asaas_customer_id VARCHAR(100) NULL,
 asaas_subscription_id VARCHAR(100) NULL UNIQUE,
 asaas_payment_id VARCHAR(100) NULL,
 invoice_url VARCHAR(1500) NULL,
 invite_id BIGINT UNSIGNED NULL UNIQUE,
 activated_tenant_id BIGINT UNSIGNED NULL,
 paid_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_order_email (buyer_email), INDEX idx_order_status (status),
 CONSTRAINT fk_order_plan FOREIGN KEY (plan_id) REFERENCES subscription_plans(id)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 $pdo->exec("CREATE TABLE IF NOT EXISTS asaas_webhook_events (
 event_id VARCHAR(150) NOT NULL PRIMARY KEY,
 event_type VARCHAR(100) NOT NULL,
 subscription_id VARCHAR(100) NULL,
 received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}];
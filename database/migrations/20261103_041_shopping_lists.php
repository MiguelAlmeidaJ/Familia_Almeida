<?php
declare(strict_types=1);

return [
 'description'=>'Listas de compras compartilhadas com itens, quantidades e preços confirmados',
 'up'=>static function(PDO $pdo):void {
  $pdo->exec("CREATE TABLE IF NOT EXISTS bemora_market_lists (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    market_name VARCHAR(120) NULL,
    status ENUM('planned','shopping','completed') NOT NULL DEFAULT 'planned',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    KEY shopping_list_tenant (tenant_id,status,updated_at),
    CONSTRAINT shopping_list_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT shopping_list_creator_fk FOREIGN KEY (created_by) REFERENCES users(id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $pdo->exec("CREATE TABLE IF NOT EXISTS bemora_market_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    list_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(160) NOT NULL,
    quantity DECIMAL(10,3) NOT NULL DEFAULT 1.000,
    unit VARCHAR(12) NOT NULL DEFAULT 'un',
    unit_price DECIMAL(12,2) NULL,
    purchased TINYINT(1) NOT NULL DEFAULT 0,
    price_source ENUM('manual','label') NOT NULL DEFAULT 'manual',
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY shopping_item_list (tenant_id,list_id,purchased),
    CONSTRAINT shopping_item_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT shopping_item_list_fk FOREIGN KEY (list_id) REFERENCES bemora_market_lists(id) ON DELETE CASCADE,
    CONSTRAINT shopping_item_creator_fk FOREIGN KEY (created_by) REFERENCES users(id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 }
];
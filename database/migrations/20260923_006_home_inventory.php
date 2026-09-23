<?php

declare(strict_types=1);

return [
    'description' => 'Adiciona estoque doméstico, movimentações e integração com compras de mercado.',
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS inventory_items (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(160) NOT NULL,
                category VARCHAR(100) NULL,
                unit VARCHAR(20) NOT NULL DEFAULT "un",
                min_quantity DECIMAL(12,3) NOT NULL DEFAULT 0,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY inventory_items_name_unique (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS inventory_movements (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                inventory_item_id BIGINT UNSIGNED NOT NULL,
                movement_type ENUM("entry","exit") NOT NULL,
                source_type ENUM("manual","purchase") NOT NULL DEFAULT "manual",
                quantity DECIMAL(12,3) NOT NULL,
                note VARCHAR(255) NULL,
                occurred_at DATETIME NOT NULL,
                created_by BIGINT UNSIGNED NULL,
                source_purchase_id BIGINT UNSIGNED NULL,
                source_shopping_item_id BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY inventory_movements_item_idx (inventory_item_id),
                KEY inventory_movements_occurred_idx (occurred_at),
                KEY inventory_movements_purchase_idx (source_purchase_id),
                UNIQUE KEY inventory_movements_shopping_item_unique (source_shopping_item_id),
                CONSTRAINT inventory_movements_item_fk
                    FOREIGN KEY (inventory_item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
                CONSTRAINT inventory_movements_created_by_fk
                    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
                CONSTRAINT inventory_movements_purchase_fk
                    FOREIGN KEY (source_purchase_id) REFERENCES shopping_purchases(id) ON DELETE SET NULL,
                CONSTRAINT inventory_movements_shopping_item_fk
                    FOREIGN KEY (source_shopping_item_id) REFERENCES shopping_items(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $shoppingExists = (int) $pdo->query(
            'SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = "shopping_items"'
        )->fetchColumn() > 0;

        if (!$shoppingExists) {
            return;
        }

        $pdo->exec(
            'INSERT IGNORE INTO inventory_items (name, category, unit, min_quantity)
             SELECT DISTINCT si.name, "Mercado", "un", 0
             FROM shopping_items si
             WHERE si.purchased = 1'
        );

        $pdo->exec(
            'INSERT IGNORE INTO inventory_movements
                (inventory_item_id, movement_type, source_type, quantity, note,
                 occurred_at, created_by, source_purchase_id, source_shopping_item_id)
             SELECT
                ii.id,
                "entry",
                "purchase",
                si.quantity,
                CONCAT("Compra de mercado", IF(si.store_name IS NOT NULL AND si.store_name <> "", CONCAT(" — ", si.store_name), "")),
                COALESCE(si.purchased_at, CONCAT(sp.purchase_date, " 12:00:00"), NOW()),
                sp.created_by,
                sp.id,
                si.id
             FROM shopping_items si
             INNER JOIN inventory_items ii ON LOWER(ii.name) = LOWER(si.name)
             LEFT JOIN shopping_purchases sp ON sp.id = si.purchase_id
             WHERE si.purchased = 1
               AND si.purchase_id IS NOT NULL'
        );
    },
];

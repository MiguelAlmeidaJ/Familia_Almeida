<?php

declare(strict_types=1);

return [
    'description' => 'Adiciona listas mensais de mercado, móveis e compras sincronizáveis.',
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS shopping_lists (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                list_type ENUM("market","furniture") NOT NULL,
                month CHAR(7) NULL,
                created_by BIGINT UNSIGNED NULL,
                copied_from_id BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY shopping_lists_market_month_unique (list_type, month),
                KEY shopping_lists_created_by_idx (created_by),
                CONSTRAINT shopping_lists_created_by_fk
                    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
                CONSTRAINT shopping_lists_copied_from_fk
                    FOREIGN KEY (copied_from_id) REFERENCES shopping_lists(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS shopping_purchases (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                list_id BIGINT UNSIGNED NOT NULL,
                transaction_id BIGINT UNSIGNED NOT NULL,
                created_by BIGINT UNSIGNED NULL,
                client_purchase_id VARCHAR(80) NOT NULL,
                purchase_date DATE NOT NULL,
                total_amount DECIMAL(12,2) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY shopping_purchases_client_unique (client_purchase_id),
                KEY shopping_purchases_list_idx (list_id),
                KEY shopping_purchases_transaction_idx (transaction_id),
                CONSTRAINT shopping_purchases_list_fk
                    FOREIGN KEY (list_id) REFERENCES shopping_lists(id) ON DELETE CASCADE,
                CONSTRAINT shopping_purchases_transaction_fk
                    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
                CONSTRAINT shopping_purchases_created_by_fk
                    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS shopping_items (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                list_id BIGINT UNSIGNED NOT NULL,
                purchase_id BIGINT UNSIGNED NULL,
                name VARCHAR(160) NOT NULL,
                category VARCHAR(100) NULL,
                priority ENUM("high","medium","low") NULL,
                quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
                estimated_price DECIMAL(12,2) NULL,
                purchased_price DECIMAL(12,2) NULL,
                store_name VARCHAR(160) NULL,
                product_url VARCHAR(700) NULL,
                purchased TINYINT(1) NOT NULL DEFAULT 0,
                purchased_at DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY shopping_items_list_idx (list_id),
                KEY shopping_items_purchase_idx (purchase_id),
                KEY shopping_items_purchased_idx (purchased),
                CONSTRAINT shopping_items_list_fk
                    FOREIGN KEY (list_id) REFERENCES shopping_lists(id) ON DELETE CASCADE,
                CONSTRAINT shopping_items_purchase_fk
                    FOREIGN KEY (purchase_id) REFERENCES shopping_purchases(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    },
];

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(80) NOT NULL,
    email VARCHAR(160) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY users_email_unique (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS debts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(160) NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL,
    paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    created_by BIGINT UNSIGNED NULL,
    debt_id BIGINT UNSIGNED NULL,
    bill_payment_id BIGINT UNSIGNED NULL,
    type ENUM('income','expense','investment','debt') NOT NULL,
    description VARCHAR(160) NOT NULL,
    category VARCHAR(100) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    occurred_on DATE NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY transactions_occurred_on_idx (occurred_on),
    KEY transactions_created_by_idx (created_by),
    KEY transactions_debt_id_idx (debt_id),
    UNIQUE KEY transactions_bill_payment_unique (bill_payment_id),
    CONSTRAINT transactions_created_by_fk
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT transactions_debt_id_fk
        FOREIGN KEY (debt_id) REFERENCES debts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fixed_bills (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    billing_type ENUM('fixed','variable','installment') NOT NULL DEFAULT 'fixed',
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    due_day TINYINT UNSIGNED NOT NULL,
    start_month CHAR(7) NULL,
    installment_total SMALLINT UNSIGNED NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bill_payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    bill_id BIGINT UNSIGNED NOT NULL,
    month CHAR(7) NOT NULL,
    amount_due DECIMAL(12,2) NULL,
    installment_number SMALLINT UNSIGNED NULL,
    paid TINYINT(1) NOT NULL DEFAULT 0,
    paid_at TIMESTAMP NULL DEFAULT NULL,
    paid_on DATE NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY bill_payments_bill_month_unique (bill_id, month),
    CONSTRAINT bill_payments_bill_fk
        FOREIGN KEY (bill_id) REFERENCES fixed_bills(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE transactions
    ADD CONSTRAINT transactions_bill_payment_fk
    FOREIGN KEY (bill_payment_id) REFERENCES bill_payments(id) ON DELETE SET NULL;


CREATE TABLE IF NOT EXISTS transaction_receipts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    transaction_id BIGINT UNSIGNED NOT NULL,
    uploaded_by BIGINT UNSIGNED NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY transaction_receipts_stored_name_unique (stored_name),
    KEY transaction_receipts_transaction_idx (transaction_id),
    KEY transaction_receipts_uploaded_by_idx (uploaded_by),
    CONSTRAINT transaction_receipts_transaction_fk
        FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    CONSTRAINT transaction_receipts_uploaded_by_fk
        FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shopping_lists (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    list_type ENUM('market','furniture') NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shopping_purchases (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shopping_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    list_id BIGINT UNSIGNED NOT NULL,
    purchase_id BIGINT UNSIGNED NULL,
    name VARCHAR(160) NOT NULL,
    category VARCHAR(100) NULL,
    priority ENUM('high','medium','low') NULL,
    quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
    purchased_quantity DECIMAL(10,2) NULL,
    track_inventory TINYINT(1) NOT NULL DEFAULT 1,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(160) NOT NULL,
    category VARCHAR(100) NULL,
    unit VARCHAR(20) NOT NULL DEFAULT 'un',
    min_quantity DECIMAL(12,3) NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY inventory_items_name_unique (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_movements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    inventory_item_id BIGINT UNSIGNED NOT NULL,
    movement_type ENUM('entry','exit') NOT NULL,
    source_type ENUM('manual','purchase') NOT NULL DEFAULT 'manual',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS spending_goals (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    category VARCHAR(100) NOT NULL,
    monthly_limit DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY spending_goals_category_unique (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(100) NOT NULL,
    value TEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS schema_migrations (
    migration VARCHAR(190) NOT NULL,
    description VARCHAR(255) NOT NULL,
    executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (migration)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

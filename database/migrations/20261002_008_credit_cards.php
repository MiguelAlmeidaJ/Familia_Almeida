<?php

declare(strict_types=1);

return [
    'description' => 'Adiciona cartões de crédito, compras parceladas e controle mensal de faturas.',
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS credit_cards (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL,
                holder_name VARCHAR(100) NULL,
                last_four CHAR(4) NULL,
                limit_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                closing_day TINYINT UNSIGNED NOT NULL,
                due_day TINYINT UNSIGNED NOT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS credit_card_purchases (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                card_id BIGINT UNSIGNED NOT NULL,
                created_by BIGINT UNSIGNED NULL,
                description VARCHAR(160) NOT NULL,
                category VARCHAR(100) NOT NULL,
                total_amount DECIMAL(12,2) NOT NULL,
                installments SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                purchase_date DATE NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY credit_card_purchases_card_idx (card_id),
                KEY credit_card_purchases_date_idx (purchase_date),
                CONSTRAINT credit_card_purchases_card_fk
                    FOREIGN KEY (card_id) REFERENCES credit_cards(id) ON DELETE CASCADE,
                CONSTRAINT credit_card_purchases_user_fk
                    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS credit_card_installments (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                purchase_id BIGINT UNSIGNED NOT NULL,
                card_id BIGINT UNSIGNED NOT NULL,
                installment_number SMALLINT UNSIGNED NOT NULL,
                installment_total SMALLINT UNSIGNED NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                invoice_month CHAR(7) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY credit_card_installment_unique (purchase_id, installment_number),
                KEY credit_card_installments_invoice_idx (card_id, invoice_month),
                CONSTRAINT credit_card_installments_purchase_fk
                    FOREIGN KEY (purchase_id) REFERENCES credit_card_purchases(id) ON DELETE CASCADE,
                CONSTRAINT credit_card_installments_card_fk
                    FOREIGN KEY (card_id) REFERENCES credit_cards(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS credit_card_invoice_payments (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                card_id BIGINT UNSIGNED NOT NULL,
                month CHAR(7) NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                paid_on DATE NOT NULL,
                transaction_id BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY credit_card_invoice_payment_unique (card_id, month),
                UNIQUE KEY credit_card_invoice_transaction_unique (transaction_id),
                CONSTRAINT credit_card_invoice_card_fk
                    FOREIGN KEY (card_id) REFERENCES credit_cards(id) ON DELETE CASCADE,
                CONSTRAINT credit_card_invoice_transaction_fk
                    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    },
];

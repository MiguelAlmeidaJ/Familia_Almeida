<?php

declare(strict_types=1);

return [
    'description' => 'Adiciona notas e comprovantes anexados às movimentações.',
    'up' => static function (PDO $pdo): void {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS transaction_receipts (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    },
];

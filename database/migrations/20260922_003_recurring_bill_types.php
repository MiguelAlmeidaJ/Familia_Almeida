<?php

declare(strict_types=1);

return [
    'description' => 'Adiciona contas fixas, variáveis e parceladas com valores mensais independentes.',
    'up' => static function (PDO $pdo): void {
        $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();

        $columnExists = static function (string $table, string $column) use ($pdo, $database): bool {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = ? AND table_name = ? AND column_name = ?'
            );
            $stmt->execute([$database, $table, $column]);
            return (int) $stmt->fetchColumn() > 0;
        };

        if (!$columnExists('fixed_bills', 'billing_type')) {
            $pdo->exec(
                'ALTER TABLE fixed_bills
                 ADD COLUMN billing_type ENUM("fixed","variable","installment") NOT NULL DEFAULT "fixed" AFTER name'
            );
        }

        if (!$columnExists('fixed_bills', 'start_month')) {
            $pdo->exec(
                'ALTER TABLE fixed_bills
                 ADD COLUMN start_month CHAR(7) NULL AFTER due_day'
            );
        }

        if (!$columnExists('fixed_bills', 'installment_total')) {
            $pdo->exec(
                'ALTER TABLE fixed_bills
                 ADD COLUMN installment_total SMALLINT UNSIGNED NULL AFTER start_month'
            );
        }

        if (!$columnExists('bill_payments', 'amount_due')) {
            $pdo->exec(
                'ALTER TABLE bill_payments
                 ADD COLUMN amount_due DECIMAL(12,2) NULL AFTER month'
            );
        }

        if (!$columnExists('bill_payments', 'installment_number')) {
            $pdo->exec(
                'ALTER TABLE bill_payments
                 ADD COLUMN installment_number SMALLINT UNSIGNED NULL AFTER amount_due'
            );
        }

        // Preserve the exact amount of payments that were already converted to transactions.
        $pdo->exec(
            'UPDATE bill_payments p
             JOIN transactions t ON t.bill_payment_id = p.id
             SET p.amount_due = t.amount
             WHERE p.amount_due IS NULL'
        );

        // Sensible defaults for existing accounts whose amount normally varies month to month.
        $pdo->exec(
            'UPDATE fixed_bills
             SET billing_type = "variable"
             WHERE billing_type = "fixed"
               AND (
                    LOWER(name) LIKE "%água%"
                 OR LOWER(name) LIKE "%agua%"
                 OR LOWER(name) LIKE "%luz%"
                 OR LOWER(name) LIKE "%energia%"
                 OR LOWER(name) LIKE "%cartão%"
                 OR LOWER(name) LIKE "%cartao%"
                 OR LOWER(name) LIKE "%fatura%"
               )'
        );
    },
];

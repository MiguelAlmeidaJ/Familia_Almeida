<?php

declare(strict_types=1);

return [
    'description' => 'Vincula pagamentos de contas fixas às movimentações financeiras.',
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

        $indexExists = static function (string $table, string $index) use ($pdo, $database): bool {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.statistics
                 WHERE table_schema = ? AND table_name = ? AND index_name = ?'
            );
            $stmt->execute([$database, $table, $index]);
            return (int) $stmt->fetchColumn() > 0;
        };

        $constraintExists = static function (string $constraint) use ($pdo, $database): bool {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.table_constraints
                 WHERE constraint_schema = ? AND constraint_name = ?'
            );
            $stmt->execute([$database, $constraint]);
            return (int) $stmt->fetchColumn() > 0;
        };

        if (!$columnExists('bill_payments', 'paid_on')) {
            $pdo->exec('ALTER TABLE bill_payments ADD COLUMN paid_on DATE NULL AFTER paid_at');
        }

        if (!$columnExists('transactions', 'bill_payment_id')) {
            $pdo->exec('ALTER TABLE transactions ADD COLUMN bill_payment_id BIGINT UNSIGNED NULL AFTER debt_id');
        }

        if (!$indexExists('transactions', 'transactions_bill_payment_unique')) {
            $pdo->exec('ALTER TABLE transactions ADD UNIQUE KEY transactions_bill_payment_unique (bill_payment_id)');
        }

        if (!$constraintExists('transactions_bill_payment_fk')) {
            $pdo->exec(
                'ALTER TABLE transactions
                 ADD CONSTRAINT transactions_bill_payment_fk
                 FOREIGN KEY (bill_payment_id) REFERENCES bill_payments(id)
                 ON DELETE SET NULL'
            );
        }

        $pdo->exec(
            'UPDATE bill_payments p
             JOIN fixed_bills b ON b.id = p.bill_id
             SET p.paid_on = STR_TO_DATE(
                 CONCAT(
                     p.month,
                     "-",
                     LPAD(
                         LEAST(
                             b.due_day,
                             DAY(LAST_DAY(CONCAT(p.month, "-01")))
                         ),
                         2,
                         "0"
                     )
                 ),
                 "%Y-%m-%d"
             )
             WHERE p.paid = 1 AND p.paid_on IS NULL'
        );

        $pdo->exec(
            'INSERT INTO transactions
                (created_by, debt_id, bill_payment_id, type, description, category, amount, occurred_on)
             SELECT
                NULL,
                NULL,
                p.id,
                "expense",
                b.name,
                "Contas fixas",
                b.amount,
                COALESCE(
                    p.paid_on,
                    STR_TO_DATE(
                        CONCAT(
                            p.month,
                            "-",
                            LPAD(
                                LEAST(
                                    b.due_day,
                                    DAY(LAST_DAY(CONCAT(p.month, "-01")))
                                ),
                                2,
                                "0"
                            )
                        ),
                        "%Y-%m-%d"
                    )
                )
             FROM bill_payments p
             JOIN fixed_bills b ON b.id = p.bill_id
             LEFT JOIN transactions t ON t.bill_payment_id = p.id
             WHERE p.paid = 1 AND t.id IS NULL'
        );
    },
];

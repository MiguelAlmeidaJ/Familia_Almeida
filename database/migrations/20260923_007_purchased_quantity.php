<?php

declare(strict_types=1);

return [
    'description' => 'Permite registrar a quantidade realmente comprada no mercado.',
    'up' => static function (PDO $pdo): void {
        $columnExists = (int) $pdo->query(
            'SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = "shopping_items"
               AND column_name = "purchased_quantity"'
        )->fetchColumn() > 0;

        if (!$columnExists) {
            $pdo->exec(
                'ALTER TABLE shopping_items
                 ADD COLUMN purchased_quantity DECIMAL(10,2) NULL AFTER quantity'
            );
        }

        $pdo->exec(
            'UPDATE shopping_items
             SET purchased_quantity = quantity
             WHERE purchased = 1
               AND purchased_quantity IS NULL'
        );
    },
];

<?php

declare(strict_types=1);

return [
    'description' => 'Classifica produtos entre estoque doméstico e consumo rápido.',
    'up' => static function (PDO $pdo): void {
        $columnExists = (int) $pdo->query(
            'SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = "shopping_items"
               AND column_name = "track_inventory"'
        )->fetchColumn() > 0;

        if (!$columnExists) {
            $pdo->exec(
                'ALTER TABLE shopping_items
                 ADD COLUMN track_inventory TINYINT(1) NOT NULL DEFAULT 1 AFTER purchased_quantity'
            );
        }
    },
];

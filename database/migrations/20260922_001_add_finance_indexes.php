<?php

declare(strict_types=1);

return [
    'description' => 'Adiciona índices para acelerar consultas mensais e metas por categoria.',
    'up' => static function (PDO $pdo): void {
        $indexes = $pdo->query('SHOW INDEX FROM transactions')->fetchAll();
        $existing = [];
        foreach ($indexes as $index) $existing[$index['Key_name']] = true;

        if (!isset($existing['transactions_type_date_idx'])) {
            $pdo->exec('ALTER TABLE transactions ADD INDEX transactions_type_date_idx (type, occurred_on)');
        }
        if (!isset($existing['transactions_category_date_idx'])) {
            $pdo->exec('ALTER TABLE transactions ADD INDEX transactions_category_date_idx (category, occurred_on)');
        }
    },
];

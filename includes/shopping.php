<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function shopping_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];

    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = ?'
    );
    $stmt->execute([$table]);

    return $cache[$table] = ((int) $stmt->fetchColumn() > 0);
}

function shopping_schema_ready(PDO $pdo): bool
{
    return shopping_table_exists($pdo, 'shopping_lists')
        && shopping_table_exists($pdo, 'shopping_items')
        && shopping_table_exists($pdo, 'shopping_purchases');
}

function shopping_get_list(PDO $pdo, string $type, ?string $month, int $userId, bool $create = true): ?array
{
    if (!shopping_schema_ready($pdo)) {
        return null;
    }

    if ($type === 'market') {
        $stmt = $pdo->prepare(
            'SELECT * FROM shopping_lists
             WHERE list_type = "market" AND month = ?
             LIMIT 1'
        );
        $stmt->execute([$month]);
    } else {
        $stmt = $pdo->query(
            'SELECT * FROM shopping_lists
             WHERE list_type = "furniture"
             ORDER BY id
             LIMIT 1'
        );
    }

    $list = $stmt->fetch();
    if ($list || !$create) {
        return $list ?: null;
    }

    $insert = $pdo->prepare(
        'INSERT INTO shopping_lists (list_type, month, created_by)
         VALUES (?, ?, ?)'
    );
    $insert->execute([$type, $type === 'market' ? $month : null, $userId]);

    $id = (int) $pdo->lastInsertId();
    $stmt = $pdo->prepare('SELECT * FROM shopping_lists WHERE id = ?');
    $stmt->execute([$id]);

    return $stmt->fetch() ?: null;
}

function shopping_items(PDO $pdo, int $listId): array
{
    if ($listId <= 0 || !shopping_schema_ready($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT id, list_id, purchase_id, name, category, priority, quantity,
                estimated_price, purchased_price, store_name, product_url,
                purchased, purchased_at, created_at, updated_at
         FROM shopping_items
         WHERE list_id = ?
         ORDER BY
            CASE priority WHEN "high" THEN 1 WHEN "medium" THEN 2 WHEN "low" THEN 3 ELSE 4 END,
            purchased ASC,
            id'
    );
    $stmt->execute([$listId]);

    return array_map(static function (array $row): array {
        $row['id'] = (int) $row['id'];
        $row['list_id'] = (int) $row['list_id'];
        $row['purchase_id'] = $row['purchase_id'] !== null ? (int) $row['purchase_id'] : null;
        $row['quantity'] = (float) $row['quantity'];
        $row['estimated_price'] = $row['estimated_price'] !== null ? (float) $row['estimated_price'] : null;
        $row['purchased_price'] = $row['purchased_price'] !== null ? (float) $row['purchased_price'] : null;
        $row['purchased'] = (bool) $row['purchased'];
        return $row;
    }, $stmt->fetchAll());
}

function shopping_previous_market_list(PDO $pdo, string $month): ?array
{
    if (!shopping_schema_ready($pdo)) {
        return null;
    }

    $previousMonth = (new DateTimeImmutable($month . '-01'))->modify('-1 month')->format('Y-m');

    $stmt = $pdo->prepare(
        'SELECT * FROM shopping_lists
         WHERE list_type = "market" AND month = ?
         LIMIT 1'
    );
    $stmt->execute([$previousMonth]);

    return $stmt->fetch() ?: null;
}

function shopping_market_summary(array $items): array
{
    $estimated = 0.0;
    $actual = 0.0;
    $purchased = 0;

    foreach ($items as $item) {
        $quantity = max(0, (float) ($item['quantity'] ?? 0));
        $estimated += $quantity * (float) ($item['estimated_price'] ?? 0);

        if (!empty($item['purchased'])) {
            $purchased++;
            $actual += $quantity * (float) ($item['purchased_price'] ?? 0);
        }
    }

    return [
        'estimated' => $estimated,
        'actual' => $actual,
        'purchased' => $purchased,
        'pending' => max(0, count($items) - $purchased),
    ];
}

function shopping_validate_url(string $url): ?string
{
    $url = trim($url);
    if ($url === '') {
        return null;
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new RuntimeException('Informe um link de produto válido.');
    }

    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true)) {
        throw new RuntimeException('O link do produto precisa começar com http:// ou https://.');
    }

    return $url;
}


function shopping_pending_market_item_exists(PDO $pdo, int $listId, string $name): bool
{
    if ($listId <= 0 || trim($name) === '' || !shopping_schema_ready($pdo)) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT id
         FROM shopping_items
         WHERE list_id = ?
           AND purchased = 0
           AND LOWER(name) = LOWER(?)
         LIMIT 1'
    );
    $stmt->execute([$listId, trim($name)]);

    return (bool) $stmt->fetchColumn();
}

function shopping_latest_market_price(PDO $pdo, string $name): ?float
{
    if (trim($name) === '' || !shopping_schema_ready($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT
            CASE
                WHEN i.purchased_price IS NOT NULL AND i.purchased_price > 0 THEN i.purchased_price
                WHEN i.estimated_price IS NOT NULL AND i.estimated_price > 0 THEN i.estimated_price
                ELSE NULL
            END AS price
         FROM shopping_items i
         INNER JOIN shopping_lists l ON l.id = i.list_id
         WHERE l.list_type = "market"
           AND LOWER(i.name) = LOWER(?)
           AND (
                (i.purchased_price IS NOT NULL AND i.purchased_price > 0)
                OR (i.estimated_price IS NOT NULL AND i.estimated_price > 0)
           )
         ORDER BY
            CASE WHEN i.purchased_price IS NOT NULL AND i.purchased_price > 0 THEN 0 ELSE 1 END,
            COALESCE(i.purchased_at, i.updated_at, i.created_at) DESC,
            i.id DESC
         LIMIT 1'
    );
    $stmt->execute([trim($name)]);
    $price = $stmt->fetchColumn();

    return $price !== false && $price !== null ? (float) $price : null;
}

function shopping_add_market_item_if_missing(
    PDO $pdo,
    int $listId,
    string $name,
    float $quantity,
    ?float $estimatedPrice = null
): bool {
    $name = trim($name);

    if ($listId <= 0 || $name === '' || $quantity <= 0) {
        throw new RuntimeException('Produto inválido para a lista de mercado.');
    }

    if (shopping_pending_market_item_exists($pdo, $listId, $name)) {
        return false;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO shopping_items
            (list_id, name, quantity, estimated_price)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([
        $listId,
        $name,
        $quantity,
        $estimatedPrice !== null && $estimatedPrice > 0 ? $estimatedPrice : null,
    ]);

    return true;
}

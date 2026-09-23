<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function inventory_table_exists(PDO $pdo, string $table): bool
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

function inventory_schema_ready(PDO $pdo): bool
{
    return inventory_table_exists($pdo, 'inventory_items')
        && inventory_table_exists($pdo, 'inventory_movements');
}

function inventory_items_with_balance(PDO $pdo, bool $includeInactive = false): array
{
    if (!inventory_schema_ready($pdo)) {
        return [];
    }

    $where = $includeInactive ? '' : 'WHERE i.active = 1';

    $stmt = $pdo->query(
        'SELECT
            i.id,
            i.name,
            i.category,
            i.unit,
            i.min_quantity,
            i.active,
            i.created_at,
            i.updated_at,
            COALESCE(SUM(
                CASE
                    WHEN m.movement_type = "entry" THEN m.quantity
                    WHEN m.movement_type = "exit" THEN -m.quantity
                    ELSE 0
                END
            ), 0) AS current_quantity,
            MAX(m.occurred_at) AS last_movement_at
         FROM inventory_items i
         LEFT JOIN inventory_movements m ON m.inventory_item_id = i.id
         ' . $where . '
         GROUP BY i.id, i.name, i.category, i.unit, i.min_quantity, i.active, i.created_at, i.updated_at
         ORDER BY
            CASE
                WHEN COALESCE(SUM(CASE WHEN m.movement_type = "entry" THEN m.quantity WHEN m.movement_type = "exit" THEN -m.quantity ELSE 0 END), 0) <= 0 THEN 1
                WHEN i.min_quantity > 0 AND COALESCE(SUM(CASE WHEN m.movement_type = "entry" THEN m.quantity WHEN m.movement_type = "exit" THEN -m.quantity ELSE 0 END), 0) <= i.min_quantity THEN 2
                ELSE 3
            END,
            i.name'
    );

    return array_map(static function (array $row): array {
        $row['id'] = (int) $row['id'];
        $row['min_quantity'] = (float) $row['min_quantity'];
        $row['current_quantity'] = (float) $row['current_quantity'];
        $row['active'] = (bool) $row['active'];
        return $row;
    }, $stmt->fetchAll());
}

function inventory_balance(PDO $pdo, int $itemId, bool $lock = false): float
{
    if ($itemId <= 0 || !inventory_schema_ready($pdo)) {
        return 0.0;
    }

    if ($lock) {
        $lockStmt = $pdo->prepare('SELECT id FROM inventory_items WHERE id = ? FOR UPDATE');
        $lockStmt->execute([$itemId]);
        if (!$lockStmt->fetchColumn()) {
            throw new RuntimeException('Produto de estoque não encontrado.');
        }
    }

    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(
            CASE
                WHEN movement_type = "entry" THEN quantity
                WHEN movement_type = "exit" THEN -quantity
                ELSE 0
            END
        ), 0)
         FROM inventory_movements
         WHERE inventory_item_id = ?'
    );
    $stmt->execute([$itemId]);

    return (float) $stmt->fetchColumn();
}

function inventory_recent_movements(PDO $pdo, int $limit = 20): array
{
    if (!inventory_schema_ready($pdo)) {
        return [];
    }

    $limit = max(1, min(100, $limit));

    $stmt = $pdo->query(
        'SELECT
            m.id,
            m.inventory_item_id,
            m.movement_type,
            m.source_type,
            m.quantity,
            m.note,
            m.occurred_at,
            m.source_purchase_id,
            m.source_shopping_item_id,
            i.name AS item_name,
            i.unit,
            u.name AS created_by_name
         FROM inventory_movements m
         INNER JOIN inventory_items i ON i.id = m.inventory_item_id
         LEFT JOIN users u ON u.id = m.created_by
         ORDER BY m.occurred_at DESC, m.id DESC
         LIMIT ' . $limit
    );

    return array_map(static function (array $row): array {
        $row['id'] = (int) $row['id'];
        $row['inventory_item_id'] = (int) $row['inventory_item_id'];
        $row['quantity'] = (float) $row['quantity'];
        return $row;
    }, $stmt->fetchAll());
}

function inventory_find_or_create(PDO $pdo, string $name, string $category = 'Mercado', string $unit = 'un'): int
{
    $name = trim($name);
    if ($name === '') {
        throw new RuntimeException('Produto de estoque sem nome.');
    }

    $stmt = $pdo->prepare('SELECT id FROM inventory_items WHERE LOWER(name) = LOWER(?) LIMIT 1');
    $stmt->execute([$name]);
    $id = $stmt->fetchColumn();

    if ($id) {
        $activate = $pdo->prepare(
            'UPDATE inventory_items
             SET active = 1,
                 category = COALESCE(NULLIF(category, ""), ?)
             WHERE id = ?'
        );
        $activate->execute([$category, (int) $id]);
        return (int) $id;
    }

    $insert = $pdo->prepare(
        'INSERT INTO inventory_items (name, category, unit)
         VALUES (?, ?, ?)'
    );
    $insert->execute([$name, $category !== '' ? $category : null, $unit !== '' ? $unit : 'un']);

    return (int) $pdo->lastInsertId();
}

function inventory_record_purchase_items(
    PDO $pdo,
    int $purchaseId,
    array $shoppingRows,
    int $userId,
    string $purchaseDate
): void {
    if (!inventory_schema_ready($pdo) || !$shoppingRows) {
        return;
    }

    $insertMovement = $pdo->prepare(
        'INSERT IGNORE INTO inventory_movements
            (inventory_item_id, movement_type, source_type, quantity, note,
             occurred_at, created_by, source_purchase_id, source_shopping_item_id)
         VALUES (?, "entry", "purchase", ?, ?, ?, ?, ?, ?)'
    );

    foreach ($shoppingRows as $row) {
        $shoppingItemId = (int) ($row['id'] ?? 0);
        $quantity = (float) ($row['quantity'] ?? 0);
        $name = trim((string) ($row['name'] ?? ''));

        if ($shoppingItemId <= 0 || $quantity <= 0 || $name === '') {
            continue;
        }

        $inventoryItemId = inventory_find_or_create($pdo, $name, 'Mercado', 'un');
        $insertMovement->execute([
            $inventoryItemId,
            $quantity,
            'Compra de mercado',
            $purchaseDate . ' 12:00:00',
            $userId,
            $purchaseId,
            $shoppingItemId,
        ]);
    }
}

function inventory_quantity_label(float $quantity, string $unit): string
{
    $formatted = rtrim(rtrim(number_format($quantity, 3, ',', '.'), '0'), ',');
    return $formatted . ' ' . $unit;
}

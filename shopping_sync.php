<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/shopping.php';
require_once __DIR__ . '/includes/inventory.php';

$user = require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'message' => 'Método não permitido.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

try {
    $payload = json_decode((string) file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);

    $token = (string) ($payload['csrf_token'] ?? '');
    if ($token === '' || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        throw new RuntimeException('Sessão expirada. Entre novamente para sincronizar a compra.');
    }

    $pdo = db();

    if (!shopping_schema_ready($pdo)) {
        throw new RuntimeException('Existe uma migration pendente para a lista de compras.');
    }
    if (!shopping_purchase_quantity_ready($pdo)) {
        throw new RuntimeException('Execute a migration 007 para registrar a quantidade realmente comprada.');
    }
    if (!shopping_inventory_tracking_ready($pdo)) {
        throw new RuntimeException('Execute a migration 008 para classificar consumo rápido e estoque.');
    }

    $listId = (int) ($payload['list_id'] ?? 0);
    $clientPurchaseId = trim((string) ($payload['client_purchase_id'] ?? ''));
    $purchaseDate = (string) ($payload['purchase_date'] ?? date('Y-m-d'));
    $itemsPayload = is_array($payload['items'] ?? null) ? $payload['items'] : [];

    if ($listId <= 0 || $clientPurchaseId === '' || strlen($clientPurchaseId) > 80) {
        throw new RuntimeException('Compra offline inválida.');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $purchaseDate)) {
        throw new RuntimeException('Data da compra inválida.');
    }

    $duplicateStmt = $pdo->prepare(
        'SELECT id, transaction_id, total_amount
         FROM shopping_purchases
         WHERE client_purchase_id = ?
         LIMIT 1'
    );
    $duplicateStmt->execute([$clientPurchaseId]);

    if ($duplicate = $duplicateStmt->fetch()) {
        echo json_encode([
            'ok' => true,
            'duplicate' => true,
            'purchase_id' => (int) $duplicate['id'],
            'transaction_id' => (int) $duplicate['transaction_id'],
            'total' => (float) $duplicate['total_amount'],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $listStmt = $pdo->prepare(
        'SELECT id, month
         FROM shopping_lists
         WHERE id = ? AND list_type = "market"
         LIMIT 1'
    );
    $listStmt->execute([$listId]);
    $list = $listStmt->fetch();

    if (!$list) {
        throw new RuntimeException('Lista de mercado não encontrada.');
    }

    $existingRequested = [];
    $localRequested = [];

    foreach ($itemsPayload as $item) {
        if (!is_array($item)) {
            continue;
        }

        $price = (float) ($item['purchased_price'] ?? 0);
        $purchasedQuantity = (float) ($item['purchased_quantity'] ?? 0);
        $store = trim((string) ($item['store_name'] ?? ''));
        $trackInventory = !array_key_exists('track_inventory', $item) || (bool) $item['track_inventory'];

        if ($price <= 0 || $purchasedQuantity <= 0) {
            continue;
        }

        $itemId = (int) ($item['id'] ?? 0);

        $normalized = [
            'purchased_price' => $price,
            'purchased_quantity' => $purchasedQuantity,
            'store_name' => substr($store, 0, 160),
            'track_inventory' => $trackInventory,
        ];

        if ($itemId > 0) {
            $existingRequested[$itemId] = $normalized;
            continue;
        }

        $name = trim((string) ($item['name'] ?? ''));
        $clientItemId = trim((string) ($item['client_item_id'] ?? $item['local_id'] ?? ''));
        $plannedQuantity = (float) ($item['planned_quantity'] ?? $item['quantity'] ?? $purchasedQuantity);
        $estimatedPrice = (float) ($item['estimated_price'] ?? 0);

        if ($name === '' || $clientItemId === '' || strlen($clientItemId) > 100 || $plannedQuantity <= 0) {
            throw new RuntimeException('Um produto adicionado offline está incompleto.');
        }

        $localRequested[] = $normalized + [
            'client_item_id' => $clientItemId,
            'name' => substr($name, 0, 160),
            'planned_quantity' => $plannedQuantity,
            'estimated_price' => $estimatedPrice > 0 ? $estimatedPrice : null,
        ];
    }

    if (!$existingRequested && !$localRequested) {
        throw new RuntimeException('Selecione ao menos um produto e informe quantidade e preço comprados.');
    }

    $pdo->beginTransaction();

    $existingRows = [];

    if ($existingRequested) {
        $placeholders = implode(',', array_fill(0, count($existingRequested), '?'));
        $itemStmt = $pdo->prepare(
            'SELECT id, name, quantity, purchased, track_inventory
             FROM shopping_items
             WHERE list_id = ? AND id IN (' . $placeholders . ')
             FOR UPDATE'
        );
        $itemStmt->execute(array_merge([$listId], array_keys($existingRequested)));
        $existingRows = $itemStmt->fetchAll();

        if (count($existingRows) !== count($existingRequested)) {
            throw new RuntimeException('Um ou mais produtos não pertencem a esta lista.');
        }
    }

    $total = 0.0;
    $stores = [];

    foreach ($existingRows as $row) {
        if ((bool) $row['purchased']) {
            throw new RuntimeException('Um dos produtos já foi comprado. Atualize a lista antes de sincronizar.');
        }

        $requested = $existingRequested[(int) $row['id']];
        $total += $requested['purchased_quantity'] * $requested['purchased_price'];

        if ($requested['store_name'] !== '') {
            $stores[strtolower($requested['store_name'])] = $requested['store_name'];
        }
    }

    foreach ($localRequested as $requested) {
        $total += $requested['purchased_quantity'] * $requested['purchased_price'];

        if ($requested['store_name'] !== '') {
            $stores[strtolower($requested['store_name'])] = $requested['store_name'];
        }
    }

    $total = round($total, 2);
    if ($total <= 0) {
        throw new RuntimeException('O total da compra precisa ser maior que zero.');
    }

    $description = 'Compra de mercado';
    if (count($stores) === 1) {
        $description .= ' — ' . array_values($stores)[0];
    } elseif (count($stores) > 1) {
        $description .= ' — vários mercados';
    }

    $transactionStmt = $pdo->prepare(
        'INSERT INTO transactions
            (created_by, type, description, category, amount, occurred_on)
         VALUES (?, "expense", ?, "Mercado", ?, ?)'
    );
    $transactionStmt->execute([(int) $user['id'], $description, $total, $purchaseDate]);
    $transactionId = (int) $pdo->lastInsertId();

    $purchaseStmt = $pdo->prepare(
        'INSERT INTO shopping_purchases
            (list_id, transaction_id, created_by, client_purchase_id, purchase_date, total_amount)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $purchaseStmt->execute([
        $listId,
        $transactionId,
        (int) $user['id'],
        $clientPurchaseId,
        $purchaseDate,
        $total,
    ]);
    $purchaseId = (int) $pdo->lastInsertId();

    $inventoryRows = [];

    if ($existingRows) {
        $updateItem = $pdo->prepare(
            'UPDATE shopping_items
             SET purchase_id = ?,
                 purchased_quantity = ?,
                 purchased_price = ?,
                 store_name = ?,
                 track_inventory = ?,
                 purchased = 1,
                 purchased_at = NOW()
             WHERE id = ? AND list_id = ?'
        );

        foreach ($existingRows as $row) {
            $itemId = (int) $row['id'];
            $requested = $existingRequested[$itemId];

            $updateItem->execute([
                $purchaseId,
                $requested['purchased_quantity'],
                $requested['purchased_price'],
                $requested['store_name'] !== '' ? $requested['store_name'] : null,
                $requested['track_inventory'] ? 1 : 0,
                $itemId,
                $listId,
            ]);

            $row['purchased_quantity'] = $requested['purchased_quantity'];
            $row['track_inventory'] = $requested['track_inventory'];
            $inventoryRows[] = $row;
        }
    }

    $localItemMap = [];

    if ($localRequested) {
        $insertLocal = $pdo->prepare(
            'INSERT INTO shopping_items
                (list_id, purchase_id, name, quantity, purchased_quantity,
                 estimated_price, purchased_price, store_name, track_inventory,
                 purchased, purchased_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())'
        );

        foreach ($localRequested as $requested) {
            $insertLocal->execute([
                $listId,
                $purchaseId,
                $requested['name'],
                $requested['planned_quantity'],
                $requested['purchased_quantity'],
                $requested['estimated_price'],
                $requested['purchased_price'],
                $requested['store_name'] !== '' ? $requested['store_name'] : null,
                $requested['track_inventory'] ? 1 : 0,
            ]);

            $serverItemId = (int) $pdo->lastInsertId();
            $localItemMap[$requested['client_item_id']] = $serverItemId;

            $inventoryRows[] = [
                'id' => $serverItemId,
                'name' => $requested['name'],
                'quantity' => $requested['planned_quantity'],
                'purchased_quantity' => $requested['purchased_quantity'],
                'track_inventory' => $requested['track_inventory'],
            ];
        }
    }

    inventory_record_purchase_items(
        $pdo,
        $purchaseId,
        $inventoryRows,
        (int) $user['id'],
        $purchaseDate
    );

    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'purchase_id' => $purchaseId,
        'transaction_id' => $transactionId,
        'total' => $total,
        'local_item_map' => $localItemMap,
        'message' => 'Compra sincronizada e lançada nos gastos.',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if (http_response_code() < 400) {
        http_response_code(422);
    }

    echo json_encode([
        'ok' => false,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}

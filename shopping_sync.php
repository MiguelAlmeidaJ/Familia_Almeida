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
        throw new RuntimeException('Existe uma atualização pendente para registrar a quantidade realmente comprada. Execute em Configurações > Manutenção.');
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
        'SELECT p.id, p.transaction_id, p.total_amount
         FROM shopping_purchases p
         WHERE p.client_purchase_id = ?
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
        'SELECT id, list_type, month
         FROM shopping_lists
         WHERE id = ? AND list_type = "market"
         LIMIT 1'
    );
    $listStmt->execute([$listId]);
    $list = $listStmt->fetch();
    if (!$list) {
        throw new RuntimeException('Lista de mercado não encontrada.');
    }

    $requested = [];
    foreach ($itemsPayload as $item) {
        if (!is_array($item)) {
            continue;
        }

        $itemId = (int) ($item['id'] ?? 0);
        $price = (float) ($item['purchased_price'] ?? 0);
        $purchasedQuantity = (float) ($item['purchased_quantity'] ?? 0);
        $store = trim((string) ($item['store_name'] ?? ''));

        if ($itemId > 0 && $price > 0) {
            $requested[$itemId] = [
                'purchased_price' => $price,
                'purchased_quantity' => $purchasedQuantity > 0 ? $purchasedQuantity : null,
                'store_name' => substr($store, 0, 160),
            ];
        }
    }

    if (!$requested) {
        throw new RuntimeException('Selecione ao menos um produto e informe o preço comprado.');
    }

    $placeholders = implode(',', array_fill(0, count($requested), '?'));
    $itemStmt = $pdo->prepare(
        'SELECT id, name, quantity, purchased
         FROM shopping_items
         WHERE list_id = ? AND id IN (' . $placeholders . ')
         FOR UPDATE'
    );

    $pdo->beginTransaction();

    $itemStmt->execute(array_merge([$listId], array_keys($requested)));
    $rows = $itemStmt->fetchAll();

    if (count($rows) !== count($requested)) {
        throw new RuntimeException('Um ou mais produtos da compra não pertencem a esta lista.');
    }

    $total = 0.0;
    $stores = [];

    foreach ($rows as $row) {
        if ((bool) $row['purchased']) {
            throw new RuntimeException('Um dos produtos já foi marcado como comprado. Atualize a lista antes de sincronizar novamente.');
        }

        $itemId = (int) $row['id'];
        $price = (float) $requested[$itemId]['purchased_price'];
        $quantity = $requested[$itemId]['purchased_quantity'] !== null
            ? (float) $requested[$itemId]['purchased_quantity']
            : (float) $row['quantity'];

        if ($quantity <= 0) {
            throw new RuntimeException('A quantidade comprada precisa ser maior que zero.');
        }

        $requested[$itemId]['purchased_quantity'] = $quantity;
        $total += $quantity * $price;

        $store = trim((string) $requested[$itemId]['store_name']);
        if ($store !== '') {
            $stores[strtolower($store)] = $store;
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

    $updateItem = $pdo->prepare(
        'UPDATE shopping_items
         SET purchase_id = ?, purchased_quantity = ?, purchased_price = ?, store_name = ?,
             purchased = 1, purchased_at = NOW()
         WHERE id = ? AND list_id = ?'
    );

    $inventoryRows = [];

    foreach ($rows as $row) {
        $itemId = (int) $row['id'];
        $updateItem->execute([
            $purchaseId,
            $requested[$itemId]['purchased_quantity'],
            $requested[$itemId]['purchased_price'],
            $requested[$itemId]['store_name'] !== '' ? $requested[$itemId]['store_name'] : null,
            $itemId,
            $listId,
        ]);

        $row['purchased_quantity'] = $requested[$itemId]['purchased_quantity'];
        $inventoryRows[] = $row;
    }

    // Se o módulo de estoque já estiver instalado, cada item comprado
    // entra automaticamente no estoque. A chave do item de compra
    // impede que uma sincronização repetida duplique a entrada.
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

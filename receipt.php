<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/receipts.php';

require_auth();

$receiptId = (int) ($_GET['id'] ?? 0);
if ($receiptId <= 0 || !receipts_schema_ready(db())) {
    http_response_code(404);
    exit('Comprovante não encontrado.');
}

$stmt = db()->prepare(
    'SELECT id, original_name, stored_name, mime_type
     FROM transaction_receipts
     WHERE id = ?
     LIMIT 1'
);
$stmt->execute([$receiptId]);
$receipt = $stmt->fetch();

if (!$receipt) {
    http_response_code(404);
    exit('Comprovante não encontrado.');
}

$storage = realpath(receipt_storage_dir());
$file = realpath(receipt_storage_dir() . DIRECTORY_SEPARATOR . basename((string) $receipt['stored_name']));

if (!$storage || !$file || !str_starts_with($file, $storage . DIRECTORY_SEPARATOR) || !is_file($file)) {
    http_response_code(404);
    exit('Arquivo do comprovante não encontrado.');
}

$original = str_replace(['"', "\r", "\n"], '', (string) $receipt['original_name']);
$mime = (string) $receipt['mime_type'];

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file));
header('Content-Disposition: inline; filename="' . $original . '"; filename*=UTF-8\'\'' . rawurlencode($original));
header('Cache-Control: private, max-age=300');
header('X-Content-Type-Options: nosniff');

readfile($file);
exit;

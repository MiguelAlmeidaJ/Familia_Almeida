<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

const RECEIPT_MAX_FILES = 5;
const RECEIPT_MAX_BYTES = 8 * 1024 * 1024;

function db_table_exists(PDO $pdo, string $table): bool
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

function receipts_schema_ready(PDO $pdo): bool
{
    return db_table_exists($pdo, 'transaction_receipts');
}

function receipt_storage_dir(): string
{
    return dirname(__DIR__) . '/storage/receipts';
}

function receipt_allowed_mimes(): array
{
    return [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/heic' => 'heic',
        'image/heif' => 'heif',
        'application/pdf' => 'pdf',
    ];
}

function normalize_receipt_files(?array $files): array
{
    if (!$files || !isset($files['error'])) {
        return [];
    }

    if (!is_array($files['error'])) {
        return [[
            'name' => $files['name'] ?? '',
            'type' => $files['type'] ?? '',
            'tmp_name' => $files['tmp_name'] ?? '',
            'error' => $files['error'] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'] ?? 0,
        ]];
    }

    $normalized = [];
    foreach ($files['error'] as $index => $error) {
        $normalized[] = [
            'name' => $files['name'][$index] ?? '',
            'type' => $files['type'][$index] ?? '',
            'tmp_name' => $files['tmp_name'][$index] ?? '',
            'error' => $error,
            'size' => $files['size'][$index] ?? 0,
        ];
    }

    return $normalized;
}

function prepare_receipt_uploads(?array $files): array
{
    $normalized = array_values(array_filter(
        normalize_receipt_files($files),
        fn(array $file) => (int) $file['error'] !== UPLOAD_ERR_NO_FILE
    ));

    if (!$normalized) {
        return [];
    }

    if (count($normalized) > RECEIPT_MAX_FILES) {
        throw new RuntimeException('Envie no máximo ' . RECEIPT_MAX_FILES . ' notas por lançamento.');
    }

    $allowed = receipt_allowed_mimes();
    $prepared = [];

    foreach ($normalized as $file) {
        $error = (int) $file['error'];

        if ($error !== UPLOAD_ERR_OK) {
            $messages = [
                UPLOAD_ERR_INI_SIZE => 'Uma das notas ultrapassa o limite permitido pela hospedagem.',
                UPLOAD_ERR_FORM_SIZE => 'Uma das notas ultrapassa o limite permitido.',
                UPLOAD_ERR_PARTIAL => 'O envio de uma das notas foi interrompido. Tente novamente.',
                UPLOAD_ERR_NO_TMP_DIR => 'A hospedagem está sem pasta temporária para uploads.',
                UPLOAD_ERR_CANT_WRITE => 'A hospedagem não conseguiu gravar o arquivo enviado.',
                UPLOAD_ERR_EXTENSION => 'Uma extensão do PHP bloqueou o upload.',
            ];
            throw new RuntimeException($messages[$error] ?? 'Não foi possível enviar uma das notas.');
        }

        $size = (int) $file['size'];
        if ($size <= 0 || $size > RECEIPT_MAX_BYTES) {
            throw new RuntimeException('Cada nota deve ter no máximo 8 MB.');
        }

        $tmp = (string) $file['tmp_name'];
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Upload de nota inválido.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmp);

        if (!isset($allowed[$mime])) {
            throw new RuntimeException('Formato de nota não permitido. Use JPG, PNG, WEBP, HEIC ou PDF.');
        }

        $original = trim((string) $file['name']);
        $original = basename(str_replace(["\0", "\r", "\n"], '', $original));
        if ($original === '') {
            $original = 'comprovante.' . $allowed[$mime];
        }

        $prepared[] = [
            'tmp_name' => $tmp,
            'original_name' => substr($original, 0, 240),
            'mime_type' => $mime,
            'extension' => $allowed[$mime],
            'file_size' => $size,
        ];
    }

    return $prepared;
}

function ensure_receipt_storage(): string
{
    $dir = receipt_storage_dir();

    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Não foi possível criar a pasta de comprovantes.');
    }

    if (!is_writable($dir)) {
        throw new RuntimeException('A pasta storage/receipts precisa de permissão de escrita.');
    }

    return $dir;
}

function save_receipts_for_transaction(PDO $pdo, int $transactionId, int $userId, array $prepared): array
{
    if (!$prepared) {
        return [];
    }

    if (!receipts_schema_ready($pdo)) {
        throw new RuntimeException('Existe uma migration pendente para anexar notas. Execute em Configurações > Manutenção.');
    }

    $dir = ensure_receipt_storage();
    $stored = [];

    try {
        $insert = $pdo->prepare(
            'INSERT INTO transaction_receipts
                (transaction_id, uploaded_by, original_name, stored_name, mime_type, file_size)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        foreach ($prepared as $file) {
            $storedName = bin2hex(random_bytes(20)) . '.' . $file['extension'];
            $destination = $dir . DIRECTORY_SEPARATOR . $storedName;

            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                throw new RuntimeException('Não foi possível salvar uma das notas na hospedagem.');
            }

            $stored[] = $storedName;

            $insert->execute([
                $transactionId,
                $userId,
                $file['original_name'],
                $storedName,
                $file['mime_type'],
                $file['file_size'],
            ]);
        }

        return $stored;
    } catch (Throwable $exception) {
        foreach ($stored as $storedName) {
            $path = $dir . DIRECTORY_SEPARATOR . $storedName;
            if (is_file($path)) {
                @unlink($path);
            }
        }
        throw $exception;
    }
}

function receipts_for_month(PDO $pdo, string $month): array
{
    if (!receipts_schema_ready($pdo)) {
        return [];
    }

    $start = $month . '-01';
    $next = (new DateTimeImmutable($start))->modify('+1 month')->format('Y-m-d');

    $stmt = $pdo->prepare(
        'SELECT r.id, r.transaction_id, r.original_name, r.mime_type, r.file_size, r.created_at
         FROM transaction_receipts r
         INNER JOIN transactions t ON t.id = r.transaction_id
         WHERE t.occurred_on >= ? AND t.occurred_on < ?
         ORDER BY r.id'
    );
    $stmt->execute([$start, $next]);

    $result = [];
    foreach ($stmt->fetchAll() as $row) {
        $transactionId = (int) $row['transaction_id'];
        $row['id'] = (int) $row['id'];
        $row['file_size'] = (int) $row['file_size'];
        $result[$transactionId][] = $row;
    }

    return $result;
}

function receipt_files_for_transaction(PDO $pdo, int $transactionId): array
{
    if (!receipts_schema_ready($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT id, stored_name
         FROM transaction_receipts
         WHERE transaction_id = ?'
    );
    $stmt->execute([$transactionId]);

    return $stmt->fetchAll();
}

function delete_receipt_files(array $rows): void
{
    $dir = receipt_storage_dir();

    foreach ($rows as $row) {
        $storedName = basename((string) ($row['stored_name'] ?? ''));
        if ($storedName === '') {
            continue;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $storedName;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

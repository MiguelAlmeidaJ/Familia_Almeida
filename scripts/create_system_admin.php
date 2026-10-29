<?php
declare(strict_types=1);

// Run from a secure CLI shell after migration 20261029_011 has been applied.
// Required environment variables: SYSTEM_ADMIN_EMAIL and SYSTEM_ADMIN_PASSWORD.
// Optional: SYSTEM_ADMIN_NAME. Never hardcode or commit credentials.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Somente CLI.');
}
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$email = strtolower(trim((string) getenv('SYSTEM_ADMIN_EMAIL')));
$password = (string) getenv('SYSTEM_ADMIN_PASSWORD');
$name = trim((string) (getenv('SYSTEM_ADMIN_NAME') ?: 'Administrador do sistema'));

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12 || strlen($name) < 1 || strlen($name) > 80) {
    fwrite(STDERR, "Informe SYSTEM_ADMIN_EMAIL, SYSTEM_ADMIN_PASSWORD (mínimo 12 caracteres) e opcionalmente SYSTEM_ADMIN_NAME.\n");
    exit(1);
}
$pdo = db();
$ready = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'system_role'")->fetchColumn();
if (!$ready) {
    fwrite(STDERR, "Execute primeiro a migration 20261029_011.\n");
    exit(1);
}
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? FOR UPDATE');
    $stmt->execute([$email]);
    $id = $stmt->fetchColumn();
    if ($id) {
        $stmt = $pdo->prepare("UPDATE users SET name=?,password_hash=?,system_role='system_admin' WHERE id=?");
        $stmt->execute([$name,password_hash($password,PASSWORD_DEFAULT),$id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO users(name,email,password_hash,system_role) VALUES (?, ?, ?, 'system_admin')");
        $stmt->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
        $id = (int)$pdo->lastInsertId();
    }
    $pdo->commit();
    echo "Administrador criado/atualizado. ID: {$id}\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "Falha: " . $e->getMessage() . "\n");
    exit(1);
}

<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

/**
 * Resolve tenant only from authenticated server-side memberships, never from
 * request parameters. Fail closed if the tenant migration is not installed.
 */
function tenant_context(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("SELECT u.system_role FROM users u WHERE u.id=?");
    $stmt->execute([$userId]);
    $role = $stmt->fetchColumn();
    if ($role === false) {
        throw new RuntimeException('Usuário não encontrado.');
    }
    $stmt = $pdo->prepare("SELECT t.id,t.name,tu.role
        FROM tenant_users tu INNER JOIN tenants t ON t.id=tu.tenant_id
        WHERE tu.user_id=? AND t.active=1 ORDER BY t.id");
    $stmt->execute([$userId]);
    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $allowed = array_map(static fn(array $t): int => (int)$t['id'], $tenants);
    $selected = (int)($_SESSION['tenant_id'] ?? 0);
    if (!in_array($selected,$allowed,true)) {
        $selected = $allowed[0] ?? 0;
    }
    if ($selected < 1 && $role !== 'system_admin') {
        throw new RuntimeException('Usuário sem família autorizada.');
    }
    $_SESSION['tenant_id'] = $selected ?: null;
    return ['system_admin'=>$role==='system_admin','tenant_id'=>$selected,'tenants'=>$tenants];
}

function require_tenant_id(): int
{
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if (!$userId) {
        http_response_code(401);
        exit('Autenticação necessária.');
    }
    $ctx = tenant_context(db(), $userId);
    if ($ctx['tenant_id'] < 1) {
        http_response_code(403);
        exit('Selecione uma família autorizada.');
    }
    return $ctx['tenant_id'];
}

function require_system_admin(): void
{
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if (!$userId || !tenant_context(db(),$userId)['system_admin']) {
        http_response_code(403);
        exit('Acesso restrito ao administrador do sistema.');
    }
}

function switch_tenant(int $tenantId): void
{
    $ctx = tenant_context(db(), (int)($_SESSION['user_id'] ?? 0));
    foreach ($ctx['tenants'] as $tenant) {
        if ((int)$tenant['id'] === $tenantId) {
            session_regenerate_id(true);
            $_SESSION['tenant_id'] = $tenantId;
            unset($_SESSION['selected_month']);
            return;
        }
    }
    http_response_code(403);
    exit('Família não autorizada.');
}

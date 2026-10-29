<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/tenant.php';

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    static $user = null;
    if (is_array($user)) {
        return $user;
    }

    $stmt = db()->prepare('SELECT id, name, email FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $_SESSION['user_id']]);
    $row = $stmt->fetch();

    if (!$row) {
        unset($_SESSION['user_id']);
        return null;
    }

    $check = db()->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='tenant_users'");
    if ((int)$check->fetchColumn() > 0) {
        $ctx = tenant_context(db(), (int)$row['id']);
        $row['tenant_id'] = $ctx['tenant_id'];
        $row['system_admin'] = $ctx['system_admin'];
    }
    $user = $row;
    return $user;
}

function require_auth(): array
{
    $user = current_user();
    if (!$user) {
        redirect_to('/login');
    }
    return $user;
}

function require_admin_access(): array
{
    $user = require_auth();
    if (empty($user['system_admin'])) {
        http_response_code(403);
        exit('Acesso restrito ao administrador do sistema.');
    }
    return $user;
}

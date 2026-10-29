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
    if (!empty($user['system_admin']) && empty($user['tenant_id'])) {
        $script = str_replace('\\\\','/',(string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $allowed = ['/system_admin.php','/admin/tenants.php','/admin/configuracoes.php','/admin/branding_upload.php','/admin/family_action.php','/pages/configuracoes.php','/pages/manutencao.php'];
        if (!in_array($script,$allowed,true)) redirect_to('/system_admin.php');
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

function require_family_access(): array
{
    $user=require_auth();
    if (!empty($user['system_admin']) && empty($user['tenant_id'])) {
        redirect_to('/system_admin.php');
    }
    if (empty($user['tenant_id'])) {
        http_response_code(403);
        exit('Usuário sem família autorizada.');
    }
    return $user;
}

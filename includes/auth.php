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
    $avatar=db()->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='users' AND column_name='avatar_path'")->fetchColumn();
    if((int)$avatar){$a=db()->prepare('SELECT avatar_path FROM users WHERE id=?');$a->execute([(int)$row['id']]);$row['avatar_path']=$a->fetchColumn()?:null;}
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
        $allowed = ['/system_admin.php','/admin/tenants.php','/admin/familia.php','/admin/configuracoes.php','/admin/branding_upload.php','/admin/family_action.php','/admin/registration_action.php','/pages/configuracoes.php','/pages/manutencao.php'];
        if (!in_array($script,$allowed,true)) redirect_to('/system_admin.php');
    }
    // Fail closed while the remaining legacy financial modules still lack
    // complete tenant/owner predicates. Never serve another tenant's data.
    if (empty($user['system_admin']) && !empty($user['tenant_id'])) {
        $stmt=db()->prepare('SELECT slug, finance_mode FROM tenants WHERE id=? AND active=1 LIMIT 1');
        $stmt->execute([(int)$user['tenant_id']]);
        $family=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$family || ($family['slug']!=='familia-almeida' && getenv('BEMORA_MULTI_TENANT_RELEASE')!=='1') ||
           !in_array($family['finance_mode'],['shared','separate'],true)){
            http_response_code(403);
            exit('Família não autorizada nesta versão do Bemora.');
        }
        if($family['finance_mode']==='separate'){
            require_once __DIR__.'/finance_scope.php';
            if(!finance_separate_schema_ready(db())){
                http_response_code(503);
                exit('Execute as migrations 015 e 016 para acessar as finanças individuais.');
            }
            // Whitelist only the active, ownership-audited module entrypoints.
            // Legacy PHP endpoints are unavailable even when accessed directly.
            $script=str_replace('\\\\','/',(string)($_SERVER['SCRIPT_NAME']??''));
            $allowed=['/index.php','/action.php','/budget_action.php','/categories_action.php',
                '/receipt.php','/pages/movimentacoes.php','/pages/contas.php',
                '/pages/categorias.php','/pages/minha_conta.php',
                '/pages/meu_perfil.php','/pages/profile_action.php','/pages/profile_avatar.php',
                '/pages/relatorios.php','/pages/membros.php','/pages/membros_action.php','/pages/member_avatar.php',
                '/pages/family_settings_action.php','/logout.php','/convite-familia.php'];
            $path=(string)(parse_url((string)($_SERVER['REQUEST_URI']??'/'),PHP_URL_PATH)??'/');
            $allowedRoutes=['/','/acao','/movimentacoes','/contas','/categorias',
                '/categorias/acao','/minha-conta','/relatorios','/membros','/membros/acao','/membros/foto','/meu-perfil','/meu-perfil/acao','/meu-perfil/foto','/comprovante','/sair','/convite-familia'];
            if(!in_array($script,$allowed,true) && !in_array(rtrim($path,'/')?:'/',$allowedRoutes,true)){
                http_response_code(403);
                exit('Funcionalidade indisponível no modo de finanças individuais.');
            }
        }
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

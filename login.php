<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/branding.php';
require_once __DIR__ . '/includes/login_protection.php';
header('Cache-Control: private, no-store, max-age=0');

if (!empty($_SESSION['user_id'])) {
    require_once __DIR__ . '/includes/auth.php';
    $existing = current_user();
    redirect_to(!empty($existing['system_admin']) ? '/system_admin.php' : '/');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    $remoteIp=(string)($_SERVER['REMOTE_ADDR']??'');
    try {
        $pdo=db();
        if(!bemora_login_check($pdo,$email,$remoteIp)){
            http_response_code(429);
            header('Retry-After: 900');
            $error='Muitas tentativas de acesso. Aguarde 15 minutos e tente novamente.';
        } elseif(!filter_var($email,FILTER_VALIDATE_EMAIL) || $password===''){
            $error='Informe e-mail e senha.';
        } else {
            $stmt=$pdo->prepare('SELECT id,name,email,password_hash FROM users WHERE email=? LIMIT 1');
            $stmt->execute([$email]);
            $user=$stmt->fetch();
            if($user && password_verify($password,(string)$user['password_hash'])){
                bemora_login_succeeded($pdo,$email,$remoteIp);
                session_regenerate_id(true);
                $_SESSION['user_id']=(int)$user['id'];
                $_SESSION['auth_issued_at']=time();
                $_SESSION['auth_last_seen']=time();
                unset($_SESSION['tenant_id'],$_SESSION['selected_month']);
                $adminCheck=$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='users' AND column_name='system_role'");
                if((int)$adminCheck->fetchColumn()>0){
                    $roleStmt=$pdo->prepare('SELECT system_role FROM users WHERE id=?');
                    $roleStmt->execute([(int)$user['id']]);
                    if($roleStmt->fetchColumn()==='system_admin')redirect_to('/system_admin.php');
                }
                redirect_to('/painel');
            }
            bemora_login_failed($pdo,$email,$remoteIp);
            $error='E-mail ou senha inválidos.';
        }
    } catch(Throwable $exception){
        error_log('Bemora login: authentication storage unavailable.');
        http_response_code(503);
        $error='Não foi possível processar o acesso neste momento. Tente novamente mais tarde.';
    }
}

?><!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Entrar • Bemora</title><?php brand_head_tags(); ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"><link rel="stylesheet" href="assets/style.css"><link rel="stylesheet" href="/assets/bemora.css?v=1"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"><link rel="stylesheet" href="/assets/bemora-login.css?v=2">
</head>
<body>
<main class="login-screen">
    <section class="login-card">
        <div class="brand brand-dark">
            <div class="brandmark"><?php if(brand_asset_url('logo')): ?><img src="<?= e(brand_asset_url('logo')) ?>" alt="Bemora"><?php else: ?><strong>bemora</strong><?php endif; ?></div>
            
        </div>

        <div class="login-copy">
            <p class="eyebrow">BEM-VINDO À BEMORA</p>
            <p>Organize suas finanças com clareza e tranquilidade.</p>
        </div>

        <form method="post" class="login-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label>E-mail<input type="email" name="email" autocomplete="email" required></label>
            <label for="login-password">Senha</label><div class="login-password-wrap"><input id="login-password" type="password" name="password" autocomplete="current-password" required><button type="button" id="login-password-toggle" class="login-password-toggle" aria-label="Mostrar senha" aria-pressed="false" aria-controls="login-password"><i class="bi bi-eye" aria-hidden="true"></i></button></div>

            <?php if ($error): ?>
                <div class="form-error"><?= e($error) ?></div>
            <?php endif; ?>

            <button class="primary login-button" type="submit">Entrar</button>
        </form>
    </section>
</main>
<script>
(()=>{const input=document.getElementById('login-password'),button=document.getElementById('login-password-toggle');if(!input||!button)return;button.addEventListener('click',()=>{const show=input.type==='password';input.type=show?'text':'password';button.setAttribute('aria-pressed',String(show));button.setAttribute('aria-label',show?'Ocultar senha':'Mostrar senha');button.querySelector('i').className=show?'bi bi-eye-slash':'bi bi-eye';input.focus({preventScroll:true})})})();
</script>
</body>
</html>

<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/branding.php';

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

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Informe e-mail e senha.';
    } else {
        try {
            $stmt = db()->prepare('SELECT id, name, email, password_hash FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $user['id'];
                $adminCheck = db()->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='users' AND column_name='system_role'");
                if ((int)$adminCheck->fetchColumn() > 0) {
                    $roleStmt = db()->prepare('SELECT system_role FROM users WHERE id=?');
                    $roleStmt->execute([(int)$user['id']]);
                    if ($roleStmt->fetchColumn() === 'system_admin') redirect_to('/system_admin.php');
                }
                redirect_to('/');
            }

            $error = 'E-mail ou senha inválidos.';
        } catch (Throwable) {
            $error = 'O banco ainda não está configurado. Execute o instalador primeiro.';
        }
    }
}

?><!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Entrar • Bemora</title><?php brand_head_tags(); ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"><link rel="stylesheet" href="assets/style.css"><link rel="stylesheet" href="/assets/bemora.css?v=1">
</head>
<body>
<main class="login-screen">
    <section class="login-card">
        <div class="brand brand-dark">
            <div class="brandmark"><?php if(brand_asset_url('logo')): ?><img src="<?= e(brand_asset_url('logo')) ?>" alt="Bemora"><?php else: ?><strong>bemora</strong><?php endif; ?></div>
            <div class="brandcopy"><b>bemora</b><strong>by Anoar</strong><small>FINANÇAS COM LEVEZA</small></div>
        </div>

        <div class="login-copy">
            <p class="eyebrow">BEM-VINDO À BEMORA</p>
            <h1>Seu dinheiro em ordem.</h1>
            <p>Organize suas finanças com clareza e tranquilidade.</p>
        </div>

        <form method="post" class="login-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label>E-mail<input type="email" name="email" autocomplete="email" required></label>
            <label>Senha<input type="password" name="password" autocomplete="current-password" required></label>

            <?php if ($error): ?>
                <div class="form-error"><?= e($error) ?></div>
            <?php endif; ?>

            <button class="primary login-button" type="submit">Entrar</button>
        </form>
    </section>
</main>
</body>
</html>

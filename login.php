<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (!empty($_SESSION['user_id'])) {
    redirect_to('/');
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
    <title>Entrar • Família Almeida Finanças</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<main class="login-screen">
    <section class="login-card">
        <div class="brand brand-dark">
            <div class="brandmark">FA</div>
            <div class="brandcopy"><b>FAMÍLIA</b><strong>ALMEIDA</strong><small>FINANÇAS</small></div>
        </div>

        <div class="login-copy">
            <p class="eyebrow">ACESSO DA FAMÍLIA</p>
            <h1>Entrar no financeiro</h1>
            <p>Use seu e-mail e senha para acessar os dados compartilhados da família.</p>
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

<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$installKey = (string) config('app', 'install_key', '');
$providedKey = (string) ($_GET['key'] ?? $_POST['install_key'] ?? '');

if ($installKey === '' || $installKey === 'TROQUE-POR-UMA-CHAVE-LONGA-E-ALEATORIA') {
    http_response_code(500);
    exit('Defina uma install_key segura em config.php antes de executar o instalador.');
}

if (!hash_equals($installKey, $providedKey)) {
    http_response_code(403);
    exit('Chave de instalação inválida.');
}

$pdo = db();
$alreadyInstalled = false;

try {
    $stmt = $pdo->query("SELECT value FROM settings WHERE setting_key = 'installed' LIMIT 1");
    $alreadyInstalled = $stmt && $stmt->fetchColumn() === '1';
} catch (Throwable) {
    $alreadyInstalled = false;
}

$error = null;
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadyInstalled) {
    $miguelName = trim((string) ($_POST['miguel_name'] ?? 'Miguel'));
    $miguelEmail = strtolower(trim((string) ($_POST['miguel_email'] ?? '')));
    $miguelPassword = (string) ($_POST['miguel_password'] ?? '');
    $gabiName = trim((string) ($_POST['gabi_name'] ?? 'Gabi'));
    $gabiEmail = strtolower(trim((string) ($_POST['gabi_email'] ?? '')));
    $gabiPassword = (string) ($_POST['gabi_password'] ?? '');

    if (!filter_var($miguelEmail, FILTER_VALIDATE_EMAIL) || !filter_var($gabiEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Informe e-mails válidos para os dois usuários.';
    } elseif (strlen($miguelPassword) < 8 || strlen($gabiPassword) < 8) {
        $error = 'Use senhas com pelo menos 8 caracteres.';
    } else {
        try {
            $schema = file_get_contents(__DIR__ . '/schema.sql');
            if ($schema === false) {
                throw new RuntimeException('Não foi possível ler schema.sql.');
            }

            $statements = preg_split('/;\s*(?:\r?\n|$)/', trim($schema));
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if ($statement !== '') {
                    $pdo->exec($statement);
                }
            }

            $pdo->beginTransaction();

            $userStmt = $pdo->prepare(
                'INSERT INTO users (name, email, password_hash)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE name = VALUES(name), password_hash = VALUES(password_hash)'
            );

            $userStmt->execute([$miguelName, $miguelEmail, password_hash($miguelPassword, PASSWORD_DEFAULT)]);
            $userStmt->execute([$gabiName, $gabiEmail, password_hash($gabiPassword, PASSWORD_DEFAULT)]);

            $billExists = $pdo->prepare('SELECT id FROM fixed_bills WHERE LOWER(name) = LOWER(?) LIMIT 1');
            $billInsert = $pdo->prepare('INSERT INTO fixed_bills (name, amount, due_day) VALUES (?, 0, ?)');
            foreach ([
                ['Dízimo', 10],
                ['Luz', 15],
                ['Água', 15],
                ['Gás', 20],
                ['Internet', 10],
                ['Aluguel', 10],
            ] as [$name, $day]) {
                $billExists->execute([$name]);
                if (!$billExists->fetchColumn()) {
                    $billInsert->execute([$name, $day]);
                }
            }

            $goalStmt = $pdo->prepare(
                'INSERT INTO spending_goals (category, monthly_limit)
                 VALUES (?, 0)
                 ON DUPLICATE KEY UPDATE category = VALUES(category)'
            );
            foreach (['Mercado', 'Farmácia', 'Pet', 'Lazer'] as $category) {
                $goalStmt->execute([$category]);
            }

            $settingStmt = $pdo->prepare(
                'INSERT INTO settings (setting_key, value)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE value = VALUES(value)'
            );
            $settingStmt->execute(['investment_goal', '0']);
            $settingStmt->execute(['installed', '1']);

            $pdo->commit();
            $success = true;
            $alreadyInstalled = true;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $exception->getMessage();
        }
    }
}

?><!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Instalação • Família Almeida Finanças</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="install-body">
<main class="installer">
    <div class="brand brand-dark">
        <div class="brandmark">FA</div>
        <div class="brandcopy"><b>FAMÍLIA</b><strong>ALMEIDA</strong><small>FINANÇAS</small></div>
    </div>

    <?php if ($success || $alreadyInstalled): ?>
        <p class="eyebrow">INSTALAÇÃO CONCLUÍDA</p>
        <h1>Banco pronto para uso.</h1>
        <p>As tabelas e os usuários foram configurados. Por segurança, remova ou renomeie <code>install.php</code> na hospedagem após concluir.</p>
        <a class="primary-link" href="/login">Ir para o login</a>
    <?php else: ?>
        <p class="eyebrow">PRIMEIRA CONFIGURAÇÃO</p>
        <h1>Criar banco e acessos</h1>
        <p>O instalador criará as tabelas no MySQL definido em <code>config.php</code> e salvará apenas hashes das senhas.</p>

        <?php if ($error): ?>
            <div class="form-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" class="install-form">
            <input type="hidden" name="install_key" value="<?= e($providedKey) ?>">

            <fieldset>
                <legend>Miguel</legend>
                <label>Nome<input name="miguel_name" value="Miguel" required></label>
                <label>E-mail<input type="email" name="miguel_email" required></label>
                <label>Senha<input type="password" name="miguel_password" minlength="8" required></label>
            </fieldset>

            <fieldset>
                <legend>Gabi</legend>
                <label>Nome<input name="gabi_name" value="Gabi" required></label>
                <label>E-mail<input type="email" name="gabi_email" required></label>
                <label>Senha<input type="password" name="gabi_password" minlength="8" required></label>
            </fieldset>

            <button class="primary" type="submit">Criar estrutura e usuários</button>
        </form>
    <?php endif; ?>
</main>
</body>
</html>

<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_auth();
$csrf = csrf_token();
?>
<!doctype html>
<html lang="pt-BR">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Configurações • Família Almeida</title><link rel="stylesheet" href="/assets/style.css"></head>
<body>
<div class="shell">
<?php render_sidebar('configuracoes', $csrf); ?>
<main>
<?php render_topbar($user); ?>
<div class="content">
<?php page_header('SISTEMA', 'Configurações', 'Ajustes e ferramentas administrativas do financeiro da família.'); ?>
<div class="settings-grid">
<a class="settings-card" href="/configuracoes/manutencao"><span class="settings-icon">⚙</span><div><strong>Manutenção</strong><p>Migrations, diagnóstico do MySQL, informações do servidor e otimização de tabelas.</p></div><span>→</span></a>
<div class="settings-card disabled"><span class="settings-icon">◉</span><div><strong>Usuários e acessos</strong><p>Gerenciamento de usuários e troca de senha será adicionado nesta área.</p></div><span>Em breve</span></div>
<div class="settings-card disabled"><span class="settings-icon">⇩</span><div><strong>Backup</strong><p>Exportação e restauração assistida da base financeira.</p></div><span>Em breve</span></div>
</div>
</div>
</main>
</div>
</body>
</html>

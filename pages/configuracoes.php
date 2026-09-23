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

<button class="settings-card settings-cache-card" id="clear-app-cache" type="button">
    <span class="settings-icon">↻</span>
    <div>
        <strong>Limpar cache e atualizar app</strong>
        <p>Corrige versões antigas presas no celular. Não apaga lançamentos, estoque nem compras offline pendentes.</p>
    </div>
    <span id="clear-app-cache-status">Atualizar</span>
</button>
<div class="settings-card disabled"><span class="settings-icon">◉</span><div><strong>Usuários e acessos</strong><p>Gerenciamento de usuários e troca de senha será adicionado nesta área.</p></div><span>Em breve</span></div>
<div class="settings-card disabled"><span class="settings-icon">⇩</span><div><strong>Backup</strong><p>Exportação e restauração assistida da base financeira.</p></div><span>Em breve</span></div>
</div>
</div>
</main>
</div>

<script>
const clearAppCacheButton = document.getElementById('clear-app-cache');
const clearAppCacheStatus = document.getElementById('clear-app-cache-status');

clearAppCacheButton?.addEventListener('click', async () => {
    const originalText = clearAppCacheStatus.textContent;
    clearAppCacheButton.disabled = true;
    clearAppCacheStatus.textContent = 'Limpando...';

    try {
        // Cache Storage guarda apenas arquivos estáticos/PWA.
        // IndexedDB não é apagado para preservar lista e compras offline pendentes.
        if ('caches' in window) {
            const keys = await caches.keys();
            await Promise.all(keys.map((key) => caches.delete(key)));
        }

        try {
            localStorage.removeItem('familia-sidebar-collapsed');
        } catch (_) {}

        if ('serviceWorker' in navigator) {
            const registrations = await navigator.serviceWorker.getRegistrations();
            await Promise.all(registrations.map((registration) => registration.unregister()));

            clearAppCacheStatus.textContent = 'Atualizando...';

            const registration = await navigator.serviceWorker.register('/sw.js?refresh=' + Date.now(), { scope: '/' });
            await navigator.serviceWorker.ready;

            registration.update?.().catch(() => {});
        }

        clearAppCacheStatus.textContent = 'Atualizado ✓';

        setTimeout(() => {
            const url = new URL(window.location.href);
            url.searchParams.set('_refresh', Date.now().toString());
            window.location.replace(url.toString());
        }, 700);
    } catch (error) {
        console.error(error);
        clearAppCacheStatus.textContent = 'Tentar novamente';
        clearAppCacheButton.disabled = false;
        alert('Não foi possível atualizar o cache automaticamente. Feche e abra o navegador e tente novamente.');
    }

    if (!clearAppCacheButton.disabled) {
        clearAppCacheStatus.textContent = originalText;
    }
});
</script>
</body>
</html>

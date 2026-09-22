<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/migrations.php';

$user = require_auth();
$csrf = csrf_token();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['maintenance_action'] ?? '');
    try {
        if ($action === 'run_migrations') {
            $executed = run_pending_migrations($pdo);
            flash('success', $executed ? 'Migrations executadas: ' . implode(', ', $executed) : 'Nenhuma migration pendente.');
        } elseif ($action === 'optimize_tables') {
            $optimized = optimize_database_tables($pdo);
            flash('success', count($optimized) . ' tabelas otimizadas.');
        } else {
            flash('error', 'Ação de manutenção inválida.');
        }
    } catch (Throwable $exception) {
        flash('error', 'Falha na manutenção: ' . $exception->getMessage());
    }
    redirect_to('/configuracoes/manutencao');
}

$flash = pull_flash();
$diagnostics = database_diagnostics($pdo);
$migrations = migration_status($pdo);
$pendingCount = count(array_filter($migrations, fn(array $migration) => !$migration['applied']));
?>
<!doctype html>
<html lang="pt-BR">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Manutenção • Família Almeida</title><link rel="stylesheet" href="/assets/style.css"></head>
<body>
<div class="shell">
<?php render_sidebar('manutencao', $csrf); ?>
<main>
<?php render_topbar($user, 'Sistema operacional'); ?>
<div class="content">
<div class="page-head-row">
<?php page_header('CONFIGURAÇÕES', 'Manutenção do sistema', 'Diagnóstico, migrations e operações seguras de banco de dados.'); ?>
<a class="secondary-link" href="/configuracoes">← Configurações</a>
</div>
<?php if ($flash): ?><div class="alert <?= $flash['type'] === 'success' ? 'success' : '' ?>"><?= e($flash['message']) ?></div><?php endif; ?>

<div class="grid4 maintenance-stats">
<div class="stat"><div class="stat-top"><span>PHP</span><span class="status-dot ok"></span></div><strong><?= e(PHP_VERSION) ?></strong></div>
<div class="stat"><div class="stat-top"><span>MySQL</span><span class="status-dot ok"></span></div><strong class="small-stat"><?= e($diagnostics['mysql_version']) ?></strong></div>
<div class="stat"><div class="stat-top"><span>Banco</span><span class="status-dot ok"></span></div><strong class="small-stat"><?= e($diagnostics['database']) ?></strong></div>
<div class="stat"><div class="stat-top"><span>Migrations pendentes</span><span class="status-dot <?= $pendingCount ? 'warn' : 'ok' ?>"></span></div><strong><?= $pendingCount ?></strong></div>
</div>

<div class="split equal">
<section class="card">
<div class="card-head"><div><p class="eyebrow">MIGRATIONS</p><h2>Controle de estrutura</h2></div><span class="pill <?= $pendingCount ? 'warning' : 'success' ?>"><?= $pendingCount ? $pendingCount . ' pendente(s)' : 'Atualizado' ?></span></div>
<p class="muted-copy">As migrations são executadas em ordem e registradas em <code>schema_migrations</code>. Uma migration aplicada não é executada novamente.</p>
<div class="migration-list">
<?php if ($migrations): foreach ($migrations as $migration): ?>
<div class="migration-row"><span class="migration-status <?= $migration['applied'] ? 'done' : 'pending' ?>"><?= $migration['applied'] ? '✓' : '!' ?></span><div><strong><?= e($migration['id']) ?></strong><p><?= e($migration['description']) ?></p></div><small><?= $migration['applied'] ? e((string) $migration['executed_at']) : 'Pendente' ?></small></div>
<?php endforeach; else: ?><div class="empty">Nenhuma migration encontrada.</div><?php endif; ?>
</div>
<form method="post" onsubmit="return confirm('Executar todas as migrations pendentes agora?')">
<input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="maintenance_action" value="run_migrations">
<button class="primary" type="submit" <?= $pendingCount === 0 ? 'disabled' : '' ?>>Executar migrations pendentes</button>
</form>
</section>

<section class="card">
<div class="card-head"><div><p class="eyebrow">BANCO DE DADOS</p><h2>Diagnóstico</h2></div><span class="pill success">Conectado</span></div>
<dl class="diagnostic-list">
<div><dt>Banco ativo</dt><dd><?= e($diagnostics['database']) ?></dd></div>
<div><dt>Tamanho estimado</dt><dd><?= e(bytes_human($diagnostics['size_bytes'])) ?></dd></div>
<div><dt>Tabelas</dt><dd><?= count($diagnostics['tables']) ?></dd></div>
<div><dt>Servidor web</dt><dd><?= e($_SERVER['SERVER_SOFTWARE'] ?? 'PHP') ?></dd></div>
</dl>
<form method="post" onsubmit="return confirm('Otimizar as tabelas agora? Em bases grandes isso pode causar bloqueios rápidos.')">
<input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="maintenance_action" value="optimize_tables">
<button class="secondary" type="submit">Otimizar tabelas</button>
</form>
</section>
</div>

<section class="card recent">
<div class="card-head"><div><p class="eyebrow">TABELAS</p><h2>Uso da base</h2></div><span class="muted-copy"><?= e(bytes_human($diagnostics['size_bytes'])) ?> no total</span></div>
<div class="table-wrap">
<table class="data-table"><thead><tr><th>Tabela</th><th>Registros estimados</th><th>Dados</th><th>Índices</th></tr></thead><tbody>
<?php foreach ($diagnostics['tables'] as $table): ?>
<tr><td><?= e($table['table_name']) ?></td><td><?= number_format((int) $table['table_rows'], 0, ',', '.') ?></td><td><?= e(bytes_human((int) $table['data_length'])) ?></td><td><?= e(bytes_human((int) $table['index_length'])) ?></td></tr>
<?php endforeach; ?>
</tbody></table>
</div>
</section>
</div>
</main>
</div>
</body>
</html>

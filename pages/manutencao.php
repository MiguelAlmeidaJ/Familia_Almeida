<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_once __DIR__ . '/../includes/migrations.php';

$user = require_admin_access();
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
        } elseif ($action === 'test_smtp') {
            $destination=trim((string)($_POST['test_email']??''));
            if(!filter_var($destination,FILTER_VALIDATE_EMAIL)||strlen($destination)>254||preg_match('/[\\r\\n]/',$destination)){
                throw new RuntimeException('Informe um e-mail de destino válido.');
            }
            $last=(int)($_SESSION['last_smtp_test_at']??0);
            if(time()-$last<60)throw new RuntimeException('Aguarde pelo menos 60 segundos entre testes SMTP.');
            $_SESSION['last_smtp_test_at']=time();
            require_once __DIR__.'/../includes/mailer.php';
            require_once __DIR__.'/../includes/email_templates.php';
            $body="Olá!\n\nEste é um teste de envio SMTP do Bemora, iniciado pela área de Manutenção.\n\nData e hora: ".date('d/m/Y H:i:s')."\n\nSe você recebeu esta mensagem, o servidor SMTP aceitou o envio. A entrega depende também do serviço de e-mail.\n\nBemora · By Anoar";
            bemora_send_email($destination,'Bemora — teste de envio SMTP',$body,bemora_smtp_test_email(date('d/m/Y H:i:s')));
            flash('success','Servidor SMTP aceitou o e-mail de teste destinado a '.$destination.'. Confira a caixa de entrada e o spam.');
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
<?php admin_shell_start('settings','Manutenção','Migrations, diagnóstico e verificação dos serviços da plataforma.'); ?><link rel="stylesheet" href="/assets/bemora-smtp-maintenance.css?v=1">
<div class="admin-maintenance">
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

<section class="card recent smtp-maintenance-card">
<div class="card-head"><div><p class="eyebrow">E-MAIL TRANSACIONAL</p><h2>Teste de envio SMTP</h2></div><span class="pill <?= trim((string)config('mail','host',''))!==''?'success':'warning' ?>"><?= trim((string)config('mail','host',''))!==''?'Servidor configurado':'Configuração pendente' ?></span></div>
<p class="muted-copy">Envie uma mensagem de teste utilizando as configurações SMTP do Bemora. O teste verifica se o servidor aceita o envio, sem revelar usuário, senha ou detalhes de autenticação.</p>
<div class="smtp-maintenance-meta"><span><i class="bi bi-hdd-network"></i> Servidor: <strong><?= e((string)config('mail','host','Não configurado')) ?></strong></span><span><i class="bi bi-shield-lock"></i> Conexão: <strong><?= e(strtoupper((string)config('mail','encryption','tls'))) ?> · <?= (int)config('mail','port',587) ?></strong></span></div>
<form method="post" class="smtp-test-form">
<input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
<input type="hidden" name="maintenance_action" value="test_smtp">
<label for="smtp-test-email">E-mail para receber o teste</label>
<div class="smtp-test-controls"><input id="smtp-test-email" type="email" name="test_email" autocomplete="email" placeholder="seuemail@dominio.com.br" maxlength="254" required><button class="primary" type="submit"><i class="bi bi-send"></i> Enviar teste</button></div>
<p class="muted-copy">Disponível somente para o administrador global. Intervalo mínimo: 60 segundos entre envios.</p>
</form>
</section>
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
<?php admin_shell_end(); ?>

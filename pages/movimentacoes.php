<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/finance.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_auth();
$month = valid_month($_GET['month'] ?? null);
$data = dashboard_data(db(), $month);
$csrf = csrf_token();
$flash = pull_flash();
$defaultDate = $month === date('Y-m') ? date('Y-m-d') : $month . '-01';
$current = new DateTimeImmutable($month . '-01');
$prevMonth = $current->modify('-1 month')->format('Y-m');
$nextMonth = $current->modify('+1 month')->format('Y-m');
?>
<!doctype html>
<html lang="pt-BR">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Movimentações • Família Almeida</title><link rel="stylesheet" href="/assets/style.css"></head>
<body>
<div class="shell">
<?php render_sidebar('movimentacoes', $csrf); ?>
<main>
<?php render_topbar($user); ?>
<div class="content">
<div class="page-head-row">
<?php page_header('FINANCEIRO', 'Movimentações', 'Entradas, gastos, investimentos e pagamentos registrados.'); ?>
<div class="month compact"><a href="?month=<?= e($prevMonth) ?>">‹</a><div class="label"><small>MÊS</small><strong><?= e($month) ?></strong></div><a href="?month=<?= e($nextMonth) ?>">›</a></div>
</div>
<?php if ($flash): ?><div class="alert <?= $flash['type'] === 'success' ? 'success' : '' ?>"><?= e($flash['message']) ?></div><?php endif; ?>

<div class="split page-split">
<section class="card">
<p class="eyebrow">NOVO LANÇAMENTO</p><h2>Registrar movimento</h2>
<form method="post" action="/acao" class="stack-form">
<input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="add_transaction"><input type="hidden" name="month" value="<?= e($month) ?>">
<label>Tipo<select name="type" required><option value="income">Entrada</option><option value="expense">Gasto</option><option value="investment">Investimento</option></select></label>
<label>Descrição<input name="description" maxlength="160" required></label>
<label>Categoria<input name="category" maxlength="100" required></label>
<div class="form-grid-2"><label>Valor<input type="number" name="amount" min="0.01" step="0.01" required></label><label>Data<input type="date" name="date" value="<?= e($defaultDate) ?>" required></label></div>
<button class="primary" type="submit">Salvar lançamento</button>
</form>
</section>
<section class="card">
<p class="eyebrow">RESUMO DO MÊS</p><h2><?= count($data['transactions']) ?> movimentações</h2>
<div class="mini-stats">
<div><span>Entradas</span><strong><?= money($data['totals']['income']) ?></strong></div>
<div><span>Gastos</span><strong><?= money($data['totals']['expense']) ?></strong></div>
<div><span>Investimentos</span><strong><?= money($data['totals']['investment']) ?></strong></div>
</div>
</section>
</div>

<section class="card recent">
<div class="card-head"><div><p class="eyebrow">HISTÓRICO</p><h2>Movimentações do mês</h2></div></div>
<div class="tx-list">
<?php if ($data['transactions']): foreach ($data['transactions'] as $transaction): ?>
<div class="tx">
<div class="txicon <?= e($transaction['type']) ?>"><?= $transaction['type'] === 'income' ? '↗' : '↘' ?></div>
<div class="txinfo"><strong><?= e($transaction['description']) ?></strong><span><?= e($transaction['category']) ?> • <?= e($transaction['date']) ?> • <?= e($transaction['created_by_name'] ?: 'Família') ?></span></div>
<div class="txval <?= $transaction['type'] === 'income' ? 'pos' : 'neg' ?>"><?= $transaction['type'] === 'income' ? '+' : '−' ?> <?= money($transaction['amount']) ?></div>
<form method="post" action="/acao" onsubmit="return confirm('Remover este lançamento?')">
<input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="delete_transaction"><input type="hidden" name="month" value="<?= e($month) ?>"><input type="hidden" name="transaction_id" value="<?= (int) $transaction['id'] ?>">
<button class="iconbtn danger" type="submit">Excluir</button>
</form>
</div>
<?php endforeach; else: ?><div class="empty"><div class="bubble">◇</div><strong>Nenhum lançamento neste mês.</strong></div><?php endif; ?>
</div>
</section>
</div>
</main>
</div>
</body>
</html>

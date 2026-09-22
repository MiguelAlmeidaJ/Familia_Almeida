<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/finance.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_auth();
$month = valid_month($_GET['month'] ?? null);
$pdo = db();
$allBills = fixed_bills_data($pdo, $month, true);
$activeBills = array_values(array_filter($allBills, fn(array $bill) => $bill['active']));
$archivedBills = array_values(array_filter($allBills, fn(array $bill) => !$bill['active']));
$csrf = csrf_token();
$flash = pull_flash();

$totalMonthly = array_reduce($activeBills, fn(float $sum, array $bill) => $sum + (float) $bill['amount'], 0.0);
$paidCount = count(array_filter($activeBills, fn(array $bill) => $bill['paid']));
$pendingCount = count($activeBills) - $paidCount;

[$year, $monthNumber] = array_map('intval', explode('-', $month));
$monthNames = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
$monthLabel = $monthNames[$monthNumber] . ' de ' . $year;
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Contas fixas • Família Almeida</title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<div class="shell">
<?php render_sidebar('contas', $csrf); ?>
<main>
<?php render_topbar($user); ?>

<div class="content bills-page">
<div class="page-head-row bills-head">
<?php page_header('ORGANIZAÇÃO MENSAL', 'Contas fixas', 'Cadastre, edite e acompanhe as despesas que se repetem todos os meses.'); ?>

<form method="get" class="month-filter">
<label>
<span>MÊS DE REFERÊNCIA</span>
<input type="month" name="month" value="<?= e($month) ?>">
</label>
<button class="secondary" type="submit">Carregar</button>
</form>
</div>

<?php if ($flash): ?>
<div class="alert <?= $flash['type'] === 'success' ? 'success' : '' ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>

<div class="bills-summary">
<div class="summary-card">
<span>Contas ativas</span>
<strong><?= count($activeBills) ?></strong>
<small>recorrências cadastradas</small>
</div>
<div class="summary-card">
<span>Custo fixo mensal</span>
<strong><?= money($totalMonthly) ?></strong>
<small>previsto para <?= e($monthLabel) ?></small>
</div>
<div class="summary-card">
<span>Pagas</span>
<strong><?= $paidCount ?></strong>
<small>de <?= count($activeBills) ?> neste mês</small>
</div>
<div class="summary-card <?= $pendingCount > 0 ? 'attention' : 'success' ?>">
<span>Pendentes</span>
<strong><?= $pendingCount ?></strong>
<small><?= $pendingCount > 0 ? 'ainda aguardando pagamento' : 'mês em dia' ?></small>
</div>
</div>

<div class="bills-workspace">
<section class="card new-bill-card">
<div class="section-title">
<div>
<p class="eyebrow">NOVA CONTA</p>
<h2>Adicionar recorrência</h2>
</div>
<span class="section-icon">＋</span>
</div>

<form method="post" action="/acao" class="stack-form">
<input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
<input type="hidden" name="return_to" value="/contas">
<input type="hidden" name="action" value="add_bill">
<input type="hidden" name="month" value="<?= e($month) ?>">

<label>Nome da conta
<input name="name" maxlength="120" placeholder="Ex.: Aluguel, Internet, Energia" required>
</label>

<div class="form-grid-2">
<label>Valor mensal
<div class="money-input"><span>R$</span><input type="number" name="amount" min="0" step="0.01" placeholder="0,00" required></div>
</label>
<label>Vencimento
<input type="number" name="due_day" min="1" max="31" value="10" required>
</label>
</div>

<button class="primary" type="submit">Adicionar conta fixa</button>
</form>
</section>

<section class="card bills-list-card">
<div class="card-head bills-list-head">
<div>
<p class="eyebrow">CONTAS ATIVAS</p>
<h2><?= count($activeBills) ?> contas recorrentes</h2>
</div>
<div class="list-legend"><span class="legend-dot paid"></span> Pago <span class="legend-dot pending"></span> Pendente</div>
</div>

<div class="managed-bill-list">
<?php if ($activeBills): ?>
<?php foreach ($activeBills as $bill): ?>
<article class="managed-bill <?= $bill['paid'] ? 'is-paid' : '' ?>">
<form method="post" action="/acao" class="bill-check-form">
<input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
<input type="hidden" name="return_to" value="/contas">
<input type="hidden" name="action" value="toggle_bill">
<input type="hidden" name="month" value="<?= e($month) ?>">
<input type="hidden" name="bill_id" value="<?= (int) $bill['id'] ?>">
<input type="hidden" name="paid" value="<?= $bill['paid'] ? '0' : '1' ?>">
<button class="check large <?= $bill['paid'] ? 'done' : '' ?>" type="submit" title="<?= $bill['paid'] ? 'Marcar como pendente' : 'Marcar como paga' ?>"><?= $bill['paid'] ? '✓' : '' ?></button>
</form>

<div class="bill-date-badge">
<small>VENCE</small>
<strong><?= str_pad((string) $bill['due_day'], 2, '0', STR_PAD_LEFT) ?></strong>
</div>

<div class="managed-bill-info">
<div class="bill-name-line">
<strong><?= e($bill['name']) ?></strong>
<span class="status-pill <?= $bill['paid'] ? 'paid' : 'pending' ?>"><?= $bill['paid'] ? 'Pago' : 'Pendente' ?></span>
</div>
<span><?= $bill['paid'] ? 'Pagamento confirmado em ' . e($monthLabel) : 'Aguardando pagamento em ' . e($monthLabel) ?></span>
</div>

<div class="managed-bill-amount"><?= money($bill['amount']) ?></div>

<div class="managed-bill-actions">
<button class="action-button" type="button"
onclick='openBillEditor(<?= (int) $bill["id"] ?>, <?= json_encode($bill["name"], JSON_HEX_APOS | JSON_HEX_QUOT) ?>, <?= json_encode($bill["amount"]) ?>, <?= (int) $bill["due_day"] ?>)'>Editar</button>

<form method="post" action="/acao" onsubmit="return confirm('Arquivar esta conta? Ela deixará de aparecer nos próximos meses, mas o histórico de pagamentos será preservado.')">
<input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
<input type="hidden" name="return_to" value="/contas">
<input type="hidden" name="action" value="archive_bill">
<input type="hidden" name="month" value="<?= e($month) ?>">
<input type="hidden" name="bill_id" value="<?= (int) $bill['id'] ?>">
<button class="action-button muted" type="submit">Arquivar</button>
</form>
</div>
</article>
<?php endforeach; ?>
<?php else: ?>
<div class="empty bills-empty"><div class="bubble">▤</div><strong>Nenhuma conta fixa ativa.</strong><span>Cadastre a primeira recorrência ao lado.</span></div>
<?php endif; ?>
</div>
</section>
</div>

<?php if ($archivedBills): ?>
<section class="card archived-section">
<details>
<summary>
<div><p class="eyebrow">ARQUIVO</p><strong><?= count($archivedBills) ?> conta(s) arquivada(s)</strong></div>
<span>Ver contas</span>
</summary>
<div class="archived-list">
<?php foreach ($archivedBills as $bill): ?>
<div class="archived-row">
<div>
<strong><?= e($bill['name']) ?></strong>
<span>Dia <?= (int) $bill['due_day'] ?> • <?= money($bill['amount']) ?></span>
</div>
<form method="post" action="/acao">
<input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
<input type="hidden" name="return_to" value="/contas">
<input type="hidden" name="action" value="restore_bill">
<input type="hidden" name="month" value="<?= e($month) ?>">
<input type="hidden" name="bill_id" value="<?= (int) $bill['id'] ?>">
<button class="secondary small-button" type="submit">Restaurar</button>
</form>
</div>
<?php endforeach; ?>
</div>
</details>
</section>
<?php endif; ?>
</div>
</main>
</div>

<dialog id="edit-bill-dialog" class="bill-edit-dialog">
<form method="post" action="/acao" class="dialog-form">
<input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
<input type="hidden" name="return_to" value="/contas">
<input type="hidden" name="action" value="update_bill">
<input type="hidden" name="month" value="<?= e($month) ?>">
<input type="hidden" name="bill_id" id="edit-bill-id">

<div class="dialog-head">
<div><p class="eyebrow">EDITAR CONTA</p><h3 id="edit-bill-title">Conta fixa</h3></div>
<button type="button" onclick="document.getElementById('edit-bill-dialog').close()">×</button>
</div>

<label>Nome da conta
<input name="name" id="edit-bill-name" maxlength="120" required>
</label>

<div class="form-grid-2">
<label>Valor mensal
<input type="number" name="amount" id="edit-bill-amount" min="0" step="0.01" required>
</label>
<label>Dia do vencimento
<input type="number" name="due_day" id="edit-bill-day" min="1" max="31" required>
</label>
</div>

<div class="dialog-note">A alteração passa a valer na conta recorrente. O histórico de pagamentos já registrado é preservado.</div>
<button class="primary" type="submit">Salvar alterações</button>
</form>
</dialog>

<script>
function openBillEditor(id, name, amount, dueDay) {
    document.getElementById('edit-bill-id').value = id;
    document.getElementById('edit-bill-name').value = name;
    document.getElementById('edit-bill-amount').value = amount;
    document.getElementById('edit-bill-day').value = dueDay;
    document.getElementById('edit-bill-title').textContent = name;
    document.getElementById('edit-bill-dialog').showModal();
}
</script>
</body>
</html>

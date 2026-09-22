<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/finance.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_auth();
$month = valid_month($_GET['month'] ?? null);
$pdo = db();
$allBills = fixed_bills_data($pdo, $month, true);

$currentBills = array_values(array_filter($allBills, fn(array $bill) => $bill['applicable']));
$archivedBills = array_values(array_filter($allBills, fn(array $bill) => !$bill['active']));
$scheduledBills = array_values(array_filter(
    $allBills,
    fn(array $bill) => $bill['active'] && !$bill['applicable'] && $bill['billing_type'] === 'installment'
));

$csrf = csrf_token();
$flash = pull_flash();

$totalMonthly = array_reduce($currentBills, fn(float $sum, array $bill) => $sum + (float) $bill['amount'], 0.0);
$paidCount = count(array_filter($currentBills, fn(array $bill) => $bill['paid']));
$needsAmountCount = count(array_filter($currentBills, fn(array $bill) => $bill['needs_amount']));
$pendingCount = count($currentBills) - $paidCount;

[$year, $monthNumber] = array_map('intval', explode('-', $month));
$monthNames = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
$monthLabel = $monthNames[$monthNumber] . ' de ' . $year;

$typeLabels = [
    'fixed' => 'Fixa',
    'variable' => 'Variável',
    'installment' => 'Parcelada',
];

function recurring_bill_subtitle(array $bill, string $monthLabel): string
{
    if ($bill['billing_type'] === 'installment') {
        if ($bill['schedule_status'] === 'future') {
            return 'Começa em ' . ($bill['start_month'] ?: '—');
        }
        if ($bill['schedule_status'] === 'completed') {
            return 'Parcelamento concluído';
        }
        return 'Parcela ' . (int) $bill['installment_number'] . '/' . (int) $bill['installment_total'];
    }

    if ($bill['needs_amount']) {
        return 'Informe o valor de ' . $monthLabel;
    }

    return $bill['paid']
        ? 'Pagamento confirmado em ' . $monthLabel
        : 'Aguardando pagamento em ' . $monthLabel;
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Contas recorrentes • Família Almeida</title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<div class="shell">
<?php render_sidebar('contas', $csrf); ?>
<main>
<?php render_topbar($user); ?>

<div class="content bills-page">
<div class="page-head-row bills-head">
<?php page_header('ORGANIZAÇÃO MENSAL', 'Contas recorrentes', 'Separe valores fixos, contas que variam todo mês e compras parceladas.'); ?>

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

<div class="recurring-explainer">
<div><span class="bill-type-dot fixed"></span><strong>Fixa</strong><small>Mesmo valor como referência todos os meses.</small></div>
<div><span class="bill-type-dot variable"></span><strong>Variável</strong><small>Água, luz e cartão: informe o valor de cada mês.</small></div>
<div><span class="bill-type-dot installment"></span><strong>Parcelada</strong><small>Aparece somente durante a quantidade de parcelas definida.</small></div>
</div>

<div class="recurring-accounting-note">
<strong>Atenção às parcelas no cartão:</strong>
se uma compra parcelada já estiver incluída em uma fatura de cartão que você controla como conta variável, não registre o pagamento da parcela separadamente, pois isso duplicaria a saída. Use “Parcelada” aqui para cobranças próprias; o vínculo com fatura será tratado separadamente.
</div>

<div class="bills-summary">
<div class="summary-card">
<span>Recorrências do mês</span>
<strong><?= count($currentBills) ?></strong>
<small>contas previstas em <?= e($monthLabel) ?></small>
</div>
<div class="summary-card">
<span>Valor conhecido</span>
<strong><?= money($totalMonthly) ?></strong>
<small><?= $needsAmountCount ? $needsAmountCount . ' conta(s) ainda sem valor' : 'todos os valores informados' ?></small>
</div>
<div class="summary-card">
<span>Pagas</span>
<strong><?= $paidCount ?>/<?= count($currentBills) ?></strong>
<small>confirmadas nas movimentações</small>
</div>
<div class="summary-card <?= $needsAmountCount > 0 ? 'attention' : ($pendingCount > 0 ? '' : 'success') ?>">
<span><?= $needsAmountCount > 0 ? 'Aguardando valor' : 'Pendentes' ?></span>
<strong><?= $needsAmountCount > 0 ? $needsAmountCount : $pendingCount ?></strong>
<small><?= $needsAmountCount > 0 ? 'variáveis precisam ser atualizadas' : ($pendingCount > 0 ? 'ainda aguardando pagamento' : 'mês em dia') ?></small>
</div>
</div>

<div class="bills-workspace recurring-workspace">
<section class="card new-bill-card">
<div class="section-title">
<div>
<p class="eyebrow">NOVA RECORRÊNCIA</p>
<h2>Adicionar conta</h2>
</div>
<span class="section-icon">＋</span>
</div>

<form method="post" action="/acao" class="stack-form recurring-form" id="newRecurringForm">
<input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
<input type="hidden" name="return_to" value="/contas">
<input type="hidden" name="action" value="add_bill">
<input type="hidden" name="month" value="<?= e($month) ?>">

<label>Tipo
<select name="billing_type" class="billing-type-select" data-form="new" required>
<option value="fixed">Fixa — valor recorrente</option>
<option value="variable">Variável — valor muda todo mês</option>
<option value="installment">Parcelada — quantidade definida</option>
</select>
</label>

<label>Nome da conta
<input name="name" maxlength="120" placeholder="Ex.: Aluguel, Água, Notebook" required>
</label>

<div class="form-grid-2">
<label>
<span class="amount-field-label">Valor mensal</span>
<div class="money-input"><span>R$</span><input type="number" name="amount" class="billing-amount-input" min="0" step="0.01" placeholder="0,00" required></div>
</label>
<label>Vencimento
<input type="number" name="due_day" min="1" max="31" value="10" required>
</label>
</div>

<div class="installment-fields" hidden>
<label>Mês da primeira parcela
<input type="month" name="start_month" value="<?= e($month) ?>">
</label>
<label>Total de parcelas
<input type="number" name="installment_total" min="1" max="360" value="10">
</label>
</div>

<div class="billing-form-hint" data-hint>
Este valor será usado como padrão em todos os meses.
</div>

<button class="primary" type="submit">Adicionar recorrência</button>
</form>
</section>

<section class="card bills-list-card">
<div class="card-head bills-list-head">
<div>
<p class="eyebrow">CONTAS DO MÊS</p>
<h2><?= count($currentBills) ?> compromissos em <?= e($monthLabel) ?></h2>
</div>
<div class="list-legend"><span class="legend-dot paid"></span> Pago <span class="legend-dot pending"></span> Pendente</div>
</div>

<div class="managed-bill-list recurring-list">
<?php if ($currentBills): ?>
<?php foreach ($currentBills as $bill): ?>
<article class="managed-bill recurring-bill <?= $bill['paid'] ? 'is-paid' : '' ?> <?= $bill['needs_amount'] ? 'needs-value' : '' ?>">
<form method="post" action="/acao" class="bill-check-form">
<input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
<input type="hidden" name="return_to" value="/contas">
<input type="hidden" name="action" value="toggle_bill">
<input type="hidden" name="month" value="<?= e($month) ?>">
<input type="hidden" name="bill_id" value="<?= (int) $bill['id'] ?>">
<input type="hidden" name="paid" value="<?= $bill['paid'] ? '0' : '1' ?>">
<button
    class="check large <?= $bill['paid'] ? 'done' : '' ?>"
    type="submit"
    <?= $bill['needs_amount'] && !$bill['paid'] ? 'disabled' : '' ?>
    title="<?= $bill['needs_amount'] ? 'Informe o valor do mês antes de pagar' : ($bill['paid'] ? 'Marcar como pendente' : 'Marcar como paga') ?>"
><?= $bill['paid'] ? '✓' : '' ?></button>
</form>

<div class="bill-date-badge">
<small>VENCE</small>
<strong><?= str_pad((string) $bill['due_day'], 2, '0', STR_PAD_LEFT) ?></strong>
</div>

<div class="managed-bill-info">
<div class="bill-name-line">
<strong><?= e($bill['name']) ?></strong>
<span class="bill-type-badge <?= e($bill['billing_type']) ?>"><?= e($typeLabels[$bill['billing_type']] ?? 'Conta') ?></span>
<?php if ($bill['paid']): ?>
<span class="status-pill paid">Pago</span>
<?php elseif ($bill['needs_amount']): ?>
<span class="status-pill value-needed">Informar valor</span>
<?php else: ?>
<span class="status-pill pending">Pendente</span>
<?php endif; ?>
</div>
<span><?= e(recurring_bill_subtitle($bill, $monthLabel)) ?></span>
</div>

<div class="managed-bill-amount recurring-amount">
<?php if ($bill['needs_amount']): ?>
<span class="amount-missing">—</span>
<?php else: ?>
<?= money($bill['amount']) ?>
<?php endif; ?>
<?php if ($bill['billing_type'] === 'installment'): ?>
<small><?= (int) $bill['installment_number'] ?>/<?= (int) $bill['installment_total'] ?></small>
<?php endif; ?>
</div>

<div class="managed-bill-actions recurring-actions">
<button
    class="action-button <?= $bill['needs_amount'] ? 'emphasis' : '' ?>"
    type="button"
    onclick='openMonthlyAmount(<?= (int) $bill["id"] ?>, <?= json_encode($bill["name"], JSON_HEX_APOS | JSON_HEX_QUOT) ?>, <?= json_encode($bill["amount_due"] ?? $bill["amount"]) ?>, <?= json_encode($bill["billing_type"]) ?>)'
><?= $bill['needs_amount'] ? 'Informar valor' : 'Valor do mês' ?></button>

<button class="action-button" type="button"
onclick='openBillEditor(
    <?= (int) $bill["id"] ?>,
    <?= json_encode($bill["name"], JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    <?= json_encode($bill["billing_type"]) ?>,
    <?= json_encode($bill["base_amount"]) ?>,
    <?= (int) $bill["due_day"] ?>,
    <?= json_encode($bill["start_month"]) ?>,
    <?= json_encode($bill["installment_total"]) ?>
)'>Editar</button>

<form method="post" action="/acao" onsubmit="return confirm('Arquivar esta recorrência? O histórico já registrado será preservado.')">
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
<div class="empty bills-empty"><div class="bubble">▤</div><strong>Nenhuma recorrência neste mês.</strong><span>Cadastre uma conta fixa, variável ou parcelada.</span></div>
<?php endif; ?>
</div>
</section>
</div>

<?php if ($scheduledBills): ?>
<section class="card recurring-secondary-section">
<div class="card-head">
<div><p class="eyebrow">PARCELAMENTOS</p><h2>Fora do mês selecionado</h2></div>
<span class="muted-copy"><?= count($scheduledBills) ?> parcelamento(s)</span>
</div>
<div class="scheduled-list">
<?php foreach ($scheduledBills as $bill): ?>
<div class="scheduled-row">
<div class="scheduled-icon"><?= $bill['schedule_status'] === 'future' ? '→' : '✓' ?></div>
<div>
<strong><?= e($bill['name']) ?></strong>
<span>
<?php if ($bill['schedule_status'] === 'future'): ?>
Começa em <?= e((string) $bill['start_month']) ?> • <?= (int) $bill['installment_total'] ?>x de <?= money($bill['base_amount']) ?>
<?php else: ?>
Concluído • <?= (int) $bill['installment_total'] ?>x de <?= money($bill['base_amount']) ?>
<?php endif; ?>
</span>
</div>
<button class="action-button" type="button"
onclick='openBillEditor(
    <?= (int) $bill["id"] ?>,
    <?= json_encode($bill["name"], JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    <?= json_encode($bill["billing_type"]) ?>,
    <?= json_encode($bill["base_amount"]) ?>,
    <?= (int) $bill["due_day"] ?>,
    <?= json_encode($bill["start_month"]) ?>,
    <?= json_encode($bill["installment_total"]) ?>
)'>Editar</button>
</div>
<?php endforeach; ?>
</div>
</section>
<?php endif; ?>

<?php if ($archivedBills): ?>
<section class="card archived-section">
<details>
<summary>
<div><p class="eyebrow">ARQUIVO</p><strong><?= count($archivedBills) ?> recorrência(s) arquivada(s)</strong></div>
<span>Ver contas</span>
</summary>
<div class="archived-list">
<?php foreach ($archivedBills as $bill): ?>
<div class="archived-row">
<div>
<strong><?= e($bill['name']) ?></strong>
<span><?= e($typeLabels[$bill['billing_type']] ?? 'Conta') ?> • dia <?= (int) $bill['due_day'] ?><?= $bill['base_amount'] > 0 ? ' • ' . money($bill['base_amount']) : '' ?></span>
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

<dialog id="monthly-amount-dialog" class="bill-edit-dialog">
<form method="post" action="/acao" class="dialog-form">
<input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
<input type="hidden" name="return_to" value="/contas">
<input type="hidden" name="action" value="set_bill_month_amount">
<input type="hidden" name="month" value="<?= e($month) ?>">
<input type="hidden" name="bill_id" id="monthly-bill-id">

<div class="dialog-head">
<div><p class="eyebrow">VALOR DE <?= e(strtoupper($monthNames[$monthNumber])) ?></p><h3 id="monthly-bill-title">Conta</h3></div>
<button type="button" onclick="document.getElementById('monthly-amount-dialog').close()">×</button>
</div>

<label>Valor deste mês
<input type="number" name="amount" id="monthly-bill-amount" min="0.01" step="0.01" required>
</label>

<div class="dialog-note" id="monthly-bill-note">
Este valor vale somente para <?= e($monthLabel) ?> e não altera os demais meses.
</div>

<button class="primary" type="submit">Salvar valor do mês</button>
</form>
</dialog>

<dialog id="edit-bill-dialog" class="bill-edit-dialog">
<form method="post" action="/acao" class="dialog-form recurring-form" id="editRecurringForm">
<input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
<input type="hidden" name="return_to" value="/contas">
<input type="hidden" name="action" value="update_bill">
<input type="hidden" name="month" value="<?= e($month) ?>">
<input type="hidden" name="bill_id" id="edit-bill-id">

<div class="dialog-head">
<div><p class="eyebrow">EDITAR RECORRÊNCIA</p><h3 id="edit-bill-title">Conta</h3></div>
<button type="button" onclick="document.getElementById('edit-bill-dialog').close()">×</button>
</div>

<label>Tipo
<select name="billing_type" id="edit-billing-type" class="billing-type-select" data-form="edit" required>
<option value="fixed">Fixa — valor recorrente</option>
<option value="variable">Variável — valor muda todo mês</option>
<option value="installment">Parcelada — quantidade definida</option>
</select>
</label>

<label>Nome da conta
<input name="name" id="edit-bill-name" maxlength="120" required>
</label>

<div class="form-grid-2">
<label>
<span class="amount-field-label">Valor mensal</span>
<input type="number" name="amount" id="edit-bill-amount" class="billing-amount-input" min="0" step="0.01" required>
</label>
<label>Dia do vencimento
<input type="number" name="due_day" id="edit-bill-day" min="1" max="31" required>
</label>
</div>

<div class="installment-fields" hidden>
<label>Mês da primeira parcela
<input type="month" name="start_month" id="edit-bill-start">
</label>
<label>Total de parcelas
<input type="number" name="installment_total" id="edit-bill-total" min="1" max="360">
</label>
</div>

<div class="billing-form-hint" data-hint></div>
<div class="dialog-note">A edição altera os próximos meses. Pagamentos já registrados mantêm o valor histórico daquele mês.</div>
<button class="primary" type="submit">Salvar alterações</button>
</form>
</dialog>

<script>
const billingHints = {
    fixed: 'Use para valores normalmente estáveis, como aluguel ou internet. Você ainda pode ajustar um mês isoladamente.',
    variable: 'Use para água, luz e fatura do cartão. O sistema pedirá o valor de cada mês antes do pagamento.',
    installment: 'Informe o valor de cada parcela, o primeiro mês e quantas parcelas existem.'
};

function syncBillingForm(form) {
    const type = form.querySelector('.billing-type-select').value;
    const amount = form.querySelector('.billing-amount-input');
    const amountLabel = form.querySelector('.amount-field-label');
    const installmentFields = form.querySelector('.installment-fields');
    const hint = form.querySelector('[data-hint]');
    const installmentInputs = installmentFields ? installmentFields.querySelectorAll('input') : [];

    if (type === 'variable') {
        amount.required = false;
        amountLabel.textContent = 'Valor estimado (opcional)';
        installmentFields.hidden = true;
        installmentInputs.forEach(input => input.required = false);
    } else if (type === 'installment') {
        amount.required = true;
        amountLabel.textContent = 'Valor de cada parcela';
        installmentFields.hidden = false;
        installmentInputs.forEach(input => input.required = true);
    } else {
        amount.required = true;
        amountLabel.textContent = 'Valor mensal';
        installmentFields.hidden = true;
        installmentInputs.forEach(input => input.required = false);
    }

    if (hint) hint.textContent = billingHints[type] || '';
}

document.querySelectorAll('.billing-type-select').forEach(select => {
    select.addEventListener('change', () => syncBillingForm(select.closest('.recurring-form')));
});
document.querySelectorAll('.recurring-form').forEach(syncBillingForm);

function openMonthlyAmount(id, name, amount, type) {
    document.getElementById('monthly-bill-id').value = id;
    document.getElementById('monthly-bill-title').textContent = name;
    document.getElementById('monthly-bill-amount').value = Number(amount) > 0 ? amount : '';
    document.getElementById('monthly-bill-note').textContent =
        type === 'variable'
            ? 'Este valor vale somente para <?= e($monthLabel) ?>. No próximo mês o sistema pedirá um novo valor.'
            : 'Este ajuste vale somente para <?= e($monthLabel) ?> e não muda o valor padrão da recorrência.';
    document.getElementById('monthly-amount-dialog').showModal();
}

function openBillEditor(id, name, type, amount, dueDay, startMonth, installmentTotal) {
    document.getElementById('edit-bill-id').value = id;
    document.getElementById('edit-bill-name').value = name;
    document.getElementById('edit-billing-type').value = type;
    document.getElementById('edit-bill-amount').value = amount;
    document.getElementById('edit-bill-day').value = dueDay;
    document.getElementById('edit-bill-start').value = startMonth || '<?= e($month) ?>';
    document.getElementById('edit-bill-total').value = installmentTotal || 10;
    document.getElementById('edit-bill-title').textContent = name;
    syncBillingForm(document.getElementById('editRecurringForm'));
    document.getElementById('edit-bill-dialog').showModal();
}
</script>
</body>
</html>

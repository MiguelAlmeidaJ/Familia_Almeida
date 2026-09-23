<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/finance.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_auth();
$month = valid_month($_GET['month'] ?? null);
$pdo = db();
$recurringSchemaReady = recurring_bills_schema_ready($pdo);
$allBills = fixed_bills_data($pdo, $month, true);

$currentBills = array_values(array_filter($allBills, fn(array $bill) => $bill['applicable']));
$archivedBills = array_values(array_filter($allBills, fn(array $bill) => !$bill['active']));
$scheduledBills = array_values(array_filter(
    $allBills,
    fn(array $bill) => $bill['active'] && !$bill['applicable'] && $bill['billing_type'] === 'installment'
));

$csrf = csrf_token();
$flash = pull_flash();

[$year, $monthNumber] = array_map('intval', explode('-', $month));
$monthNames = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
$monthLabel = $monthNames[$monthNumber] . ' de ' . $year;

$currentDate = new DateTimeImmutable($month . '-01');
$prevMonth = $currentDate->modify('-1 month')->format('Y-m');
$nextMonth = $currentDate->modify('+1 month')->format('Y-m');

$knownBills = array_values(array_filter($currentBills, fn(array $bill) => !$bill['needs_amount']));
$paidBills = array_values(array_filter($currentBills, fn(array $bill) => $bill['paid']));
$pendingBills = array_values(array_filter($currentBills, fn(array $bill) => !$bill['paid']));

$expectedTotal = array_reduce($knownBills, fn(float $sum, array $bill) => $sum + (float) $bill['amount'], 0.0);
$paidTotal = array_reduce(
    array_filter($paidBills, fn(array $bill) => !$bill['needs_amount']),
    fn(float $sum, array $bill) => $sum + (float) $bill['amount'],
    0.0
);
$pendingTotal = max(0, $expectedTotal - $paidTotal);
$needsAmountCount = count(array_filter($currentBills, fn(array $bill) => $bill['needs_amount']));

$typeLabels = [
    'fixed' => 'Fixa',
    'variable' => 'Variável',
    'installment' => 'Parcelada',
];

function fixed_bill_subtitle(array $bill): string
{
    if ($bill['paid']) {
        return 'Pago neste mês';
    }

    if ($bill['needs_amount']) {
        return 'Definir valor deste mês';
    }

    if ($bill['billing_type'] === 'installment') {
        return 'Parcela ' . (int) $bill['installment_number'] . '/' . (int) $bill['installment_total'];
    }

    return 'Vence dia ' . (int) $bill['due_day'];
}
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
<div class="shell ref-shell">
    <?php render_sidebar('contas', $csrf); ?>

    <main>
        <?php render_topbar($user); ?>

        <div class="content ref-dashboard fixed-reference-page">
            <section class="ref-dashboard-heading fixed-reference-heading">
                <div>
                    <p class="eyebrow">CADA ESCOLHA CONTA</p>
                    <h1>Contas fixas</h1>
                    <p>As despesas que fazem parte da rotina da família.</p>
                </div>

                <div class="ref-month-picker">
                    <a href="?month=<?= e($prevMonth) ?>" aria-label="Mês anterior">‹</a>
                    <span><?= e($monthLabel) ?></span>
                    <span class="ref-calendar">▣</span>
                    <a href="?month=<?= e($nextMonth) ?>" aria-label="Próximo mês">›</a>
                </div>
            </section>

            <?php if ($flash): ?>
                <div class="alert <?= $flash['type'] === 'success' ? 'success' : '' ?>"><?= e($flash['message']) ?></div>
            <?php endif; ?>

            <?php if (!$recurringSchemaReady): ?>
                <div class="alert migration-alert">
                    O banco ainda está no formato anterior.
                    <a href="/configuracoes/manutencao">Executar migrations →</a>
                </div>
            <?php endif; ?>

            <section class="fixed-reference-summary">
                <div>
                    <span>Previsto em contas</span>
                    <strong><?= money($expectedTotal) ?></strong>
                    <?php if ($needsAmountCount): ?>
                        <small><?= $needsAmountCount ?> conta(s) ainda sem valor definido</small>
                    <?php endif; ?>
                </div>
                <div class="paid">
                    <span>Já pago</span>
                    <strong><?= money($paidTotal) ?></strong>
                    <small><?= count($paidBills) ?> de <?= count($currentBills) ?> contas</small>
                </div>
                <div>
                    <span>A pagar</span>
                    <strong><?= money($pendingTotal) ?></strong>
                    <small><?= count($pendingBills) ?> conta(s) pendente(s)</small>
                </div>
            </section>

            <section class="card fixed-reference-card">
                <header class="fixed-reference-card-head">
                    <div>
                        <h2>Despesas fixas</h2>
                        <p>Valores e vencimentos são reaproveitados nos próximos meses. Pagamentos não.</p>
                    </div>

                    <button class="ref-green-button fixed-add-button" type="button" onclick="openNewBill()">
                        ＋&nbsp; Adicionar conta
                    </button>
                </header>

                <div class="fixed-reference-list">
                    <?php if ($currentBills): ?>
                        <?php foreach ($currentBills as $bill): ?>
                            <div class="fixed-reference-row <?= $bill['paid'] ? 'is-paid' : '' ?>">
                                <div class="fixed-reference-day">
                                    <small>DIA</small>
                                    <strong><?= str_pad((string) $bill['due_day'], 2, '0', STR_PAD_LEFT) ?></strong>
                                </div>

                                <div class="fixed-reference-copy">
                                    <div class="fixed-reference-title">
                                        <strong><?= e($bill['name']) ?></strong>
                                        <span class="fixed-type-tag <?= e($bill['billing_type']) ?>">
                                            <?= e($typeLabels[$bill['billing_type']] ?? 'Conta') ?>
                                        </span>
                                        <?php if ($bill['paid']): ?>
                                            <span class="fixed-paid-tag">Pago</span>
                                        <?php endif; ?>
                                    </div>
                                    <span><?= e(fixed_bill_subtitle($bill)) ?></span>
                                </div>

                                <div class="fixed-reference-value">
                                    <strong><?= $bill['needs_amount'] ? '—' : money($bill['amount']) ?></strong>
                                    <?php if ($bill['billing_type'] === 'installment' && !empty($bill['installment_number'])): ?>
                                        <small><?= (int) $bill['installment_number'] ?>/<?= (int) $bill['installment_total'] ?></small>
                                    <?php endif; ?>
                                </div>

                                <div class="fixed-reference-actions">
                                    <?php if (!$bill['paid']): ?>
                                        <?php if ($bill['needs_amount']): ?>
                                            <button
                                                type="button"
                                                class="fixed-text-action emphasis"
                                                onclick='openMonthlyAmount(
                                                    <?= (int) $bill["id"] ?>,
                                                    <?= json_encode($bill["name"], JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                                                    <?= json_encode($bill["amount_due"] ?? $bill["amount"]) ?>,
                                                    <?= json_encode($bill["billing_type"]) ?>
                                                )'
                                            >Definir valor</button>
                                        <?php else: ?>
                                            <form method="post" action="/acao">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                                <input type="hidden" name="return_to" value="/contas">
                                                <input type="hidden" name="action" value="toggle_bill">
                                                <input type="hidden" name="month" value="<?= e($month) ?>">
                                                <input type="hidden" name="bill_id" value="<?= (int) $bill['id'] ?>">
                                                <input type="hidden" name="paid" value="1">
                                                <button class="fixed-text-action" type="submit">Marcar paga</button>
                                            </form>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <form method="post" action="/acao">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                            <input type="hidden" name="return_to" value="/contas">
                                            <input type="hidden" name="action" value="toggle_bill">
                                            <input type="hidden" name="month" value="<?= e($month) ?>">
                                            <input type="hidden" name="bill_id" value="<?= (int) $bill['id'] ?>">
                                            <input type="hidden" name="paid" value="0">
                                            <button class="fixed-text-action muted" type="submit">Desmarcar</button>
                                        </form>
                                    <?php endif; ?>

                                    <button
                                        type="button"
                                        class="fixed-icon-action"
                                        title="Editar"
                                        onclick='openBillEditor(
                                            <?= (int) $bill["id"] ?>,
                                            <?= json_encode($bill["name"], JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                                            <?= json_encode($bill["billing_type"]) ?>,
                                            <?= json_encode($bill["base_amount"]) ?>,
                                            <?= (int) $bill["due_day"] ?>,
                                            <?= json_encode($bill["start_month"]) ?>,
                                            <?= json_encode($bill["installment_total"]) ?>
                                        )'
                                    >✎</button>

                                    <form method="post" action="/acao" onsubmit="return confirm('Excluir esta conta definitivamente? Pagamentos e movimentações gerados por ela também serão removidos.')">
                                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                        <input type="hidden" name="return_to" value="/contas">
                                        <input type="hidden" name="action" value="delete_bill">
                                        <input type="hidden" name="month" value="<?= e($month) ?>">
                                        <input type="hidden" name="bill_id" value="<?= (int) $bill['id'] ?>">
                                        <button class="fixed-icon-action danger" type="submit" title="Excluir">⌫</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="ref-empty fixed-reference-empty">Nenhuma conta cadastrada para este mês.</div>
                    <?php endif; ?>
                </div>

                <footer class="fixed-reference-footer">
                    Contas variáveis, como água, luz e cartão, podem ter um valor diferente em cada mês.
                    Compras parceladas encerram automaticamente depois da última parcela.
                </footer>
            </section>

            <?php if ($scheduledBills || $archivedBills): ?>
                <section class="fixed-reference-secondary">
                    <?php if ($scheduledBills): ?>
                        <details class="fixed-reference-details">
                            <summary>Parcelamentos fora do mês selecionado <span><?= count($scheduledBills) ?></span></summary>
                            <div>
                                <?php foreach ($scheduledBills as $bill): ?>
                                    <p>
                                        <strong><?= e($bill['name']) ?></strong>
                                        — <?= $bill['schedule_status'] === 'future' ? 'começa em ' . e((string) $bill['start_month']) : 'concluído' ?>
                                    </p>
                                <?php endforeach; ?>
                            </div>
                        </details>
                    <?php endif; ?>

                    <?php if ($archivedBills): ?>
                        <details class="fixed-reference-details">
                            <summary>Contas arquivadas <span><?= count($archivedBills) ?></span></summary>
                            <div class="fixed-archived-list">
                                <?php foreach ($archivedBills as $bill): ?>
                                    <div>
                                        <span><?= e($bill['name']) ?></span>
                                        <form method="post" action="/acao">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                            <input type="hidden" name="return_to" value="/contas">
                                            <input type="hidden" name="action" value="restore_bill">
                                            <input type="hidden" name="month" value="<?= e($month) ?>">
                                            <input type="hidden" name="bill_id" value="<?= (int) $bill['id'] ?>">
                                            <button class="fixed-text-action" type="submit">Restaurar</button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </details>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <footer class="ref-page-footer fixed-reference-page-footer">
                <span>Família Almeida&nbsp;&nbsp; / &nbsp;&nbsp;Um mês de cada vez.</span>
                <span>Dados salvos na sua conta</span>
            </footer>
        </div>
    </main>
</div>

<dialog id="bill-dialog" class="fixed-reference-dialog">
    <form method="post" action="/acao" class="fixed-reference-dialog-form recurring-form" id="billForm">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="return_to" value="/contas">
        <input type="hidden" name="action" id="bill-action" value="add_bill">
        <input type="hidden" name="month" value="<?= e($month) ?>">
        <input type="hidden" name="bill_id" id="bill-id">

        <div class="fixed-reference-dialog-head">
            <div>
                <h3 id="bill-dialog-title">Nova conta fixa</h3>
                <p id="bill-dialog-subtitle">Configure o valor e o dia de vencimento.</p>
            </div>
            <button type="button" onclick="document.getElementById('bill-dialog').close()">×</button>
        </div>

        <label>Descrição
            <input id="bill-name" name="name" maxlength="120" placeholder="Ex.: Aluguel, Internet, Água" required>
        </label>

        <div class="fixed-reference-dialog-grid">
            <label>
                <span class="amount-field-label">Valor (R$)</span>
                <input id="bill-amount" class="billing-amount-input" type="number" name="amount" min="0" step="0.01" placeholder="0,00">
            </label>

            <label>Dia do vencimento
                <input id="bill-day" type="number" name="due_day" min="1" max="31" value="10" required>
            </label>
        </div>

        <label>Tipo de conta
            <select id="bill-type" class="billing-type-select" name="billing_type" required>
                <option value="fixed">Fixa — mesmo valor todo mês</option>
                <option value="variable">Variável — muda todo mês</option>
                <option value="installment">Parcelada — termina após algumas parcelas</option>
            </select>
        </label>

        <div class="installment-fields" hidden>
            <div class="fixed-reference-dialog-grid">
                <label>Mês da primeira parcela
                    <input id="bill-start" type="month" name="start_month" value="<?= e($month) ?>">
                </label>

                <label>Total de parcelas
                    <input id="bill-total" type="number" name="installment_total" min="1" max="360" value="10">
                </label>
            </div>
        </div>

        <div class="billing-form-hint" data-hint></div>

        <div class="fixed-reference-dialog-actions">
            <button class="fixed-cancel-button" type="button" onclick="document.getElementById('bill-dialog').close()">Cancelar</button>
            <button class="fixed-save-button" type="submit">Salvar ✓</button>
        </div>
    </form>
</dialog>

<dialog id="monthly-amount-dialog" class="fixed-reference-dialog fixed-reference-small-dialog">
    <form method="post" action="/acao" class="fixed-reference-dialog-form">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="return_to" value="/contas">
        <input type="hidden" name="action" value="set_bill_month_amount">
        <input type="hidden" name="month" value="<?= e($month) ?>">
        <input type="hidden" name="bill_id" id="monthly-bill-id">

        <div class="fixed-reference-dialog-head">
            <div>
                <h3 id="monthly-bill-title">Valor do mês</h3>
                <p>Este valor vale somente para <?= e($monthLabel) ?>.</p>
            </div>
            <button type="button" onclick="document.getElementById('monthly-amount-dialog').close()">×</button>
        </div>

        <label>Valor (R$)
            <input type="number" name="amount" id="monthly-bill-amount" min="0.01" step="0.01" required>
        </label>

        <div class="fixed-reference-dialog-actions">
            <button class="fixed-cancel-button" type="button" onclick="document.getElementById('monthly-amount-dialog').close()">Cancelar</button>
            <button class="fixed-save-button" type="submit">Salvar ✓</button>
        </div>
    </form>
</dialog>

<script>
const billingHints = {
    fixed: 'Use para aluguel, internet e outras despesas com valor normalmente estável.',
    variable: 'Use para água, luz, gás e fatura do cartão. O valor é definido mês a mês.',
    installment: 'Informe o valor de cada parcela, o mês inicial e a quantidade total.'
};

function syncBillingForm() {
    const form = document.getElementById('billForm');
    const type = document.getElementById('bill-type').value;
    const amount = document.getElementById('bill-amount');
    const amountLabel = form.querySelector('.amount-field-label');
    const installmentFields = form.querySelector('.installment-fields');
    const installmentInputs = installmentFields.querySelectorAll('input');
    const hint = form.querySelector('[data-hint]');

    if (type === 'variable') {
        amount.required = false;
        amountLabel.textContent = 'Valor estimado (opcional)';
        installmentFields.hidden = true;
        installmentInputs.forEach(input => input.required = false);
    } else if (type === 'installment') {
        amount.required = true;
        amountLabel.textContent = 'Valor da parcela (R$)';
        installmentFields.hidden = false;
        installmentInputs.forEach(input => input.required = true);
    } else {
        amount.required = true;
        amountLabel.textContent = 'Valor (R$)';
        installmentFields.hidden = true;
        installmentInputs.forEach(input => input.required = false);
    }

    hint.textContent = billingHints[type] || '';
}

document.getElementById('bill-type').addEventListener('change', syncBillingForm);

function openNewBill() {
    const form = document.getElementById('billForm');
    form.reset();

    document.getElementById('bill-action').value = 'add_bill';
    document.getElementById('bill-id').value = '';
    document.getElementById('bill-dialog-title').textContent = 'Nova conta fixa';
    document.getElementById('bill-dialog-subtitle').textContent = 'Configure o valor e o dia de vencimento.';
    document.getElementById('bill-type').value = 'fixed';
    document.getElementById('bill-day').value = '10';
    document.getElementById('bill-start').value = '<?= e($month) ?>';
    document.getElementById('bill-total').value = '10';

    syncBillingForm();
    document.getElementById('bill-dialog').showModal();
}

function openBillEditor(id, name, type, amount, dueDay, startMonth, installmentTotal) {
    document.getElementById('bill-action').value = 'update_bill';
    document.getElementById('bill-id').value = id;
    document.getElementById('bill-name').value = name;
    document.getElementById('bill-type').value = type;
    document.getElementById('bill-amount').value = amount || '';
    document.getElementById('bill-day').value = dueDay;
    document.getElementById('bill-start').value = startMonth || '<?= e($month) ?>';
    document.getElementById('bill-total').value = installmentTotal || 10;
    document.getElementById('bill-dialog-title').textContent = 'Editar conta fixa';
    document.getElementById('bill-dialog-subtitle').textContent = 'Atualize os dados da recorrência sem perder o histórico.';

    syncBillingForm();
    document.getElementById('bill-dialog').showModal();
}

function openMonthlyAmount(id, name, amount) {
    document.getElementById('monthly-bill-id').value = id;
    document.getElementById('monthly-bill-title').textContent = name;
    document.getElementById('monthly-bill-amount').value = Number(amount) > 0 ? amount : '';
    document.getElementById('monthly-amount-dialog').showModal();
}

syncBillingForm();
</script>
</body>
</html>

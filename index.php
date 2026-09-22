<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/finance.php';

$user = require_auth();
$month = valid_month($_GET['month'] ?? null);
$data = dashboard_data(db(), $month);
$flash = pull_flash();

[$year, $monthNumber] = array_map('intval', explode('-', $month));
$monthNames = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
$monthLabel = $monthNames[$monthNumber] . ' de ' . $year;

$current = new DateTimeImmutable($month . '-01');
$prevMonth = $current->modify('-1 month')->format('Y-m');
$nextMonth = $current->modify('+1 month')->format('Y-m');

$totals = $data['totals'];
$balance = $totals['income'] - $totals['expense'] - $totals['investment'] - $totals['debt'];
$debtPending = 0.0;
foreach ($data['debts'] as $debt) {
    $debtPending += max(0, $debt['total_amount'] - $debt['paid_amount']);
}
$paidBills = count(array_filter($data['bills'], fn(array $bill) => $bill['paid']));
$defaultDate = $month === date('Y-m') ? date('Y-m-d') : $month . '-01';
$csrf = csrf_token();

function progress_percent(float $current, float $total): float
{
    if ($total <= 0) {
        return 0;
    }
    return min(100, max(0, ($current / $total) * 100));
}

?><!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Família Almeida Finanças</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <div class="brand">
            <div class="brandmark">FA</div>
            <div class="brandcopy"><b>FAMÍLIA</b><strong>ALMEIDA</strong><small>FINANÇAS</small></div>
        </div>

        <div class="side-note">
            <small>PROPÓSITO</small>
            <p>Dar nome a cada real para construir liberdade com intenção.</p>
        </div>

        <form method="post" action="/sair">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <button class="logout" type="submit">Sair da conta</button>
        </form>
    </aside>

    <main>
        <header class="topbar">
            <div class="who"><small>LOGADO COMO</small><strong><?= e($user['name']) ?></strong></div>
            <div class="privacy"><i></i>MySQL conectado</div>
        </header>

        <div class="content">
            <div class="hero">
                <div>
                    <p class="eyebrow">PLANEJAMENTO FINANCEIRO</p>
                    <h1>Para onde nosso dinheiro está indo?</h1>
                    <p class="sub">Acompanhe o mês e ajuste as prioridades da família.</p>
                </div>

                <div class="month">
                    <a href="?month=<?= e($prevMonth) ?>" aria-label="Mês anterior">‹</a>
                    <div class="label"><small>MÊS DE REFERÊNCIA</small><strong><?= e($monthLabel) ?></strong></div>
                    <a href="?month=<?= e($nextMonth) ?>" aria-label="Próximo mês">›</a>
                </div>
            </div>

            <?php if ($flash): ?>
                <div class="alert <?= $flash['type'] === 'success' ? 'success' : '' ?>">
                    <?= e($flash['message']) ?>
                </div>
            <?php endif; ?>

            <div class="grid4">
                <div class="stat"><div class="stat-top"><span>Entradas</span><span class="stat-icon">↗</span></div><strong><?= money($totals['income']) ?></strong></div>
                <div class="stat"><div class="stat-top"><span>Saídas</span><span class="stat-icon">↘</span></div><strong><?= money($totals['expense'] + $totals['debt']) ?></strong></div>
                <div class="stat"><div class="stat-top"><span>Saldo projetado</span><span class="stat-icon">◌</span></div><strong><?= money($balance) ?></strong></div>
                <div class="stat"><div class="stat-top"><span>Dívidas pendentes</span><span class="stat-icon">▤</span></div><strong><?= money($debtPending) ?></strong></div>
            </div>

            <div class="actions">
                <button type="button" onclick="openTransaction('income')">+ Entrada</button>
                <button type="button" onclick="openTransaction('expense')">+ Gasto</button>
                <button type="button" onclick="openTransaction('investment')">+ Investimento</button>
                <button type="button" onclick="document.getElementById('bill-dialog').showModal()">+ Conta fixa</button>
                <button type="button" onclick="openGoal()">+ Meta</button>
                <button type="button" onclick="document.getElementById('debt-dialog').showModal()">+ Dívida</button>
            </div>

            <div class="split equal">
                <section class="card">
                    <div class="card-head">
                        <div><p class="eyebrow">CONTAS DO MÊS</p><h2><?= $paidBills ?> de <?= count($data['bills']) ?> contas pagas</h2></div>
                    </div>

                    <div class="bill-list">
                        <?php foreach ($data['bills'] as $bill): ?>
                            <div class="bill">
                                <form method="post" action="/acao">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="action" value="toggle_bill">
                                    <input type="hidden" name="month" value="<?= e($month) ?>">
                                    <input type="hidden" name="bill_id" value="<?= (int) $bill['id'] ?>">
                                    <input type="hidden" name="paid" value="<?= $bill['paid'] ? '0' : '1' ?>">
                                    <button class="check <?= $bill['paid'] ? 'done' : '' ?>" type="submit"><?= $bill['paid'] ? '✓' : '' ?></button>
                                </form>
                                <div class="due"><small>DIA</small><strong><?= str_pad((string) $bill['due_day'], 2, '0', STR_PAD_LEFT) ?></strong></div>
                                <div class="billinfo"><strong><?= e($bill['name']) ?></strong><span>Vence dia <?= (int) $bill['due_day'] ?></span></div>
                                <div class="billamt"><strong><?= money($bill['amount']) ?></strong></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="card investment">
                    <div>
                        <p class="eyebrow">CONSTRUIR O FUTURO</p>
                        <h2>Meta de investimento</h2>
                        <p>O investimento entra no orçamento antes de virar sobra.</p>
                    </div>
                    <div class="goal-number"><strong><?= money($totals['investment']) ?></strong><span>de <?= money($data['investmentGoal']) ?></span></div>
                    <div class="progress"><span style="width:<?= progress_percent($totals['investment'], $data['investmentGoal']) ?>%"></span></div>

                    <form method="post" action="/acao" class="inline-edit">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="set_investment_goal">
                        <input type="hidden" name="month" value="<?= e($month) ?>">
                        <input type="number" name="value" min="0" step="0.01" value="<?= e($data['investmentGoal']) ?>" aria-label="Meta mensal de investimento">
                        <button class="lightbtn" type="submit">Salvar meta</button>
                    </form>
                </section>
            </div>

            <div class="split equal">
                <section class="card">
                    <div class="card-head"><div><p class="eyebrow">METAS DE GASTOS</p><h2>Limites por categoria</h2></div></div>
                    <div class="goal-list">
                        <?php foreach ($data['goals'] as $goal): ?>
                            <div class="goal">
                                <div>
                                    <strong><?= e($goal['category']) ?></strong>
                                    <span><?= money($goal['spent']) ?> de <?= money($goal['monthly_limit']) ?></span>
                                    <div class="progress"><span style="width:<?= progress_percent($goal['spent'], $goal['monthly_limit']) ?>%"></span></div>
                                </div>
                                <button class="iconbtn" type="button" onclick='editGoal(<?= (int) $goal["id"] ?>, <?= json_encode($goal["category"], JSON_HEX_APOS | JSON_HEX_QUOT) ?>, <?= json_encode($goal["monthly_limit"]) ?>)'>Editar</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="card">
                    <div class="card-head"><div><p class="eyebrow">DÍVIDAS</p><h2>Pendências</h2></div></div>
                    <div class="goal-list">
                        <?php if ($data['debts']): ?>
                            <?php foreach ($data['debts'] as $debt): ?>
                                <?php $remaining = max(0, $debt['total_amount'] - $debt['paid_amount']); ?>
                                <div class="goal debt-row">
                                    <div>
                                        <strong><?= e($debt['name']) ?></strong>
                                        <span><?= money($remaining) ?> pendente</span>
                                        <div class="progress"><span style="width:<?= progress_percent($debt['paid_amount'], $debt['total_amount']) ?>%"></span></div>
                                    </div>
                                    <div class="row-actions">
                                        <?php if ($remaining > 0): ?>
                                            <button class="iconbtn" type="button" onclick='openDebtPayment(<?= (int) $debt["id"] ?>, <?= json_encode($debt["name"], JSON_HEX_APOS | JSON_HEX_QUOT) ?>, <?= json_encode($remaining) ?>)'>Pagar</button>
                                        <?php endif; ?>
                                        <form method="post" action="/acao" onsubmit="return confirm('Remover esta dívida?')">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                            <input type="hidden" name="action" value="delete_debt">
                                            <input type="hidden" name="month" value="<?= e($month) ?>">
                                            <input type="hidden" name="debt_id" value="<?= (int) $debt['id'] ?>">
                                            <button class="iconbtn danger" type="submit">Excluir</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty"><div class="bubble">◇</div><strong>Nenhuma dívida cadastrada.</strong></div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <section class="card recent">
                <div class="card-head"><div><p class="eyebrow">MOVIMENTAÇÕES</p><h2>Histórico do mês</h2></div></div>
                <div class="tx-list">
                    <?php if ($data['transactions']): ?>
                        <?php foreach ($data['transactions'] as $transaction): ?>
                            <div class="tx">
                                <div class="txicon <?= e($transaction['type']) ?>"><?= $transaction['type'] === 'income' ? '↗' : '↘' ?></div>
                                <div class="txinfo">
                                    <strong><?= e($transaction['description']) ?></strong>
                                    <span><?= e($transaction['category']) ?> • <?= e($transaction['date']) ?> • <?= e($transaction['created_by_name'] ?: 'Família') ?></span>
                                </div>
                                <div class="txval <?= $transaction['type'] === 'income' ? 'pos' : 'neg' ?>">
                                    <?= $transaction['type'] === 'income' ? '+' : '−' ?> <?= money($transaction['amount']) ?>
                                </div>
                                <form method="post" action="/acao" onsubmit="return confirm('Remover este lançamento?')">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="action" value="delete_transaction">
                                    <input type="hidden" name="month" value="<?= e($month) ?>">
                                    <input type="hidden" name="transaction_id" value="<?= (int) $transaction['id'] ?>">
                                    <button class="iconbtn danger" type="submit">Excluir</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty"><div class="bubble">◇</div><strong>Nenhum lançamento neste mês.</strong></div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </main>
</div>

<dialog id="transaction-dialog">
    <form method="post" action="/acao" class="dialog-form">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="add_transaction">
        <input type="hidden" name="month" value="<?= e($month) ?>">
        <div class="dialog-head"><div><p class="eyebrow">LANÇAMENTO</p><h3>Novo movimento</h3></div><button type="button" onclick="this.closest('dialog').close()">×</button></div>
        <label>Tipo<select name="type" id="transaction-type" required><option value="income">Entrada</option><option value="expense">Gasto</option><option value="investment">Investimento</option></select></label>
        <label>Descrição<input name="description" maxlength="160" required></label>
        <label>Categoria<input name="category" maxlength="100" required></label>
        <label>Valor<input type="number" name="amount" min="0.01" step="0.01" required></label>
        <label>Data<input type="date" name="date" value="<?= e($defaultDate) ?>" required></label>
        <button class="primary" type="submit">Salvar lançamento</button>
    </form>
</dialog>

<dialog id="bill-dialog">
    <form method="post" action="/acao" class="dialog-form">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="add_bill">
        <input type="hidden" name="month" value="<?= e($month) ?>">
        <div class="dialog-head"><div><p class="eyebrow">CONTA FIXA</p><h3>Nova conta mensal</h3></div><button type="button" onclick="this.closest('dialog').close()">×</button></div>
        <label>Nome<input name="name" maxlength="120" required></label>
        <label>Valor<input type="number" name="amount" min="0" step="0.01" value="0" required></label>
        <label>Dia do vencimento<input type="number" name="due_day" min="1" max="31" value="10" required></label>
        <button class="primary" type="submit">Adicionar conta</button>
    </form>
</dialog>

<dialog id="goal-dialog">
    <form method="post" action="/acao" class="dialog-form">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" id="goal-action" value="add_goal">
        <input type="hidden" name="goal_id" id="goal-id" value="">
        <input type="hidden" name="month" value="<?= e($month) ?>">
        <div class="dialog-head"><div><p class="eyebrow">META DE GASTO</p><h3 id="goal-title">Nova categoria</h3></div><button type="button" onclick="this.closest('dialog').close()">×</button></div>
        <label>Categoria<input name="category" id="goal-category" maxlength="100" required></label>
        <label>Limite mensal<input type="number" name="limit" id="goal-limit" min="0" step="0.01" required></label>
        <button class="primary" type="submit">Salvar meta</button>
    </form>
</dialog>

<dialog id="debt-dialog">
    <form method="post" action="/acao" class="dialog-form">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="add_debt">
        <input type="hidden" name="month" value="<?= e($month) ?>">
        <div class="dialog-head"><div><p class="eyebrow">DÍVIDA</p><h3>Nova pendência</h3></div><button type="button" onclick="this.closest('dialog').close()">×</button></div>
        <label>Nome<input name="name" maxlength="160" required></label>
        <label>Valor total<input type="number" name="total" min="0.01" step="0.01" required></label>
        <label>Valor já pago<input type="number" name="paid" min="0" step="0.01" value="0" required></label>
        <button class="primary" type="submit">Adicionar dívida</button>
    </form>
</dialog>

<dialog id="debt-payment-dialog">
    <form method="post" action="/acao" class="dialog-form">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="pay_debt">
        <input type="hidden" name="month" value="<?= e($month) ?>">
        <input type="hidden" name="debt_id" id="payment-debt-id">
        <div class="dialog-head"><div><p class="eyebrow">PAGAMENTO</p><h3 id="payment-title">Registrar pagamento</h3></div><button type="button" onclick="this.closest('dialog').close()">×</button></div>
        <label>Valor<input type="number" name="amount" id="payment-amount" min="0.01" step="0.01" required></label>
        <label>Data<input type="date" name="date" value="<?= e($defaultDate) ?>" required></label>
        <button class="primary" type="submit">Registrar pagamento</button>
    </form>
</dialog>

<script>
function openTransaction(type) {
    document.getElementById('transaction-type').value = type;
    document.getElementById('transaction-dialog').showModal();
}
function openGoal() {
    document.getElementById('goal-action').value = 'add_goal';
    document.getElementById('goal-id').value = '';
    document.getElementById('goal-category').value = '';
    document.getElementById('goal-limit').value = '';
    document.getElementById('goal-title').textContent = 'Nova categoria';
    document.getElementById('goal-dialog').showModal();
}
function editGoal(id, category, limit) {
    document.getElementById('goal-action').value = 'update_goal';
    document.getElementById('goal-id').value = id;
    document.getElementById('goal-category').value = category;
    document.getElementById('goal-limit').value = limit;
    document.getElementById('goal-title').textContent = 'Editar meta';
    document.getElementById('goal-dialog').showModal();
}
function openDebtPayment(id, name, remaining) {
    document.getElementById('payment-debt-id').value = id;
    document.getElementById('payment-title').textContent = 'Pagar ' + name;
    document.getElementById('payment-amount').max = remaining;
    document.getElementById('debt-payment-dialog').showModal();
}
</script>
</body>
</html>

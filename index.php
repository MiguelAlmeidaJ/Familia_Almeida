<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/finance.php';
require_once __DIR__ . '/includes/layout.php';

$user = require_auth();
$pdo = db();
$month = valid_month($_GET['month'] ?? null);
$data = dashboard_data($pdo, $month);
$recurringSchemaReady = recurring_bills_schema_ready($pdo);
$flash = pull_flash();
$csrf = csrf_token();

[$year, $monthNumber] = array_map('intval', explode('-', $month));
$monthNames = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
$monthLabel = $monthNames[$monthNumber] . ' de ' . $year;

$current = new DateTimeImmutable($month . '-01');
$prevMonth = $current->modify('-1 month')->format('Y-m');
$nextMonth = $current->modify('+1 month')->format('Y-m');

$totals = $data['totals'];
$paidOutflow = $totals['expense'] + $totals['debt'];
$balance = $totals['income'] - $paidOutflow - $totals['investment'];

$debtPending = 0.0;
foreach ($data['debts'] as $debt) {
    $debtPending += max(0, $debt['total_amount'] - $debt['paid_amount']);
}

$paidBills = array_values(array_filter($data['bills'], fn(array $bill) => $bill['paid']));
$pendingBills = array_values(array_filter($data['bills'], fn(array $bill) => !$bill['paid']));
$knownPendingBillsAmount = array_reduce(
    array_filter($pendingBills, fn(array $bill) => !$bill['needs_amount']),
    fn(float $sum, array $bill) => $sum + (float) $bill['amount'],
    0.0
);
$needsValueCount = count(array_filter($pendingBills, fn(array $bill) => $bill['needs_amount']));

$recurringPaid = 0.0;
$variableExpenses = 0.0;
foreach ($data['transactions'] as $transaction) {
    if ($transaction['type'] !== 'expense') {
        continue;
    }

    if (!empty($transaction['bill_payment_id'])
        || in_array(strtolower((string) $transaction['category']), ['contas recorrentes', 'contas fixas'], true)) {
        $recurringPaid += (float) $transaction['amount'];
    } else {
        $variableExpenses += (float) $transaction['amount'];
    }
}

$destinationTotal = $recurringPaid + $variableExpenses + $totals['debt'] + $totals['investment'];
$investmentGoal = (float) $data['investmentGoal'];
$investmentProgress = $investmentGoal > 0
    ? min(100, ($totals['investment'] / $investmentGoal) * 100)
    : 0;

$accountPreview = array_slice($pendingBills ?: $data['bills'], 0, 5);
$goalPreview = array_slice($data['goals'], 0, 4);
$recentTransactions = array_slice($data['transactions'], 0, 6);

$chartPayload = [
    'labels' => ['Contas recorrentes', 'Gastos variáveis', 'Dívidas pagas', 'Investimentos'],
    'values' => [$recurringPaid, $variableExpenses, (float) $totals['debt'], (float) $totals['investment']],
    'total' => $destinationTotal,
    'month' => ucfirst($monthNames[$monthNumber]) . ' de ' . $year,
];

function goal_progress(array $goal): float
{
    if ((float) $goal['monthly_limit'] <= 0) {
        return 0;
    }

    return min(100, ((float) $goal['spent'] / (float) $goal['monthly_limit']) * 100);
}

function dashboard_bill_subtitle(array $bill): string
{
    if ($bill['needs_amount']) {
        return 'Definir valor do mês';
    }

    if ($bill['billing_type'] === 'installment' && !empty($bill['installment_number'])) {
        return 'Parcela ' . (int) $bill['installment_number'] . '/' . (int) $bill['installment_total'];
    }

    return $bill['paid'] ? 'Pago neste mês' : 'Vence dia ' . (int) $bill['due_day'];
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Visão geral • Família Almeida</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<div class="shell ref-shell">
    <?php render_sidebar('dashboard', $csrf); ?>

    <main>
        <?php render_topbar($user); ?>

        <div class="content ref-dashboard">
            <section class="ref-dashboard-heading">
                <div>
                    <p class="eyebrow">CADA ESCOLHA CONTA</p>
                    <h1>Visão geral</h1>
                    <p>Tudo o que entra, tudo o que sai. E o que fica para os seus planos.</p>
                </div>

                <div class="ref-month-picker">
                    <a href="?month=<?= e($prevMonth) ?>" aria-label="Mês anterior">‹</a>
                    <span><?= e($monthLabel) ?></span>
                    <span class="ref-calendar">□</span>
                    <a href="?month=<?= e($nextMonth) ?>" aria-label="Próximo mês">›</a>
                </div>
            </section>

            <?php if ($flash): ?>
                <div class="alert <?= $flash['type'] === 'success' ? 'success' : '' ?>"><?= e($flash['message']) ?></div>
            <?php endif; ?>

            <?php if (!$recurringSchemaReady): ?>
                <div class="alert migration-alert">
                    Há uma atualização de banco pendente para Contas recorrentes.
                    <a href="/configuracoes/manutencao">Executar migrations →</a>
                </div>
            <?php endif; ?>

            <section class="ref-kpi-grid">
                <article class="ref-kpi ref-kpi-balance <?= $balance < 0 ? 'negative' : '' ?>">
                    <div class="ref-kpi-head"><span>Saldo do mês</span><span>▣</span></div>
                    <strong><?= money($balance) ?></strong>
                    <small>Entradas − saídas − investimentos</small>
                </article>

                <article class="ref-kpi">
                    <div class="ref-kpi-head"><span>Entradas</span><span class="ref-kpi-arrow income">↙</span></div>
                    <strong><?= money($totals['income']) ?></strong>
                    <a href="/movimentacoes">Registrar entrada +</a>
                </article>

                <article class="ref-kpi">
                    <div class="ref-kpi-head"><span>Saídas pagas</span><span class="ref-kpi-arrow expense">↗</span></div>
                    <strong><?= money($paidOutflow) ?></strong>
                    <small><?= money($knownPendingBillsAmount) ?> em contas a pagar<?= $needsValueCount ? ' + ' . $needsValueCount . ' sem valor' : '' ?></small>
                </article>

                <article class="ref-kpi">
                    <div class="ref-kpi-head"><span>Dívidas pendentes</span><span class="ref-debt-icon">▭</span></div>
                    <strong><?= money($debtPending) ?></strong>
                    <a href="/dividas">Saldo total atual →</a>
                </article>
            </section>

            <section class="ref-primary-grid">
                <article class="card ref-money-card">
                    <div class="ref-card-heading">
                        <div>
                            <h2>Para onde vai o dinheiro</h2>
                            <p>Distribuição das saídas do mês</p>
                        </div>
                        <a class="ref-green-button" href="/movimentacoes">+&nbsp; Novo gasto</a>
                    </div>

                    <div class="ref-donut-area">
                        <div class="ref-donut-wrap">
                            <canvas id="destinationChart"></canvas>
                            <div class="ref-donut-center">
                                <small>Total destinado</small>
                                <strong><?= money($destinationTotal) ?></strong>
                                <span><?= e(ucfirst($monthLabel)) ?></span>
                            </div>
                        </div>

                        <div class="ref-donut-legend">
                            <div><span class="ref-legend-dot recurring"></span><span>Contas recorrentes</span><strong><?= money($recurringPaid) ?></strong></div>
                            <div><span class="ref-legend-dot variable"></span><span>Gastos variáveis</span><strong><?= money($variableExpenses) ?></strong></div>
                            <div><span class="ref-legend-dot debt"></span><span>Dívidas pagas</span><strong><?= money($totals['debt']) ?></strong></div>
                            <div><span class="ref-legend-dot investment"></span><span>Investimentos</span><strong><?= money($totals['investment']) ?></strong></div>
                        </div>
                    </div>

                    <footer>Cada lançamento ajuda a enxergar melhor suas escolhas.</footer>
                </article>

                <article class="ref-investment-card" id="investimento">
                    <div class="ref-investment-top">
                        <p class="eyebrow">CONSTRUINDO O FUTURO</p>
                        <span>↗</span>
                    </div>

                    <div class="ref-investment-copy">
                        <h2>Um passo por mês.</h2>
                        <p>Seu investimento também tem lugar no orçamento.</p>
                    </div>

                    <div class="ref-investment-value">
                        <strong><?= money($totals['investment']) ?></strong>
                        <span>de <?= money($investmentGoal) ?></span>
                    </div>

                    <div class="ref-investment-progress"><span style="width:<?= $investmentProgress ?>%"></span></div>

                    <a class="ref-investment-meta" href="/metas?tab=investimento">
                        <?= $investmentGoal > 0 ? number_format($investmentProgress, 0, ',', '.') . '% da meta mensal' : 'Defina sua meta mensal' ?>
                        <span>✎</span>
                    </a>

                    <a class="ref-investment-button" href="/movimentacoes">＋&nbsp; Registrar investimento</a>
                </article>
            </section>

            <section class="ref-secondary-grid">
                <article class="card ref-list-card">
                    <header class="ref-section-header">
                        <div>
                            <h2>Contas do mês</h2>
                            <p><?= count($paidBills) ?> de <?= count($data['bills']) ?> contas pagas</p>
                        </div>
                        <a href="/contas?month=<?= e($month) ?>">Ver todas →</a>
                    </header>

                    <div class="ref-account-list">
                        <?php if ($accountPreview): ?>
                            <?php foreach ($accountPreview as $bill): ?>
                                <div class="ref-account-row">
                                    <div class="ref-day-box"><small>DIA</small><strong><?= str_pad((string) $bill['due_day'], 2, '0', STR_PAD_LEFT) ?></strong></div>
                                    <div class="ref-account-copy">
                                        <strong><?= e($bill['name']) ?></strong>
                                        <span><?= e(dashboard_bill_subtitle($bill)) ?></span>
                                    </div>
                                    <strong class="ref-account-value"><?= $bill['needs_amount'] ? '—' : money($bill['amount']) ?></strong>
                                    <a class="ref-edit-link" href="/contas?month=<?= e($month) ?>" aria-label="Editar <?= e($bill['name']) ?>">✎</a>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="ref-empty">Nenhuma conta recorrente cadastrada.</div>
                        <?php endif; ?>
                    </div>
                </article>

                <article class="card ref-list-card">
                    <header class="ref-section-header">
                        <div>
                            <h2>Gastar com intenção</h2>
                            <p>Suas metas por categoria</p>
                        </div>
                        <a href="/metas?month=<?= e($month) ?>">Ver metas →</a>
                    </header>

                    <div class="ref-goals-list">
                        <?php if ($goalPreview): ?>
                            <?php foreach ($goalPreview as $index => $goal): ?>
                                <?php $progress = goal_progress($goal); ?>
                                <div class="ref-goal-row">
                                    <div class="ref-goal-icon tone-<?= ($index % 4) + 1 ?>"><?= e(strtoupper(substr((string) $goal['category'], 0, 1))) ?></div>
                                    <div class="ref-goal-main">
                                        <div class="ref-goal-title">
                                            <strong><?= e($goal['category']) ?></strong>
                                            <a href="/metas?month=<?= e($month) ?>">✎</a>
                                        </div>
                                        <span>
                                            <?= money($goal['spent']) ?>
                                            <?= $goal['monthly_limit'] > 0 ? ' de ' . money($goal['monthly_limit']) : ' • sem meta definida' ?>
                                        </span>
                                        <div class="ref-goal-progress"><span style="width:<?= $progress ?>%"></span></div>
                                        <small>
                                            <?= $goal['monthly_limit'] > 0
                                                ? number_format($progress, 0, ',', '.') . '% do limite utilizado'
                                                : 'Defina um limite para acompanhar' ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="ref-empty">Nenhuma meta de gasto cadastrada.</div>
                        <?php endif; ?>
                    </div>
                </article>
            </section>

            <section class="card ref-transactions-card">
                <header class="ref-section-header">
                    <div><h2>Últimas movimentações</h2></div>
                    <a href="/movimentacoes?month=<?= e($month) ?>">Ver todas →</a>
                </header>

                <div class="ref-table-wrap">
                    <div class="ref-table-head">
                        <span>Descrição</span><span>Categoria</span><span>Data</span><span>Valor</span><span></span>
                    </div>

                    <?php if ($recentTransactions): ?>
                        <?php foreach ($recentTransactions as $transaction): ?>
                            <div class="ref-transaction-row">
                                <div class="ref-transaction-description">
                                    <span class="ref-tx-icon <?= e($transaction['type']) ?>"><?= $transaction['type'] === 'income' ? '↙' : '↗' ?></span>
                                    <div>
                                        <strong><?= e($transaction['description']) ?></strong>
                                        <small><?= $transaction['type'] === 'income' ? 'Entrada' : ($transaction['type'] === 'investment' ? 'Investimento' : 'Saída') ?></small>
                                    </div>
                                </div>
                                <span><?= e($transaction['category']) ?></span>
                                <span><?= e(date('d/m', strtotime($transaction['date']))) ?></span>
                                <strong class="<?= $transaction['type'] === 'income' ? 'positive' : 'negative' ?>">
                                    <?= $transaction['type'] === 'income' ? '+' : '−' ?> <?= money($transaction['amount']) ?>
                                </strong>
                                <div class="ref-table-actions">
                                    <?php if (empty($transaction['bill_payment_id'])): ?>
                                        <form method="post" action="/acao" onsubmit="return confirm('Remover este lançamento?')">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                            <input type="hidden" name="return_to" value="/">
                                            <input type="hidden" name="action" value="delete_transaction">
                                            <input type="hidden" name="month" value="<?= e($month) ?>">
                                            <input type="hidden" name="transaction_id" value="<?= (int) $transaction['id'] ?>">
                                            <button type="submit" title="Excluir">⌫</button>
                                        </form>
                                    <?php else: ?>
                                        <a href="/contas?month=<?= e($month) ?>" title="Gerenciar conta">↗</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="ref-empty ref-table-empty">Nenhuma movimentação neste mês.</div>
                    <?php endif; ?>
                </div>
            </section>

            <footer class="ref-page-footer">
                <span>Família Almeida&nbsp;&nbsp; / &nbsp;&nbsp;Um mês de cada vez.</span>
                <span>Dados salvos na sua conta</span>
            </footer>
        </div>
    </main>
</div>

<script>
window.dashboardChartData = <?= json_encode($chartPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script src="/assets/dashboard.js"></script>
</body>
</html>

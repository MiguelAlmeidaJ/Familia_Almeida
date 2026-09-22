<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/finance.php';
require_once __DIR__ . '/includes/layout.php';

$user = require_auth();
$pdo = db();
$month = valid_month($_GET['month'] ?? null);
$data = dashboard_data($pdo, $month);
$analytics = dashboard_analytics($pdo, $month);
$flash = pull_flash();
$csrf = csrf_token();

[$year, $monthNumber] = array_map('intval', explode('-', $month));
$monthNames = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
$monthShort = [1 => 'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
$monthLabel = $monthNames[$monthNumber] . ' de ' . $year;

$current = new DateTimeImmutable($month . '-01');
$prevMonth = $current->modify('-1 month')->format('Y-m');
$nextMonth = $current->modify('+1 month')->format('Y-m');

$totals = $data['totals'];
$outflow = $totals['expense'] + $totals['debt'];
$balance = $totals['income'] - $outflow - $totals['investment'];
$previousOutflow = $analytics['previous']['expense'] + $analytics['previous']['debt'];
$previousBalance = $analytics['previous']['income'] - $previousOutflow - $analytics['previous']['investment'];

$fixedMonthly = array_reduce($data['bills'], fn(float $sum, array $bill) => $sum + (float) $bill['amount'], 0.0);
$paidBills = array_values(array_filter($data['bills'], fn(array $bill) => $bill['paid']));
$pendingBills = array_values(array_filter($data['bills'], fn(array $bill) => !$bill['paid']));
$paidBillAmount = array_reduce($paidBills, fn(float $sum, array $bill) => $sum + (float) $bill['amount'], 0.0);
$billProgress = count($data['bills']) > 0 ? (count($paidBills) / count($data['bills'])) * 100 : 0;

$debtPending = 0.0;
foreach ($data['debts'] as $debt) {
    $debtPending += max(0, $debt['total_amount'] - $debt['paid_amount']);
}

$investmentProgress = $data['investmentGoal'] > 0 ? min(100, ($totals['investment'] / $data['investmentGoal']) * 100) : 0;
$savingsRate = $totals['income'] > 0 ? ($totals['investment'] / $totals['income']) * 100 : 0;
$fixedCommitment = $totals['income'] > 0 ? ($fixedMonthly / $totals['income']) * 100 : 0;

function dashboard_change(float $current, float $previous, bool $lowerIsBetter = false): array
{
    if (abs($previous) < 0.01) {
        if (abs($current) < 0.01) {
            return ['text' => 'Sem movimento', 'class' => 'neutral'];
        }
        return ['text' => 'Novo neste mês', 'class' => 'neutral'];
    }

    $change = (($current - $previous) / abs($previous)) * 100;
    if (abs($change) < 0.5) {
        return ['text' => 'Estável vs. mês anterior', 'class' => 'neutral'];
    }

    $isGood = $lowerIsBetter ? $change < 0 : $change > 0;
    return [
        'text' => ($change > 0 ? '↑ ' : '↓ ') . number_format(abs($change), 1, ',', '.') . '% vs. mês anterior',
        'class' => $isGood ? 'positive' : 'negative',
    ];
}

$incomeChange = dashboard_change($totals['income'], $analytics['previous']['income']);
$outflowChange = dashboard_change($outflow, $previousOutflow, true);
$investmentChange = dashboard_change($totals['investment'], $analytics['previous']['investment']);
$balanceChange = dashboard_change($balance, $previousBalance);

$overGoals = [];
foreach ($data['goals'] as $goal) {
    if ($goal['monthly_limit'] > 0 && $goal['spent'] > $goal['monthly_limit']) {
        $overGoals[] = $goal;
    }
}
usort($overGoals, fn(array $a, array $b) => ($b['spent'] - $b['monthly_limit']) <=> ($a['spent'] - $a['monthly_limit']));

$insights = [];
if ($totals['income'] <= 0) {
    $insights[] = ['type' => 'attention', 'title' => 'Entradas ainda não registradas', 'text' => 'O mês ainda não possui receitas lançadas. O saldo e os percentuais ficam mais úteis depois das entradas.'];
} elseif ($balance >= 0) {
    $insights[] = ['type' => 'success', 'title' => 'Saldo do mês está positivo', 'text' => 'Depois de gastos, dívidas e investimentos, restam ' . money($balance) . '.'];
} else {
    $insights[] = ['type' => 'attention', 'title' => 'Saídas superam as entradas', 'text' => 'O mês está com saldo projetado de ' . money($balance) . '. Vale revisar os maiores gastos.'];
}

if (count($data['bills']) > 0) {
    $insights[] = [
        'type' => count($pendingBills) === 0 ? 'success' : 'info',
        'title' => count($pendingBills) === 0 ? 'Contas fixas em dia' : count($pendingBills) . ' conta(s) fixa(s) pendente(s)',
        'text' => number_format($billProgress, 0, ',', '.') . '% das contas recorrentes foram marcadas como pagas em ' . $monthLabel . '.'
    ];
}

if ($overGoals) {
    $goal = $overGoals[0];
    $excess = $goal['spent'] - $goal['monthly_limit'];
    $insights[] = ['type' => 'attention', 'title' => 'Meta ultrapassada em ' . $goal['category'], 'text' => 'A categoria passou ' . money($excess) . ' do limite definido para o mês.'];
} elseif ($data['investmentGoal'] > 0) {
    $insights[] = [
        'type' => $investmentProgress >= 100 ? 'success' : 'info',
        'title' => $investmentProgress >= 100 ? 'Meta de investimento concluída' : 'Investimento em andamento',
        'text' => number_format($investmentProgress, 0, ',', '.') . '% da meta mensal de ' . money($data['investmentGoal']) . ' foi alcançada.'
    ];
}

if ($analytics['largest_expense']) {
    $expense = $analytics['largest_expense'];
    $insights[] = ['type' => 'info', 'title' => 'Maior gasto do mês', 'text' => $expense['description'] . ' representa ' . money($expense['amount']) . ' em ' . $expense['category'] . '.'];
}

$insights = array_slice($insights, 0, 4);

$daysInMonth = (int) $current->format('t');
$dailyIndex = [];
foreach ($analytics['daily'] as $row) {
    $dailyIndex[$row['day']] = $row;
}
$dailyLabels = [];
$dailyIncome = [];
$dailyOutflow = [];
$dailyInvestment = [];
for ($day = 1; $day <= $daysInMonth; $day++) {
    $date = sprintf('%s-%02d', $month, $day);
    $dailyLabels[] = str_pad((string) $day, 2, '0', STR_PAD_LEFT);
    $row = $dailyIndex[$date] ?? ['income' => 0, 'outflow' => 0, 'investment' => 0];
    $dailyIncome[] = (float) $row['income'];
    $dailyOutflow[] = (float) $row['outflow'];
    $dailyInvestment[] = (float) $row['investment'];
}

$categoryLabels = array_map(fn(array $row) => $row['category'], $analytics['categories']);
$categoryValues = array_map(fn(array $row) => (float) $row['total'], $analytics['categories']);

$historyLabels = [];
$historyIncome = [];
$historyOutflow = [];
$historyBalance = [];
foreach ($analytics['six_months'] as $row) {
    [$rowYear, $rowMonth] = array_map('intval', explode('-', $row['month']));
    $historyLabels[] = ucfirst($monthShort[$rowMonth]) . '/' . substr((string) $rowYear, -2);
    $historyIncome[] = (float) $row['income'];
    $historyOutflow[] = (float) $row['outflow'];
    $historyBalance[] = (float) $row['balance'];
}

$chartPayload = [
    'daily' => [
        'labels' => $dailyLabels,
        'income' => $dailyIncome,
        'outflow' => $dailyOutflow,
        'investment' => $dailyInvestment,
    ],
    'categories' => [
        'labels' => $categoryLabels,
        'values' => $categoryValues,
    ],
    'months' => [
        'labels' => $historyLabels,
        'income' => $historyIncome,
        'outflow' => $historyOutflow,
        'balance' => $historyBalance,
    ],
];

$recentTransactions = array_slice($data['transactions'], 0, 6);
$nextBills = array_slice($pendingBills, 0, 5);
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Dashboard • Família Almeida Finanças</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<div class="shell dashboard-shell">
    <?php render_sidebar('dashboard', $csrf); ?>

    <main>
        <?php render_topbar($user); ?>

        <div class="content dashboard-content">
            <div class="dashboard-heading">
                <div>
                    <p class="eyebrow">VISÃO FINANCEIRA</p>
                    <h1>Seu dinheiro, com contexto.</h1>
                    <p>Acompanhe o que entrou, o que saiu e o que precisa de atenção em <?= e($monthLabel) ?>.</p>
                </div>

                <div class="dashboard-heading-actions">
                    <div class="month modern-month">
                        <a href="?month=<?= e($prevMonth) ?>" aria-label="Mês anterior">‹</a>
                        <div class="label"><small>MÊS DE REFERÊNCIA</small><strong><?= e($monthLabel) ?></strong></div>
                        <a href="?month=<?= e($nextMonth) ?>" aria-label="Próximo mês">›</a>
                    </div>
                    <a class="primary quick-primary" href="/movimentacoes">+ Novo lançamento</a>
                </div>
            </div>

            <?php if ($flash): ?>
                <div class="alert <?= $flash['type'] === 'success' ? 'success' : '' ?>"><?= e($flash['message']) ?></div>
            <?php endif; ?>

            <section class="dashboard-kpis">
                <article class="kpi-card balance-kpi">
                    <div class="kpi-top">
                        <span>Saldo do mês</span>
                        <span class="kpi-symbol">⌁</span>
                    </div>
                    <strong><?= money($balance) ?></strong>
                    <div class="kpi-bottom">
                        <span class="trend <?= e($balanceChange['class']) ?>"><?= e($balanceChange['text']) ?></span>
                        <small>após gastos e investimentos</small>
                    </div>
                </article>

                <article class="kpi-card">
                    <div class="kpi-top"><span>Entradas</span><span class="metric-dot income"></span></div>
                    <strong><?= money($totals['income']) ?></strong>
                    <span class="trend <?= e($incomeChange['class']) ?>"><?= e($incomeChange['text']) ?></span>
                </article>

                <article class="kpi-card">
                    <div class="kpi-top"><span>Saídas</span><span class="metric-dot outflow"></span></div>
                    <strong><?= money($outflow) ?></strong>
                    <span class="trend <?= e($outflowChange['class']) ?>"><?= e($outflowChange['text']) ?></span>
                </article>

                <article class="kpi-card">
                    <div class="kpi-top"><span>Investimentos</span><span class="metric-dot investment"></span></div>
                    <strong><?= money($totals['investment']) ?></strong>
                    <span class="trend <?= e($investmentChange['class']) ?>"><?= e($investmentChange['text']) ?></span>
                </article>
            </section>

            <section class="dashboard-context-strip">
                <a href="/contas" class="context-stat">
                    <span>Fixo mensal</span>
                    <strong><?= money($fixedMonthly) ?></strong>
                    <small><?= number_format($fixedCommitment, 0, ',', '.') ?>% das entradas</small>
                </a>
                <a href="/contas?month=<?= e($month) ?>" class="context-stat">
                    <span>Contas pagas</span>
                    <strong><?= count($paidBills) ?>/<?= count($data['bills']) ?></strong>
                    <small><?= money($paidBillAmount) ?> confirmado</small>
                </a>
                <a href="/metas?month=<?= e($month) ?>" class="context-stat">
                    <span>Taxa de investimento</span>
                    <strong><?= number_format($savingsRate, 1, ',', '.') ?>%</strong>
                    <small>sobre as entradas do mês</small>
                </a>
                <a href="/dividas" class="context-stat">
                    <span>Dívidas pendentes</span>
                    <strong><?= money($debtPending) ?></strong>
                    <small><?= count($data['debts']) ?> dívida(s) cadastrada(s)</small>
                </a>
            </section>

            <section class="dashboard-main-grid">
                <article class="card chart-card cashflow-card">
                    <div class="card-title-row">
                        <div>
                            <p class="eyebrow">FLUXO DO MÊS</p>
                            <h2>Entradas x saídas</h2>
                            <p>Movimentação diária registrada em <?= e($monthLabel) ?>.</p>
                        </div>
                        <a href="/movimentacoes?month=<?= e($month) ?>" class="card-link">Ver movimentações →</a>
                    </div>
                    <div class="chart-shell large-chart">
                        <canvas id="cashflowChart"></canvas>
                        <div class="chart-empty-message">Ainda não há movimentações suficientes para desenhar o gráfico.</div>
                    </div>
                </article>

                <aside class="card insights-card">
                    <div class="card-title-row">
                        <div><p class="eyebrow">LEITURA RÁPIDA</p><h2>Insights do mês</h2></div>
                        <span class="insight-badge">AUTO</span>
                    </div>

                    <div class="insights-list">
                        <?php foreach ($insights as $insight): ?>
                            <div class="insight-item <?= e($insight['type']) ?>">
                                <span class="insight-dot"></span>
                                <div><strong><?= e($insight['title']) ?></strong><p><?= e($insight['text']) ?></p></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </aside>
            </section>

            <section class="dashboard-chart-grid">
                <article class="card chart-card">
                    <div class="card-title-row">
                        <div><p class="eyebrow">HISTÓRICO</p><h2>Últimos 6 meses</h2><p>Compare entradas, saídas e saldo ao longo do tempo.</p></div>
                    </div>
                    <div class="chart-shell history-chart"><canvas id="historyChart"></canvas></div>
                </article>

                <article class="card chart-card category-card">
                    <div class="card-title-row">
                        <div><p class="eyebrow">DISTRIBUIÇÃO</p><h2>Gastos por categoria</h2><p>Onde os gastos variáveis estão concentrados.</p></div>
                    </div>
                    <div class="chart-shell category-chart">
                        <canvas id="categoryChart"></canvas>
                        <div class="chart-empty-message">Registre gastos para visualizar a distribuição por categoria.</div>
                    </div>
                </article>
            </section>

            <section class="dashboard-bottom-grid">
                <article class="card dashboard-list-card">
                    <div class="card-title-row">
                        <div><p class="eyebrow">PRÓXIMOS COMPROMISSOS</p><h2>Contas do mês</h2></div>
                        <a href="/contas?month=<?= e($month) ?>" class="card-link">Gerenciar →</a>
                    </div>

                    <div class="dashboard-bills">
                        <?php if ($nextBills): ?>
                            <?php foreach ($nextBills as $bill): ?>
                                <div class="dashboard-bill-row">
                                    <div class="dashboard-due"><small>DIA</small><strong><?= str_pad((string) $bill['due_day'], 2, '0', STR_PAD_LEFT) ?></strong></div>
                                    <div class="dashboard-bill-copy"><strong><?= e($bill['name']) ?></strong><span>Pendente</span></div>
                                    <strong class="dashboard-row-value"><?= money($bill['amount']) ?></strong>
                                    <form method="post" action="/acao">
                                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                        <input type="hidden" name="return_to" value="/">
                                        <input type="hidden" name="action" value="toggle_bill">
                                        <input type="hidden" name="month" value="<?= e($month) ?>">
                                        <input type="hidden" name="bill_id" value="<?= (int) $bill['id'] ?>">
                                        <input type="hidden" name="paid" value="1">
                                        <button class="dashboard-check" type="submit" title="Marcar como paga">✓</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="dashboard-empty-state"><span>✓</span><strong>Nenhuma conta pendente</strong><p>As contas fixas do mês estão em dia.</p></div>
                        <?php endif; ?>
                    </div>

                    <?php if (count($pendingBills) > count($nextBills)): ?>
                        <a class="list-more" href="/contas?month=<?= e($month) ?>">+ <?= count($pendingBills) - count($nextBills) ?> conta(s) pendente(s)</a>
                    <?php endif; ?>
                </article>

                <article class="card dashboard-list-card">
                    <div class="card-title-row">
                        <div><p class="eyebrow">ATIVIDADE RECENTE</p><h2>Últimos lançamentos</h2></div>
                        <a href="/movimentacoes?month=<?= e($month) ?>" class="card-link">Ver todos →</a>
                    </div>

                    <div class="dashboard-transactions">
                        <?php if ($recentTransactions): ?>
                            <?php foreach ($recentTransactions as $transaction): ?>
                                <div class="dashboard-tx-row">
                                    <span class="dashboard-tx-icon <?= e($transaction['type']) ?>"><?= $transaction['type'] === 'income' ? '↗' : '↘' ?></span>
                                    <div class="dashboard-tx-copy">
                                        <strong><?= e($transaction['description']) ?></strong>
                                        <span><?= e($transaction['category']) ?> • <?= e(date('d/m', strtotime($transaction['date']))) ?></span>
                                    </div>
                                    <strong class="dashboard-row-value <?= $transaction['type'] === 'income' ? 'positive-value' : '' ?>">
                                        <?= $transaction['type'] === 'income' ? '+' : '−' ?> <?= money($transaction['amount']) ?>
                                    </strong>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="dashboard-empty-state"><span>↕</span><strong>Nenhum lançamento ainda</strong><p>Adicione entradas e gastos para começar a leitura financeira.</p></div>
                        <?php endif; ?>
                    </div>
                </article>
            </section>

            <section class="card investment-progress-card">
                <div class="investment-progress-copy">
                    <p class="eyebrow">CONSTRUÇÃO DE PATRIMÔNIO</p>
                    <h2>Meta de investimento</h2>
                    <p><?= $data['investmentGoal'] > 0 ? 'Você já acumulou ' . number_format($investmentProgress, 0, ',', '.') . '% da meta deste mês.' : 'Defina uma meta mensal para acompanhar sua consistência de investimento.' ?></p>
                </div>
                <div class="investment-progress-values">
                    <strong><?= money($totals['investment']) ?></strong>
                    <span>de <?= money($data['investmentGoal']) ?></span>
                </div>
                <div class="investment-progress-bar"><span style="width:<?= $investmentProgress ?>%"></span></div>
                <a href="/metas?month=<?= e($month) ?>" class="secondary">Gerenciar meta</a>
            </section>
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

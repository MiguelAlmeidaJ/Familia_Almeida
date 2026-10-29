<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/monthly_budget.php';
require_once __DIR__ . '/includes/layout.php';

$user = require_family_access();
$pdo = db();
$month = selected_month();
$ready = monthly_budget_ready($pdo);
$data = $ready ? monthly_budget_data($pdo, $month) : null;
$flash = pull_flash();
$csrf = csrf_token();
$date = new DateTimeImmutable($month . '-01');
$monthNames = [1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'];
$label = $monthNames[(int)$date->format('n')] . ' de ' . $date->format('Y');
if ($ready) {
    $stmt = $pdo->prepare("SELECT description,category,type,amount,occurred_on
      FROM transactions WHERE type IN ('income','expense') AND occurred_on >= ? AND occurred_on < ?
      ORDER BY occurred_on DESC,id DESC LIMIT 6");
    $stmt->execute([$month.'-01',$date->modify('+1 month')->format('Y-m-d')]);
    $recent = $stmt->fetchAll();
    $rankedBills = array_values(array_filter($data['bills'],static fn($b) => !$b['needs_amount'] && (float)$b['amount']>0));
    usort($rankedBills,static fn($a,$b) => (float)$b['amount'] <=> (float)$a['amount']);
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Painel • Bemora</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"><link rel="stylesheet" href="/assets/style.css"><link rel="stylesheet" href="/assets/bemora.css?v=1">
<link rel="stylesheet" href="/assets/budget-dashboard.css?v=2">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"><link rel="stylesheet" href="/assets/bemora.css?v=2"><link rel="stylesheet" href="/assets/bemora-finance.css?v=2"><link rel="stylesheet" href="/assets/bemora-finish.css?v=3"><?php brand_head_tags(); ?></head>
<body class="bemora-finance">
<div class="shell ref-shell"><?php render_sidebar('dashboard',$csrf); ?>
<main><?php render_topbar($user); ?>
<div class="budget-wrap budget-dashboard">
<header class="budget-title">
<div><p class="eyebrow">VISÃO GERAL · <?= e($label) ?></p>
<h1>Painel financeiro</h1>
<p>Uma visão clara de tudo que está previsto para o mês.</p></div>
</header>
<?php if($flash): ?><div class="budget-alert"><?= e($flash['message']) ?></div><?php endif; ?>
<?php if(!$ready): ?>
<div class="budget-alert">Atualização necessária: execute as migrations em <a href="/configuracoes/manutencao">Configurações → Manutenção</a>.</div>
<?php else: ?>
<section class="budget-grid" aria-label="Indicadores financeiros">
<article class="budget-card metric"><span class="metric-label">Receitas previstas</span><strong><?= money($data['incomeTotal']) ?></strong><small><?= money($data['recordedIncomeTotal']) ?> em lançamentos</small></article>
<article class="budget-card metric"><span class="metric-label">Contas previstas</span><strong><?= money($data['billTotal']) ?></strong><small><?= count($data['bills']) ?> contas no mês</small></article>
<article class="budget-card metric metric-feature"><span class="metric-label">Disponível após contas</span><strong><?= money($data['after_bills']) ?></strong><small>Antes dos gastos avulsos</small></article>
<article class="budget-card metric"><span class="metric-label">Saldo projetado</span><strong><?= money($data['expected_end']) ?></strong><small>Após gastos registrados</small></article>
</section>
<?php if($data['missingBills']): ?><div class="budget-inline-alert" role="status">⚠ <?= (int)$data['missingBills'] ?> conta(s) sem valor informado. A previsão pode estar incompleta.</div><?php endif; ?>
<section class="dashboard-visual-grid" aria-label="Análise visual do mês">
<article class="budget-card dashboard-main-chart">
<div class="dashboard-section-head"><div><h2>Para onde vai o dinheiro</h2><p>Distribuição da receita mensal entre compromissos, gastos e saldo projetado.</p></div><span class="budget-chart-pill"><?= e($label) ?></span></div>
<div class="dashboard-donut-layout">
<div class="dashboard-donut" id="budget-donut" role="img" aria-label="Gráfico de distribuição do orçamento">
<div class="dashboard-donut-center"><span id="donut-focus-label">Saldo projetado</span><strong id="donut-focus-value"><?= money($data['expected_end']) ?></strong><small id="donut-focus-share">do total previsto</small></div>
</div>
<div class="dashboard-chart-side">
<div class="dashboard-legend" id="budget-chart-legend"></div>
<div class="dashboard-chart-foot">Toque em uma categoria para destacar sua participação.</div>
</div>
</div>
</article>
<article class="budget-card dashboard-breakdown">
<div class="dashboard-section-head"><div><h2>Maiores contas</h2><p>Principais compromissos previstos no mês.</p></div><span class="budget-chart-pill"><?= count($rankedBills) ?> com valor</span></div>
<?php if($rankedBills): ?>
<div class="dashboard-bill-bars">
<?php foreach(array_slice($rankedBills,0,6) as $bill):
$ratio=$data['billTotal']>0?min(100,100*(float)$bill['amount']/(float)$data['billTotal']):0;
?>
<div class="dashboard-bill">
<div class="dashboard-bill-top"><span><?= e($bill['name']) ?></span><strong><?= money($bill['amount']) ?></strong></div>
<div class="dashboard-track"><span style="width:<?= number_format($ratio,2,'.','') ?>%"></span></div>
</div>
<?php endforeach; ?>
</div>
<?php else: ?><p class="dashboard-empty">Nenhuma conta com valor previsto neste mês.</p><?php endif; ?>
</article>
</section>
<section class="dashboard-bottom-grid">
<article class="budget-card dashboard-stat-panel">
<div class="dashboard-section-head"><div><h2>Resumo do mês</h2><p>Valores previstos, sem depender de confirmação de pagamento.</p></div></div>
<div class="dashboard-summary-row"><span>Receitas recorrentes</span><strong><?= money($data['recurringIncomeTotal']) ?></strong></div>
<div class="dashboard-summary-row"><span>Receitas lançadas</span><strong><?= money($data['recordedIncomeTotal']) ?></strong></div>
<div class="dashboard-summary-row"><span>Gastos avulsos registrados</span><strong><?= money($data['variableSpent']) ?></strong></div>
<div class="dashboard-summary-row"><span>Valor destinado a categorias</span><strong><?= money($data['allocated']) ?></strong></div>
<div class="dashboard-summary-row"><span>Ainda sem destino</span><strong><?= money($data['unallocated']) ?></strong></div>
</article>
<article class="budget-card dashboard-recent">
<div class="dashboard-section-head"><div><h2>Últimos lançamentos</h2><p>Movimentações registradas em <?= e($label) ?>.</p></div><a href="/movimentacoes">Ver histórico →</a></div>
<?php if($recent): ?>
<?php foreach($recent as $transaction): ?>
<div class="dashboard-transaction"><div><strong><?= e($transaction['description']) ?></strong><small><?= e($transaction['category']) ?> · <?= e(date('d/m',strtotime($transaction['occurred_on']))) ?></small></div><span class="<?= $transaction['type']==='income'?'income':'expense' ?>"><?= $transaction['type']==='income'?'+':'−' ?> <?= money($transaction['amount']) ?></span></div>
<?php endforeach; ?>
<?php else: ?><p class="dashboard-empty">Nenhum lançamento para este mês.</p><?php endif; ?>
</article>
</section>
<p class="dashboard-disclaimer">Os números são projeções do mês selecionado, não o saldo de uma conta bancária. Contas entram no cálculo mesmo sem confirmação de pagamento.</p>
<script id="budget-visual-data" type="application/json"><?= json_encode([
'income'=>(float)$data['incomeTotal'],
'bills'=>(float)$data['billTotal'],
'spent'=>(float)$data['variableSpent'],
'remaining'=>(float)$data['expected_end']
],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script>
<?php endif; ?>
</div>
</main></div>
<script src="/assets/budget-dashboard.js?v=2" defer></script>
</body></html>

<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/layout.php';
require_once __DIR__.'/../includes/reports.php';
$user=require_family_access();$pdo=db();
$scope=finance_scope($pdo,$user);
$selected=selected_month();
$mode=(string)$scope['mode'];
$view=(string)($_GET['visao']??'mine');
if($mode==='shared')$view='family';
elseif(!in_array($view,['mine','shared'],true))$view='mine';
$series=bemora_report_series($pdo,$selected,$scope,$view);
$current=end($series);$previous=$series[count($series)-2];
$trend=static fn(float $a,float $b):string=>$b==0?($a>0?'Novo':'—'):number_format(100*($a-$b)/abs($b),1,',','.').'%';
$monthLabels=['jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'];
$displayMonth=static fn(string $m):string=>$monthLabels[(int)substr($m,5,2)-1].'/'.substr($m,2,2);
$totals=['income'=>0.0,'bills'=>0.0,'expenses'=>0.0,'balance'=>0.0];foreach($series as $item)foreach($totals as $k=>$_)$totals[$k]+=$item[$k];
$lastSix=array_slice($series,-6);
$csrf=csrf_token();
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Relatórios • Bemora</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"><link rel="stylesheet" href="/assets/style.css"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"><link rel="stylesheet" href="/assets/bemora.css?v=2"><link rel="stylesheet" href="/assets/bemora-finance.css?v=2"><link rel="stylesheet" href="/assets/bemora-finish.css?v=3"><?php brand_head_tags(); ?><link rel="stylesheet" href="/assets/bemora-sidebar.css?v=3"><link rel="stylesheet" href="/assets/bemora-reports.css?v=1"></head>
<body class="bemora-finance"><div class="shell ref-shell"><?php render_sidebar('relatorios',$csrf); ?><main><?php render_topbar($user); ?><div class="reports-wrap">
<header class="reports-top"><div><p class="eyebrow">INTELIGÊNCIA FINANCEIRA</p><h1>Relatórios</h1><p>Acompanhe a evolução das receitas, compromissos e do saldo projetado mês a mês.</p></div><a href="/" class="reports-back"><i class="bi bi-grid-1x2"></i> Voltar ao painel</a></header>
<div class="reports-toolbar"><div class="reports-period"><i class="bi bi-calendar3"></i><span>Últimos 12 meses até <?= e($displayMonth($selected)) ?></span></div>
<?php if($mode==='separate'): ?><nav class="reports-segments" aria-label="Escopo do relatório"><a href="/relatorios?mes=<?= e($selected) ?>&amp;visao=mine" class="<?= $view==='mine'?'is-active':'' ?>"><i class="bi bi-person"></i> Minha visão</a><a href="/relatorios?mes=<?= e($selected) ?>&amp;visao=shared" class="<?= $view==='shared'?'is-active':'' ?>"><i class="bi bi-people"></i> Compartilhado</a></nav><?php else: ?><span class="reports-family-label"><i class="bi bi-people"></i> Visão da família</span><?php endif; ?></div>
<?php if($mode==='separate'): ?><div class="reports-privacy"><i class="bi bi-shield-lock"></i> <?= $view==='mine'?'Esta visão mostra seus registros e os itens compartilhados aos quais você tem acesso.':'Esta visão considera somente registros compartilhados da família, sem revelar informações pessoais dos demais membros.' ?></div><?php endif; ?>
<section class="reports-kpis">
<?php foreach([['income','Receitas previstas','bi-arrow-down-left'],['bills','Contas previstas','bi-receipt'],['expenses','Gastos avulsos','bi-arrow-up-right'],['balance','Saldo projetado','bi-wallet2']] as [$key,$title,$icon]): ?>
<article class="reports-kpi"><div class="reports-kpi-label"><i class="bi <?= e($icon) ?>"></i> <?= e($title) ?></div><strong><?= money($current[$key]) ?></strong><span class="reports-change">Mês anterior: <?= money($previous[$key]) ?> · <?= e($trend($current[$key],$previous[$key])) ?></span></article>
<?php endforeach; ?>
</section>
<section class="reports-main-grid">
<article class="reports-card reports-trend"><div class="reports-card-title"><div><h2>Evolução financeira</h2><p>Receitas, despesas previstas e saldo estimado nos últimos 12 meses.</p></div><span class="reports-tag">12 meses</span></div>
<div class="reports-chart-key"><span><i class="key-income"></i> Receitas</span><span><i class="key-costs"></i> Contas + gastos</span><span><i class="key-balance"></i> Saldo</span></div>
<div class="reports-chart-shell"><svg id="reports-trend-chart" viewBox="0 0 760 310" role="img" aria-label="Gráfico de evolução mensal das receitas, custos e saldo" preserveAspectRatio="xMidYMid meet"></svg></div></article>
<article class="reports-card reports-composition"><div class="reports-card-title"><div><h2>Composição do mês</h2><p>Distribuição da renda prevista selecionada.</p></div></div>
<div class="reports-donut" id="reports-donut" role="img" aria-label="Composição das despesas e do saldo"><div><small>Saldo projetado</small><strong><?= money($current['balance']) ?></strong></div></div>
<div class="reports-donut-legend"><div><i class="key-bills"></i> Contas previstas <strong><?= money($current['bills']) ?></strong></div><div><i class="key-expenses"></i> Gastos avulsos <strong><?= money($current['expenses']) ?></strong></div><div><i class="key-balance"></i> Saldo <strong><?= money($current['balance']) ?></strong></div></div>
</article></section>
<section class="reports-card reports-history"><div class="reports-card-title"><div><h2>Comparativo mês a mês</h2><p>Confira a diferença entre meses, inclusive períodos anteriores.</p></div></div>
<div class="reports-table-scroll"><table class="reports-table"><thead><tr><th>Mês</th><th>Receitas</th><th>Contas</th><th>Gastos</th><th>Saldo previsto</th><th>Variação do saldo</th></tr></thead><tbody>
<?php foreach(array_reverse($series) as $idx=>$row): $priorIndex=array_search($row['month'],array_column($series,'month'),true)-1;$prior=$priorIndex>=0?$series[$priorIndex]:null; ?>
<tr><th><?= e($displayMonth($row['month'])) ?></th><td><?= money($row['income']) ?></td><td><?= money($row['bills']) ?></td><td><?= money($row['expenses']) ?></td><td class="<?= $row['balance']<0?'reports-negative':'reports-positive' ?>"><?= money($row['balance']) ?></td><td><?= $prior?e($trend($row['balance'],$prior['balance'])):'—' ?></td></tr>
<?php endforeach; ?></tbody></table></div></section>
<p class="reports-footnote"><i class="bi bi-info-circle"></i> Os valores são projeções baseadas nas rendas cadastradas, lançamentos e contas previstas de cada período. Não representam extratos bancários nem comprovam recebimentos. Mudanças nas fontes de renda recorrentes também alteram as projeções de meses passados.</p>
<script id="reports-data" type="application/json"><?= json_encode($series,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
</div></main></div><script src="/assets/bemora-reports.js?v=1" defer></script></body></html>
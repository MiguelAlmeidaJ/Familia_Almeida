<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/monthly_budget.php';
require_once __DIR__ . '/includes/layout.php';
$user=require_auth();$pdo=db();$month=valid_month($_GET['month'] ?? null);
$ready=monthly_budget_ready($pdo);$data=$ready?monthly_budget_data($pdo,$month):null;
$flash=pull_flash();$csrf=csrf_token();
$date=new DateTimeImmutable($month.'-01');
$previous=$date->modify('-1 month')->format('Y-m');
$next=$date->modify('+1 month')->format('Y-m');
$months=[1=>'janeiro',2=>'fevereiro',3=>'março',4=>'abril',5=>'maio',6=>'junho',7=>'julho',8=>'agosto',9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro'];
$label=ucfirst($months[(int)$date->format('n')]).' de '.$date->format('Y');
function budget_percent(float $used,float $limit):float{return $limit>0?min(100,max(0,$used/$limit*100)):0;}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Fluxo mensal • Família Almeida</title><link rel="stylesheet" href="/assets/style.css">
<style>
.budget-wrap{max-width:1230px;margin:auto;padding:28px 24px 70px}
.budget-title{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:25px}
.budget-title h1{font-size:clamp(26px,3vw,40px);margin:4px 0}.budget-title p{color:#64748b;margin:0}
.budget-month{display:flex;align-items:center;gap:14px}.budget-month a{font-size:24px;text-decoration:none}
.budget-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.budget-card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:22px;min-width:0}
.budget-card small{display:block;color:#64748b;margin-bottom:12px}.budget-card strong{display:block;font-size:clamp(20px,2vw,29px);overflow-wrap:anywhere}
.budget-highlight{background:#123b32;color:#fff}.budget-highlight small{color:#c4dfd3}
.budget-panels{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:18px}
.budget-panel h2{font-size:19px;margin:0 0 6px}.budget-panel p{font-size:13px;color:#64748b;margin:0 0 16px}
.budget-item{display:flex;justify-content:space-between;gap:14px;align-items:center;padding:12px 0;border-bottom:1px solid #edf2f7}
.budget-item:last-child{border-bottom:0}.budget-item span{font-size:14px}.budget-item small{margin:3px 0 0;color:#64748b;display:block}
.budget-item strong{white-space:nowrap}.budget-form{display:flex;gap:9px;flex-wrap:wrap;margin-top:18px;align-items:end}
.budget-form label{font-size:12px;font-weight:600;flex:1;min-width:115px}.budget-form input,.budget-form select{width:100%;border:1px solid #cbd5e1;border-radius:9px;padding:11px;background:white;margin-top:5px;color:#1e293b}
.budget-form button,.budget-button{background:#145c48;color:white;border:0;border-radius:9px;padding:12px 15px;font-weight:600;cursor:pointer}
.budget-remove{border:0;background:transparent;color:#9f3131;cursor:pointer;font-size:12px;padding:5px}
.budget-meter{height:9px;background:#e2e8f0;border-radius:10px;overflow:hidden;margin:8px 0}
.budget-meter div{height:100%;background:#2d9976}.budget-note{color:#64748b;font-size:13px;margin-top:12px}
.budget-warning{color:#9a3412}.budget-actions{margin-top:18px}.budget-actions a{display:inline-block;color:#145c48;font-weight:650}
.budget-alert{border:1px solid #f4c77c;background:#fff8e8;padding:15px;border-radius:12px;margin-bottom:18px}
@media(max-width:1050px){.budget-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:720px){.budget-panels,.budget-grid{grid-template-columns:1fr}.budget-wrap{padding:20px 14px 55px}}
</style></head><body><div class="shell ref-shell"><?php render_sidebar('dashboard',$csrf); ?><main><?php render_topbar($user); ?>
<div class="budget-wrap">
<header class="budget-title"><div><p class="eyebrow">PLANEJAMENTO DA FAMÍLIA</p><h1>Fluxo mensal</h1><p>Quanto vamos receber, gastar e reservar neste mês.</p></div>
<nav class="budget-month" aria-label="Escolher mês"><a href="?month=<?= e($previous) ?>" aria-label="Mês anterior">‹</a><strong><?= e($label) ?></strong><a href="?month=<?= e($next) ?>" aria-label="Próximo mês">›</a></nav></header>
<?php if($flash): ?><div class="budget-alert"><?= e($flash['message']) ?></div><?php endif; ?>
<?php if(!$ready): ?><div class="budget-alert"><strong>Atualização necessária</strong><p>Execute a migration de orçamento mensal em <a href="/configuracoes/manutencao">Configurações → Manutenção</a> para ativar esta tela.</p></div>
<?php else: ?>
<section class="budget-grid" aria-label="Resumo da previsão">
<div class="budget-card"><small>Receitas previstas</small><strong><?= money($data['incomeTotal']) ?></strong></div>
<div class="budget-card"><small>Contas do mês</small><strong><?= money($data['billTotal']) ?></strong></div>
<div class="budget-card budget-highlight"><small>Disponível após contas</small><strong><?= money($data['after_bills']) ?></strong></div>
<div class="budget-card"><small>Ainda sem destino</small><strong><?= money($data['unallocated']) ?></strong></div>
</section>
<?php if($data['missingBills']): ?><p class="budget-note budget-warning">Atenção: <?= (int)$data['missingBills'] ?> conta(s) sem valor definido. A previsão pode estar incompleta.</p><?php endif; ?>
<?php if($data['unallocated']<0): ?><p class="budget-note budget-warning">O total reservado ultrapassa o dinheiro disponível depois das contas. Ajuste as categorias ou receitas.</p><?php endif; ?>
<section class="budget-panels">
<article class="budget-card budget-panel"><h2>Ganhos mensais</h2><p>Receitas recorrentes entram automaticamente na previsão de todos os meses.</p>
<?php foreach($data['incomes'] as $income): ?><div class="budget-item"><span><?= e($income['name']) ?></span><div><strong><?= money($income['amount']) ?></strong><form method="post" action="/budget_action.php" style="display:inline"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="month" value="<?= e($month) ?>"><input type="hidden" name="action" value="remove_income"><input type="hidden" name="id" value="<?= (int)$income['id'] ?>"><button class="budget-remove" title="Desativar receita">Remover</button></form></div></div><?php endforeach; ?>
<form class="budget-form" method="post" action="/budget_action.php"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="month" value="<?= e($month) ?>"><input type="hidden" name="action" value="save_income"><label>Receita<input name="name" placeholder="Ex.: Salário" maxlength="160" required></label><label>Valor mensal<input name="amount" type="number" min=".01" step=".01" placeholder="0,00" required></label><button type="submit">Adicionar</button></form>
</article>
<article class="budget-card budget-panel"><h2>Contas previstas</h2><p>Entram na previsão independentemente de marcar como pagas. Contas parceladas respeitam seu período.</p>
<?php foreach($data['bills'] as $bill): ?><div class="budget-item"><span><?= e($bill['name']) ?><small>Dia <?= (int)$bill['due_day'] ?><?= $bill['needs_amount']?' · valor não informado':'' ?></small></span><div><strong><?= $bill['needs_amount']?'—':money($bill['amount']) ?></strong><form method="post" action="/budget_action.php" style="display:inline"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="month" value="<?= e($month) ?>"><input type="hidden" name="action" value="remove_bill"><input type="hidden" name="id" value="<?= (int)$bill['id'] ?>"><button class="budget-remove" title="Desativar conta">Remover</button></form></div></div><?php endforeach; ?>
<form class="budget-form" method="post" action="/budget_action.php"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="month" value="<?= e($month) ?>"><input type="hidden" name="action" value="save_bill"><label>Conta<input name="name" placeholder="Ex.: Internet" maxlength="120" required></label><label>Valor<input name="amount" type="number" min=".01" step=".01" required></label><label>Dia<input name="due_day" type="number" min="1" max="31" required></label><button type="submit">Adicionar</button></form>
<p class="budget-note">Para ajustar valores variáveis e parcelas, <a href="/contas?month=<?= e($month) ?>">edite as contas</a>.</p>
</article></section>
<section class="budget-panels">
<article class="budget-card budget-panel"><h2>Para onde vai o restante?</h2><p>Distribua o dinheiro após as contas. Cada valor vale apenas para <?= e($label) ?>.</p>
<?php foreach($data['allocations'] as $allocation):
$category=(string)$allocation['category'];$limit=(float)$allocation['amount'];$used=(float)($data['spent'][mb_strtolower(trim($category),'UTF-8')]??0);
?><div class="budget-item"><div style="flex:1"><strong><?= e($category) ?></strong><small><?= money($used) ?> gasto(s) de <?= money($limit) ?> destinados</small><div class="budget-meter"><div style="width:<?= budget_percent($used,$limit) ?>%"></div></div></div><form method="post" action="/budget_action.php"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="month" value="<?= e($month) ?>"><input type="hidden" name="action" value="remove_allocation"><input type="hidden" name="category" value="<?= e($category) ?>"><button class="budget-remove" title="Remover categoria">×</button></form></div><?php endforeach; ?>
<form class="budget-form" method="post" action="/budget_action.php"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="month" value="<?= e($month) ?>"><input type="hidden" name="action" value="save_allocation"><label>Destino<input name="category" list="budget-categories" placeholder="Mercado, Farmácia..." maxlength="100" required><datalist id="budget-categories"><option value="Mercado"><option value="Farmácia"><option value="Lazer"><option value="Transporte"><option value="Pet"><option value="Outros"></datalist></label><label>Valor destinado<input name="amount" type="number" min="0" step=".01" required></label><button type="submit">Destinar / atualizar</button></form>
<p class="budget-note">Total destinado: <strong><?= money($data['allocated']) ?></strong>. Livre para distribuir: <strong><?= money($data['unallocated']) ?></strong>.</p></article>
<article class="budget-card budget-panel"><h2>Gastos do mês</h2><p>Os gastos lançados reduzem o saldo previsto e consomem os valores destinados por categoria.</p>
<div class="budget-item"><span>Gastos avulsos registrados</span><strong><?= money($data['variableSpent']) ?></strong></div>
<div class="budget-item"><span>Saldo projetado após gastos registrados</span><strong><?= money($data['expected_end']) ?></strong></div>
<form class="budget-form" method="post" action="/acao"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="return_to" value="/"><input type="hidden" name="month" value="<?= e($month) ?>"><input type="hidden" name="action" value="add_transaction"><input type="hidden" name="type" value="expense"><label>Descrição<input name="description" required placeholder="Ex.: Compras da semana"></label><label>Categoria<input name="category" list="budget-categories" required placeholder="Mercado"></label><label>Valor<input name="amount" type="number" min=".01" step=".01" required></label><label>Data<input name="date" type="date" value="<?= e(date('Y-m-d')) ?>" required></label><button type="submit">Registrar gasto</button></form>
<div class="budget-actions"><a href="/movimentacoes?month=<?= e($month) ?>">Ver todos os ganhos e gastos →</a></div>
<p class="budget-note">Previsão não é saldo bancário: contas são consideradas no mês mesmo sem confirmação de pagamento. Lançamentos de contas já vinculados não são descontados outra vez.</p>
</article></section>
<?php endif; ?></div></main></div></body></html>

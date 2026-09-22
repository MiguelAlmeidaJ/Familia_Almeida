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
?>
<!doctype html>
<html lang="pt-BR">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Contas fixas • Família Almeida</title><link rel="stylesheet" href="/assets/style.css"></head>
<body>
<div class="shell">
<?php render_sidebar('contas', $csrf); ?>
<main>
<?php render_topbar($user); ?>
<div class="content">
<?php page_header('ORGANIZAÇÃO MENSAL', 'Contas fixas', 'Controle vencimentos e marque o que já foi pago em cada mês.'); ?>
<?php if ($flash): ?><div class="alert <?= $flash['type'] === 'success' ? 'success' : '' ?>"><?= e($flash['message']) ?></div><?php endif; ?>

<div class="split page-split">
<section class="card">
<p class="eyebrow">NOVA CONTA</p><h2>Adicionar conta fixa</h2>
<form method="post" action="/acao" class="stack-form">
<input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="add_bill"><input type="hidden" name="month" value="<?= e($month) ?>">
<label>Nome<input name="name" maxlength="120" required></label>
<div class="form-grid-2"><label>Valor<input type="number" name="amount" min="0" step="0.01" value="0" required></label><label>Dia do vencimento<input type="number" name="due_day" min="1" max="31" value="10" required></label></div>
<button class="primary" type="submit">Adicionar conta</button>
</form>
</section>
<section class="card">
<p class="eyebrow">MÊS DE REFERÊNCIA</p><h2><?= e($month) ?></h2>
<form method="get" class="stack-form compact-form"><label>Alterar mês<input type="month" name="month" value="<?= e($month) ?>"></label><button class="secondary" type="submit">Carregar mês</button></form>
</section>
</div>

<section class="card recent">
<div class="card-head"><div><p class="eyebrow">CONTAS CADASTRADAS</p><h2><?= count($data['bills']) ?> contas fixas</h2></div></div>
<div class="bill-list">
<?php foreach ($data['bills'] as $bill): ?>
<div class="bill bill-page">
<form method="post" action="/acao">
<input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="toggle_bill"><input type="hidden" name="month" value="<?= e($month) ?>"><input type="hidden" name="bill_id" value="<?= (int) $bill['id'] ?>"><input type="hidden" name="paid" value="<?= $bill['paid'] ? '0' : '1' ?>">
<button class="check <?= $bill['paid'] ? 'done' : '' ?>" type="submit"><?= $bill['paid'] ? '✓' : '' ?></button>
</form>
<div class="due"><small>DIA</small><strong><?= str_pad((string) $bill['due_day'], 2, '0', STR_PAD_LEFT) ?></strong></div>
<div class="billinfo"><strong><?= e($bill['name']) ?></strong><span><?= $bill['paid'] ? 'Pago neste mês' : 'Pendente neste mês' ?></span></div>
<div class="billamt"><strong><?= money($bill['amount']) ?></strong></div>
</div>
<?php endforeach; ?>
</div>
</section>
</div>
</main>
</div>
</body>
</html>

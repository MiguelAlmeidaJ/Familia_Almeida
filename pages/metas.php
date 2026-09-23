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
$sidebarSection = (($_GET['section'] ?? '') === 'investment') ? 'investimentos' : 'metas';
function goal_percent(float $spent, float $limit): float { return $limit > 0 ? min(100, ($spent / $limit) * 100) : 0; }
?>
<!doctype html>
<html lang="pt-BR">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Metas • Família Almeida</title><link rel="stylesheet" href="/assets/style.css"></head>
<body>
<div class="shell">
<?php render_sidebar($sidebarSection, $csrf); ?>
<main>
<?php render_topbar($user); ?>
<div class="content">
<?php page_header('LIMITES E PRIORIDADES', 'Metas financeiras', 'Defina limites de gastos e a meta mensal de investimento.'); ?>
<?php if ($flash): ?><div class="alert <?= $flash['type'] === 'success' ? 'success' : '' ?>"><?= e($flash['message']) ?></div><?php endif; ?>

<div class="split equal">
<section class="card">
<p class="eyebrow">NOVA META</p><h2>Categoria de gasto</h2>
<form method="post" action="/acao" class="stack-form">
<input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="return_to" value="/metas"><input type="hidden" name="action" value="add_goal"><input type="hidden" name="month" value="<?= e($month) ?>">
<label>Categoria<input name="category" maxlength="100" required></label>
<label>Limite mensal<input type="number" name="limit" min="0" step="0.01" required></label>
<button class="primary" type="submit">Salvar meta</button>
</form>
</section>
<section class="card investment" id="investimento">
<p class="eyebrow">INVESTIMENTO</p><h2>Meta mensal</h2>
<div class="goal-number"><strong><?= money($data['totals']['investment']) ?></strong><span>de <?= money($data['investmentGoal']) ?></span></div>
<div class="progress"><span style="width:<?= goal_percent($data['totals']['investment'], $data['investmentGoal']) ?>%"></span></div>
<form method="post" action="/acao" class="inline-edit">
<input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="return_to" value="/metas"><input type="hidden" name="action" value="set_investment_goal"><input type="hidden" name="month" value="<?= e($month) ?>">
<input type="number" name="value" min="0" step="0.01" value="<?= e($data['investmentGoal']) ?>"><button class="lightbtn" type="submit">Salvar meta</button>
</form>
</section>
</div>

<section class="card recent">
<div class="card-head"><div><p class="eyebrow">CATEGORIAS</p><h2>Limites do mês</h2></div></div>
<div class="goal-list">
<?php foreach ($data['goals'] as $goal): ?>
<form method="post" action="/acao" class="goal goal-edit">
<input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="return_to" value="/metas"><input type="hidden" name="action" value="update_goal"><input type="hidden" name="month" value="<?= e($month) ?>"><input type="hidden" name="goal_id" value="<?= (int) $goal['id'] ?>">
<div>
<div class="form-grid-2 compact-fields"><label>Categoria<input name="category" value="<?= e($goal['category']) ?>" required></label><label>Limite<input type="number" name="limit" min="0" step="0.01" value="<?= e($goal['monthly_limit']) ?>" required></label></div>
<span><?= money($goal['spent']) ?> gastos neste mês</span>
<div class="progress"><span style="width:<?= goal_percent($goal['spent'], $goal['monthly_limit']) ?>%"></span></div>
</div>
<button class="secondary" type="submit">Atualizar</button>
</form>
<?php endforeach; ?>
</div>
</section>
</div>
</main>
</div>
</body>
</html>

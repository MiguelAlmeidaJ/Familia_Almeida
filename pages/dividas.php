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
$defaultDate = $month === date('Y-m') ? date('Y-m-d') : $month . '-01';
?>
<!doctype html>
<html lang="pt-BR">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dívidas • Família Almeida</title><link rel="stylesheet" href="/assets/style.css"></head>
<body>
<div class="shell">
<?php render_sidebar('dividas', $csrf); ?>
<main>
<?php render_topbar($user); ?>
<div class="content">
<?php page_header('PLANO DE QUITAÇÃO', 'Dívidas', 'Acompanhe o saldo pendente e registre pagamentos.'); ?>
<?php if ($flash): ?><div class="alert <?= $flash['type'] === 'success' ? 'success' : '' ?>"><?= e($flash['message']) ?></div><?php endif; ?>

<section class="card narrow-card">
<p class="eyebrow">NOVA DÍVIDA</p><h2>Adicionar pendência</h2>
<form method="post" action="/acao" class="stack-form">
<input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="return_to" value="/dividas"><input type="hidden" name="action" value="add_debt"><input type="hidden" name="month" value="<?= e($month) ?>">
<label>Nome<input name="name" maxlength="160" required></label>
<div class="form-grid-2"><label>Valor total<input type="number" name="total" min="0.01" step="0.01" required></label><label>Já pago<input type="number" name="paid" min="0" step="0.01" value="0" required></label></div>
<button class="primary" type="submit">Adicionar dívida</button>
</form>
</section>

<div class="debt-grid">
<?php foreach ($data['debts'] as $debt): $remaining = max(0, $debt['total_amount'] - $debt['paid_amount']); ?>
<section class="card debt-card">
<div class="card-head"><div><p class="eyebrow">DÍVIDA</p><h2><?= e($debt['name']) ?></h2></div><strong><?= money($remaining) ?></strong></div>
<p class="muted-copy">Pago: <?= money($debt['paid_amount']) ?> de <?= money($debt['total_amount']) ?></p>
<div class="progress"><span style="width:<?= $debt['total_amount'] > 0 ? min(100, ($debt['paid_amount'] / $debt['total_amount']) * 100) : 0 ?>%"></span></div>
<?php if ($remaining > 0): ?>
<form method="post" action="/acao" class="stack-form compact-form">
<input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="return_to" value="/dividas"><input type="hidden" name="action" value="pay_debt"><input type="hidden" name="month" value="<?= e($month) ?>"><input type="hidden" name="debt_id" value="<?= (int) $debt['id'] ?>">
<div class="form-grid-2"><label>Pagamento<input type="number" name="amount" min="0.01" max="<?= e($remaining) ?>" step="0.01" required></label><label>Data<input type="date" name="date" value="<?= e($defaultDate) ?>" required></label></div>
<button class="secondary" type="submit">Registrar pagamento</button>
</form>
<?php endif; ?>
<form method="post" action="/acao" onsubmit="return confirm('Remover esta dívida?')">
<input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="return_to" value="/dividas"><input type="hidden" name="action" value="delete_debt"><input type="hidden" name="month" value="<?= e($month) ?>"><input type="hidden" name="debt_id" value="<?= (int) $debt['id'] ?>">
<button class="text-danger" type="submit">Excluir dívida</button>
</form>
</section>
<?php endforeach; ?>
<?php if (!$data['debts']): ?><section class="card empty"><div class="bubble">◇</div><strong>Nenhuma dívida cadastrada.</strong></section><?php endif; ?>
</div>
</div>
</main>
</div>
</body>
</html>

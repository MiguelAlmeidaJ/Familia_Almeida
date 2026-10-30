<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/categories.php';
$user=require_auth();$pdo=db();$csrf=csrf_token();$flash=pull_flash();
$ready=categories_ready($pdo);$groups=category_list($pdo);
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Categorias • Bemora</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"><link rel="stylesheet" href="/assets/style.css"><link rel="stylesheet" href="/assets/categories.css">
<style>
.category-page{max-width:920px;padding:32px 22px 70px;margin:auto}
.category-page h1{font-size:32px;margin:0 0 8px}.category-page p{color:#607382}
.category-card{background:#fff;border:1px solid #dce6eb;border-radius:16px;padding:22px;margin-top:20px}
.category-form{display:flex;gap:12px;align-items:end;flex-wrap:wrap}
.category-form label{flex:1;min-width:150px;font-size:13px;font-weight:600}.category-form input,.category-form select{display:block;width:100%;margin-top:7px;padding:12px;border:1px solid #ccdbe3;border-radius:10px;background:#fff}
.category-form button{border:0;border-radius:10px;padding:13px 18px;background:#118b70;color:#fff;font-weight:700}
.category-row{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid #edf1f4}
.category-row:last-child{border:0}.category-row button{border:0;background:transparent;color:#b44141;cursor:pointer}
.category-group{display:grid;grid-template-columns:1fr 1fr;gap:18px}
@media(max-width:680px){.category-group{grid-template-columns:1fr}}
</style><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"><link rel="stylesheet" href="/assets/bemora.css?v=2"><link rel="stylesheet" href="/assets/bemora-finance.css?v=2"><link rel="stylesheet" href="/assets/bemora-finish.css?v=3"><?php brand_head_tags(); ?><link rel="stylesheet" href="/assets/bemora-sidebar.css?v=3"><link rel="stylesheet" href="/assets/bemora-categories-refresh.css?v=1"></head><body class="bemora-finance"><div class="shell ref-shell"><?php render_sidebar('categorias',$csrf);?><main><?php render_topbar($user);?>
<div class="category-page"><p class="eyebrow">ORGANIZAÇÃO</p><h1>Categorias</h1>
<p>Organize os tipos de lançamentos da sua família. Categorias também podem ser criadas durante o registro de uma movimentação.</p>

<?php if($flash):?><div class="alert"><?= e($flash['message']) ?></div><?php endif;?>
<?php if(!$ready):?><div class="alert">Execute a migração de categorias em <a href="/configuracoes/manutencao">Configurações → Manutenção</a>.</div><?php else:?>
<section class="category-card card category-create-card"><div class="bemora-category-head"><span class="category-head-icon"><i class="bi bi-plus-circle"></i></span><div><h2>Nova categoria</h2><p>Crie uma categoria para organizar seus próximos lançamentos.</p></div></div>
<form class="category-form" action="/categorias/acao" method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="create">
<label>Nome<input class="form-control" name="name" maxlength="100" placeholder="Ex.: Saúde" required></label>
<label>Tipo<select class="form-select" name="type"><option value="expense">Gasto</option><option value="income">Ganho</option></select></label>
<button class="btn btn-primary" type="submit"><i class="bi bi-plus-lg" aria-hidden="true"></i> Adicionar categoria</button></form></section>
<div class="category-directory-head"><div><h2>Categorias cadastradas</h2><p>Encontre e administre suas categorias de ganhos e gastos.</p></div><label class="category-search"><i class="bi bi-search"></i><input id="bemora-category-search" type="search" placeholder="Buscar categoria..." aria-label="Buscar categoria"></label></div><div class="category-group">
<?php foreach(['expense'=>'Categorias de gastos','income'=>'Categorias de ganhos'] as $type=>$title):?>
<section class="category-card category-list-card"><div class="category-list-heading"><span class="category-list-icon <?= e($type) ?>"><i class="bi <?= $type==='expense'?'bi-arrow-up-right':'bi-arrow-down-left' ?>"></i></span><div><h2><?= e($title) ?></h2><small><?= count($groups[$type]) ?> cadastrada(s)</small></div></div>
<?php if(!$groups[$type]):?><p>Nenhuma categoria cadastrada.</p><?php endif;?>
<?php foreach($groups[$type] as $cat):?>
<div class="category-row" data-category-name="<?= e(mb_strtolower((string)$cat['name'])) ?>"><span><?= e($cat['name']) ?></span>
<form action="/categorias/acao" method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="<?= (int)$cat['id'] ?>"><button type="submit" title="Remover categoria" aria-label="Remover categoria <?= e($cat['name']) ?>" onclick="return confirm('Remover esta categoria? Lançamentos históricos não serão apagados.')"><i class="bi bi-trash3"></i><span>Remover</span></button></form></div>
<?php endforeach;?><p class="category-no-results" hidden>Nenhuma categoria corresponde à busca.</p></section>
<?php endforeach;?></div>
<?php endif;?></div></main></div><script src="/assets/bemora-categories-refresh.js?v=1" defer></script></body></html>
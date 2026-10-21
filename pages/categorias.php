<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/categories.php';
$user=require_auth();$pdo=db();$csrf=csrf_token();$flash=pull_flash();
$ready=categories_ready($pdo);$groups=category_list($pdo);
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Categorias • Família Almeida</title><link rel="stylesheet" href="/assets/style.css"><link rel="stylesheet" href="/assets/categories.css">
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
</style></head><body><div class="shell ref-shell"><?php render_sidebar('categorias',$csrf);?><main><?php render_topbar($user);?>
<div class="category-page"><p class="eyebrow">ORGANIZAÇÃO</p><h1>Categorias</h1>
<p>Cadastre as categorias usadas nos ganhos e gastos. Ao registrar um lançamento, você também poderá criar uma nova diretamente pelo campo de busca.</p>
<a href="/movimentacoes">← Voltar aos lançamentos</a>
<?php if($flash):?><div class="alert"><?= e($flash['message']) ?></div><?php endif;?>
<?php if(!$ready):?><div class="alert">Execute a migração de categorias em <a href="/configuracoes/manutencao">Configurações → Manutenção</a>.</div><?php else:?>
<section class="category-card"><h2>Nova categoria</h2>
<form class="category-form" action="/categorias/acao" method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="create">
<label>Nome<input name="name" maxlength="100" placeholder="Ex.: Saúde" required></label>
<label>Tipo<select name="type"><option value="expense">Gasto</option><option value="income">Ganho</option></select></label>
<button type="submit">Adicionar categoria</button></form></section>
<div class="category-group">
<?php foreach(['expense'=>'Categorias de gastos','income'=>'Categorias de ganhos'] as $type=>$title):?>
<section class="category-card"><h2><?= e($title) ?></h2>
<?php if(!$groups[$type]):?><p>Nenhuma categoria cadastrada.</p><?php endif;?>
<?php foreach($groups[$type] as $cat):?>
<div class="category-row"><span><?= e($cat['name']) ?></span>
<form action="/categorias/acao" method="post"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="<?= (int)$cat['id'] ?>"><button type="submit" title="Remover categoria">Remover</button></form></div>
<?php endforeach;?></section>
<?php endforeach;?></div>
<?php endif;?></div></main></div></body></html>
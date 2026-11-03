<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/layout.php';
require_once __DIR__.'/../includes/market_lists.php';
$user=require_family_access();$pdo=db();$csrf=csrf_token();$flash=pull_flash();$tenant=(int)$user['tenant_id'];
$ready=bemora_market_ready($pdo);$lists=[];$current=null;
if($ready){
 $q=$pdo->prepare('SELECT id,name,status,updated_at FROM bemora_market_lists WHERE tenant_id=? ORDER BY FIELD(status,"shopping","planned","completed"),updated_at DESC LIMIT 80');
 $q->execute([$tenant]);$lists=$q->fetchAll(PDO::FETCH_ASSOC);
 $id=filter_input(INPUT_GET,'lista',FILTER_VALIDATE_INT)?:($lists?(int)$lists[0]['id']:0);
 if($id){try{$current=bemora_market_response($pdo,$tenant,(int)$id);}catch(RuntimeException $ignored){}}
}
$mode=($_GET['modo']??'')==='mercado'?'mercado':'lista';
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Compras · Bemora</title><meta name="referrer" content="same-origin">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="/assets/style.css"><link rel="stylesheet" href="/assets/bemora.css?v=2">
<link rel="stylesheet" href="/assets/bemora-finance.css?v=2"><link rel="stylesheet" href="/assets/bemora-finish.css?v=3">
<?php brand_head_tags(); ?><link rel="stylesheet" href="/assets/bemora-sidebar.css?v=6">
<link rel="stylesheet" href="/assets/bemora-market.css?v=7"></head>
<body class="bemora-finance <?= $mode==='mercado'?'market-shopping-mode':'' ?>"><div class="shell ref-shell"><?php render_sidebar('compras',$csrf); ?><main><?php render_topbar($user); ?>
<div class="content market-page">
<header class="market-heading"><div><p class="eyebrow">COMPRAS EM FAMÍLIA</p><h1>Lista de compras</h1><p>Planeje em casa, confira preços no mercado e acompanhe o total conforme compra.</p></div><span class="market-badge"><i class="bi bi-basket2"></i> Bemora Compras</span></header>
<?php if($flash): ?><div class="market-alert <?= $flash['type']==='success'?'success':'error' ?>" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
<?php if(!$ready): ?><section class="market-panel"><h2>Preparar listas de compras</h2><p>Execute a migration <strong>20261103_041_shopping_lists</strong> no painel Manutenção.</p></section>
<?php else: ?>
<section class="market-panel market-list-picker">
<div><label for="market-list-picker">Lista atual</label><select id="market-list-picker"><option value="">Selecionar lista</option><?php foreach($lists as $list): ?><option value="<?= (int)$list['id'] ?>" <?= $current&&(int)$list['id']===$current['id']?'selected':'' ?>><?= e($list['name']) ?><?= $list['status']==='completed'?' · concluída':'' ?></option><?php endforeach; ?></select></div>
<form method="post" action="/compras/acao"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="create_list"><label for="market-new-list">Nova lista</label><div class="market-inline"><input id="market-new-list" name="name" maxlength="120" minlength="2" placeholder="Ex.: Compra do mês" required><button type="submit"><i class="bi bi-plus"></i> Criar</button></div></form>
</section>
<?php if($current): ?>
<section class="market-overview"><div><small>LISTA EM ANDAMENTO</small><h2><?= e($current['name']) ?></h2><p><span id="market-purchased"><?= $current['purchased'] ?></span> de <span id="market-count"><?= $current['count'] ?></span> produtos conferidos</p></div><div class="market-overview-total"><small>VALOR CONFERIDO</small><strong id="market-total"><?= money($current['total']) ?></strong><span id="market-missing"><?= $current['missing_prices']?'Há produto comprado sem preço':'' ?></span></div></section>
<nav class="market-tabs" aria-label="Modo de compras"><a href="/compras?lista=<?= $current['id'] ?>" class="<?= $mode==='lista'?'active':'' ?>"><i class="bi bi-card-checklist"></i> Planejar lista</a><a href="/compras?lista=<?= $current['id'] ?>&modo=mercado" class="<?= $mode==='mercado'?'active':'' ?>"><i class="bi bi-camera"></i> Estou no mercado</a></nav>
<?php if($mode==='lista'): ?>
<div class="market-layout">
<section class="market-panel"><h2>Produtos</h2><p class="market-muted">O preço pode ser preenchido ao encontrar o produto no mercado.</p>
<button class="market-add-product-open" id="market-open-product" type="button" <?= $current['status']==='completed'?'disabled':'' ?>><i class="bi bi-plus-circle"></i> Adicionar produto</button>
<div class="market-list-items">
<?php if(!$current['items']): ?><div class="market-empty"><i class="bi bi-basket2"></i><strong>Nenhum produto ainda</strong><p>Comece adicionando o que precisa comprar.</p></div><?php endif; ?>
<?php foreach($current['items'] as $item): ?>
<div class="market-product <?= $item['purchased']?'is-done':'' ?>">
<form method="post" action="/compras/acao" class="market-product-form">
<input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="list_id" value="<?= $current['id'] ?>"><input type="hidden" name="item_id" value="<?= $item['id'] ?>"><input type="hidden" name="action" value="update_item">
<label class="market-product-done"><input name="purchased" type="checkbox" value="1" <?= $item['purchased']?'checked':'' ?> <?= $current['status']==='completed'?'disabled':'' ?>><span class="visually-hidden">Comprado</span></label>
<div class="market-product-edit"><input name="name" value="<?= e($item['name']) ?>" maxlength="160" required aria-label="Nome do produto" <?= $current['status']==='completed'?'disabled':'' ?>><div class="market-product-details"><input name="quantity" type="number" min="0.001" step="0.001" value="<?= e($item['quantity']) ?>" aria-label="Quantidade" <?= $current['status']==='completed'?'disabled':'' ?>><select name="unit" aria-label="Unidade"><?php foreach(['un','kg','g','L','ml','pct','cx'] as $unit): ?><option value="<?= e($unit) ?>" <?= $unit===$item['unit']?'selected':'' ?>><?= e($unit) ?></option><?php endforeach; ?></select><input name="price" type="number" min="0.01" step="0.01" placeholder="Preço R$" aria-label="Preço unitário" value="<?= $item['unit_price']!==null?e(number_format($item['unit_price'],2,'.','')):'' ?>" <?= $current['status']==='completed'?'disabled':'' ?>></div></div>
<div class="market-product-action"><strong><?= $item['purchased']&&$item['unit_price']!==null?money($item['quantity']*$item['unit_price']):'—' ?></strong><button type="submit" <?= $current['status']==='completed'?'disabled':'' ?>>Salvar</button></div></form>
<form class="market-remove" method="post" action="/compras/acao" onsubmit="return confirm('Remover produto?')"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="list_id" value="<?= $current['id'] ?>"><input type="hidden" name="item_id" value="<?= $item['id'] ?>"><input type="hidden" name="action" value="remove_item"><button type="submit" aria-label="Remover <?= e($item['name']) ?>" <?= $current['status']==='completed'?'disabled':'' ?>><i class="bi bi-trash"></i></button></form>
</div><?php endforeach; ?></div></section>
<aside class="market-panel market-manage"><h2>Organizar a compra</h2><form method="post" action="/compras/acao"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="list_id" value="<?= $current['id'] ?>"><input type="hidden" name="action" value="set_market"><label>Supermercado<input name="market_name" maxlength="120" value="<?= e($current['market_name']??'') ?>" placeholder="Ex.: Mercado do bairro"></label><button type="submit">Salvar mercado</button></form><form method="post" action="/compras/acao"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="list_id" value="<?= $current['id'] ?>"><input type="hidden" name="action" value="set_status"><label>Situação<select name="status"><?php foreach(['planned'=>'Planejando','shopping'=>'Comprando'] as $status=>$text): ?><option value="<?= e($status) ?>" <?= $current['status']===$status?'selected':'' ?>><?= e($text) ?></option><?php endforeach; ?><?php if($current['status']==='completed'): ?><option selected value="completed">Concluída</option><?php endif; ?></select></label><button type="submit" <?= $current['status']==='completed'?'disabled':'' ?>>Atualizar situação</button></form><p class="market-muted">Esta lista é compartilhada com a família. Ao finalizar no Modo Mercado, o total comprado vira um único gasto de supermercado.</p></aside>
</div>
<?php else: ?>
<div class="market-scanner-layout">
<section class="market-panel market-cart-panel">
<div class="market-cart-top"><div><span class="market-items-kicker">DURANTE AS COMPRAS</span><h2>Seu carrinho</h2><p>Confira seus produtos e valores. Use a câmera para registrar novos preços.</p></div></div>
<section class="market-scan-items" id="market-shopping-items" aria-labelledby="market-shopping-heading">
<div class="market-items-head"><div><span class="market-items-kicker">SEU CARRINHO</span><h3 id="market-shopping-heading">Itens da compra</h3><p>Desfaça uma compra ou retire um produto sem sair do modo mercado.</p></div><span class="market-items-count" id="market-items-count"><?= $current['purchased'] ?>/<?= $current['count'] ?></span></div>
<div class="market-progress-track" role="progressbar" aria-label="Progresso da lista" aria-valuemin="0" aria-valuemax="<?= max(1,$current['count']) ?>" aria-valuenow="<?= $current['purchased'] ?>" id="market-progress-track"><span id="market-progress-fill" style="width:<?= $current['count']?round($current['purchased']/$current['count']*100):0 ?>%"></span></div>
<div class="market-basket-total"><span>Total no carrinho</span><strong id="market-scan-total"><?= money($current['total']) ?></strong></div>
<div id="market-scan-items" class="market-basket-list" aria-live="polite"></div>
<div class="market-load-more" id="market-load-more" hidden>
  <button type="button" id="market-load-more-button">Carregar mais produtos</button>
  <p id="market-load-more-count" aria-live="polite"></p>
</div>
<p class="market-shopping-feedback" id="market-shopping-feedback" role="status" aria-live="polite"></p>
</section>

</section>
</div>
<dialog id="market-scan-dialog" class="market-dialog market-scan-dialog" aria-labelledby="market-scan-heading">
<div class="market-dialog-header"><div><span class="market-items-kicker">MODO MERCADO</span><h2 id="market-scan-heading">Escanear etiqueta</h2></div><button type="button" class="market-dialog-close" id="market-close-camera" aria-label="Fechar câmera"><i class="bi bi-x-lg"></i></button></div>
<div class="market-dialog-body"><div class="market-scanner-modal-grid">
<section class="market-panel"><h2>Escanear etiqueta de preço</h2><p class="market-muted">Centralize o nome e o preço na moldura e toque em capturar. Você poderá corrigir os dados antes de confirmar.</p>
<div class="market-shape-controls" role="group" aria-label="Formato da etiqueta">
<span>Formato da etiqueta</span>
<div class="market-shape-buttons">
<button type="button" class="market-shape active" data-market-shape="wide" aria-pressed="true"><i class="bi bi-aspect-ratio"></i> Horizontal</button>
<button type="button" class="market-shape" data-market-shape="medium" aria-pressed="false"><i class="bi bi-card-text"></i> Padrão</button>
<button type="button" class="market-shape" data-market-shape="square" aria-pressed="false"><i class="bi bi-square"></i> Quadrada</button>
<button type="button" class="market-shape" data-market-shape="tall" aria-pressed="false"><i class="bi bi-phone"></i> Vertical</button>
</div>
<small id="market-shape-hint">A moldura e o recorte se ajustam ao formato escolhido.</small>
</div>
<div class="market-camera" data-shape="wide"><video id="market-video" playsinline muted autoplay></video><div class="market-guides"><div>Centralize a etiqueta aqui</div></div><div class="market-placeholder" id="market-placeholder"><i class="bi bi-camera"></i><strong>Câmera desligada</strong><span>Ative a câmera ou escolha uma imagem.</span></div><canvas id="market-canvas" hidden></canvas></div>
<div class="market-camera-actions"><button type="button" id="market-start">Ativar câmera</button><button type="button" id="market-capture" disabled>Capturar etiqueta</button><button type="button" id="market-stop" disabled>Desligar</button></div>
<p class="market-muted" id="market-ocr-status" role="status" aria-live="polite">A imagem será analisada no navegador. Confira os valores antes de salvar.</p><img id="market-photo-preview" class="market-photo-preview" alt="Apenas a área recortada e analisada pelo OCR" hidden>
</section>

<section class="market-panel market-confirm-panel"><h2>Conferir produto</h2><p class="market-muted">A leitura da etiqueta é uma sugestão. Confirme ou corrija o produto, preço e quantidade.</p>
<form id="market-scan-form">
<label>Produto<input id="market-name" maxlength="160" minlength="2" required placeholder="Ex.: Leite integral 1L"></label>
<div class="market-inline"><label>Preço unitário (R$)<input id="market-price" type="number" step="0.01" min="0.01" required placeholder="0,00"></label><label>Quantidade<input id="market-qty" type="number" step="0.001" min="0.001" value="1" required></label></div>
<label>Unidade<select id="market-unit"><?php foreach(['un'=>'Unidade','kg'=>'kg','g'=>'g','L'=>'L','ml'=>'ml','pct'=>'Pacote','cx'=>'Caixa'] as $unit=>$label): ?><option value="<?= e($unit) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
<div class="market-price-options" id="market-price-options" hidden><strong>Encontramos mais de um preço</strong><p>Escolha o valor unitário da sua compra. Preços de atacado podem exigir uma quantidade mínima.</p><div id="market-price-buttons"></div></div>
<label>Produto na lista<select id="market-match"><option value="">Adicionar como novo</option></select></label><small class="market-muted" id="market-match-tip">Se já estiver planejado, selecione-o para registrar a compra e o preço.</small>
<button id="market-save" type="submit" <?= $current['status']==='completed'?'disabled':'' ?>>Confirmar produto e preço</button></form>
</section>
</div></div>
</dialog>
<div class="market-special-actions" id="market-special-actions">
<button type="button" class="market-page-fab market-camera-fab" id="market-open-camera" aria-label="Abrir câmera e escanear etiqueta" title="Escanear etiqueta" <?= $current['status']==='completed'?'disabled':'' ?>><i class="bi bi-camera"></i><span>Escanear</span></button>
<button type="button" class="market-page-fab market-finish-fab" id="market-open-finish" aria-label="Finalizar compra" title="Finalizar compra" <?= $current['status']==='completed'?'disabled':'' ?>><i class="bi bi-check2-circle"></i><span>Finalizar compra</span></button>
</div>
<dialog id="market-finish-dialog" class="market-dialog market-finish-dialog" aria-labelledby="market-finish-heading">
<div class="market-dialog-header"><div><span class="market-items-kicker">CONFERÊNCIA FINAL</span><h2 id="market-finish-heading">Finalizar compra</h2></div><button type="button" class="market-dialog-close" data-close-market-dialog aria-label="Fechar"><i class="bi bi-x-lg"></i></button></div>
<form id="market-finish-form" method="post" action="/compras/acao" class="market-dialog-body market-finish-form">
<input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="list_id" value="<?= $current['id'] ?>"><input type="hidden" name="action" value="finish_purchase">
<p>Ao finalizar, o Bemora registra <strong>um gasto na categoria Supermercado</strong> e encerra esta lista.</p>
<div class="market-finish-total"><span>Total dos produtos comprados</span><strong id="market-finish-total"><?= money($current['total']) ?></strong></div>
<p class="market-muted">Produtos que não foram marcados como comprados ficam fora do total. Após finalizar, a compra não poderá ser editada.</p>
<div class="market-dialog-buttons"><button type="button" class="market-secondary" data-close-market-dialog>Cancelar</button><button type="submit" id="market-finish-confirm">Confirmar e lançar gasto</button></div>
</form>
</dialog>
<?php endif; ?>
<?php else: ?><section class="market-panel market-empty"><i class="bi bi-basket2"></i><h2>Crie sua primeira lista</h2><p>Adicione produtos e quantidades para planejar a próxima compra.</p></section><?php endif; ?>
<?php endif; ?>
</div></main></div>
<?php if($ready&&$current&&$mode==='lista'): ?>
<dialog id="market-product-dialog" class="market-dialog market-product-dialog" aria-labelledby="market-product-heading">
<div class="market-dialog-header"><div><span class="market-items-kicker">PLANEJAR COMPRAS</span><h2 id="market-product-heading">Adicionar produto</h2></div><button class="market-dialog-close" id="market-close-product" type="button" aria-label="Fechar"><i class="bi bi-x-lg"></i></button></div>
<div class="market-dialog-body">
<form id="market-add-product-form" class="market-add market-product-modal-form" method="post" action="/compras/acao"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="list_id" value="<?= $current['id'] ?>"><input type="hidden" name="action" value="add_item">
<label>Nome do produto<input name="name" maxlength="160" minlength="2" placeholder="Ex.: Arroz 5 kg" required <?= $current['status']==='completed'?'disabled':'' ?>></label>
<div class="market-inline"><label>Quantidade<input name="quantity" type="number" min="0.001" step="0.001" value="1" required <?= $current['status']==='completed'?'disabled':'' ?>></label><label>Unidade<select name="unit"><?php foreach(['un'=>'un','kg'=>'kg','g'=>'g','L'=>'L','ml'=>'ml','pct'=>'pacote','cx'=>'caixa'] as $key=>$val): ?><option value="<?= e($key) ?>"><?= e($val) ?></option><?php endforeach; ?></select></label><button type="submit" <?= $current['status']==='completed'?'disabled':'' ?>>Adicionar</button></div>
</form>
<div class="market-dialog-buttons market-product-buttons">
<button type="button" class="market-secondary" id="market-product-cancel">Cancelar</button>
<button type="button" id="market-product-save-close">Salvar e sair</button>
<button type="button" id="market-product-save-continue">Salvar e continuar adicionando</button>
</div>
<p class="market-dialog-feedback" id="market-product-feedback" role="status" aria-live="polite"></p>
</div>
</dialog>
<?php endif; ?>
<script>window.BEMORA_MARKET=<?= json_encode(['csrf'=>$csrf,'list'=>$current],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR) ?>;</script>
<script src="/assets/bemora-market.js?v=7" defer></script><script src="/assets/bemora-market-popups.js?v=1" defer></script>
</body></html>
<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/admin_layout.php';
require_once __DIR__.'/../includes/single_offer.php';
require_once __DIR__.'/../includes/plan_benefits.php';
require_admin_access();
$pdo=db();$ready=bemora_single_offer_ready($pdo);
$offer=$ready?bemora_single_offer($pdo):null;
$flash=pull_flash();$csrf=csrf_token();
admin_shell_start('plans','Assinatura Bemora','Uma assinatura, com integrantes extras e o módulo de Compras opcionais.');
?>
<link rel="stylesheet" href="/assets/bemora-single.css?v=1">
<div class="single-admin">
<?php if($flash): ?><div class="single-message" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
<?php if(!$ready): ?><div class="single-message">Execute a migration <strong>20261104_045_single_offer</strong> em <a href="/configuracoes/manutencao">Manutenção</a> antes de configurar a assinatura.</div><?php endif; ?>
<section class="single-admin-intro"><div><span class="single-kicker">MODELO DE CONTRATAÇÃO</span><h2>Um plano. Só os adicionais necessários.</h2><p>O cliente começa com um acesso individual. Pode contratar mais integrantes e incluir Compras com scanner no momento da assinatura.</p></div><span class="single-status"><?= $offer&&(int)$offer['enabled']?'Oferta ativa':'Aguardando configuração' ?></span></section>
<form method="post" action="/admin/assinatura/acao" id="single-admin-form" class="single-form">
<input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
<section class="single-panel"><div class="single-panel-title"><span>01</span><div><h3>Assinatura principal</h3><p>O preço de entrada inclui uma pessoa e os recursos financeiros básicos.</p></div></div><div class="single-form-grid">
<label>Nome público<input name="name" maxlength="100" required value="<?= e($offer['name']??'Bemora') ?>"></label>
<label>Preço mensal base (R$)<input name="base_monthly" inputmode="decimal" required value="<?= e(isset($offer['base_monthly'])?number_format((float)$offer['base_monthly'],2,',','.'):'') ?>" placeholder="14,90"></label>
</div></section>
<section class="single-panel"><div class="single-panel-title"><span>02</span><div><h3>Adicionais opcionais</h3><p>Os preços são mensais e se ajustam à periodicidade da cobrança.</p></div></div><div class="single-form-grid">
<label>Valor por integrante extra (R$/mês)<input name="extra_member_monthly" inputmode="decimal" required value="<?= e(isset($offer['extra_member_monthly'])?number_format((float)$offer['extra_member_monthly'],2,',','.'):'') ?>" placeholder="5,00"></label>
<label>Máximo de pessoas por assinatura<input type="number" name="max_members" min="1" max="30" required value="<?= (int)($offer['max_members']??6) ?>"></label>
<label>Compras + scanner (R$/mês)<input name="shopping_monthly" inputmode="decimal" required value="<?= e(isset($offer['shopping_monthly'])?number_format((float)$offer['shopping_monthly'],2,',','.'):'') ?>" placeholder="9,90"></label>
</div><p class="single-note">O adicional de Compras inclui lista, modo mercado e scanner de etiquetas. Sem ele, o assinante mantém apenas os recursos financeiros básicos.</p></section>
<section class="single-panel"><div class="single-panel-title"><span>03</span><div><h3>Periodicidade e descontos</h3><p>O Asaas receberá o valor total do período selecionado pelo cliente.</p></div></div><div class="single-form-grid">
<label>Desconto semestral (%)<input name="semi_discount" type="number" min="0" max="80" step=".01" required value="<?= e((string)($offer['semi_discount']??'0')) ?>"></label>
<label>Desconto anual (%)<input name="annual_discount" type="number" min="0" max="80" step=".01" required value="<?= e((string)($offer['annual_discount']??'0')) ?>"></label>
</div><div class="single-preview" id="single-admin-preview" aria-live="polite"></div></section>
<section class="single-panel single-publish"><div><h3>Disponibilidade</h3><p>Salvar valores não muda o preço recorrente dos contratos já emitidos no Asaas.</p></div>
<label class="single-switch"><input type="checkbox" name="enabled" value="1" <?= $offer&&(int)$offer['enabled']?'checked':'' ?>> Disponibilizar contratação pública</label>
<?php if(!$offer||(int)$offer['enabled']===0): ?><label class="single-confirm">Para substituir o catálogo anterior e publicar esta oferta, digite <strong>SUBSTITUIR PLANOS</strong>:<input name="confirmation" autocomplete="off" placeholder="SUBSTITUIR PLANOS"></label><?php endif; ?>
</section>
<div class="single-actions"><a href="/admin/planos">Consultar histórico de planos</a><button type="submit" <?= !$ready?'disabled':'' ?>>Salvar configuração</button></div>
</form>
</div>
<script src="/assets/bemora-single.js?v=1" defer></script>
<?php admin_shell_end(); ?>

<?php
declare(strict_types=1);
require_once __DIR__.'/includes/asaas.php';
require_once __DIR__.'/includes/legal.php';
require_once __DIR__.'/includes/single_offer.php';
$pdo=db();$ready=false;$plan=null;$error='';$publicToken='';$single=null;$quote=null;
try{
 $ready=bemora_plans_table_exists($pdo) && (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='subscription_orders'")->fetchColumn()===1;
 if($ready){
  $single=bemora_single_offer_enabled($pdo)?bemora_single_offer($pdo):null;
  if($single){
   $cycle=(string)($_GET['ciclo']??'monthly');
   if(!in_array($cycle,['monthly','semiannual','annual'],true))$cycle='monthly';
   $members=filter_var($_GET['integrantes']??1,FILTER_VALIDATE_INT);
   if($members===false)$members=1;
   $shopping=($_GET['compras']??'')==='1';
   $quote=bemora_single_quote($single,$cycle,$members,$shopping);
   $id=$quote['plan_id'];
  }else $id=filter_input(INPUT_GET,'plano',FILTER_VALIDATE_INT);
  $q=$pdo->prepare('SELECT * FROM subscription_plans WHERE id=? AND active=1 AND public_sale=1');
  $q->execute([$id?:0]);$plan=$q->fetch(PDO::FETCH_ASSOC);
 }
}catch(Throwable $e){$error='Checkout temporariamente indisponível.';}
if(!$plan && $error===''){$error='Este plano não está disponível.';}
$promo=$plan?bemora_plan_promo_active($plan):false;
$amount=$quote?(float)$quote['total']:($plan?($promo?(float)$plan['promo_price']:(float)$plan['price']):0);
$cycle=$plan?bemora_plan_cycles()[$plan['billing_cycle']]??'':'';
$legalApproved=bemora_legal_approved();
$commercialRequiresLegal=bemora_asaas_mode()==='production';
if($commercialRequiresLegal&&!$legalApproved)$error='Contratação temporariamente indisponível enquanto os documentos legais são finalizados.';
$csrf=csrf_token();$checkoutError=(string)($_SESSION['checkout_error']??'');unset($_SESSION['checkout_error']);
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="referrer" content="strict-origin-when-cross-origin"><title>Assinar Bemora</title><link rel="icon" href="/assets/brand/favicon.png"><link rel="stylesheet" href="/assets/bemora-checkout.css?v=9"></head><body>
<header class="checkout-header"><a href="/"><img src="/assets/brand/logo.png" alt="Bemora by Anoar"></a><span class="checkout-secure-label">🔒 Checkout seguro</span></header><main class="checkout-main">
<div class="checkout-head"><a href="/#planos">← Voltar aos planos</a><h1>Vamos começar sua nova fase financeira?</h1><p>Confira sua assinatura e informe os dados do responsável pelo pagamento.</p></div>
<?php if($checkoutError): ?><div class="checkout-alert"><?= htmlspecialchars($checkoutError,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
<?php if($error): ?><div class="checkout-alert"><?= htmlspecialchars($error,ENT_QUOTES,'UTF-8') ?></div><?php elseif(!bemora_asaas_enabled()): ?><div class="checkout-alert">As contratações online ainda não estão habilitadas. Entre em contato com o comercial para conhecer o plano.</div><?php else: ?>
<div class="checkout-columns"><section class="checkout-panel"><div class="checkout-section-label">ETAPA 1 DE 2 · IDENTIFICAÇÃO</div><h2>Seus dados</h2><p class="checkout-intro">Precisamos de algumas informações para emitir sua cobrança com segurança.</p><form method="post" action="/checkout/iniciar" id="checkout-form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>"><input type="hidden" name="plan_id" value="<?= (int)$plan['id'] ?>"><input type="hidden" name="quoted_amount" value="<?= htmlspecialchars(number_format($amount,2,'.',''),ENT_QUOTES,'UTF-8') ?>">

<label>Nome completo<input name="name" autocomplete="name" maxlength="120" minlength="3" placeholder="Seu nome completo" required></label>
<label>E-mail<input type="email" name="email" autocomplete="email" maxlength="254" placeholder="voce@exemplo.com.br" required></label>
<div class="checkout-row"><label>CPF ou CNPJ<input id="checkout-document" name="cpf_cnpj" autocomplete="off" inputmode="numeric" maxlength="18" placeholder="000.000.000-00" aria-describedby="checkout-document-help" required><small id="checkout-document-help" class="checkout-field-hint">CPF ou CNPJ do responsável pelo pagamento</small></label><label>Celular com DDD<input id="checkout-phone" name="phone" autocomplete="tel" inputmode="numeric" maxlength="15" placeholder="(00) 90000-0000" aria-describedby="checkout-phone-help" required><small id="checkout-phone-help" class="checkout-field-hint">Para atualizações sobre a assinatura</small></label></div>
<div class="checkout-coupon"><label for="checkout-coupon-code">Tem um cupom?</label><div class="checkout-coupon-entry"><input id="checkout-coupon-code" name="coupon_code" maxlength="32" placeholder="Digite seu código" autocomplete="off"><button id="checkout-coupon-apply" type="button">Aplicar</button></div><p id="checkout-coupon-feedback" aria-live="polite">Aplique o cupom para conferir seu benefício antes de contratar.</p></div><div class="checkout-note"><strong>🔒 Seus dados estão protegidos.</strong> Após continuar, você será encaminhado à cobrança segura do Asaas. Nenhum dado de cartão é solicitado pelo Bemora.</div>
<?php if($legalApproved): ?>
<section class="checkout-legal-consents" aria-labelledby="checkout-legal-title">
<div class="checkout-legal-heading"><span class="checkout-legal-eyebrow">ANTES DO PAGAMENTO</span><h3 id="checkout-legal-title">Documentos e confirmações</h3><p>Abra cada documento para ler e confirmar. As duas etapas são necessárias para continuar.</p></div>
<div class="checkout-legal-items">
<div class="checkout-legal-item" data-consent-item="terms">
<span class="checkout-legal-item-icon" aria-hidden="true">01</span>
<div class="checkout-legal-item-copy"><strong>Termos de uso e contratação</strong><small>Condições do plano, renovação e cancelamento.</small><span class="checkout-legal-status" data-consent-status="terms">Aguardando aceite</span></div>
<button type="button" class="checkout-legal-open" data-open-legal="terms" aria-haspopup="dialog">Ler e aceitar <span aria-hidden="true">→</span></button>
</div>
<div class="checkout-legal-item" data-consent-item="privacy">
<span class="checkout-legal-item-icon" aria-hidden="true">02</span>
<div class="checkout-legal-item-copy"><strong>Política de privacidade</strong><small>Como seus dados são utilizados e protegidos.</small><span class="checkout-legal-status" data-consent-status="privacy">Aguardando ciência</span></div>
<button type="button" class="checkout-legal-open" data-open-legal="privacy" aria-haspopup="dialog">Ler e confirmar <span aria-hidden="true">→</span></button>
</div>
</div>
<p class="checkout-legal-message" id="checkout-legal-message" role="status" aria-live="polite">Confirme os dois documentos para liberar o pagamento.</p>
</section>
<input type="hidden" name="legal_accept" id="checkout-legal-accept" value="">
<input type="hidden" name="terms_accepted" id="checkout-terms-accepted" value="">
<input type="hidden" name="privacy_acknowledged" id="checkout-privacy-acknowledged" value="">
<input type="hidden" name="terms_version" value="<?= e(BEMORA_TERMS_VERSION) ?>">
<input type="hidden" name="privacy_version" value="<?= e(BEMORA_PRIVACY_VERSION) ?>">
<?php else: ?><p class="checkout-small">Ambiente de homologação: documentos legais ainda não publicados. Não utilize dados reais de clientes nesta contratação.</p><?php endif; ?>
<button type="submit" id="checkout-submit" class="checkout-primary">Ir para pagamento seguro <span aria-hidden="true">→</span></button>
<p class="checkout-small">O acesso à família será disponibilizado após a confirmação da contratação conforme as condições do plano e eventuais períodos gratuitos.</p></form></section>
<aside class="checkout-panel checkout-summary"><div class="checkout-summary-heading"><span class="checkout-small-label">SEU PLANO BEMORA</span><?php if($single): ?><button type="button" id="checkout-change-plan" class="checkout-change-plan" aria-controls="checkout-single-options" aria-expanded="false"><span aria-hidden="true">✎</span> Alterar</button><?php endif; ?></div><h2><?= htmlspecialchars($plan['name'],ENT_QUOTES,'UTF-8') ?></h2><p id="checkout-seats-cycle"><?= htmlspecialchars($cycle,ENT_QUOTES,'UTF-8') ?> · <?= $quote?(int)$quote['members']:(int)$plan['max_members'] ?> pessoas</p><?php if($single): ?>
<div class="checkout-single-options" hidden id="checkout-single-options" data-base="<?= e((string)$single['base_monthly']) ?>" data-seat="<?= e((string)$single['extra_member_monthly']) ?>" data-shop="<?= e((string)$single['shopping_monthly']) ?>" data-semi="<?= e((string)$single['semi_discount']) ?>" data-annual="<?= e((string)$single['annual_discount']) ?>">
 <label>Periodicidade<select form="checkout-form" name="cycle" id="checkout-cycle"><?php foreach(bemora_plan_cycles() as $key=>$label): ?><option value="<?= e($key) ?>" data-plan="<?= bemora_single_offer_plan_id($single,$key) ?>" <?= $key===$quote['cycle']?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
 <label>Quantidade de pessoas (incluindo você)<select form="checkout-form" name="members" id="checkout-members"><?php for($n=1;$n<=(int)$single['max_members'];$n++): ?><option value="<?= $n ?>" <?= $n===$quote['members']?'selected':'' ?>><?= $n ?> <?= $n===1?'pessoa':'pessoas' ?></option><?php endfor; ?></select></label>
 <label class="checkout-single-shopping"><input form="checkout-form" type="checkbox" name="shopping_addon" value="1" <?= $quote['shopping']?'checked':'' ?>><span>Incluir Compras + scanner</span></label>
</div>
<?php endif; ?><?php if(!$single): ?><div class="checkout-description"><?= nl2br(htmlspecialchars($plan['details'],ENT_QUOTES,'UTF-8')) ?></div><?php endif; ?>
<div class="checkout-total"><span>Valor da assinatura</span><div><?php if($promo): ?><del><?= htmlspecialchars(bemora_plan_price((float)$plan['price']),ENT_QUOTES,'UTF-8') ?></del><?php endif; ?><strong id="checkout-final-price"><?= htmlspecialchars(bemora_plan_price($amount),ENT_QUOTES,'UTF-8') ?></strong></div></div>
<p id="checkout-coupon-saving" class="checkout-coupon-saving" hidden></p><p class="checkout-small" id="checkout-recurring-note">Cobrança recorrente <?= htmlspecialchars(strtolower($cycle),ENT_QUOTES,'UTF-8') ?> pelo valor indicado. Preço promocional, quando aplicável, será usado como valor recorrente contratado até alteração comunicada das condições.</p></aside></div>
<?php endif; ?>
<footer class="checkout-legal-footer">
<?php if($legalApproved): ?><p>Fornecedor: <strong><?= bemora_legal_label('company_name') ?></strong> · CNPJ <?= bemora_legal_label('company_tax_id') ?> · <?= bemora_legal_label('company_address') ?>. Atendimento: <a href="mailto:<?= bemora_legal_label('support_email') ?>"><?= bemora_legal_label('support_email') ?></a>. Direito de arrependimento e condições de cancelamento disponíveis nos Termos.</p><?php endif; ?>
<a href="/termos" target="_blank" rel="noopener noreferrer">Termos de uso</a><a href="/privacidade" target="_blank" rel="noopener noreferrer">Privacidade</a><a href="/#contato">Atendimento</a>
</footer>
<?php if($legalApproved): ?>
<dialog class="checkout-legal-dialog" id="checkout-legal-dialog" aria-labelledby="checkout-legal-dialog-title" aria-describedby="checkout-legal-dialog-description">
<div class="checkout-legal-dialog-shell">
<div class="checkout-legal-dialog-top">
<div><span class="checkout-legal-eyebrow">BEMORA · DOCUMENTAÇÃO</span><h2 id="checkout-legal-dialog-title">Documento legal</h2><p id="checkout-legal-dialog-description">Leia as informações antes de confirmar.</p></div>
<button type="button" class="checkout-legal-close" id="checkout-legal-close" aria-label="Fechar documento">×</button>
</div>
<iframe class="checkout-legal-frame" id="checkout-legal-frame" title="Conteúdo do documento legal" loading="lazy" referrerpolicy="same-origin"></iframe>
<div class="checkout-legal-dialog-actions">
<a class="checkout-legal-full" id="checkout-legal-full" href="/termos" target="_blank" rel="noopener noreferrer">Abrir em nova aba</a>
<button type="button" class="checkout-legal-back" id="checkout-legal-back">Voltar</button>
<button type="button" class="checkout-legal-confirm" id="checkout-legal-confirm" disabled>Li e aceito os termos</button>
</div>
</div>
</dialog>
<?php endif; ?>
</main><script src="/assets/bemora-checkout.js?v=8" defer></script><script src="/assets/bemora-checkout-legal.js?v=1" defer></script></body></html>
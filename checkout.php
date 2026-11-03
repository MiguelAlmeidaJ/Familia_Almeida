<?php
declare(strict_types=1);
require_once __DIR__.'/includes/asaas.php';
require_once __DIR__.'/includes/legal.php';
$pdo=db();$ready=false;$plan=null;$error='';$publicToken='';
try{
 $ready=bemora_plans_table_exists($pdo) && (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='subscription_orders'")->fetchColumn()===1;
 if($ready){$id=filter_input(INPUT_GET,'plano',FILTER_VALIDATE_INT);$q=$pdo->prepare('SELECT * FROM subscription_plans WHERE id=? AND active=1 AND public_sale=1');$q->execute([$id?:0]);$plan=$q->fetch(PDO::FETCH_ASSOC);}
}catch(Throwable $e){$error='Checkout temporariamente indisponível.';}
if(!$plan && $error===''){$error='Este plano não está disponível.';}
$promo=$plan?bemora_plan_promo_active($plan):false;
$amount=$plan?($promo?(float)$plan['promo_price']:(float)$plan['price']):0;
$cycle=$plan?bemora_plan_cycles()[$plan['billing_cycle']]??'':'';
$legalApproved=bemora_legal_approved();
$commercialRequiresLegal=bemora_asaas_mode()==='production';
if($commercialRequiresLegal&&!$legalApproved)$error='Contratação temporariamente indisponível enquanto os documentos legais são finalizados.';
$csrf=csrf_token();$checkoutError=(string)($_SESSION['checkout_error']??'');unset($_SESSION['checkout_error']);
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="referrer" content="strict-origin-when-cross-origin"><title>Assinar Bemora</title><link rel="icon" href="/assets/brand/favicon.png"><link rel="stylesheet" href="/assets/bemora-checkout.css?v=5"></head><body>
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
<?php if($legalApproved): ?><label class="checkout-legal-accept"><input type="checkbox" name="legal_accept" value="yes" required> Li e concordo com os <a href="/termos" target="_blank" rel="noopener noreferrer">Termos de uso e contratação</a> e estou ciente da <a href="/privacidade" target="_blank" rel="noopener noreferrer">Política de privacidade</a>.</label>
<input type="hidden" name="terms_version" value="<?= e(BEMORA_TERMS_VERSION) ?>">
<input type="hidden" name="privacy_version" value="<?= e(BEMORA_PRIVACY_VERSION) ?>">
<?php else: ?><p class="checkout-small">Ambiente de homologação: documentos legais ainda não publicados. Não utilize dados reais de clientes nesta contratação.</p><?php endif; ?>
<button type="submit" id="checkout-submit" class="checkout-primary">Ir para pagamento seguro <span aria-hidden="true">→</span></button>
<p class="checkout-small">O acesso à família será disponibilizado após a confirmação da contratação conforme as condições do plano e eventuais períodos gratuitos.</p></form></section>
<aside class="checkout-panel checkout-summary"><span class="checkout-small-label">SEU PLANO BEMORA</span><h2><?= htmlspecialchars($plan['name'],ENT_QUOTES,'UTF-8') ?></h2><p><?= htmlspecialchars($cycle,ENT_QUOTES,'UTF-8') ?> · Até <?= (int)$plan['max_members'] ?> <?= (int)$plan['max_members']===1?'membro':'membros' ?></p><div class="checkout-description"><?= nl2br(htmlspecialchars($plan['details'],ENT_QUOTES,'UTF-8')) ?></div>
<div class="checkout-total"><span>Valor da assinatura</span><div><?php if($promo): ?><del><?= htmlspecialchars(bemora_plan_price((float)$plan['price']),ENT_QUOTES,'UTF-8') ?></del><?php endif; ?><strong id="checkout-final-price"><?= htmlspecialchars(bemora_plan_price($amount),ENT_QUOTES,'UTF-8') ?></strong></div></div>
<p id="checkout-coupon-saving" class="checkout-coupon-saving" hidden></p><p class="checkout-small">Cobrança recorrente <?= htmlspecialchars(strtolower($cycle),ENT_QUOTES,'UTF-8') ?> pelo valor indicado. Preço promocional, quando aplicável, será usado como valor recorrente contratado até alteração comunicada das condições.</p></aside></div>
<?php endif; ?>
<footer class="checkout-legal-footer">
<?php if($legalApproved): ?><p>Fornecedor: <strong><?= bemora_legal_label('company_name') ?></strong> · CNPJ <?= bemora_legal_label('company_tax_id') ?> · <?= bemora_legal_label('company_address') ?>. Atendimento: <a href="mailto:<?= bemora_legal_label('support_email') ?>"><?= bemora_legal_label('support_email') ?></a>. Direito de arrependimento e condições de cancelamento disponíveis nos Termos.</p><?php endif; ?>
<a href="/termos" target="_blank" rel="noopener noreferrer">Termos de uso</a><a href="/privacidade" target="_blank" rel="noopener noreferrer">Privacidade</a><a href="/#contato">Atendimento</a>
</footer></main><script src="/assets/bemora-checkout.js?v=5" defer></script></body></html>
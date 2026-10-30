<?php
declare(strict_types=1);
require_once __DIR__.'/includes/asaas.php';
$pdo=db();$ready=false;$plan=null;$error='';$publicToken='';
try{
 $ready=bemora_plans_table_exists($pdo) && (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='subscription_orders'")->fetchColumn()===1;
 if($ready){$id=filter_input(INPUT_GET,'plano',FILTER_VALIDATE_INT);$q=$pdo->prepare('SELECT * FROM subscription_plans WHERE id=? AND active=1');$q->execute([$id?:0]);$plan=$q->fetch(PDO::FETCH_ASSOC);}
}catch(Throwable $e){$error='Checkout temporariamente indisponível.';}
if(!$plan && $error===''){$error='Este plano não está disponível.';}
$promo=$plan?bemora_plan_promo_active($plan):false;
$amount=$plan?($promo?(float)$plan['promo_price']:(float)$plan['price']):0;
$cycle=$plan?bemora_plan_cycles()[$plan['billing_cycle']]??'':'';
$csrf=csrf_token();$checkoutError=(string)($_SESSION['checkout_error']??'');unset($_SESSION['checkout_error']);
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="referrer" content="strict-origin-when-cross-origin"><title>Assinar Bemora</title><link rel="icon" href="/assets/brand/favicon.png"><link rel="stylesheet" href="/assets/bemora-checkout.css?v=1"></head><body>
<header class="checkout-header"><a href="/"><img src="/assets/brand/logo.png" alt="Bemora by Anoar"></a><span>Checkout seguro</span></header><main class="checkout-main">
<div class="checkout-head"><a href="/#planos">← Voltar aos planos</a><h1>Vamos começar sua nova fase financeira?</h1><p>Confira sua assinatura e informe os dados do responsável pelo pagamento.</p></div>
<?php if($checkoutError): ?><div class="checkout-alert"><?= htmlspecialchars($checkoutError,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>
<?php if($error): ?><div class="checkout-alert"><?= htmlspecialchars($error,ENT_QUOTES,'UTF-8') ?></div><?php elseif(!bemora_asaas_enabled()): ?><div class="checkout-alert">As contratações online ainda não estão habilitadas. Entre em contato com o comercial para conhecer o plano.</div><?php else: ?>
<div class="checkout-columns"><section class="checkout-panel"><h2>Dados para a assinatura</h2><form method="post" action="/checkout/iniciar" id="checkout-form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>"><input type="hidden" name="plan_id" value="<?= (int)$plan['id'] ?>"><input type="hidden" name="quoted_amount" value="<?= htmlspecialchars(number_format($amount,2,'.',''),ENT_QUOTES,'UTF-8') ?>">
<label>Nome completo<input name="name" autocomplete="name" maxlength="120" minlength="3" placeholder="Seu nome completo" required></label>
<label>E-mail<input type="email" name="email" autocomplete="email" maxlength="254" placeholder="voce@exemplo.com.br" required></label>
<div class="checkout-row"><label>CPF ou CNPJ<input name="cpf_cnpj" autocomplete="off" inputmode="numeric" maxlength="18" placeholder="Somente números" required></label><label>Celular<input name="phone" autocomplete="tel" inputmode="tel" maxlength="20" placeholder="(00) 90000-0000" required></label></div>
<div class="checkout-note">Após continuar, você será encaminhado à cobrança segura do Asaas. Nenhum dado de cartão é solicitado pelo Bemora.</div>
<button type="submit" id="checkout-submit" class="checkout-primary">Continuar para pagamento ↗</button>
<p class="checkout-small">O acesso à família será disponibilizado somente após a confirmação do pagamento. Ao prosseguir, você concorda com as condições de contratação exibidas.</p></form></section>
<aside class="checkout-panel checkout-summary"><span class="checkout-small-label">RESUMO DA ASSINATURA</span><h2><?= htmlspecialchars($plan['name'],ENT_QUOTES,'UTF-8') ?></h2><p><?= htmlspecialchars($cycle,ENT_QUOTES,'UTF-8') ?> · Até <?= (int)$plan['max_members'] ?> <?= (int)$plan['max_members']===1?'membro':'membros' ?></p><div class="checkout-description"><?= nl2br(htmlspecialchars($plan['details'],ENT_QUOTES,'UTF-8')) ?></div>
<div class="checkout-total"><span>Valor da assinatura</span><div><?php if($promo): ?><del><?= htmlspecialchars(bemora_plan_price((float)$plan['price']),ENT_QUOTES,'UTF-8') ?></del><?php endif; ?><strong><?= htmlspecialchars(bemora_plan_price($amount),ENT_QUOTES,'UTF-8') ?></strong></div></div>
<p class="checkout-small">Cobrança recorrente <?= htmlspecialchars(strtolower($cycle),ENT_QUOTES,'UTF-8') ?> pelo valor indicado. Preço promocional, quando aplicável, será usado como valor recorrente contratado até alteração comunicada das condições.</p></aside></div>
<?php endif; ?>
</main><script src="/assets/bemora-checkout.js?v=1" defer></script></body></html>
<?php
declare(strict_types=1);
require_once __DIR__.'/includes/asaas.php';
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/branding.php';
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
$kind=(string)($_GET['tipo']??'checkout');
$paymentId='';$back='/';$title='Pagamento Bemora';$amount=0.0;$invoice=null;
if($kind==='upgrade'){
 $user=require_family_access();
 $q=db()->prepare("SELECT c.*,p.name target_name,o.asaas_customer_id FROM subscription_change_requests c JOIN subscription_plans p ON p.id=c.to_plan_id JOIN subscription_orders o ON o.id=c.order_id WHERE c.id=? AND c.tenant_id=? AND c.status IN ('awaiting_payment','completed') LIMIT 1");
 $q->execute([(int)($_GET['id']??0),(int)$user['tenant_id']]);$change=$q->fetch(PDO::FETCH_ASSOC);
 if(!$change){http_response_code(404);exit('Pagamento indisponível.');}
 $paymentId=(string)$change['asaas_payment_id'];$amount=(float)$change['payment_amount'];$invoice=$change['invoice_url'];$title='Evolução para '.$change['target_name'];$back='/evoluir-plano';
}else{
 $id=(int)($_SESSION['checkout_order_id']??0);$token=(string)($_SESSION['checkout_order_token']??'');
 $q=db()->prepare('SELECT * FROM subscription_orders WHERE id=? AND public_token_hash=? LIMIT 1');
 $q->execute([$id,hash('sha256',$token)]);$order=$q->fetch(PDO::FETCH_ASSOC);
 if(!$order||!in_array($order['status'],['awaiting_payment','paid','completed'],true)){http_response_code(403);exit('Pedido indisponível.');}
 $paymentId=(string)$order['asaas_payment_id'];$amount=(float)$order['amount'];$invoice=$order['invoice_url'];$title='Assinatura '.$order['plan_name'];$back='/checkout/aguardando';
}
if(!preg_match('/^[A-Za-z0-9_-]{3,100}$/D',$paymentId)){http_response_code(409);exit('Cobrança ainda não disponível. Volte à página anterior e atualize.');}
$error='';$pix=null;$boleto=null;$payment=null;
try{
 $payment=bemora_asaas_request('GET','/payments/'.rawurlencode($paymentId));
 if(abs((float)($payment['value']??-1)-$amount)>.009)throw new RuntimeException('Valor da cobrança divergente. Contate o suporte.');
 if($kind==='upgrade' && ((string)($payment['externalReference']??'')!=='bemora-change-'.$change['id'] || (string)($payment['customer']??'')!==(string)$change['asaas_customer_id'] || !empty($payment['subscription'])))throw new RuntimeException('Cobrança não corresponde ao upgrade.');
 if($kind!=='upgrade' && ((string)($payment['subscription']??'')!==(string)$order['asaas_subscription_id'] || (string)($payment['customer']??'')!==(string)$order['asaas_customer_id']))throw new RuntimeException('Cobrança não corresponde à assinatura.');
 if(in_array((string)($payment['status']??''),['PENDING','OVERDUE'],true)){
  try{$pix=bemora_asaas_request('GET','/payments/'.rawurlencode($paymentId).'/pixQrCode');}catch(Throwable $ignore){}
  try{$boleto=bemora_asaas_request('GET','/payments/'.rawurlencode($paymentId).'/identificationField');}catch(Throwable $ignore){}
 }
}catch(Throwable $e){error_log('Bemora embedded billing lookup failed.');$error='Não foi possível consultar os dados de pagamento. Volte e tente novamente.';}
$paid=$payment && in_array((string)($payment['status']??''),['CONFIRMED','RECEIVED'],true);
$pixImage=(string)($pix['encodedImage']??'');if(strlen($pixImage)>150000||!preg_match('/^[A-Za-z0-9+\/=]+$/D',$pixImage))$pixImage='';
$pixCode=(string)($pix['payload']??'');$line=(string)($boleto['identificationField']??'');
$invoiceSafe=$invoice&&bemora_asaas_invoice_url(['invoiceUrl'=>$invoice])?$invoice:null;
$paymentStatus=(string)($payment['status']??'');$payable=in_array($paymentStatus,['PENDING','OVERDUE'],true);
$dueDate=(string)($payment['dueDate']??'');$dueFormatted=preg_match('/^\d{4}-\d{2}-\d{2}$/D',$dueDate)?date('d/m/Y',strtotime($dueDate)):null;
$pixExpires=(string)($pix['expirationDate']??'');
$paymentLabel=$kind==='upgrade'?'Diferença do upgrade':'Valor da assinatura';
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer"><title>Pagamento seguro • Bemora</title><?php brand_head_tags(); ?><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"><link rel="stylesheet" href="/assets/bemora-payment.css?v=3"></head><body>
<header class="pay-header"><a class="pay-back" href="<?= htmlspecialchars($back,ENT_QUOTES,'UTF-8') ?>">← Voltar</a><a class="pay-brand" href="/" aria-label="Bemora, página inicial"><img src="/assets/brand/logo.png" alt="Bemora"></a><span class="pay-secure"><i class="bi bi-shield-lock" aria-hidden="true"></i> Ambiente seguro</span></header>
<main><section class="pay-intro"><span><?= $kind==='upgrade'?'EVOLUÇÃO DE PLANO':'NOVA ASSINATURA' ?> · PAGAMENTO SEGURO</span><h1>Finalize seu pagamento com tranquilidade.</h1><p>Escolha uma forma de pagamento para <?= htmlspecialchars($title,ENT_QUOTES,'UTF-8') ?>. Pix e boleto ficam aqui no Bemora; cartões são processados com segurança no Asaas.</p></section>
<div class="pay-steps"><span class="done">✓ <?= $kind==='upgrade'?'Plano escolhido':'Dados informados' ?></span><span class="active">2 · Pagamento</span><span>3 · Confirmação</span></div>
<section class="pay-layout"><div class="pay-card pay-main"><div class="pay-section-heading"><div><span class="pay-eyebrow">ESCOLHA SEU PAGAMENTO</span><h2>Como prefere pagar?</h2></div><span class="pay-processor"><i class="bi bi-shield-check" aria-hidden="true"></i> Cobrança protegida</span></div>
<?php if($error): ?><div class="pay-notice" role="alert"><?= htmlspecialchars($error,ENT_QUOTES,'UTF-8') ?></div><a class="pay-button pay-muted" href="<?= htmlspecialchars($back,ENT_QUOTES,'UTF-8') ?>">Voltar e tentar novamente</a>
<?php elseif($paid): ?><div class="pay-success" role="status"><strong>✓ Pagamento confirmado!</strong><p>O Asaas confirmou o recebimento. Consulte a situação da assinatura no Bemora.</p></div><a class="pay-button" href="<?= htmlspecialchars($back,ENT_QUOTES,'UTF-8') ?>">Acompanhar minha assinatura →</a>
<?php elseif(!$payable): ?><div class="pay-notice" role="status">Esta cobrança está com situação <?= htmlspecialchars($paymentStatus?:'indisponível',ENT_QUOTES,'UTF-8') ?> e não pode ser paga por esta tela. Consulte a situação antes de tentar novamente.</div><a class="pay-button" href="<?= htmlspecialchars($back,ENT_QUOTES,'UTF-8') ?>">Consultar situação →</a>
<?php else: ?>
<div class="pay-tabs" role="tablist" aria-label="Forma de pagamento">
<button type="button" role="tab" id="tab-pix" aria-controls="pay-pix" aria-selected="true" data-method="pix" class="selected"><span class="pay-method-icon"><i class="bi bi-qr-code" aria-hidden="true"></i></span><strong>Pix</strong><small>Instantâneo · No Bemora</small><em class="pay-recommended">Recomendado</em></button>
<button type="button" role="tab" id="tab-boleto" aria-controls="pay-boleto" aria-selected="false" tabindex="-1" data-method="boleto"><span class="pay-method-icon"><i class="bi bi-receipt-cutoff" aria-hidden="true"></i></span><strong>Boleto</strong><small>Código no Bemora</small></button>
<button type="button" role="tab" id="tab-credito" aria-controls="pay-credito" aria-selected="false" tabindex="-1" data-method="credito"><span class="pay-method-icon"><i class="bi bi-credit-card-2-front" aria-hidden="true"></i></span><strong>Crédito</strong><small>Seguro pelo Asaas <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></small></button>
<button type="button" role="tab" id="tab-debito" aria-controls="pay-debito" aria-selected="false" tabindex="-1" data-method="debito"><span class="pay-method-icon"><i class="bi bi-credit-card" aria-hidden="true"></i></span><strong>Débito</strong><small>Opções no Asaas <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></small></button></div>
<div id="pay-pix" role="tabpanel" aria-labelledby="tab-pix" class="pay-pane"><div class="pay-pane-heading"><span class="pay-pane-icon"><i class="bi bi-qr-code-scan" aria-hidden="true"></i></span><strong>Pague com Pix</strong><p>Abra o aplicativo do seu banco, escaneie o código ou use o Pix Copia e Cola.</p></div>
<?php if($pixImage): ?><div class="pay-qr-shell"><img class="pay-qr" src="data:image/png;base64,<?= htmlspecialchars($pixImage,ENT_QUOTES,'UTF-8') ?>" alt="QR Code Pix desta cobrança"></div><?php endif; ?>
<?php if($pixCode): ?><label for="pix-code">Código Pix Copia e Cola</label><textarea readonly id="pix-code" rows="3"><?= htmlspecialchars($pixCode,ENT_QUOTES,'UTF-8') ?></textarea><button class="pay-button" type="button" data-copy="pix-code"><i class="bi bi-copy" aria-hidden="true"></i> Copiar código Pix</button>
<?php if($pixExpires): ?><p class="pay-help">Validade informada pelo Asaas: <?= htmlspecialchars($pixExpires,ENT_QUOTES,'UTF-8') ?></p><?php endif; ?>
<?php else: ?><div class="pay-notice">O Pix não está disponível para esta cobrança neste momento.</div><?php endif; ?></div>
<div id="pay-boleto" role="tabpanel" aria-labelledby="tab-boleto" class="pay-pane" hidden><div class="pay-pane-heading"><span class="pay-pane-icon"><i class="bi bi-receipt-cutoff" aria-hidden="true"></i></span><strong>Pague com boleto</strong><p>Copie a linha digitável no seu banco. A confirmação depende da compensação bancária.</p></div>
<?php if($line): ?><label for="boleto-line">Linha digitável do boleto</label><textarea readonly id="boleto-line" rows="3"><?= htmlspecialchars($line,ENT_QUOTES,'UTF-8') ?></textarea><button type="button" class="pay-button" data-copy="boleto-line"><i class="bi bi-copy" aria-hidden="true"></i> Copiar linha digitável</button><?php else: ?><div class="pay-notice">Linha digitável indisponível para esta cobrança.</div><?php endif; ?>
<?php if($invoiceSafe): ?><a class="pay-provider pay-provider-inline" href="<?= htmlspecialchars($invoiceSafe,ENT_QUOTES,'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Visualizar boleto no Asaas ↗</a><?php endif; ?></div>
<div id="pay-credito" role="tabpanel" aria-labelledby="tab-credito" class="pay-pane" hidden><div class="pay-provider-card"><span class="pay-provider-icon"><i class="bi bi-credit-card-2-front" aria-hidden="true"></i></span><strong>Cartão de crédito</strong><p>Você será direcionado à página segura do Asaas para informar seu cartão. O Bemora não coleta nem armazena dados de cartão.</p><?php if($invoiceSafe): ?><a class="pay-button" href="<?= htmlspecialchars($invoiceSafe,ENT_QUOTES,'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Continuar no Asaas ↗</a><?php else: ?><div class="pay-notice">Link de pagamento ainda indisponível.</div><?php endif; ?></div></div>
<div id="pay-debito" role="tabpanel" aria-labelledby="tab-debito" class="pay-pane" hidden><div class="pay-provider-card"><span class="pay-provider-icon"><i class="bi bi-credit-card" aria-hidden="true"></i></span><strong>Cartão de débito</strong><p>A finalização acontece no ambiente seguro do Asaas, quando esta modalidade estiver habilitada para a cobrança. Não recebemos os dados do seu cartão.</p><?php if($invoiceSafe): ?><a class="pay-button" href="<?= htmlspecialchars($invoiceSafe,ENT_QUOTES,'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Ver opções no Asaas ↗</a><?php else: ?><div class="pay-notice">Link de pagamento ainda indisponível.</div><?php endif; ?></div></div>
<div class="pay-after"><span class="pay-wait-icon"><i class="bi bi-clock-history" aria-hidden="true"></i></span><div><strong>Após pagar, o que acontece?</strong><p>O Asaas avisa o Bemora quando confirmar o pagamento. O acesso ou upgrade só é liberado depois dessa confirmação.</p></div></div>
<?php endif; ?></div>
<aside class="pay-card pay-summary"><span class="pay-eyebrow">RESUMO DA COBRANÇA</span><div class="pay-summary-mark"><i class="bi bi-receipt" aria-hidden="true"></i></div><h2><?= htmlspecialchars($title,ENT_QUOTES,'UTF-8') ?></h2><div class="pay-summary-line"><span><?= htmlspecialchars($paymentLabel,ENT_QUOTES,'UTF-8') ?></span><strong>R$ <?= number_format($amount,2,',','.') ?></strong></div><?php if($dueFormatted): ?><div class="pay-summary-line"><span>Vencimento</span><strong><?= htmlspecialchars($dueFormatted,ENT_QUOTES,'UTF-8') ?></strong></div><?php endif; ?><div class="pay-summary-total"><span>Total desta cobrança</span><strong>R$ <?= number_format($amount,2,',','.') ?></strong></div><div class="pay-trust"><strong><i class="bi bi-shield-check" aria-hidden="true"></i> Seus dados estão protegidos</strong><p>A confirmação e os valores vêm do Asaas. Dados de cartão são preenchidos somente no ambiente do provedor.</p></div><a class="pay-status-link" href="<?= htmlspecialchars($back,ENT_QUOTES,'UTF-8') ?>">Consultar situação do pagamento →</a></aside></section>
<p class="pay-footer">Bemora by Anoar · Pagamentos processados pelo Asaas</p></main>
<script src="/assets/bemora-payment.js?v=3" defer></script></body></html>
<?php
declare(strict_types=1);
require_once __DIR__.'/includes/asaas.php';
require_once __DIR__.'/includes/auth.php';
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
$kind=(string)($_GET['tipo']??'checkout');
$paymentId='';$back='/';$title='Pagamento Bemora';$amount=0.0;$invoice=null;
if($kind==='upgrade'){
 $user=require_family_access();
 $q=db()->prepare("SELECT c.*,p.name target_name FROM subscription_change_requests c JOIN subscription_plans p ON p.id=c.to_plan_id WHERE c.id=? AND c.tenant_id=? AND c.status IN ('awaiting_payment','completed') LIMIT 1");
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
 if($kind==='upgrade' && ((string)($payment['externalReference']??'')!=='bemora-change-'.$change['id'] || (string)$payment['customer']!==(string)($change['asaas_customer_id']??$payment['customer']))) {
  if((string)($payment['externalReference']??'')!=='bemora-change-'.$change['id'])throw new RuntimeException('Cobrança não corresponde à mudança solicitada.');
 }
 if($kind!=='upgrade' && (string)($payment['subscription']??'')!==(string)$order['asaas_subscription_id'])throw new RuntimeException('Cobrança não corresponde à assinatura.');
 if(in_array((string)($payment['status']??''),['PENDING','OVERDUE'],true)){
  try{$pix=bemora_asaas_request('GET','/payments/'.rawurlencode($paymentId).'/pixQrCode');}catch(Throwable $ignore){}
  try{$boleto=bemora_asaas_request('GET','/payments/'.rawurlencode($paymentId).'/identificationField');}catch(Throwable $ignore){}
 }
}catch(Throwable $e){error_log('Bemora embedded billing lookup failed.');$error='Não foi possível consultar os dados de pagamento. Volte e tente novamente.';}
$paid=$payment && in_array((string)($payment['status']??''),['CONFIRMED','RECEIVED'],true);
$pixImage=(string)($pix['encodedImage']??'');if(strlen($pixImage)>150000||!preg_match('/^[A-Za-z0-9+\/=]+$/D',$pixImage))$pixImage='';
$pixCode=(string)($pix['payload']??'');$line=(string)($boleto['identificationField']??'');
$invoiceSafe=$invoice&&bemora_asaas_invoice_url(['invoiceUrl'=>$invoice])?$invoice:null;
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Pagamento • Bemora</title><link rel="stylesheet" href="/assets/bemora-payment.css?v=1"></head><body>
<header><a href="<?= htmlspecialchars($back,ENT_QUOTES,'UTF-8') ?>">← Voltar</a><strong>bemora</strong><span>Pagamento seguro</span></header>
<main><section class="pay-intro"><span>PAGAMENTO DA ASSINATURA</span><h1><?= htmlspecialchars($title,ENT_QUOTES,'UTF-8') ?></h1><p>Você permanece no Bemora para consultar Pix, boleto e confirmar sua cobrança.</p></section>
<section class="pay-layout"><div class="pay-card"><h2>Escolha como pagar</h2>
<?php if($error): ?><p class="pay-notice"><?= htmlspecialchars($error,ENT_QUOTES,'UTF-8') ?></p>
<?php elseif($paid): ?><div class="pay-success">Pagamento confirmado pelo Asaas! O acesso será atualizado após a conciliação do sistema.</div>
<?php else: ?>
<div class="pay-tabs"><button data-method="pix" class="selected">Pix</button><button data-method="boleto">Boleto</button><button data-method="credito">Crédito</button><button data-method="debito">Débito</button></div>
<div id="pay-pix" class="pay-pane"><?php if($pixImage): ?><img class="pay-qr" src="data:image/png;base64,<?= htmlspecialchars($pixImage,ENT_QUOTES,'UTF-8') ?>" alt="QR Code Pix para pagamento"><?php endif; ?>
<?php if($pixCode): ?><label>Pix copia e cola<textarea readonly id="pix-code"><?= htmlspecialchars($pixCode,ENT_QUOTES,'UTF-8') ?></textarea></label><button type="button" data-copy="pix-code">Copiar código Pix</button><?php else: ?><p>Pix indisponível para esta cobrança. Tente boleto ou consulte a fatura.</p><?php endif; ?></div>
<div id="pay-boleto" class="pay-pane" hidden><?php if($line): ?><label>Linha digitável<textarea readonly id="boleto-line"><?= htmlspecialchars($line,ENT_QUOTES,'UTF-8') ?></textarea></label><button type="button" data-copy="boleto-line">Copiar linha digitável</button><?php else: ?><p>Boleto indisponível para esta cobrança.</p><?php endif; ?></div>
<div id="pay-credito" class="pay-pane" hidden><p>Para proteger os dados do cartão, o formulário de crédito será disponibilizado após a homologação do processamento transparente e controles de segurança. Você ainda pode pagar com cartão na página segura do Asaas.</p></div>
<div id="pay-debito" class="pay-pane" hidden><p>O Asaas exige que pagamentos com cartão de débito sejam concluídos na página segura dele. Essa etapa não pode ser processada diretamente pela API do Bemora.</p></div>
<?php if($invoiceSafe): ?><a class="pay-provider" target="_blank" rel="noopener noreferrer" href="<?= htmlspecialchars($invoiceSafe,ENT_QUOTES,'UTF-8') ?>">Pagar com cartão no Asaas ↗</a><?php endif; ?>
<?php endif; ?></div>
<aside class="pay-card"><span>RESUMO</span><h2><?= htmlspecialchars($title,ENT_QUOTES,'UTF-8') ?></h2><strong class="pay-amount">R$ <?= number_format($amount,2,',','.') ?></strong><p>Os valores e a confirmação são consultados diretamente no Asaas. Nenhum dado de cartão é armazenado pelo Bemora.</p><a href="<?= htmlspecialchars($back,ENT_QUOTES,'UTF-8') ?>">Consultar situação →</a></aside></section></main>
<script src="/assets/bemora-payment.js?v=1" defer></script></body></html>
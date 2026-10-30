<?php
declare(strict_types=1);
require_once __DIR__.'/includes/asaas.php';
$id=(int)($_SESSION['checkout_order_id']??0);
$token=(string)($_SESSION['checkout_order_token']??'');
$order=null;
if($id>0 && strlen($token)===64){
 $q=db()->prepare('SELECT * FROM subscription_orders WHERE id=? AND public_token_hash=? LIMIT 1');
 $q->execute([$id,hash('sha256',$token)]);$order=$q->fetch(PDO::FETCH_ASSOC);
}
if(!$order){http_response_code(403);}
if($order && $order['status']==='awaiting_payment' && !$order['invoice_url'] && $order['asaas_subscription_id']){
 $last=(int)($_SESSION['checkout_last_invoice_query']??0);
 if(time()-$last>=45){$_SESSION['checkout_last_invoice_query']=time();
  try{$payments=bemora_asaas_request('GET','/subscriptions/'.rawurlencode($order['asaas_subscription_id']).'/payments?limit=10');
   foreach(($payments['data']??[]) as $payment){$url=bemora_asaas_invoice_url($payment);if($url){db()->prepare('UPDATE subscription_orders SET invoice_url=?,asaas_payment_id=? WHERE id=?')->execute([$url,(string)($payment['id']??''),$id]);$order['invoice_url']=$url;break;}}
  }catch(Throwable $e){error_log('Bemora: invoice lookup failed.');}
 }
}
$inviteUrl=null;
if($order && $order['status']==='paid' && $order['invite_id']){
 $secret=(string)config('asaas','invite_secret','');
 if(strlen($secret)>=32){
  $inviteToken=hash_hmac('sha256','bemora-paid-order-'.$id,$secret);
  $q=db()->prepare('SELECT id FROM family_invitations WHERE id=? AND token_hash=? AND used_at IS NULL AND revoked_at IS NULL AND expires_at>NOW()');
  $q->execute([(int)$order['invite_id'],hash('sha256',$inviteToken)]);
  if($q->fetchColumn())$inviteUrl='/convite-familia.php?token='.$inviteToken;
 }
}
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer"><title>Sua assinatura • Bemora</title><link rel="icon" href="/assets/brand/favicon.png"><link rel="stylesheet" href="/assets/bemora-checkout.css?v=1"></head><body>
<header class="checkout-header"><a href="/"><img src="/assets/brand/logo.png" alt="Bemora"></a><span>Minha assinatura</span></header><main class="checkout-status">
<?php if(!$order): ?><h1>Pedido indisponível</h1><p>Abra seu pedido na mesma sessão utilizada para começar a contratação.</p><a href="/#planos" class="checkout-primary checkout-link">Ver planos</a>
<?php elseif($order['status']==='paid' || $order['status']==='completed'): ?>
<div class="checkout-status-icon">✓</div><h1>Pagamento confirmado!</h1><p>Obrigado por escolher o Bemora. Enviamos as instruções para <?= htmlspecialchars($order['buyer_email'],ENT_QUOTES,'UTF-8') ?>.</p>
<?php if($inviteUrl): ?><a class="checkout-primary checkout-link" href="<?= htmlspecialchars($inviteUrl,ENT_QUOTES,'UTF-8') ?>">Criar minha família ↗</a><p class="checkout-small">O cadastro deve ser concluído em até 72 horas após a confirmação do pagamento.</p><?php else: ?><p>Se ainda não concluiu o cadastro, entre em contato com o suporte para obter um novo convite.</p><?php endif; ?>
<?php else: ?><div class="checkout-status-icon wait">◷</div><h1>Estamos aguardando seu pagamento</h1><p>Seu plano <?= htmlspecialchars($order['plan_name'],ENT_QUOTES,'UTF-8') ?> foi reservado. O acesso só será liberado após a confirmação pelo Asaas.</p>
<?php if($order['invoice_url'] && bemora_asaas_invoice_url(['invoiceUrl'=>$order['invoice_url']])): ?><a class="checkout-primary checkout-link" href="<?= htmlspecialchars($order['invoice_url'],ENT_QUOTES,'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Ir para pagamento seguro ↗</a><?php else: ?><p>O Asaas ainda está preparando a primeira cobrança. Atualize esta página para consultar o link de pagamento.</p><?php endif; ?>
<p class="checkout-small">A confirmação é automática. Volte a esta página depois de pagar, ou consulte o e-mail informado.</p><a class="checkout-secondary" href="/checkout/aguardando">Atualizar situação ↻</a>
<?php endif; ?>
</main></body></html>
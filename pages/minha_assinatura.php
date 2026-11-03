<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/layout.php';
require_once __DIR__.'/../includes/asaas.php';
require_once __DIR__.'/../includes/billing_access.php';
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
$user=require_family_access();$pdo=db();$csrf=csrf_token();$tenantId=(int)$user['tenant_id'];$flash=pull_flash();
$q=$pdo->prepare("SELECT t.name,t.max_members,tu.role FROM tenants t JOIN tenant_users tu ON tu.tenant_id=t.id AND tu.user_id=? WHERE t.id=? AND t.active=1");
$q->execute([(int)$user['id'],$tenantId]);$family=$q->fetch(PDO::FETCH_ASSOC);
if(!$family){http_response_code(403);exit('Família indisponível.');}
$q=$pdo->prepare("SELECT o.* FROM subscription_orders o WHERE o.activated_tenant_id=? AND o.status IN ('completed','paid','payment_attention') ORDER BY o.id DESC LIMIT 1");
$q->execute([$tenantId]);$order=$q->fetch(PDO::FETCH_ASSOC);
$internalPlan=null;
if(!$order){
 $internalReady=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='tenant_internal_plans'")->fetchColumn()===1;
 if($internalReady){
  $q=$pdo->prepare("SELECT p.name,p.billing_cycle,p.max_members FROM tenant_internal_plans x JOIN subscription_plans p ON p.id=x.plan_id WHERE x.tenant_id=?");
  $q->execute([$tenantId]);$internalPlan=$q->fetch(PDO::FETCH_ASSOC)?:null;
 }
}
$billingAccess=bemora_billing_access($pdo,$tenantId);
if(($billingAccess['state']??'active')==='suspended' && !empty($billingAccess['checked_at']) && strtotime((string)$billingAccess['checked_at'])<time()-120){
 try{$billingAccess=bemora_reconcile_billing_access($pdo,$tenantId);}
 catch(Throwable $error){error_log('Bemora: billing status refresh deferred to scheduled reconciliation.');}
}
$invoices=[];$billingWarning='';$nextDue=null;$subscriptionState='';
if($order && !empty($order['asaas_subscription_id'])){
 try{
  $sub=bemora_asaas_request('GET','/subscriptions/'.rawurlencode((string)$order['asaas_subscription_id']));
  $subscriptionState=(string)($sub['status']??'');
  $rawDue=(string)($sub['nextDueDate']??'');
  if(preg_match('/^\d{4}-\d{2}-\d{2}$/D',$rawDue))$nextDue=new DateTimeImmutable($rawDue);
  $payments=bemora_asaas_request('GET','/subscriptions/'.rawurlencode((string)$order['asaas_subscription_id']).'/payments?limit=30');
  foreach(($payments['data']??[]) as $payment){
   if(!is_array($payment) || (string)($payment['subscription']??'')!==(string)$order['asaas_subscription_id'])continue;
   $url=bemora_asaas_invoice_url($payment);
   $invoices[]=['date'=>(string)($payment['dueDate']??''),'amount'=>(float)($payment['value']??0),'status'=>(string)($payment['status']??''),'url'=>$url];
  }
 }catch(Throwable $e){error_log('Bemora: subscription billing lookup failed.');$billingWarning='Não foi possível consultar as faturas agora. Tente novamente mais tarde.';}
}
$freeUntil=null;
if($order && !empty($order['coupon_free_until'])){try{$freeUntil=new DateTimeImmutable((string)$order['coupon_free_until']);}catch(Throwable $ignored){}}
$referenceDate=$freeUntil && $freeUntil>new DateTimeImmutable('today')?$freeUntil:$nextDue;
$days=$referenceDate?(int)(new DateTimeImmutable('today'))->diff($referenceDate)->format('%r%a'):null;
$cycles=bemora_plan_cycles();
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Minha assinatura • Bemora</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="/assets/style.css"><link rel="stylesheet" href="/assets/bemora.css?v=2"><link rel="stylesheet" href="/assets/bemora-finance.css?v=2"><link rel="stylesheet" href="/assets/bemora-finish.css?v=3"><?php brand_head_tags(); ?>
<link rel="stylesheet" href="/assets/bemora-sidebar.css?v=5"><link rel="stylesheet" href="/assets/bemora-support.css?v=1"><link rel="stylesheet" href="/assets/bemora-subscription.css?v=3"></head>
<body class="bemora-finance"><div class="shell ref-shell"><?php render_sidebar('minha-assinatura',$csrf); ?><main><?php render_topbar($user); ?><div class="content bemora-subscription"><?php page_header('MINHA CONTA','Minha assinatura','Acompanhe seu plano, próximas cobranças e faturas em um só lugar.'); ?>
<?php if($flash): ?><p class="sub-support-notice" role="status"><?= e($flash['message']) ?></p><?php endif; ?>
<?php if((int)($billingAccess['manual_suspended']??0)===1): ?>
<section class="sub-card sub-billing-alert" role="alert"><span class="sub-kicker">ACESSO TEMPORARIAMENTE SUSPENSO</span><h2>Suspensão administrativa</h2><p>O acesso financeiro desta família foi suspenso pela administração do Bemora. Seus dados continuam preservados. Use o botão Relatar problema para falar com nossa equipe.</p></section>
<?php endif; ?>
<?php if(($billingAccess['state']??'active')==='suspended'): ?>
<section class="sub-card sub-billing-alert" role="alert"><span class="sub-kicker">REGULARIZE SUA ASSINATURA</span><h2>Acesso financeiro temporariamente suspenso</h2><p>Identificamos uma fatura recorrente vencida após o período de tolerância. Seus lançamentos e dados continuam preservados. Consulte as faturas abaixo, regularize o pagamento e o acesso será restabelecido após a confirmação do Asaas.</p><p>Você pode consultar suas faturas e relatar um problema nesta página.</p></section>
<?php elseif(($billingAccess['state']??'active')==='grace'): ?>
<section class="sub-card sub-billing-grace" role="status"><span class="sub-kicker">PAGAMENTO PENDENTE</span><h2>Há uma cobrança em atraso</h2><p>Regularize a fatura para evitar a suspensão após o período de tolerância de 3 dias.</p></section>
<?php endif; ?>
<?php if(!$order && $internalPlan): ?>
<div class="sub-grid"><section class="sub-card sub-main"><span class="sub-kicker">SEU ACESSO ATUAL</span><div class="sub-heading"><h2><?= e($internalPlan['name']) ?></h2><span class="sub-pill">Acesso interno gratuito</span></div><p>Até <?= (int)$internalPlan['max_members'] ?> membros · <?= e(bemora_plan_cycles()[$internalPlan['billing_cycle']]??$internalPlan['billing_cycle']) ?></p><div class="sub-metrics"><div><small>Mensalidade</small><strong>R$ 0,00</strong></div><div><small>Tipo de acesso</small><strong>Administrativo</strong></div><div><small>Cobrança</small><strong>Não aplicável</strong></div></div><p class="sub-note">Plano disponibilizado pela administração do Bemora, sem assinatura financeira no Asaas.</p></section><section class="sub-card"><span class="sub-kicker">SEU PLANO</span><h2>Gerenciado pela administração</h2><p>Este plano é atribuído diretamente pelo administrador da plataforma. Para alterações, entre em contato com o suporte.</p></section></div>
<?php elseif(!$order): ?>
<section class="sub-card"><h2>Assinatura não vinculada</h2><p>Não encontramos uma contratação comercial vinculada a esta família. Isso pode ocorrer em contas anteriores ao lançamento dos planos.</p><a class="sub-btn" href="/#planos">Conhecer planos</a></section>
<?php else: ?>
<div class="sub-grid"><section class="sub-card sub-main"><span class="sub-kicker">SEU PLANO ATUAL</span><div class="sub-heading"><h2><?= e($order['plan_name']) ?></h2><span class="sub-pill"><?= $freeUntil && $freeUntil>new DateTimeImmutable('today')?'Período gratuito':($subscriptionState==='ACTIVE'?'Assinatura ativa':'Situação: '.e($subscriptionState?:$order['status'])) ?></span></div>
<p><?= e($cycles[$order['billing_cycle']]??$order['billing_cycle']) ?> · Até <?= (int)$order['max_members'] ?> <?= (int)$order['max_members']===1?'membro':'membros' ?></p>
<div class="sub-metrics"><div><small>Valor recorrente</small><strong><?= bemora_plan_price((float)($order['recurring_amount']??$order['amount'])) ?></strong></div><div><small><?= $freeUntil && $freeUntil>new DateTimeImmutable('today')?'Fim do período gratuito':'Próxima cobrança prevista' ?></small><strong><?= $referenceDate?e($referenceDate->format('d/m/Y')):'A confirmar' ?></strong></div><div><small>Dias até a próxima cobrança</small><strong><?= $days!==null && $days>=0?$days.' dias':'A confirmar' ?></strong></div></div>
<p class="sub-note">Planos recorrentes não possuem uma data fixa de expiração enquanto permanecerem ativos. A data acima indica a próxima cobrança prevista, não o encerramento do acesso.</p></section>
<?php if(!bemora_billing_is_blocked($billingAccess)): ?><section class="sub-card"><span class="sub-kicker">EVOLUA COM O BEMORA</span><h2>Precisa de mais espaço?</h2><p>Consulte os planos disponíveis para incluir mais membros ou mudar sua modalidade.</p><a class="sub-btn" href="/evoluir-plano">Comparar e evoluir meu plano <i class="bi bi-arrow-up-right"></i></a><p class="sub-note">Compare planos e registre sua solicitação. A troca da assinatura existente depende de confirmação; não fazemos uma nova cobrança automaticamente.</p></section><?php endif; ?></div>
<section class="sub-card"><div class="sub-heading"><div><span class="sub-kicker">HISTÓRICO DE PAGAMENTOS</span><h2>Minhas faturas</h2></div><span class="sub-count"><?= count($invoices) ?> encontrada(s)</span></div>
<?php if($billingWarning): ?><p class="sub-warning" role="status"><?= e($billingWarning) ?></p><?php endif; ?>
<?php if(!$invoices): ?><p class="sub-empty">Ainda não há faturas disponíveis para esta assinatura. As próximas cobranças aparecerão aqui quando forem geradas pelo Asaas.</p>
<?php else: ?><div class="sub-table-scroll"><table class="sub-table"><thead><tr><th>Vencimento</th><th>Valor</th><th>Situação</th><th>Fatura</th></tr></thead><tbody>
<?php foreach($invoices as $invoice): ?><tr><td><?= preg_match('/^\d{4}-\d{2}-\d{2}$/',$invoice['date'])?e(date('d/m/Y',strtotime($invoice['date']))):'—' ?></td><td><?= bemora_plan_price($invoice['amount']) ?></td><td><?= e(['PENDING'=>'Pendente','RECEIVED'=>'Recebida','CONFIRMED'=>'Confirmada','OVERDUE'=>'Vencida','REFUNDED'=>'Reembolsada','CANCELLED'=>'Cancelada'][$invoice['status']]??$invoice['status']) ?></td><td><?php if($invoice['url']): ?><a href="<?= e($invoice['url']) ?>" target="_blank" rel="noopener noreferrer">Visualizar <i class="bi bi-box-arrow-up-right"></i></a><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></section>
<?php endif; ?><section class="sub-card sub-support-entry"><div><span class="sub-kicker">PRECISA DE AJUDA?</span><h2>Podemos ajudar com sua assinatura.</h2><p>Conte o que aconteceu e escolha como prefere receber o retorno da nossa equipe.</p></div><button type="button" class="sub-btn" id="bemora-report-open"><i class="bi bi-chat-dots" aria-hidden="true"></i> Relatar problema</button></section>
</div></main></div>
<dialog class="bemora-support-dialog" id="bemora-support-dialog" aria-labelledby="bemora-support-title">
<div class="support-dialog-head"><div><span class="sub-kicker">SUPORTE BEMORA</span><h2 id="bemora-support-title">Relatar problema</h2><p>Preencha os detalhes para receber ajuda da nossa equipe.</p></div><button type="button" id="bemora-report-close" aria-label="Fechar formulário"><i class="bi bi-x-lg"></i></button></div>
<form method="post" action="/suporte/relatar" id="bemora-report-form">
<input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
<label for="support-description">Descrição do problema <span aria-hidden="true">*</span></label>
<textarea name="description" id="support-description" maxlength="3000" minlength="15" rows="5" placeholder="Descreva o que aconteceu, o que tentou fazer e a mensagem exibida..." required></textarea>
<fieldset class="support-contact-method"><legend>Como prefere receber o contato?</legend><label><input type="radio" name="contact_method" value="whatsapp" checked> WhatsApp</label><label><input type="radio" name="contact_method" value="email"> E-mail</label></fieldset>
<label for="support-contact-value" id="support-contact-label">WhatsApp com DDD <span aria-hidden="true">*</span></label>
<input id="support-contact-value" name="contact_value" type="tel" inputmode="tel" autocomplete="tel" maxlength="16" placeholder="(00) 00000-0000" required>
<p class="support-privacy">Seus dados de contato serão usados apenas para atender este relato. Não informe senhas ou dados de cartão.</p>
<div class="support-dialog-actions"><button type="button" class="support-cancel" id="bemora-report-cancel">Cancelar</button><button type="submit" class="sub-btn"><i class="bi bi-send" aria-hidden="true"></i> Enviar relato</button></div>
</form></dialog>
<script src="/assets/bemora-support.js?v=1" defer></script></body></html>

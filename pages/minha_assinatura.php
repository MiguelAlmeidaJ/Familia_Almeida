<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/layout.php';
require_once __DIR__.'/../includes/asaas.php';
$user=require_family_access();$pdo=db();$csrf=csrf_token();$tenantId=(int)$user['tenant_id'];
$q=$pdo->prepare("SELECT t.name,t.max_members,tu.role FROM tenants t JOIN tenant_users tu ON tu.tenant_id=t.id AND tu.user_id=? WHERE t.id=? AND t.active=1");
$q->execute([(int)$user['id'],$tenantId]);$family=$q->fetch(PDO::FETCH_ASSOC);
if(!$family){http_response_code(403);exit('Família indisponível.');}
$q=$pdo->prepare("SELECT o.* FROM subscription_orders o WHERE o.activated_tenant_id=? AND o.status IN ('completed','paid','payment_attention') ORDER BY o.id DESC LIMIT 1");
$q->execute([$tenantId]);$order=$q->fetch(PDO::FETCH_ASSOC);
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
<link rel="stylesheet" href="/assets/bemora-sidebar.css?v=4"><link rel="stylesheet" href="/assets/bemora-subscription.css?v=1"></head>
<body class="bemora-finance"><div class="shell ref-shell"><?php render_sidebar('minha-assinatura',$csrf); ?><main><?php render_topbar($user); ?><div class="content bemora-subscription"><?php page_header('MINHA CONTA','Minha assinatura','Acompanhe seu plano, próximas cobranças e faturas em um só lugar.'); ?>
<?php if(!$order): ?>
<section class="sub-card"><h2>Assinatura não vinculada</h2><p>Não encontramos uma contratação comercial vinculada a esta família. Isso pode ocorrer em contas anteriores ao lançamento dos planos.</p><a class="sub-btn" href="/#planos">Conhecer planos</a></section>
<?php else: ?>
<div class="sub-grid"><section class="sub-card sub-main"><span class="sub-kicker">SEU PLANO ATUAL</span><div class="sub-heading"><h2><?= e($order['plan_name']) ?></h2><span class="sub-pill"><?= $freeUntil && $freeUntil>new DateTimeImmutable('today')?'Período gratuito':($subscriptionState==='ACTIVE'?'Assinatura ativa':'Situação: '.e($subscriptionState?:$order['status'])) ?></span></div>
<p><?= e($cycles[$order['billing_cycle']]??$order['billing_cycle']) ?> · Até <?= (int)$order['max_members'] ?> <?= (int)$order['max_members']===1?'membro':'membros' ?></p>
<div class="sub-metrics"><div><small>Valor recorrente</small><strong><?= bemora_plan_price((float)($order['recurring_amount']??$order['amount'])) ?></strong></div><div><small><?= $freeUntil && $freeUntil>new DateTimeImmutable('today')?'Fim do período gratuito':'Próxima cobrança prevista' ?></small><strong><?= $referenceDate?e($referenceDate->format('d/m/Y')):'A confirmar' ?></strong></div><div><small>Dias até a próxima cobrança</small><strong><?= $days!==null && $days>=0?$days.' dias':'A confirmar' ?></strong></div></div>
<p class="sub-note">Planos recorrentes não possuem uma data fixa de expiração enquanto permanecerem ativos. A data acima indica a próxima cobrança prevista, não o encerramento do acesso.</p></section>
<section class="sub-card"><span class="sub-kicker">EVOLUA COM O BEMORA</span><h2>Precisa de mais espaço?</h2><p>Consulte os planos disponíveis para incluir mais membros ou mudar sua modalidade.</p><a class="sub-btn" href="/evoluir-plano">Comparar e evoluir meu plano <i class="bi bi-arrow-up-right"></i></a><p class="sub-note">Compare planos e registre sua solicitação. A troca da assinatura existente depende de confirmação; não fazemos uma nova cobrança automaticamente.</p></section></div>
<section class="sub-card"><div class="sub-heading"><div><span class="sub-kicker">HISTÓRICO DE PAGAMENTOS</span><h2>Minhas faturas</h2></div><span class="sub-count"><?= count($invoices) ?> encontrada(s)</span></div>
<?php if($billingWarning): ?><p class="sub-warning" role="status"><?= e($billingWarning) ?></p><?php endif; ?>
<?php if(!$invoices): ?><p class="sub-empty">Ainda não há faturas disponíveis para esta assinatura. As próximas cobranças aparecerão aqui quando forem geradas pelo Asaas.</p>
<?php else: ?><div class="sub-table-scroll"><table class="sub-table"><thead><tr><th>Vencimento</th><th>Valor</th><th>Situação</th><th>Fatura</th></tr></thead><tbody>
<?php foreach($invoices as $invoice): ?><tr><td><?= preg_match('/^\d{4}-\d{2}-\d{2}$/',$invoice['date'])?e(date('d/m/Y',strtotime($invoice['date']))):'—' ?></td><td><?= bemora_plan_price($invoice['amount']) ?></td><td><?= e(['PENDING'=>'Pendente','RECEIVED'=>'Recebida','CONFIRMED'=>'Confirmada','OVERDUE'=>'Vencida','REFUNDED'=>'Reembolsada','CANCELLED'=>'Cancelada'][$invoice['status']]??$invoice['status']) ?></td><td><?php if($invoice['url']): ?><a href="<?= e($invoice['url']) ?>" target="_blank" rel="noopener noreferrer">Visualizar <i class="bi bi-box-arrow-up-right"></i></a><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></section>
<?php endif; ?></div></main></div></body></html>

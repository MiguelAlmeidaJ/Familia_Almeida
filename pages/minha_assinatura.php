<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/layout.php';
require_once __DIR__.'/../includes/asaas.php';
require_once __DIR__.'/../includes/single_offer.php';
require_once __DIR__.'/../includes/billing_access.php';
require_once __DIR__.'/../includes/subscription_cancellation.php';
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
$user=require_family_access();$pdo=db();$csrf=csrf_token();$tenantId=(int)$user['tenant_id'];
if(($_GET['access_check']??'')==='1'){
 header('Content-Type: application/json; charset=utf-8');
 header('Cache-Control: private, no-store');
 echo json_encode(['blocked'=>bemora_billing_is_blocked(bemora_billing_access($pdo,$tenantId))],JSON_THROW_ON_ERROR);
 exit;
}
$flash=pull_flash();
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
$cancellation=bemora_cancellation_for_family($pdo,$tenantId);
$isCancelled=($cancellation['status']??'')==='confirmed';
$cancelPending=in_array((string)($cancellation['status']??''),['processing','reconciliation_required'],true);
$isFamilyAdmin=($family['role']??'')==='family_admin';
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
if($isCancelled)$referenceDate=!empty($cancellation['access_until'])?new DateTimeImmutable((string)$cancellation['access_until']):null;
$days=$referenceDate?(int)(new DateTimeImmutable('today'))->diff($referenceDate)->format('%r%a'):null;
$cycles=bemora_plan_cycles();
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Minha assinatura • Bemora</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="/assets/style.css"><link rel="stylesheet" href="/assets/bemora.css?v=2"><link rel="stylesheet" href="/assets/bemora-finance.css?v=2"><link rel="stylesheet" href="/assets/bemora-finish.css?v=3"><?php brand_head_tags(); ?>
<link rel="stylesheet" href="/assets/bemora-sidebar.css?v=6"><link rel="stylesheet" href="/assets/bemora-subscription.css?v=5"></head>
<body class="bemora-finance"><div class="shell ref-shell"><?php render_sidebar('minha-assinatura',$csrf); ?><main><?php render_topbar($user); ?><div class="content bemora-subscription"><?php page_header('MINHA CONTA','Minha assinatura','Acompanhe seu plano, próximas cobranças e faturas em um só lugar.'); ?>
<?php if($flash): $flashIsSuccess=($flash['type']??'')==='success'; $flashMessage=(string)($flash['message']??''); $isSupportReport=$flashIsSuccess && preg_match('/^Relato #(\\d+)\\b/u',$flashMessage,$ticketMatch)===1; ?>
<section class="bemora-feedback <?= $flashIsSuccess?'is-success':'is-error' ?>" id="bemora-feedback" role="<?= $flashIsSuccess?'status':'alert' ?>" aria-live="<?= $flashIsSuccess?'polite':'assertive' ?>" aria-labelledby="bemora-feedback-title">
  <div class="bemora-feedback-icon"><i class="bi <?= $flashIsSuccess?'bi-check-circle-fill':'bi-exclamation-triangle-fill' ?>" aria-hidden="true"></i></div>
  <div class="bemora-feedback-content">
    <span class="bemora-feedback-label"><?= $flashIsSuccess?'CONFIRMAÇÃO':'ATENÇÃO' ?></span>
    <h2 id="bemora-feedback-title"><?= $isSupportReport?'Recebemos seu relato!':($flashIsSuccess?'Tudo certo!':'Não foi possível concluir') ?></h2>
    <p><?= e($flashMessage) ?></p>
    <?php if($isSupportReport): ?><div class="bemora-feedback-details"><span><i class="bi bi-ticket-perforated" aria-hidden="true"></i> Protocolo <strong>#<?= e($ticketMatch[1]) ?></strong></span><span><i class="bi bi-headset" aria-hidden="true"></i> Acompanhe pelo contato escolhido</span></div><?php endif; ?>
  </div>
  <button type="button" class="bemora-feedback-dismiss" aria-label="Fechar mensagem" title="Dispensar mensagem" onclick="this.closest('.bemora-feedback').remove()"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
</section>
<?php endif; ?>
<?php if((int)($billingAccess['manual_suspended']??0)===1): ?>
<section class="sub-card sub-billing-alert" role="alert"><span class="sub-kicker">ACESSO TEMPORARIAMENTE SUSPENSO</span><h2>Suspensão administrativa</h2><p>O acesso financeiro desta família foi suspenso pela administração do Bemora. Seus dados continuam preservados. Use o botão Relatar problema para falar com nossa equipe.</p></section>
<?php endif; ?>
<?php if(($billingAccess['state']??'active')==='suspended'): ?>
<section class="sub-card sub-billing-alert" role="alert"><span class="sub-kicker">ASSINATURA REQUER ATENÇÃO</span><h2>Acesso financeiro temporariamente suspenso</h2>
<?php if(($billingAccess['reason']??'')==='subscription_canceled'): ?>
<p>O período de acesso da sua assinatura cancelada terminou. Suas informações continuam preservadas. Para contratar novamente, entre em contato com o suporte.</p>
<?php elseif(in_array((string)($billingAccess['reason']??''),['subscription_inactive','subscription_deleted','subscription_missing'],true)): ?>
<p>Esta assinatura foi interrompida ou cancelada no Asaas. Seus dados permanecem preservados. Para reativar o serviço, entre em contato com o suporte e regularize a situação contratual.</p>
<?php elseif(($billingAccess['reason']??'')==='upgrade_payment_reversed'): ?>
<p>Uma cobrança relacionada à evolução do seu plano foi estornada ou contestada. A assinatura requer conciliação financeira. Seus dados continuam preservados. Entre em contato com o suporte.</p>
<?php elseif(($billingAccess['reason']??'')==='payment_reversed'): ?>
<p>Foi identificada uma devolução ou contestação de pagamento. O acesso fica temporariamente suspenso até que a situação financeira seja regularizada e confirmada pelo Asaas.</p>
<?php else: ?>
<p>Identificamos uma fatura recorrente vencida após o período de tolerância. Seus dados estão preservados. Consulte as faturas abaixo e regularize o pagamento para restabelecer o acesso após a confirmação do Asaas.</p>
<?php endif; ?><p>Você pode consultar suas faturas, relatar um problema e <a href="/meus-dados">exercer seus direitos sobre dados pessoais</a>, mesmo durante a suspensão.</p></section>
<?php elseif(($billingAccess['state']??'active')==='grace'): ?>
<section class="sub-card sub-billing-grace" role="status"><span class="sub-kicker">PAGAMENTO PENDENTE</span><h2>Há uma cobrança em atraso</h2><p>Regularize a fatura para evitar a suspensão após o período de tolerância de 3 dias.</p></section>
<?php endif; ?>
<?php if(!$order && $internalPlan): ?>
<div class="sub-grid"><section class="sub-card sub-main"><span class="sub-kicker">SEU ACESSO ATUAL</span><div class="sub-heading"><h2><?= e($internalPlan['name']) ?></h2><span class="sub-pill">Acesso interno gratuito</span></div><p>Até <?= (int)$internalPlan['max_members'] ?> membros · <?= e(bemora_plan_cycles()[$internalPlan['billing_cycle']]??$internalPlan['billing_cycle']) ?></p><div class="sub-metrics"><div><small>Mensalidade</small><strong>R$ 0,00</strong></div><div><small>Tipo de acesso</small><strong>Administrativo</strong></div><div><small>Cobrança</small><strong>Não aplicável</strong></div></div><p class="sub-note">Plano disponibilizado pela administração do Bemora, sem assinatura financeira no Asaas.</p></section><section class="sub-card"><span class="sub-kicker">SEU PLANO</span><h2>Gerenciado pela administração</h2><p>Este plano é atribuído diretamente pelo administrador da plataforma. Para alterações, entre em contato com o suporte.</p></section></div>
<?php elseif(!$order): ?>
<section class="sub-card"><h2>Assinatura não vinculada</h2><p>Não encontramos uma contratação comercial vinculada a esta família. Isso pode ocorrer em contas anteriores ao lançamento dos planos.</p><a class="sub-btn" href="/#planos">Conhecer planos</a></section>
<?php else: ?>
<div class="sub-grid"><section class="sub-card sub-main"><span class="sub-kicker">SEU PLANO ATUAL</span><div class="sub-heading"><h2><?= e($order['plan_name']) ?></h2><span class="sub-pill"><?= $isCancelled?'Renovação cancelada':($cancelPending?'Cancelamento em análise':($freeUntil && $freeUntil>new DateTimeImmutable('today')?'Período gratuito':($subscriptionState==='ACTIVE'?'Assinatura ativa':'Situação: '.e($subscriptionState?:$order['status'])))) ?></span></div>
<p><?= e($cycles[$order['billing_cycle']]??$order['billing_cycle']) ?> · Até <?= (int)$order['max_members'] ?> <?= (int)$order['max_members']===1?'membro':'membros' ?></p>
<div class="sub-metrics"><div><small>Valor recorrente</small><strong><?= bemora_plan_price((float)($order['recurring_amount']??$order['amount'])) ?></strong></div><div><small><?= $isCancelled?'Acesso disponível até':($freeUntil && $freeUntil>new DateTimeImmutable('today')?'Fim do período gratuito':'Próxima cobrança prevista') ?></small><strong><?= $referenceDate?e($referenceDate->format('d/m/Y')):($isCancelled?'Encerrado':'A confirmar') ?></strong></div><div><small><?= $isCancelled?'Dias restantes de acesso':'Dias até a próxima cobrança' ?></small><strong><?= $days!==null && $days>=0?$days.' dias':($isCancelled?'0 dias':'A confirmar') ?></strong></div></div>
<p class="sub-note"><?= $isCancelled?'A renovação automática foi interrompida. Nenhuma nova cobrança recorrente será gerada pelo Asaas. Seus dados estão preservados.':'Planos recorrentes não possuem uma data fixa de expiração enquanto permanecerem ativos. A data acima indica a próxima cobrança prevista, não o encerramento do acesso.' ?></p></section>
<?php if(!bemora_billing_is_blocked($billingAccess) && !$cancellation): ?><?php if($order && (int)($order['single_offer']??0)===1): ?><section class="sub-card"><span class="sub-kicker">PERSONALIZE O BEMORA</span><h2>Precisa de mais integrantes ou Compras?</h2><p>Adicione pessoas e o módulo de Compras à assinatura existente, mantendo sua periodicidade.</p><a class="sub-btn" href="/minha-assinatura/adicionais">Gerenciar adicionais <i class="bi bi-arrow-up-right"></i></a><p class="sub-note">A liberação após a contratação depende da confirmação de pagamento e das condições do Asaas.</p></section><?php else: ?><section class="sub-card"><span class="sub-kicker">EVOLUA COM O BEMORA</span><h2>Precisa de mais espaço?</h2><p>Consulte os planos disponíveis para incluir mais membros ou mudar sua modalidade.</p><a class="sub-btn" href="/evoluir-plano">Comparar e evoluir meu plano <i class="bi bi-arrow-up-right"></i></a><p class="sub-note">Compare planos e registre sua solicitação. A troca da assinatura existente depende de confirmação; não fazemos uma nova cobrança automaticamente.</p></section><?php endif; ?><?php endif; ?></div>
<section class="sub-card"><div class="sub-heading"><div><span class="sub-kicker">HISTÓRICO DE PAGAMENTOS</span><h2>Minhas faturas</h2></div><span class="sub-count"><?= count($invoices) ?> encontrada(s)</span></div>
<?php if($billingWarning): ?><p class="sub-warning" role="status"><?= e($billingWarning) ?></p><?php endif; ?>
<?php if(!$invoices): ?><p class="sub-empty">Ainda não há faturas disponíveis para esta assinatura. As próximas cobranças aparecerão aqui quando forem geradas pelo Asaas.</p>
<?php else: ?><div class="sub-table-scroll"><table class="sub-table"><thead><tr><th>Vencimento</th><th>Valor</th><th>Situação</th><th>Fatura</th></tr></thead><tbody>
<?php foreach($invoices as $invoice): ?><tr><td><?= preg_match('/^\d{4}-\d{2}-\d{2}$/',$invoice['date'])?e(date('d/m/Y',strtotime($invoice['date']))):'—' ?></td><td><?= bemora_plan_price($invoice['amount']) ?></td><td><?= e(['PENDING'=>'Pendente','RECEIVED'=>'Recebida','CONFIRMED'=>'Confirmada','OVERDUE'=>'Vencida','REFUNDED'=>'Reembolsada','CANCELLED'=>'Cancelada'][$invoice['status']]??$invoice['status']) ?></td><td><?php if($invoice['url']): ?><a href="<?= e($invoice['url']) ?>" target="_blank" rel="noopener noreferrer">Visualizar <i class="bi bi-box-arrow-up-right"></i></a><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></section>
<section class="sub-card bemora-cancellation">
<?php if($isCancelled): ?>
 <span class="sub-kicker">CANCELAMENTO CONFIRMADO</span>
 <h2>Renovação automática encerrada</h2>
 <p>Não serão geradas novas cobranças recorrentes. <?= !empty($cancellation['access_until'])?'O acesso financeiro permanece até '.e(date('d/m/Y',strtotime((string)$cancellation['access_until']))).'.':'O acesso financeiro já foi encerrado.' ?> Os dados da família estão preservados.</p>
 <p class="sub-note">Faturas emitidas antes do cancelamento continuam disponíveis, quando aplicável. Para uma nova contratação, fale com o suporte.</p>
<?php elseif($cancelPending): ?>
 <span class="sub-kicker">CANCELAMENTO EM CONCILIAÇÃO</span>
 <h2>Estamos verificando seu cancelamento</h2>
 <p>A solicitação foi registrada, mas ainda precisa de confirmação do Asaas. Entre em contato pelo botão Relatar problema para obter ajuda. Não realize uma segunda solicitação.</p>
<?php elseif($isFamilyAdmin && $subscriptionState==='ACTIVE' && !$billingWarning): ?>
 <span class="sub-kicker">GERENCIAMENTO DA ASSINATURA</span>
 <h2>Deseja cancelar sua assinatura?</h2>
 <p>Você pode interromper as próximas renovações sem apagar as informações da sua família. O prazo restante será verificado pelas cobranças confirmadas no Asaas.</p>
 <button type="button" id="bemora-cancel-open" class="bemora-cancel-button" aria-haspopup="dialog" aria-controls="bemora-cancel-dialog"><i class="bi bi-x-circle" aria-hidden="true"></i> Cancelar renovação da assinatura</button>
<?php else: ?>
 <span class="sub-kicker">GERENCIAMENTO</span><h2>Cancelamento de assinatura</h2>
 <p><?= $isFamilyAdmin?'Não foi possível confirmar que a assinatura está ativa no Asaas. Solicite ajuda pelo botão Relatar problema para conciliar o cancelamento.':'Somente o administrador familiar pode cancelar a renovação desta assinatura.' ?></p>
<?php endif; ?>
</section>
<?php endif; ?></div></main></div>
<?php if($order && $isFamilyAdmin && !$cancellation && $subscriptionState==='ACTIVE' && !$billingWarning): ?>
<dialog class="bemora-cancel-dialog" id="bemora-cancel-dialog" aria-labelledby="bemora-cancel-title" aria-describedby="bemora-cancel-description">
 <div class="bemora-cancel-dialog-top"><i class="bi bi-exclamation-circle" aria-hidden="true"></i><button type="button" id="bemora-cancel-close" aria-label="Fechar"><i class="bi bi-x-lg"></i></button></div>
 <span class="sub-kicker">CONFIRMAÇÃO DO CANCELAMENTO</span>
 <h2 id="bemora-cancel-title">Cancelar a renovação?</h2>
 <p id="bemora-cancel-description">Faturas já emitidas continuam válidas. O cancelamento não gera reembolso automático. Para contratar novamente após o encerramento, será necessário solicitar uma nova assinatura.</p>
 <form method="post" action="/minha-assinatura/cancelar">
  <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
  <label class="bemora-cancel-reason" for="cancel-reason">Motivo (opcional)<textarea id="cancel-reason" name="reason" rows="2" maxlength="500" placeholder="Como podemos melhorar?"></textarea></label>
  <label class="bemora-cancel-check"><input type="checkbox" name="confirm_cancel" value="yes" required> Entendo que a renovação será interrompida e as faturas já emitidas não serão automaticamente estornadas.</label>
  <div class="bemora-cancel-actions"><button type="button" id="bemora-cancel-back">Manter minha assinatura</button><button type="submit" class="bemora-cancel-danger"><i class="bi bi-check2"></i> Confirmar cancelamento</button></div>
 </form>
</dialog>
<script src="/assets/bemora-cancellation.js?v=1" defer></script>
<?php endif; ?>

</body></html>

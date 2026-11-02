<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/layout.php';
require_once __DIR__.'/../includes/plans.php';
require_once __DIR__.'/../includes/asaas.php';
require_once __DIR__.'/../includes/paid_plan_changes.php';
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
$user=require_family_access();$pdo=db();$tid=(int)$user['tenant_id'];$csrf=csrf_token();$flash=pull_flash();
$q=$pdo->prepare("SELECT o.*,t.name family_name FROM subscription_orders o JOIN tenants t ON t.id=o.activated_tenant_id WHERE o.activated_tenant_id=? AND o.status='completed' ORDER BY o.id DESC LIMIT 1");
$q->execute([$tid]);$order=$q->fetch(PDO::FETCH_ASSOC);
$q=$pdo->prepare("SELECT role FROM tenant_users WHERE tenant_id=? AND user_id=?");$q->execute([$tid,(int)$user['id']]);$isAdmin=$q->fetchColumn()==='family_admin';
$q=$pdo->prepare("SELECT COUNT(*) FROM tenant_users WHERE tenant_id=?");$q->execute([$tid]);$memberCount=(int)$q->fetchColumn();
$plans=$pdo->query('SELECT * FROM subscription_plans WHERE active=1 ORDER BY max_members,billing_cycle')->fetchAll(PDO::FETCH_ASSOC);
$requests=[];$migrationReady=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='subscription_change_requests'")->fetchColumn()===1;
if($migrationReady){$q=$pdo->prepare("SELECT r.*,p.name target_name,p.billing_cycle target_cycle FROM subscription_change_requests r JOIN subscription_plans p ON p.id=r.to_plan_id WHERE r.tenant_id=? ORDER BY r.id DESC LIMIT 5");$q->execute([$tid]);$requests=$q->fetchAll(PDO::FETCH_ASSOC);}
// Recover payment notifications that reached Asaas before our invoice ID was persisted.
foreach($requests as $candidate){
 if($candidate['status']==='awaiting_payment' && !empty($candidate['asaas_payment_id'])){
  try {bemora_apply_paid_plan_change($pdo,(string)$candidate['asaas_payment_id']);}
  catch(Throwable $error){error_log('Bemora: plan change confirmation needs reconciliation.');}
  $refresh=$pdo->prepare('SELECT status FROM subscription_change_requests WHERE id=? AND tenant_id=?');
  $refresh->execute([(int)$candidate['id'],$tid]);
  foreach($requests as &$entry){if((int)$entry['id']===(int)$candidate['id'])$entry['status']=$refresh->fetchColumn();}
  unset($entry);
 }
}
// Refresh the order after payment reconciliation, before rendering the current plan.
$q=$pdo->prepare("SELECT o.*,t.name family_name FROM subscription_orders o JOIN tenants t ON t.id=o.activated_tenant_id WHERE o.activated_tenant_id=? AND o.status='completed' ORDER BY o.id DESC LIMIT 1");
$q->execute([$tid]);$order=$q->fetch(PDO::FETCH_ASSOC);
$pending=false;foreach($requests as $r)if(in_array($r['status'],['scheduled','processing','reconciliation_required','awaiting_payment','payment_confirmed'],true))$pending=true;
$cycles=bemora_plan_cycles();
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Evoluir meu plano • Bemora</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"><link rel="stylesheet" href="/assets/style.css"><link rel="stylesheet" href="/assets/bemora.css?v=2"><link rel="stylesheet" href="/assets/bemora-finance.css?v=2"><link rel="stylesheet" href="/assets/bemora-finish.css?v=3"><?php brand_head_tags(); ?><link rel="stylesheet" href="/assets/bemora-sidebar.css?v=5"><link rel="stylesheet" href="/assets/bemora-subscription.css?v=1"><link rel="stylesheet" href="/assets/bemora-plan-change.css?v=3"></head>
<body class="bemora-finance"><div class="shell ref-shell"><?php render_sidebar('minha-assinatura',$csrf); ?><main><?php render_topbar($user); ?><div class="content bemora-subscription plan-change"><?php page_header('MINHA ASSINATURA','Evoluir meu plano','Escolha quando alterar seu plano e mantenha seus dados e benefícios.'); ?>
<a href="/minha-assinatura" class="plan-back"><i class="bi bi-arrow-left"></i> Voltar à minha assinatura</a>
<?php if($flash): ?><div class="alert <?= $flash['type']==='success'?'alert-success':'alert-danger' ?>" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
<?php if(!$order): ?><section class="sub-card"><h2>Não encontramos uma assinatura ativa vinculada</h2><p>Para comparar a evolução, precisamos identificar sua contratação atual.</p></section>
<?php else: ?>
<section class="sub-card plan-current"><div><span class="sub-kicker">SEU PLANO ATUAL</span><h2><?= e($order['plan_name']) ?></h2><p><?= e($cycles[$order['billing_cycle']]??$order['billing_cycle']) ?> · <?= (int)$order['max_members'] ?> <?= (int)$order['max_members']===1?'pessoa':'pessoas' ?> · <?= bemora_plan_price((float)($order['recurring_amount']??$order['amount'])) ?> por período</p></div><span class="sub-pill">Sua assinatura permanece ativa</span></section>
<?php if(!$migrationReady): ?><div class="alert alert-warning">Execute a migration 20261102_028_plan_change_requests em Administração → Manutenção para habilitar solicitações.</div><?php endif; ?>
<div class="alert alert-info" role="status">Upgrades imediatos, inclusive durante a cortesia, geram uma cobrança proporcional no Asaas Sandbox. A primeira cobrança recorrente mantém a data original do benefício, desde que esteja consistente no Asaas. O novo limite de membros só será liberado após a confirmação do pagamento. Trocas na renovação e downgrades ainda estão em homologação.</div>
<?php if($pending): ?><div class="alert alert-info">Há uma troca agendada ou uma alteração aguardando conciliação. Confira o histórico abaixo antes de tentar novamente.</div><?php endif; ?>
<div class="plan-cycle-tabs" role="group" aria-label="Filtrar periodicidade"><?php foreach($cycles as $key=>$label): ?><button type="button" data-cycle="<?= e($key) ?>" aria-pressed="<?= $key===$order['billing_cycle']?'true':'false' ?>" class="<?= $key===$order['billing_cycle']?'selected':'' ?>"><?= e($label) ?></button><?php endforeach; ?></div>
<div class="plan-options">
<?php foreach($plans as $p):$current=(int)$p['id']===(int)$order['plan_id'];$amount=bemora_plan_promo_active($p)?(float)$p['promo_price']:(float)$p['price'];$enough=(int)$p['max_members'] >= $memberCount; ?>
<article class="plan-option <?= $current?'is-current':'' ?>" data-cycle="<?= e($p['billing_cycle']) ?>" <?= $p['billing_cycle']!==$order['billing_cycle']?'hidden':'' ?>><div class="plan-option-top"><span><?= e($cycles[$p['billing_cycle']]) ?></span><?php if($current): ?><strong>Seu plano</strong><?php elseif((int)$p['max_members']>(int)$order['max_members']): ?><strong>Mais espaço</strong><?php endif; ?></div>
<h2><?= e($p['name']) ?></h2><p class="plan-option-description"><?= nl2br(e($p['details'])) ?></p><div class="plan-option-price"><?= bemora_plan_price($amount) ?><small>/<?= e(strtolower($cycles[$p['billing_cycle']])) ?></small></div><p class="plan-option-limit"><i class="bi bi-people"></i> Até <?= (int)$p['max_members'] ?> <?= (int)$p['max_members']===1?'membro':'membros' ?></p>
<?php if($current): ?><div class="plan-option-disabled">Plano contratado</div>
<?php elseif(!$enough): ?><div class="plan-option-disabled">Incompatível com os <?= $memberCount ?> membros atuais</div>
<?php elseif(!$isAdmin): ?><div class="plan-option-disabled">Somente o administrador pode solicitar</div>
<?php elseif($p['billing_cycle']!==$order['billing_cycle'] || $amount<=(float)($order['recurring_amount']??$order['amount'])): ?><div class="plan-option-disabled">Troca disponível após implementar a renovação</div>
<?php elseif($pending||!$migrationReady): ?><div class="plan-option-disabled">Solicitação indisponível</div>
<?php else: ?><form method="post" action="/evoluir-plano/solicitar" data-plan-name="<?= e($p['name']) ?>" data-plan-price="<?= e(bemora_plan_price($amount)) ?>" data-plan-cycle="<?= e($cycles[$p['billing_cycle']]) ?>" data-plan-members="<?= (int)$p['max_members'] ?>"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="plan_id" value="<?= (int)$p['id'] ?>"><fieldset class="change-mode"><legend>Quando deseja trocar?</legend><label><input type="radio" name="change_mode" value="immediate" required> Agora, com cobrança proporcional</label><label><input type="radio" name="change_mode" value="renewal" disabled> Na próxima renovação (em breve)</label></fieldset><button type="submit" class="sub-btn">Gerar cobrança de upgrade <i class="bi bi-arrow-right"></i></button></form><?php endif; ?></article>
<?php endforeach; ?></div>
<section class="sub-card"><span class="sub-kicker">COMO FUNCIONA</span><h2>Uma mudança segura, sem novas assinaturas</h2><p>Para upgrades na mesma periodicidade, inclusive durante a cortesia, geramos uma cobrança proporcional pelo período restante no Asaas Sandbox e preservamos o vencimento original da assinatura. Seu plano só muda após confirmação de pagamento. Downgrades, mudanças de periodicidade e trocas na renovação permanecem indisponíveis até a implementação do ciclo de confirmação apropriado.</p></section>
<?php if($requests): ?><section class="sub-card"><h2>Solicitações recentes</h2><div class="sub-table-scroll"><table class="sub-table"><thead><tr><th>Data</th><th>Plano solicitado</th><th>Valor de referência</th><th>Efetivação</th><th>Situação</th><th>Cobrança</th></tr></thead><tbody><?php foreach($requests as $r): ?><tr><td><?= e(date('d/m/Y',strtotime($r['created_at']))) ?></td><td><?= e($r['target_name'].' — '.($cycles[$r['target_cycle']]??$r['target_cycle'])) ?></td><td><?= bemora_plan_price((float)$r['quoted_amount']) ?></td><td><?= !empty($r['effective_at'])?e(date('d/m/Y',strtotime($r['effective_at']))):'Imediata' ?></td><td><?= e(['pending'=>'Solicitação antiga (substituível)','superseded'=>'Substituída','approved'=>'Aprovada','rejected'=>'Recusada','completed'=>'Concluída','processing'=>'Processando','reconciliation_required'=>'Conciliação necessária','scheduled'=>'Agendada','awaiting_payment'=>'Aguardando pagamento','payment_confirmed'=>'Pagamento confirmado'][$r['status']]??$r['status']) ?></td><td><?php if($r['status']==='awaiting_payment' && !empty($r['invoice_url']) && bemora_asaas_invoice_url(['invoiceUrl'=>$r['invoice_url']])): ?><a href="/pagamento?tipo=upgrade&amp;id=<?= (int)$r['id'] ?>">Pagar no Bemora →</a><?php elseif($r['status']==='completed'): ?><span class="plan-payment-done"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Pago e ativado</span><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></section><?php endif; ?>
<?php endif; ?></div></main></div>
<dialog id="plan-confirm-dialog" class="bemora-plan-confirm" aria-labelledby="plan-confirm-title" aria-describedby="plan-confirm-description">
  <div class="plan-confirm-icon"><i class="bi bi-arrow-left-right" aria-hidden="true"></i></div>
  <span class="sub-kicker">CONFIRMAÇÃO DA ASSINATURA</span>
  <h2 id="plan-confirm-title">Confirmar evolução do plano?</h2>
  <p id="plan-confirm-description">Confira os detalhes da mudança antes de continuar.</p>
  <div class="plan-confirm-summary">
    <div><small>Novo plano</small><strong id="plan-confirm-name"></strong></div>
    <div><small>Valor recorrente</small><strong id="plan-confirm-price"></strong></div>
    <div><small>Quando</small><strong id="plan-confirm-when"></strong></div>
    <div><small>Limite</small><strong id="plan-confirm-members"></strong></div>
  </div>
  <p class="plan-confirm-note" id="plan-confirm-note"></p>
  <div class="plan-confirm-actions"><button type="button" class="plan-confirm-cancel" id="plan-confirm-cancel">Voltar</button><button type="button" class="sub-btn" id="plan-confirm-accept">Confirmar alteração <i class="bi bi-check-lg"></i></button></div>
</dialog><script src="/assets/bemora-plan-change.js?v=3" defer></script><script>window.addEventListener("pageshow",function(event){if(event.persisted)window.location.reload()});</script></body></html>

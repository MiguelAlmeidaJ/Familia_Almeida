<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/layout.php';
require_once __DIR__.'/../includes/asaas.php';
require_once __DIR__.'/../includes/single_offer.php';
require_once __DIR__.'/../includes/subscription_cancellation.php';
require_once __DIR__.'/../includes/paid_addon_changes.php';
$user=require_family_access();$pdo=db();$tid=(int)$user['tenant_id'];
$csrf=csrf_token();$flash=pull_flash();
$offer=bemora_single_offer_enabled($pdo)?bemora_single_offer($pdo):null;
$q=$pdo->prepare("SELECT * FROM subscription_orders WHERE activated_tenant_id=? AND single_offer=1 AND status='completed' ORDER BY id DESC LIMIT 1");
$q->execute([$tid]);$order=$q->fetch(PDO::FETCH_ASSOC);
if(!$offer||!$order){flash('error','Esta configuração de adicionais está disponível apenas para as novas assinaturas Bemora.');redirect_to('/minha-assinatura');}
$q=$pdo->prepare('SELECT role FROM tenant_users WHERE tenant_id=? AND user_id=?');$q->execute([$tid,(int)$user['id']]);$admin=$q->fetchColumn()==='family_admin';
$ready=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='bemora_addon_changes'")->fetchColumn()===1;
$changes=[];
if($ready){
 $pendingQuery=$pdo->prepare("SELECT asaas_payment_id FROM bemora_addon_changes WHERE tenant_id=? AND status IN ('awaiting_payment','payment_confirmed') AND asaas_payment_id IS NOT NULL ORDER BY id DESC LIMIT 3");
 $pendingQuery->execute([$tid]);
 foreach($pendingQuery->fetchAll(PDO::FETCH_COLUMN) as $paymentId){
  try{bemora_apply_paid_addon_change($pdo,(string)$paymentId);}
  catch(Throwable $error){error_log('Bemora addon display reconciliation deferred to scheduled job.');}
 }
 $q=$pdo->prepare('SELECT * FROM bemora_addon_changes WHERE tenant_id=? ORDER BY id DESC LIMIT 8');$q->execute([$tid]);$changes=$q->fetchAll(PDO::FETCH_ASSOC);}
$pending=false;foreach($changes as $c)if(in_array($c['status'],['processing','awaiting_payment','payment_confirmed','reconciliation_required'],true))$pending=true;
$canUse=$admin&&$ready&&!$pending&&!bemora_cancellation_for_family($pdo,$tid)
 &&(bemora_asaas_mode()!=='production'||(string)config('asaas','addon_upgrades_production_enabled','0')==='1');
// Reload after reconciliation; newly confirmed upgrades must be displayed immediately.
$q=$pdo->prepare("SELECT * FROM subscription_orders WHERE id=? AND activated_tenant_id=?");
$q->execute([(int)$order['id'],$tid]);$order=$q->fetch(PDO::FETCH_ASSOC);
$oldMembers=(int)$order['max_members'];$oldShopping=(int)$order['shopping_addon']===1;
$oldQuote=bemora_single_quote($offer,(string)$order['billing_cycle'],$oldMembers,$oldShopping);
$baseRecurring=(float)$order['recurring_amount'];
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Adicionar recursos • Bemora</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"><link rel="stylesheet" href="/assets/style.css"><link rel="stylesheet" href="/assets/bemora.css?v=2"><link rel="stylesheet" href="/assets/bemora-finance.css?v=2"><link rel="stylesheet" href="/assets/bemora-finish.css?v=3"><?php brand_head_tags(); ?><link rel="stylesheet" href="/assets/bemora-sidebar.css?v=6"><link rel="stylesheet" href="/assets/bemora-subscription.css?v=1"><link rel="stylesheet" href="/assets/bemora-addon-account.css?v=1"></head>
<body class="bemora-finance"><div class="shell ref-shell"><?php render_sidebar('minha-assinatura',$csrf); ?><main><?php render_topbar($user); ?><div class="content bemora-subscription">
<?php page_header('MINHA ASSINATURA','Adicionar integrantes e Compras','Personalize seu Bemora sem contratar uma segunda assinatura.'); ?>
<a class="addon-back" href="/minha-assinatura"><i class="bi bi-arrow-left"></i> Minha assinatura</a>
<?php if($flash): ?><div class="alert alert-info" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
<section class="sub-card"><span class="sub-kicker">ASSINATURA ATUAL</span><h2><?= e($order['plan_name']) ?></h2><p><?= (int)$order['max_members'] ?> pessoas · Compras <?= $oldShopping?'incluídas':'não incluídas' ?> · <?= e(bemora_plan_cycles()[$order['billing_cycle']]??$order['billing_cycle']) ?></p><strong><?= bemora_plan_price($baseRecurring) ?> por período</strong></section>
<?php if(!$ready): ?><div class="alert alert-warning">Execute a migration 20261104_046_addon_changes em Manutenção.</div><?php endif; ?>
<?php if(bemora_asaas_mode()==='production' && (string)config('asaas','addon_upgrades_production_enabled','0')!=='1'): ?><div class="alert alert-info">A contratação de novos adicionais em assinaturas ativas ainda está em homologação para produção. Entre em contato com o suporte para solicitar uma alteração.</div><?php endif; ?>
<?php if($pending): ?><div class="alert alert-warning">Existe uma solicitação aguardando pagamento ou conciliação. Não é possível criar outra até sua conclusão.</div><?php endif; ?>
<section class="sub-card addon-config"><h2>Escolha seus adicionais</h2>
<form method="post" action="/minha-assinatura/adicionais/acao" id="addon-upgrade-form"
 data-base="<?= e((string)$offer['base_monthly']) ?>" data-seat="<?= e((string)$offer['extra_member_monthly']) ?>"
 data-shop="<?= e((string)$offer['shopping_monthly']) ?>" data-discount="<?= e((string)($order['billing_cycle']==='semiannual'?$offer['semi_discount']:($order['billing_cycle']==='annual'?$offer['annual_discount']:0))) ?>"
 data-months="<?= (int)$oldQuote['months'] ?>" data-old-quote="<?= e((string)$oldQuote['total']) ?>"
 data-old-recurring="<?= e((string)$baseRecurring) ?>">
<input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
<label>Quantidade total de pessoas<select name="members" <?= !$canUse?'disabled':'' ?>><?php for($n=$oldMembers;$n<=(int)$offer['max_members'];$n++): ?><option value="<?= $n ?>"><?= $n ?> <?= $n===1?'pessoa':'pessoas' ?></option><?php endfor; ?></select></label>
<label class="addon-option"><input type="checkbox" name="shopping_addon" value="1" <?= $oldShopping?'checked disabled':(!$canUse?'disabled':'') ?>><span>Adicionar módulo Compras + scanner</span></label><?php if($oldShopping): ?><input type="hidden" name="shopping_addon" value="1"><?php endif; ?>
<div class="addon-price"><small>Novo total recorrente estimado</small><strong id="addon-upgrade-total"><?= bemora_plan_price($baseRecurring) ?></strong><small>O pagamento imediato será proporcional aos dias restantes; o valor exato será confirmado pelo Asaas.</small></div>
<button type="submit" <?= !$canUse?'disabled':'' ?>>Solicitar adicionais</button>
</form></section>
<?php if($changes): ?><section class="sub-card"><h2>Solicitações recentes</h2><div class="addon-history"><?php foreach($changes as $change): ?><div><span><?= (int)$change['new_members'] ?> pessoas · Compras <?= $change['new_shopping']?'sim':'não' ?></span><strong><?= e($change['status']) ?></strong><?php if($change['status']==='awaiting_payment' && $change['invoice_url']): ?><a href="<?= e($change['invoice_url']) ?>" target="_blank" rel="noopener noreferrer">Pagar adicional</a><?php endif; ?></div><?php endforeach; ?></div></section><?php endif; ?>
</div></main></div><script src="/assets/bemora-addon-account.js?v=1" defer></script></body></html>

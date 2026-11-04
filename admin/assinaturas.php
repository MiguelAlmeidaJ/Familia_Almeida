<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/admin_layout.php';
require_once __DIR__.'/../includes/plans.php';
require_once __DIR__.'/../includes/admin_test_cleanup.php';
require_admin_access();
$pdo=db();$cleanupReady=bemora_cleanup_ready($pdo);$exists=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='subscription_orders'")->fetchColumn()===1;
$cancelReady=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='subscription_cancellations'")->fetchColumn()===1;
$removedColumn=$cleanupReady?'o.admin_subscription_removed_at':'NULL AS admin_subscription_removed_at';
$settledColumn=$cleanupReady?'o.admin_subscription_had_settled_payments':'0 AS admin_subscription_had_settled_payments';
$orders=$exists?$pdo->query("SELECT o.id,o.plan_name,o.billing_cycle,o.amount,o.buyer_name,o.buyer_email,o.status,o.asaas_subscription_id,o.activated_tenant_id,$removedColumn,$settledColumn,o.created_at,o.paid_at FROM subscription_orders o ORDER BY o.id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC):[];
$cancellations=[];
if($cancelReady){
 $q=$pdo->query("SELECT c.tenant_id,c.order_id,c.status,c.access_until,c.requested_at FROM subscription_cancellations c");
 foreach($q->fetchAll(PDO::FETCH_ASSOC) as $c)$cancellations[(int)$c['order_id']]=$c;
}
admin_shell_start('subscriptions','Assinaturas','Acompanhe solicitações de pagamento e assinaturas vinculadas às famílias.');
?><link rel="stylesheet" href="/assets/bemora-plans-admin.css?v=1">
<link rel="stylesheet" href="/assets/bemora-admin-cleanup.css?v=1">
<div class="plans-admin">
<?php if(!$exists): ?><div class="notice">Execute a migration 20261030_024_asaas_orders em Manutenção para disponibilizar o acompanhamento de assinaturas.</div><?php endif; ?>
<div class="admin-cleanup-note"><strong>Limpeza de testes:</strong> você pode cancelar a recorrência no Asaas e excluir pedidos de teste sem pagamento. Assinaturas pagas e seus registros financeiros são preservados. Antes de excluir um espaço, remova os pedidos de teste vinculados a ele. Cada ação exige uma confirmação digitada.<?php if(!$cleanupReady): ?> <strong>Execute a migration 20261104_048_admin_cleanup.</strong><?php endif; ?></div>
<section class="panel"><div class="panel-head"><div><h2>Últimas contratações</h2><p class="sub">Situações informadas por eventos confirmados do Asaas. Até 100 pedidos recentes.</p></div><span class="member"><?= count($orders) ?> pedidos</span></div>
<div class="table-wrap"><table><thead><tr><th>Cliente</th><th>Plano</th><th>Valor</th><th>Situação</th><th>Família</th><th>Criado em</th><th>Ações</th></tr></thead><tbody>
<?php foreach($orders as $order): ?><tr>
<td><strong><?= e($order['buyer_name']) ?></strong><br><small><?= e($order['buyer_email']) ?></small></td>
<td><?= e($order['plan_name']) ?><br><small><?= e(bemora_plan_cycles()[$order['billing_cycle']]??$order['billing_cycle']) ?></small></td>
<td><?= e(bemora_plan_price((float)$order['amount'])) ?></td>
<td><span class="member"><?= e(['initiated'=>'Iniciado','customer_created'=>'Cliente cadastrado','awaiting_payment'=>'Aguardando pagamento','paid'=>'Pago · aguardando cadastro','completed'=>'Família cadastrada','payment_attention'=>'Pagamento pendente'][$order['status']]??$order['status']) ?></span>
<?php $cancel=$cancellations[(int)$order['id']]??null; if($cancel): ?>
<br><small class="plans-cancel-status"><?= $cancel['status']==='confirmed'?'Renovação cancelada': 'Cancelamento em conciliação' ?><?= $cancel['status']==='confirmed' && $cancel['access_until']?' · Acesso até '.e(date('d/m/Y',strtotime($cancel['access_until']))):'' ?></small>
<?php endif; ?></td>
<td><?= $order['activated_tenant_id']?(int)$order['activated_tenant_id']:'—' ?></td><td><?= e(date('d/m/Y H:i',strtotime((string)$order['created_at']))) ?></td>
<td><div class="admin-cleanup-actions">
<?php if($cleanupReady && $order['asaas_subscription_id'] && !$order['admin_subscription_removed_at']): ?>
<form method="post" action="/admin/limpeza/acao" data-admin-cleanup
 data-confirm-text="CANCELAR ASSINATURA <?= (int)$order['id'] ?>"
 data-confirm-description="Esta ação remove definitivamente a recorrência no Asaas e também as cobranças pendentes ou vencidas associadas. Cobranças já pagas permanecem no Asaas. Não poderá ser desfeita." class="admin-danger-form">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="cancel_subscription"><input type="hidden" name="id" value="<?= (int)$order['id'] ?>"><input type="hidden" name="confirmation" value="">
<button type="submit" class="admin-danger-button cancel" title="Remover recorrência no Asaas"><i class="bi bi-x-circle" aria-hidden="true"></i> Cancelar assinatura</button></form>
<?php elseif($order['admin_subscription_removed_at']): ?><span class="plans-cancel-status">Removida do Asaas</span><?php endif; ?>
<?php if($cleanupReady && !$order['paid_at'] && !(int)$order['admin_subscription_had_settled_payments'] && !in_array($order['status'],['paid','completed','payment_attention'],true) && (!$order['asaas_subscription_id'] || $order['admin_subscription_removed_at'])): ?>
<form method="post" action="/admin/limpeza/acao" data-admin-cleanup
 data-confirm-text="EXCLUIR PEDIDO <?= (int)$order['id'] ?>"
 data-confirm-description="Apaga definitivamente este pedido de teste sem pagamento e seus dados locais associados. Não é possível desfazer. Confira que a recorrência foi removida antes de prosseguir." class="admin-danger-form">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete_order"><input type="hidden" name="id" value="<?= (int)$order['id'] ?>"><input type="hidden" name="confirmation" value="">
<button type="submit" class="admin-danger-button" title="Excluir pedido sem pagamento"><i class="bi bi-trash3" aria-hidden="true"></i> Excluir teste</button></form>
<?php endif; ?>
</div></td></tr>
<?php endforeach; ?>
<?php if(!$orders): ?><tr><td colspan="7">Nenhuma contratação foi iniciada.</td></tr><?php endif; ?>
</tbody></table></div></section><div class="plans-admin-note"><i class="bi bi-shield-lock"></i> Pagamentos confirmados por webhook. Para cancelamentos, cobranças em atraso e reembolsos, consulte também o painel do Asaas. A automação de suspensão e renovação de acessos ainda requer validação antes do uso comercial.</div>
</div><script src="/assets/bemora-admin-cleanup.js?v=1" defer></script><?php admin_shell_end(); ?>
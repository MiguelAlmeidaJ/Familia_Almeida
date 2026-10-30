<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/admin_layout.php';
require_once __DIR__.'/../includes/plans.php';
require_admin_access();
$pdo=db();$exists=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='subscription_orders'")->fetchColumn()===1;
$orders=$exists?$pdo->query("SELECT o.id,o.plan_name,o.billing_cycle,o.amount,o.buyer_name,o.buyer_email,o.status,o.asaas_subscription_id,o.activated_tenant_id,o.created_at,o.paid_at FROM subscription_orders o ORDER BY o.id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC):[];
admin_shell_start('subscriptions','Assinaturas','Acompanhe solicitações de pagamento e assinaturas vinculadas às famílias.');
?><link rel="stylesheet" href="/assets/bemora-plans-admin.css?v=1">
<div class="plans-admin">
<?php if(!$exists): ?><div class="notice">Execute a migration 20261030_024_asaas_orders em Manutenção para disponibilizar o acompanhamento de assinaturas.</div><?php endif; ?>
<section class="panel"><div class="panel-head"><div><h2>Últimas contratações</h2><p class="sub">Situações informadas por eventos confirmados do Asaas. Até 100 pedidos recentes.</p></div><span class="member"><?= count($orders) ?> pedidos</span></div>
<div class="table-wrap"><table><thead><tr><th>Cliente</th><th>Plano</th><th>Valor</th><th>Situação</th><th>Família</th><th>Criado em</th></tr></thead><tbody>
<?php foreach($orders as $order): ?><tr>
<td><strong><?= e($order['buyer_name']) ?></strong><br><small><?= e($order['buyer_email']) ?></small></td>
<td><?= e($order['plan_name']) ?><br><small><?= e(bemora_plan_cycles()[$order['billing_cycle']]??$order['billing_cycle']) ?></small></td>
<td><?= e(bemora_plan_price((float)$order['amount'])) ?></td>
<td><span class="member"><?= e(['initiated'=>'Iniciado','customer_created'=>'Cliente cadastrado','awaiting_payment'=>'Aguardando pagamento','paid'=>'Pago · aguardando cadastro','completed'=>'Família cadastrada','payment_attention'=>'Pagamento pendente'][$order['status']]??$order['status']) ?></span></td>
<td><?= $order['activated_tenant_id']?(int)$order['activated_tenant_id']:'—' ?></td><td><?= e(date('d/m/Y H:i',strtotime((string)$order['created_at']))) ?></td></tr>
<?php endforeach; ?>
<?php if(!$orders): ?><tr><td colspan="6">Nenhuma contratação foi iniciada.</td></tr><?php endif; ?>
</tbody></table></div></section><div class="plans-admin-note"><i class="bi bi-shield-lock"></i> Pagamentos confirmados por webhook. Para cancelamentos, cobranças em atraso e reembolsos, consulte também o painel do Asaas. A automação de suspensão e renovação de acessos ainda requer validação antes do uso comercial.</div>
</div><?php admin_shell_end(); ?>
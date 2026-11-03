<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/admin_layout.php';
require_admin_access();
$pdo=db();$flash=pull_flash();
$ready=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='support_tickets'")->fetchColumn()===1;
$tickets=[];$counts=['open'=>0,'in_progress'=>0,'resolved'=>0];
if($ready){
 $q=$pdo->query("SELECT s.*,t.name family_name,u.name user_name,u.email user_email FROM support_tickets s JOIN tenants t ON t.id=s.tenant_id JOIN users u ON u.id=s.user_id ORDER BY CASE s.status WHEN 'open' THEN 0 WHEN 'in_progress' THEN 1 ELSE 2 END,s.created_at DESC LIMIT 200");
 $tickets=$q->fetchAll(PDO::FETCH_ASSOC);
 foreach($pdo->query('SELECT status,COUNT(*) total FROM support_tickets GROUP BY status') as $r){$counts[$r['status']]=(int)$r['total'];}
}
admin_shell_start('support','Suporte','Relatos enviados pelas famílias e acompanhamento de atendimento.');
?><link rel="stylesheet" href="/assets/bemora-admin-support.css?v=1">
<?php if($flash): ?><div class="notice" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
<?php if(!$ready): ?><section class="panel"><p class="notice">Execute a migration 20261103_036_support_tickets em Manutenção para ativar o suporte.</p></section>
<?php else: ?>
<div class="metrics family-metrics">
<article class="metric"><span>Abertos</span><strong><?= $counts['open'] ?></strong></article>
<article class="metric"><span>Em andamento</span><strong><?= $counts['in_progress'] ?></strong></article>
<article class="metric"><span>Resolvidos</span><strong><?= $counts['resolved'] ?></strong></article></div>
<section class="panel"><div class="panel-head"><div><h2>Solicitações recebidas</h2><p class="sub">Últimos 200 relatos. Use os contatos informados pelos clientes para retornar.</p></div><span class="member"><?= count($tickets) ?> exibidos</span></div>
<?php if(!$tickets): ?><p class="sub">Nenhum problema relatado até o momento.</p><?php endif; ?>
<div class="support-items">
<?php foreach($tickets as $ticket): ?>
<article class="support-ticket" id="ticket-<?= (int)$ticket['id'] ?>">
<div class="support-ticket-top"><div><span class="support-ticket-id">#<?= (int)$ticket['id'] ?> · <?= e(date('d/m/Y H:i',strtotime($ticket['created_at']))) ?></span><h3><?= e($ticket['family_name']) ?></h3><p><?= e($ticket['user_name']) ?> · <?= e($ticket['user_email']) ?></p></div><span class="support-status status-<?= e($ticket['status']) ?>"><?= e(['open'=>'Aberto','in_progress'=>'Em andamento','resolved'=>'Resolvido'][$ticket['status']]??$ticket['status']) ?></span></div>
<div class="support-description"><?= nl2br(e($ticket['description'])) ?></div>
<div class="support-ticket-foot"><div class="support-contact"><strong>Contato escolhido:</strong> <?php if($ticket['contact_method']==='whatsapp'): ?><a href="https://wa.me/55<?= e($ticket['contact_value']) ?>" target="_blank" rel="noopener noreferrer"><i class="bi bi-whatsapp" aria-hidden="true"></i> <?= e($ticket['contact_value']) ?></a><?php else: ?><a href="mailto:<?= e($ticket['contact_value']) ?>"><i class="bi bi-envelope" aria-hidden="true"></i> <?= e($ticket['contact_value']) ?></a><?php endif; ?><span class="support-email-status">E-mail admin: <?= e(['sent'=>'enviado','failed'=>'falhou','pending'=>'pendente'][$ticket['email_status']]??$ticket['email_status']) ?></span></div>
<form action="/admin/support_action.php" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$ticket['id'] ?>"><label>Atendimento<select name="status" aria-label="Situação do relato #<?= (int)$ticket['id'] ?>"><option value="open" <?= $ticket['status']==='open'?'selected':'' ?>>Aberto</option><option value="in_progress" <?= $ticket['status']==='in_progress'?'selected':'' ?>>Em andamento</option><option value="resolved" <?= $ticket['status']==='resolved'?'selected':'' ?>>Resolvido</option></select></label><button type="submit" class="family-create-button">Salvar</button></form></div>
</article><?php endforeach; ?></div></section><?php endif; ?>
<?php admin_shell_end(); ?>

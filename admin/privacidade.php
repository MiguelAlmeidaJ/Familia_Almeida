<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/admin_layout.php';
require_admin_access();
$pdo=db();$flash=pull_flash();
$ready=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='privacy_requests'")->fetchColumn()===1;
$types=['access'=>'Acesso','correction'=>'Correção','deletion'=>'Exclusão','portability'=>'Portabilidade','information'=>'Informações','objection'=>'Oposição'];
$statuses=['received'=>'Recebida','identity_verification'=>'Verificar identidade','in_progress'=>'Em análise','completed'=>'Concluída','denied'=>'Encerrada com justificativa'];
$requests=[];
if($ready){
 $q=$pdo->query("SELECT r.*,u.name user_name,u.email user_email,t.name tenant_name FROM privacy_requests r JOIN users u ON u.id=r.user_id JOIN tenants t ON t.id=r.tenant_id ORDER BY CASE r.status WHEN 'received' THEN 0 WHEN 'identity_verification' THEN 1 WHEN 'in_progress' THEN 2 ELSE 3 END,r.created_at DESC LIMIT 150");
 $requests=$q->fetchAll(PDO::FETCH_ASSOC);
}
admin_shell_start('privacy','Privacidade e LGPD','Solicitações de titulares, verificação de identidade e decisões documentadas.');
?><link rel="stylesheet" href="/assets/bemora-admin-privacy.css?v=1">
<?php if($flash): ?><div class="notice" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
<section class="panel"><div class="panel-head"><div><h2>Solicitações de dados pessoais</h2><p class="sub">Registre a análise e a resposta. Exclusões não são executadas automaticamente; verifique retenção legal, documentos fiscais e direitos de outros integrantes.</p></div><span class="member"><?= count($requests) ?> exibidas</span></div>
<?php if(!$ready): ?><div class="notice">Execute a migration 20261103_040_privacy_requests_and_contracts no painel Manutenção.</div>
<?php elseif(!$requests): ?><p class="sub">Nenhuma solicitação registrada.</p>
<?php else: ?>
<div class="privacy-admin-list">
<?php foreach($requests as $r): ?>
<article class="privacy-admin-ticket">
<div class="privacy-admin-head"><div><span class="privacy-admin-id">#<?= (int)$r['id'] ?> · <?= e(date('d/m/Y H:i',strtotime($r['created_at']))) ?></span><h3><?= e($types[$r['request_type']]??$r['request_type']) ?></h3><p><?= e($r['user_name']) ?> · <?= e($r['user_email']) ?> · <?= e($r['tenant_name']) ?></p></div><span class="privacy-admin-status"><?= e($statuses[$r['status']]??$r['status']) ?></span></div>
<div class="privacy-admin-description"><?= nl2br(e($r['description'])) ?></div>
<form method="post" action="/admin/privacidade/acao">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
<div class="privacy-admin-fields"><label>Situação<select name="status" required>
<?php foreach($statuses as $key=>$label): ?><option value="<?= e($key) ?>" <?= $r['status']===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?>
</select></label><label>Resposta ou encaminhamento<textarea name="response_note" maxlength="3000" rows="3" placeholder="Descreva a providência adotada, a necessidade de verificação de identidade ou a justificativa de retenção..." required><?= e($r['response_note']??'') ?></textarea></label></div>
<div class="privacy-admin-actions"><p>Registre apenas informações necessárias. A resposta ficará visível ao titular autenticado.</p><button type="submit">Registrar decisão</button></div>
</form></article>
<?php endforeach; ?></div><?php endif; ?>
</section>
<?php admin_shell_end(); ?>
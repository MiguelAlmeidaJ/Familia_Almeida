<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/admin_layout.php';
require_admin_access();
$pdo=db();$flash=pull_flash();
$tenants=$pdo->query("SELECT t.id,t.name,t.slug,t.status,t.active,COUNT(tu.user_id) members FROM tenants t LEFT JOIN tenant_users tu ON tu.tenant_id=t.id GROUP BY t.id,t.name,t.slug,t.status,t.active ORDER BY t.name")->fetchAll();
$invitations=$pdo->query("SELECT id,max_members,expires_at,used_at,revoked_at FROM family_invitations ORDER BY id DESC LIMIT 20")->fetchAll();
$pending=$pdo->query("SELECT r.family_name,r.member_count,r.submitted_at,r.id FROM family_registrations r WHERE state='pending' ORDER BY r.id DESC")->fetchAll();
$token=$_SESSION['new_family_invite']??null;unset($_SESSION['new_family_invite']);
$origin=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'?'https':'http').'://'.($_SERVER['HTTP_HOST']??'localhost');
admin_shell_start('tenants','Famílias','Gerencie famílias, convites e cadastros pendentes.');
?>
<?php if($flash): ?><div class="notice" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
<div class="metrics"><article class="metric"><span>Famílias cadastradas</span><strong><?= count($tenants) ?></strong></article><article class="metric"><span>Cadastros pendentes</span><strong><?= count($pending) ?></strong></article></div>
<section class="panel"><div class="panel-head"><div><h2>Gerar convite</h2><p class="sub">Crie um link único, válido por 72 horas.</p></div></div>
<form method="post" action="/admin/family_action.php" class="admin-brand-form" style="max-width:490px">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="invite"><label for="max">Máximo de membros</label><input id="max" type="number" min="1" max="30" name="max_members" value="5" required><button class="button" type="submit">Gerar link de convite</button></form>
<?php if($token): ?><div class="notice"><strong>Copie agora o link (ele será mostrado apenas uma vez):</strong><p><input style="width:100%;padding:10px" readonly value="<?= e($origin.'/convite-familia.php?token='.$token) ?>" onclick="this.select()"></p></div><?php endif; ?>
</section>
<section class="panel"><div class="panel-head"><div><h2>Famílias existentes</h2></div><span class="member"><?= count($tenants) ?> registros</span></div>
<div class="table-wrap"><table><thead><tr><th>Família</th><th>Membros</th><th>Situação</th><th>Ações</th></tr></thead><tbody>
<?php foreach($tenants as $t): ?><tr><td><strong><?= e($t['name']) ?></strong><br><small><?= e($t['slug']) ?></small></td><td><?= (int)$t['members'] ?></td><td><span class="member"><?= e(['active'=>'Ativa','archived'=>'Arquivada','maintenance'=>'Manutenção','pending'=>'Pendente'][$t['status']]??$t['status']) ?></span></td>
<td><div class="actions"><form method="post" action="/admin/family_action.php" onsubmit="return confirm('Alterar o nome desta família?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="rename"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><input name="name" value="<?= e($t['name']) ?>" aria-label="Nome da família" required maxlength="160"><button title="Salvar nome" aria-label="Salvar nome" class="button secondary">✎</button></form>
<?php foreach(['archive'=>'Arquivar','maintenance'=>'Manutenção','activate'=>'Ativar'] as $action=>$label): ?><form method="post" action="/admin/family_action.php" onsubmit="return confirm('<?= e($label) ?> esta família?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="<?= e($action) ?>"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><button class="button secondary" type="submit" title="<?= e($label) ?>" aria-label="<?= e($label) ?>"><?= ['archive'=>'▣','maintenance'=>'⚒','activate'=>'✓'][$action] ?></button></form><?php endforeach; ?></div></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="panel"><h2>Cadastros aguardando análise</h2><p class="sub">Os dados são armazenados, mas nenhum acesso financeiro é habilitado antes da conclusão do isolamento multi-tenant.</p><div class="table-wrap"><table><thead><tr><th>Família</th><th>Membros</th><th>Recebido em</th><th>Estado</th></tr></thead><tbody><?php foreach($pending as $r): ?><tr><td><?= e($r['family_name']) ?></td><td><?= (int)$r['member_count'] ?></td><td><?= e($r['submitted_at']) ?></td><td>Pendente</td></tr><?php endforeach; ?><?php if(!$pending): ?><tr><td colspan="4">Nenhum cadastro pendente.</td></tr><?php endif; ?></tbody></table></div></section>
<section class="panel"><h2>Convites recentes</h2><div class="table-wrap"><table><thead><tr><th>ID</th><th>Limite</th><th>Validade</th><th>Estado</th></tr></thead><tbody><?php foreach($invitations as $inv): ?><tr><td>#<?= (int)$inv['id'] ?></td><td><?= (int)$inv['max_members'] ?></td><td><?= e($inv['expires_at']) ?></td><td><?= $inv['used_at']?'Utilizado':($inv['revoked_at']?'Revogado':'Emitido') ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php admin_shell_end(); ?>

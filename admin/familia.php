<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/admin_layout.php';
require_admin_access();
$pdo=db();$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT);
if(!$id||$id<1){http_response_code(400);exit('Família inválida.');}
$q=$pdo->prepare('SELECT id,name,slug,status,max_members FROM tenants WHERE id=?');$q->execute([$id]);$family=$q->fetch();
if(!$family){http_response_code(404);exit('Família não encontrada.');}
$q=$pdo->prepare("SELECT u.id,u.name,u.email,u.sex,u.birth_date,tu.role
FROM tenant_users tu INNER JOIN users u ON u.id=tu.user_id WHERE tu.tenant_id=? ORDER BY CASE WHEN tu.role='family_admin' THEN 0 ELSE 1 END,u.name");
$q->execute([$id]);$members=$q->fetchAll();
$flash=pull_flash();$csrf=csrf_token();
admin_shell_start('tenants','Gerenciar família','Membros, permissões e limites da família.');
?>
<a href="/admin/tenants" class="family-back"><i class="bi bi-arrow-left"></i> Voltar às famílias</a>
<?php if($flash): ?><div class="notice" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
<section class="panel"><div class="panel-head"><div><h2><?= e($family['name']) ?></h2><p class="sub">Identificador: <?= e($family['slug']) ?> · Situação: <?= e($family['status']) ?></p></div><span class="member"><?= count($members) ?> de <?= (int)$family['max_members'] ?> membros</span></div>
<div class="family-detail-grid">
<form method="post" action="/admin/family_action.php" class="family-detail-form"><h3>Nome da família</h3><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="rename"><input type="hidden" name="id" value="<?= $id ?>"><label for="family-name">Nome</label><input id="family-name" name="name" value="<?= e($family['name']) ?>" minlength="3" maxlength="160" required><button class="family-create-button" type="submit"><i class="bi bi-check-lg"></i> Salvar nome</button></form>
<form method="post" action="/admin/family_action.php" class="family-detail-form"><h3>Limite de membros</h3><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="set_limit"><input type="hidden" name="id" value="<?= $id ?>"><label for="max-members">Máximo permitido (1 a 30)</label><input id="max-members" name="max_members" type="number" min="<?= max(1,count($members)) ?>" max="30" value="<?= (int)$family['max_members'] ?>" required><p class="sub">Não é possível reduzir abaixo dos <?= count($members) ?> membros atuais.</p><button class="family-create-button" type="submit"><i class="bi bi-people"></i> Atualizar limite</button></form>
</div></section>
<section class="panel"><div class="panel-head"><div><h2>Membros cadastrados</h2><p class="sub">Informações e papéis da família</p></div><span class="member"><?= count($members) ?> pessoas</span></div>
<div class="table-wrap"><table><thead><tr><th>Nome</th><th>E-mail</th><th>Sexo</th><th>Nascimento</th><th>Papel</th></tr></thead><tbody>
<?php foreach($members as $m): ?><tr><td><strong><?= e($m['name']) ?></strong></td><td><?= e($m['email']) ?></td><td><?= e(['M'=>'Masculino','F'=>'Feminino'][$m['sex']]??'Não informado') ?></td><td><?= e($m['birth_date']??'—') ?></td><td><span class="member"><?= $m['role']==='family_admin'?'Administrador familiar':'Membro' ?></span></td></tr><?php endforeach; ?>
<?php if(!$members): ?><tr><td colspan="5">Ainda não existem membros vinculados.</td></tr><?php endif; ?></tbody></table></div>
</section>
<section class="panel"><h2>Administrador da família</h2><p class="sub">Escolha o membro responsável pelo gerenciamento familiar. A transferência substitui os administradores familiares atuais.</p>
<?php if($members): ?><form method="post" action="/admin/family_action.php" class="family-detail-form family-admin-select" onsubmit="return confirm('Confirmar a troca do administrador da família?')"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="set_admin"><input type="hidden" name="id" value="<?= $id ?>"><label for="family-admin">Administrador familiar</label><select id="family-admin" name="user_id" required>
<?php foreach($members as $m): ?><option value="<?= (int)$m['id'] ?>" <?= $m['role']==='family_admin'?'selected':'' ?>><?= e($m['name']) ?> (<?= e($m['email']) ?>)</option><?php endforeach; ?>
</select><button type="submit" class="family-create-button"><i class="bi bi-shield-check"></i> Definir administrador</button></form>
<?php else: ?><p class="notice">Cadastre membros antes de definir um administrador.</p><?php endif; ?>
</section>
<?php admin_shell_end(); ?>

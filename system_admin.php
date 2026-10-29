<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/tenant.php';
$user = require_admin_access();
$pdo = db();
$tenants=$pdo->query("SELECT t.id,t.name,t.slug,t.active,COUNT(tu.user_id) AS members FROM tenants t LEFT JOIN tenant_users tu ON tu.tenant_id=t.id GROUP BY t.id,t.name,t.slug,t.active ORDER BY t.name")->fetchAll();
$users=$pdo->query("SELECT u.id,u.name,u.email,u.system_role,GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ', ') AS families FROM users u LEFT JOIN tenant_users tu ON tu.user_id=u.id LEFT JOIN tenants t ON t.id=tu.tenant_id GROUP BY u.id,u.name,u.email,u.system_role ORDER BY u.name")->fetchAll();
$activeCount=count(array_filter($tenants,static fn($t)=>(bool)$t['active']));
require_once __DIR__ . '/includes/admin_layout.php';
admin_shell_start('panel','Painel','Visão geral de famílias, usuários e operação da plataforma.');
?>
<div class="metrics">
<article class="metric"><span>Famílias cadastradas</span><strong><?= count($tenants) ?></strong><small><?= $activeCount ?> ativas</small></article>
<article class="metric"><span>Usuários cadastrados</span><strong><?= count($users) ?></strong><small>Contas na plataforma</small></article>
<article class="metric"><span>Administradores globais</span><strong><?= count(array_filter($users,static fn($u)=>$u['system_role']==='system_admin')) ?></strong><small>Contas privilegiadas</small></article>
</div>
<section class="panel"><div class="panel-head"><div><h2>Tenants</h2><p class="sub">Visualize e acompanhe as famílias cadastradas</p></div><a class="button secondary" href="/admin/tenants">Ver tenants →</a></div><p class="sub">Atualmente existem <?= count($tenants) ?> tenants cadastrados.</p></section>
<section class="panel"><div class="panel-head"><div><h2>Usuários e permissões</h2><p class="sub">Visão geral dos acessos existentes</p></div><span class="member"><?= count($users) ?> usuários</span></div>
<div class="table-wrap"><table><thead><tr><th>Nome</th><th>E-mail</th><th>Papel global</th><th>Famílias vinculadas</th></tr></thead><tbody>
<?php foreach($users as $u): ?><tr><td><strong><?= e($u['name']) ?></strong></td><td><?= e($u['email']) ?></td><td><span class="member"><?= $u['system_role']==='system_admin'?'Administrador global':'Usuário' ?></span></td><td class="muted"><?= e($u['families']?:'Sem família associada') ?></td></tr><?php endforeach ?>
</tbody></table></div></section>
<section class="panel"><div class="panel-head"><div><h2>Ferramentas administrativas</h2><p class="sub">Diagnóstico e configuração da instalação</p></div></div>
<div class="actions"><a class="button" href="/admin/configuracoes">Abrir configurações</a></div>
<p class="notice"><strong>Ambiente em transição multi-tenant.</strong> O isolamento completo das operações financeiras ainda não está concluído. O cadastro de novas famílias permanece indisponível por segurança.</p>
</section>

<?php admin_shell_end(); ?>
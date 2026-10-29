<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/admin_layout.php';
require_admin_access();
$tenants=db()->query("SELECT t.id,t.name,t.slug,t.active,COUNT(tu.user_id) members FROM tenants t LEFT JOIN tenant_users tu ON tu.tenant_id=t.id GROUP BY t.id,t.name,t.slug,t.active ORDER BY t.name")->fetchAll();
admin_shell_start('tenants','Tenants','Famílias e espaços financeiros cadastrados na plataforma.');
?>
<div class="metrics"><article class="metric"><span>Famílias cadastradas</span><strong><?= count($tenants) ?></strong><small>Organizações registradas</small></article><article class="metric"><span>Famílias ativas</span><strong><?= count(array_filter($tenants,fn($x)=>(bool)$x['active'])) ?></strong><small>Com acesso habilitado</small></article></div>
<section class="panel"><div class="panel-head"><div><h2>Tenants</h2><p class="sub">Visão geral dos ambientes familiares</p></div><span class="member"><?= count($tenants) ?> registros</span></div><div class="table-wrap"><table><thead><tr><th>Família</th><th>Identificador</th><th>Membros</th><th>Status</th></tr></thead><tbody>
<?php foreach($tenants as $t): ?><tr><td><strong><?= e($t['name']) ?></strong></td><td class="muted"><?= e($t['slug']) ?></td><td><?= (int)$t['members'] ?></td><td><span class="<?= $t['active']?'status':'member' ?>"><?= $t['active']?'Ativo':'Inativo' ?></span></td></tr><?php endforeach; ?></tbody></table></div>
<p class="notice">O cadastro de novos tenants estará disponível após a conclusão e auditoria do isolamento das operações financeiras.</p></section>
<?php admin_shell_end(); ?>

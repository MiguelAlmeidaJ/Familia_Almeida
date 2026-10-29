<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/tenant.php';
$user = require_admin_access();
$pdo = db();
$tenants=$pdo->query("SELECT t.id,t.name,t.slug,t.active,COUNT(tu.user_id) AS members FROM tenants t LEFT JOIN tenant_users tu ON tu.tenant_id=t.id GROUP BY t.id,t.name,t.slug,t.active ORDER BY t.name")->fetchAll();
$users=$pdo->query("SELECT u.id,u.name,u.email,u.system_role,GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ', ') AS families FROM users u LEFT JOIN tenant_users tu ON tu.user_id=u.id LEFT JOIN tenants t ON t.id=tu.tenant_id GROUP BY u.id,u.name,u.email,u.system_role ORDER BY u.name")->fetchAll();
$activeCount=count(array_filter($tenants,static fn($t)=>(bool)$t['active']));
?><!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Administração geral • Família Almeida</title><link rel="stylesheet" href="/assets/system-admin.css?v=2"></head>
<body><div class="admin-shell">
<aside class="admin-side">
<div class="admin-brand"><span class="brand-icon">⌂</span><span>Família Almeida<small style="display:block;font-size:11px;font-weight:500;color:#b5ced0">Painel do sistema</small></span></div>
<nav class="admin-nav" aria-label="Administração">
<a class="active" href="/system_admin.php" aria-current="page">▦　Visão geral</a>
<a href="/configuracoes">⚙　Configurações</a>
<a href="/configuracoes/manutencao">◴　Manutenção</a>
</nav>
<footer>Administração protegida<form method="post" action="/sair" style="margin-top:15px"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button type="submit" style="border:0;background:transparent;color:white;padding:0;cursor:pointer;font:inherit">↪　Sair da conta</button></form></footer>
</aside>
<main class="admin-main">
<div class="admin-top"><div><p class="eyebrow">Gestão da plataforma</p><h1>Administração do sistema</h1><p class="sub">Gerencie e acompanhe as famílias e os acessos à plataforma.</p></div><span class="pill">● Acesso de administrador</span></div>
<div class="metrics">
<article class="metric"><span>Famílias cadastradas</span><strong><?= count($tenants) ?></strong><small><?= $activeCount ?> ativas</small></article>
<article class="metric"><span>Usuários cadastrados</span><strong><?= count($users) ?></strong><small>Contas na plataforma</small></article>
<article class="metric"><span>Administradores globais</span><strong><?= count(array_filter($users,static fn($u)=>$u['system_role']==='system_admin')) ?></strong><small>Contas privilegiadas</small></article>
</div>
<section class="panel"><div class="panel-head"><div><h2>Famílias</h2><p class="sub">Organizações e quantidade de membros</p></div><span class="member"><?= count($tenants) ?> cadastradas</span></div>
<div class="table-wrap"><table><thead><tr><th>Família</th><th>Identificador</th><th>Membros</th><th>Situação</th></tr></thead><tbody>
<?php foreach($tenants as $t): ?><tr><td><strong><?= e($t['name']) ?></strong></td><td class="muted"><?= e($t['slug']) ?></td><td><?= (int)$t['members'] ?></td><td><span class="<?= $t['active']?'status':'member' ?>"><?= $t['active']?'Ativa':'Inativa' ?></span></td></tr><?php endforeach ?>
<?php if(!$tenants): ?><tr><td colspan="4">Nenhuma família cadastrada.</td></tr><?php endif ?>
</tbody></table></div></section>
<section class="panel"><div class="panel-head"><div><h2>Usuários e permissões</h2><p class="sub">Visão geral dos acessos existentes</p></div><span class="member"><?= count($users) ?> usuários</span></div>
<div class="table-wrap"><table><thead><tr><th>Nome</th><th>E-mail</th><th>Papel global</th><th>Famílias vinculadas</th></tr></thead><tbody>
<?php foreach($users as $u): ?><tr><td><strong><?= e($u['name']) ?></strong></td><td><?= e($u['email']) ?></td><td><span class="member"><?= $u['system_role']==='system_admin'?'Administrador global':'Usuário' ?></span></td><td class="muted"><?= e($u['families']?:'Sem família associada') ?></td></tr><?php endforeach ?>
</tbody></table></div></section>
<section class="panel"><div class="panel-head"><div><h2>Ferramentas administrativas</h2><p class="sub">Diagnóstico e configuração da instalação</p></div></div>
<div class="actions"><a class="button" href="/configuracoes">Abrir configurações</a><a class="button secondary" href="/configuracoes/manutencao">Migrations e diagnóstico</a></div>
<p class="notice"><strong>Ambiente em transição multi-tenant.</strong> O isolamento completo das operações financeiras ainda não está concluído. O cadastro de novas famílias permanece indisponível por segurança.</p>
</section>
</main></div></body></html>
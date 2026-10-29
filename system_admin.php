<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/tenant.php';
require_auth();
require_system_admin();
$pdo=db();
$tenants=$pdo->query("SELECT t.id,t.name,t.slug,t.active,
    COUNT(tu.user_id) AS members
    FROM tenants t LEFT JOIN tenant_users tu ON tu.tenant_id=t.id
    GROUP BY t.id,t.name,t.slug,t.active ORDER BY t.name")->fetchAll();
$users=$pdo->query("SELECT u.id,u.name,u.email,u.system_role,
    GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ', ') AS families
    FROM users u LEFT JOIN tenant_users tu ON tu.user_id=u.id
    LEFT JOIN tenants t ON t.id=tu.tenant_id
    GROUP BY u.id,u.name,u.email,u.system_role ORDER BY u.name")->fetchAll();
$ctx=tenant_context($pdo,(int)$_SESSION['user_id']);
?><!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Administração do sistema</title><link rel="stylesheet" href="/assets/style.css">
<style>main{max-width:1000px;margin:3rem auto;padding:1rem}section{padding:1rem;margin:1rem 0;border:1px solid #ccc;border-radius:12px;overflow:auto}table{width:100%;border-collapse:collapse}td,th{text-align:left;padding:.75rem;border-bottom:1px solid #ddd}nav a{margin-right:1rem}</style></head>
<body><main><nav><a href="/">Voltar ao financeiro</a><a href="/logout">Sair</a></nav>
<h1>Administração do sistema</h1>
<p>Visão global somente de usuários e famílias. Dados financeiros não são exibidos.</p>
<section><h2>Famílias</h2><table><thead><tr><th>Família</th><th>Identificador</th><th>Membros</th><th>Status</th></tr></thead><tbody>
<?php foreach($tenants as $tenant): ?><tr><td><?= e($tenant['name']) ?></td><td><?= e($tenant['slug']) ?></td><td><?= (int)$tenant['members'] ?></td><td><?= $tenant['active']?'Ativa':'Inativa' ?></td></tr><?php endforeach ?>
</tbody></table></section>
<section><h2>Usuários</h2><table><thead><tr><th>Nome</th><th>E-mail</th><th>Papel global</th><th>Famílias</th></tr></thead><tbody>
<?php foreach($users as $user): ?><tr><td><?= e($user['name']) ?></td><td><?= e($user['email']) ?></td><td><?= e($user['system_role']) ?></td><td><?= e($user['families']??'—') ?></td></tr><?php endforeach ?>
</tbody></table></section>
<section><h2>Família ativa</h2>
<?php if($ctx['tenants']): ?><form action="/tenant_switch.php" method="post">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<select name="tenant_id"><?php foreach($ctx['tenants'] as $tenant): ?>
<option value="<?= (int)$tenant['id'] ?>" <?= $ctx['tenant_id']==(int)$tenant['id']?'selected':'' ?>><?= e($tenant['name']) ?></option>
<?php endforeach ?></select><button type="submit">Selecionar</button></form>
<?php else: ?><p>Nenhuma família associada.</p><?php endif ?></section>
<p>Novas famílias permanecem desabilitadas até que todas as operações do financeiro estejam isoladas por tenant.</p>
</main></body></html>

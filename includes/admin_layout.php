<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
function admin_shell_start(string $active,string $title,string $description): void {
 $u=require_admin_access(); $csrf=csrf_token();
 ?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($title) ?> • Administração</title><link rel="stylesheet" href="/assets/system-admin.css?v=4"></head><body><div class="admin-shell">
 <aside class="admin-side"><a class="admin-brand" href="/system_admin.php"><span class="brand-icon">A</span><span><strong>Anoar</strong><small>Administração</small></span></a>
 <span class="admin-side-label">WORKSPACE</span><nav class="admin-nav" aria-label="Menu administrativo">
 <a href="/system_admin.php" class="<?= $active==='panel'?'active':'' ?>" <?= $active==='panel'?'aria-current="page"':'' ?>><span>▦</span>Painel</a>
 <a href="/admin/tenants" class="<?= $active==='tenants'?'active':'' ?>" <?= $active==='tenants'?'aria-current="page"':'' ?>><span>♧</span>Tenants</a>
 <a href="/admin/configuracoes" class="<?= $active==='settings'?'active':'' ?>" <?= $active==='settings'?'aria-current="page"':'' ?>><span>⚙</span>Configurações</a>
 </nav><div class="admin-side-bottom"><div class="admin-profile"><div class="admin-avatar"><?= e(strtoupper(substr((string)$u['name'],0,1))) ?></div><div><strong><?= e($u['name']) ?></strong><small>Administrador do sistema</small></div></div>
 <form method="post" action="/sair"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><button class="admin-logout" type="submit">↪ Sair da conta</button></form></div></aside>
 <main class="admin-main"><div class="admin-top"><div><p class="eyebrow">ANOAR / ADMINISTRAÇÃO</p><h1><?= e($title) ?></h1><p class="sub"><?= e($description) ?></p></div><span class="pill">● Ambiente administrativo</span></div>
 <?php
}
function admin_shell_end(): void { echo '</main></div></body></html>'; }

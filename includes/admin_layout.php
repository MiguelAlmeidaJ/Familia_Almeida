<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/branding.php';
function admin_shell_start(string $active,string $title,string $description): void {
 $u=require_admin_access(); $csrf=csrf_token();
 ?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($title) ?> • Bemora</title><?php brand_head_tags(); ?><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"><link rel="stylesheet" href="/assets/system-admin.css?v=4"><link rel="stylesheet" href="/assets/bemora.css?v=1"><link rel="stylesheet" href="/assets/bemora-admin.css?v=5"><link rel="stylesheet" href="/assets/bemora-finish.css?v=3"></head><body><div class="admin-shell">
 <aside class="admin-side"><a class="admin-brand" href="/system_admin.php"><span class="brand-icon"><?php if(brand_asset_url('logo')): ?><img src="<?= e(brand_asset_url('logo')) ?>" alt="Bemora"><?php else: ?><strong>bemora</strong><?php endif; ?></span><span class="admin-brand-subtitle">Administração</span></a>
 <span class="admin-side-label">WORKSPACE</span><nav class="admin-nav" aria-label="Menu administrativo">
 <a href="/system_admin.php" class="<?= $active==='panel'?'active':'' ?>" <?= $active==='panel'?'aria-current="page"':'' ?>><i class="bi bi-grid-1x2" aria-hidden="true"></i>Painel</a>
 <a href="/admin/planos" class="<?= $active==='plans'?'active':'' ?>" <?= $active==='plans'?'aria-current="page"':'' ?>><i class="bi bi-tags" aria-hidden="true"></i>Planos</a>
 <a href="/admin/assinaturas" class="<?= $active==='subscriptions'?'active':'' ?>" <?= $active==='subscriptions'?'aria-current="page"':'' ?>><i class="bi bi-credit-card" aria-hidden="true"></i>Assinaturas</a>
 <a href="/admin/tenants" class="<?= $active==='tenants'?'active':'' ?>" <?= $active==='tenants'?'aria-current="page"':'' ?>><i class="bi bi-people" aria-hidden="true"></i>Famílias</a>
 <a href="/admin/configuracoes" class="<?= $active==='settings'?'active':'' ?>" <?= $active==='settings'?'aria-current="page"':'' ?>><i class="bi bi-gear" aria-hidden="true"></i>Configurações</a>
 </nav><div class="admin-side-bottom"><div class="admin-profile"><div class="admin-avatar"><?= e(strtoupper(substr((string)$u['name'],0,1))) ?></div><div><strong><?= e($u['name']) ?></strong><small>Administrador do sistema</small></div></div>
 <form method="post" action="/sair"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><button class="admin-logout" type="submit">↪ Sair da conta</button></form></div></aside>
 <main class="admin-main"><div class="admin-top"><div><p class="eyebrow">BEMORA / ADMINISTRAÇÃO</p><h1><?= e($title) ?></h1><p class="sub"><?= e($description) ?></p></div><span class="pill">● Ambiente administrativo</span></div>
 <?php
}
function admin_shell_end(): void { echo '</main></div></body></html>'; }

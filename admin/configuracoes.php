<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/admin_layout.php';
require_admin_access();
require_once __DIR__ . '/../includes/branding.php';
$flash=pull_flash();
admin_shell_start('settings','Configurações','Ferramentas e parâmetros globais da plataforma.');
?>
<?php if($flash): ?><div class="notice" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
<section class="panel"><div class="panel-head"><div><h2>Gestão do sistema</h2><p class="sub">Operações exclusivas do administrador global</p></div></div><div class="admin-tiles">
<a class="admin-tile" href="/configuracoes/manutencao"><span class="tile-icon"><i class="bi bi-tools" aria-hidden="true"></i></span><strong>Manutenção</strong><small>Atualizações, migrations e diagnóstico de banco de dados</small><span class="tile-arrow">→</span></a>
<a class="admin-tile" href="/admin/tenants"><span class="tile-icon"><i class="bi bi-people" aria-hidden="true"></i></span><strong>Gerenciar famílias</strong><small>Consulte famílias, convites e cadastros pendentes</small><span class="tile-arrow">→</span></a>
</div></section>
<section class="panel"><h2>Segurança e isolamento</h2><p class="sub">A administração global permanece separada das configurações pessoais dos usuários.</p><p class="notice"><?= getenv('BEMORA_MULTI_TENANT_RELEASE')==='1' ? 'O multi-tenant está habilitado. Mantenha os testes de isolamento e backups em dia.' : 'A aprovação de famílias segue bloqueada até os testes de isolamento e a liberação controlada na hospedagem.' ?></p></section>
<section class="panel"><div class="panel-head"><div><h2>Identidade visual</h2><p class="sub">Personalize a logo e o favicon do Bemora.</p></div></div>
<div class="admin-brand-grid">
<?php foreach(['logo'=>'Logo da plataforma','favicon'=>'Favicon do navegador'] as $kind=>$label): ?>
<form method="post" action="/admin/branding_upload.php" enctype="multipart/form-data" class="admin-brand-form">
<h3><?= e($label) ?></h3><div class="brand-preview">
<?php if(brand_asset_url($kind)): ?><img src="<?= e(brand_asset_url($kind)) ?>" alt="<?= e($label) ?>"><?php else: ?><span>Sem imagem personalizada</span><?php endif; ?>
</div><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="asset_kind" value="<?= e($kind) ?>">
<label for="asset-<?= e($kind) ?>">PNG ou WebP (máx. 2 MB)</label><input id="asset-<?= e($kind) ?>" name="brand_file" type="file" accept="image/png,image/webp" required>
<button type="submit" class="button">Salvar <?= $kind==='logo'?'logo':'favicon' ?></button></form><?php endforeach; ?>
</div><p class="sub">Use logo horizontal transparente e favicon quadrado, de preferência 256 × 256 pixels.</p></section>
<?php admin_shell_end(); ?>

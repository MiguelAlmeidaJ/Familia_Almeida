<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/admin_layout.php';
require_once __DIR__.'/../includes/plan_benefits.php';
require_admin_access();
$pdo=db();$ready=bemora_benefits_ready($pdo);
$benefits=bemora_benefits_catalog($pdo);
$csrf=csrf_token();$flash=pull_flash();
admin_shell_start('benefits','Benefícios dos planos','Cadastre benefícios uma única vez e escolha em quais planos aparecem.');
?>
<link rel="stylesheet" href="/assets/bemora-plans-admin.css?v=6">
<div class="plans-admin plans-benefits-admin">
 <nav class="plans-builder-breadcrumb"><a href="/admin/planos"><i class="bi bi-arrow-left"></i> Voltar aos planos</a></nav>
 <?php if($flash): ?><div class="notice" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
 <?php if(!$ready): ?><div class="notice">Execute a migration <strong>20261104_044_plan_benefits</strong> em Configurações → Manutenção para ativar o catálogo.</div><?php endif; ?>
 <div class="plans-benefits-layout">
  <section class="plans-benefits-panel">
   <h2><i class="bi bi-plus-circle"></i> Novo benefício</h2>
   <p>O texto será exibido nos cards dos planos que tiverem este benefício selecionado.</p>
   <form method="post" action="/admin/beneficios/acao" class="plans-benefit-add">
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
    <input type="hidden" name="action" value="save">
    <label>Nome do benefício<input type="text" name="title" maxlength="160" required placeholder="Ex.: Organizar receitas e despesas"></label>
    <label>Ordem de exibição<input type="number" name="sort_order" value="100" min="0" max="99999" required></label>
    <button type="submit" class="plans-add" <?= !$ready?'disabled':'' ?>>Cadastrar benefício</button>
   </form>
  </section>
  <section class="plans-benefits-panel">
   <h2><i class="bi bi-list-check"></i> Benefícios cadastrados</h2>
   <p>Edite a descrição, altere a ordem ou desative sem apagar vínculos existentes. Benefícios inativos deixam de aparecer no site.</p>
   <?php if(!$benefits): ?><p class="plans-benefits-empty">Nenhum benefício cadastrado.</p><?php endif; ?>
   <div class="plans-benefit-rows">
    <?php foreach($benefits as $benefit): ?>
    <form method="post" action="/admin/beneficios/acao" class="plans-benefit-row">
     <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$benefit['id'] ?>">
     <label>Benefício<input type="text" name="title" value="<?= e($benefit['title']) ?>" maxlength="160" required></label>
     <label>Ordem<input type="number" name="sort_order" min="0" max="99999" value="<?= (int)$benefit['sort_order'] ?>" required></label>
     <label class="plans-benefit-status"><input type="checkbox" name="active" value="1" <?= $benefit['active']?'checked':'' ?>> Ativo</label>
     <button type="submit">Salvar</button>
    </form>
    <?php endforeach; ?>
   </div>
  </section>
 </div>
 <p class="plans-admin-note"><i class="bi bi-info-circle"></i> Benefícios são textos comerciais exibidos nos cards. A liberação real de Relatórios, Compras e Scanner continua controlada separadamente pelos módulos do plano. Não anuncie benefícios de módulos que o plano não oferece.</p>
</div>
<?php admin_shell_end(); ?>

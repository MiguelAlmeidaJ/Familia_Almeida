<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/admin_layout.php';
require_once __DIR__.'/../includes/plans.php';
require_once __DIR__.'/../includes/plan_modules.php';
require_admin_access();
$pdo=db();
$ready=bemora_plans_table_exists($pdo);
$moduleReady=$ready&&bemora_modules_ready($pdo);
$csrf=csrf_token();
$flash=pull_flash();
admin_shell_start('plans','Criar plano','Configure um pacote de módulos, integrantes e periodicidades em uma tela dedicada.');
?>
<link rel="stylesheet" href="/assets/bemora-plans-admin.css?v=5">
<div class="plans-admin plans-builder-page">
 <nav class="plans-builder-breadcrumb" aria-label="Caminho"><a href="/admin/planos"><i class="bi bi-arrow-left"></i> Voltar aos planos</a><span> / </span><span>Novo pacote</span></nav>
 <?php if($flash): ?><div class="notice" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
 <?php if(!$moduleReady): ?><div class="notice">Para criar pacotes, execute as migrations de planos e módulos em <a href="/configuracoes/manutencao">Configurações → Manutenção</a>, incluindo <strong>20261104_043_subscription_plan_modules</strong>.</div><?php endif; ?>
 <section class="plans-catalog-tools plans-builder-shell" aria-labelledby="plans-catalog-title">
  <div class="plans-tool-header"><div><span class="plans-tool-eyebrow">NOVO PLANO</span><h2 id="plans-catalog-title">Monte seu pacote Bemora</h2><p>Defina os módulos, quantas pessoas podem participar e o valor de cada período. Cada pacote gera três ofertas (mensal, semestral e anual), com os valores confirmados antes da publicação.</p></div></div>
  <form method="post" action="/admin/plans_action.php" id="plan-bundle-form" class="plans-bundle-form">
  <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="create_bundle">
  <div class="plans-bundle-grid">
   <label class="wide">Nome do pacote<input name="bundle_name" maxlength="100" required placeholder="Ex.: Bemora Plus"></label>
   <label>Quantidade de integrantes<select name="bundle_members" required><?php foreach([1,2,4,6,8,10,15,20,30] as $size): ?><option value="<?= $size ?>" <?= $size===1?'selected':'' ?>>Até <?= $size ?> <?= $size===1?'pessoa':'pessoas' ?></option><?php endforeach; ?></select></label>
   <label>Preço base mensal (R$)<input name="bundle_monthly_base" inputmode="decimal" required placeholder="Ex.: 14,90"></label>
   <label>Adicional mensal por integrante (R$)<input name="bundle_seat_price" inputmode="decimal" value="0,00" required><small>O valor adicional é incorporado ao preço final da assinatura, sem cobrança separada no Asaas.</small></label>
   <label>Desconto semestral (%)<input type="number" name="bundle_semi_discount" min="0" max="80" step="0.01" value="0" required></label>
   <label>Desconto anual (%)<input type="number" name="bundle_annual_discount" min="0" max="80" step="0.01" value="0" required></label>
   <label class="wide">Publicação<select name="bundle_visibility"><option value="draft" selected>Criar como rascunho — não aparece no site</option><option value="publish">Publicar as 3 ofertas agora</option></select></label>
   <label class="wide">Descrição da oferta<textarea name="bundle_details" maxlength="3000" rows="3" required placeholder="Explique os benefícios desta modalidade."></textarea></label>
  </div>
  <fieldset class="plans-modules-fieldset" <?= !$moduleReady?'disabled':'' ?>><legend>Módulos deste pacote</legend><p>Painel, receitas, despesas, categorias e contas previstas são recursos básicos. O restante pode ser combinado:</p><div class="plans-modules-grid"><?php foreach(bemora_module_catalog() as $key=>$module): ?><label class="plans-module-option"><input type="checkbox" name="bundle_modules[]" value="<?= e($key) ?>" checked><span><strong><?= e($module['label']) ?></strong><small><?= e($module['description']) ?></small></span></label><?php endforeach; ?></div></fieldset>
  <div class="plans-bundle-preview" id="plans-bundle-preview" aria-live="polite">Informe o preço base para pré-visualizar as três cobranças.</div>
  <div class="plans-bundle-actions"><button type="submit" class="plans-add" <?= !$moduleReady?'disabled':'' ?>><i class="bi bi-layers"></i> Criar 3 ofertas</button><p>Por segurança, o padrão é salvar como rascunho. Depois de conferir os valores, publique as ofertas desejadas.</p></div>
 </form>
 </section>
 <section class="plans-builder-guide" aria-label="Como funciona"><div><i class="bi bi-shield-check"></i><strong>Primeiro em rascunho</strong><p>Revise os três valores antes de liberar a contratação no site.</p></div><div><i class="bi bi-people"></i><strong>Integrantes e módulos</strong><p>Os acessos ficam vinculados à oferta escolhida, sem alterar assinaturas antigas.</p></div><div><i class="bi bi-credit-card"></i><strong>Checkout existente</strong><p>Os preços são totais por período. Nenhuma cobrança é feita nesta tela.</p></div></section>
</div>
<script src="/assets/bemora-plan-builder.js?v=1" defer></script>
<?php admin_shell_end(); ?>

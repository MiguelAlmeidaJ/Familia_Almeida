<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/admin_layout.php';
require_once __DIR__.'/../includes/plans.php';
require_once __DIR__.'/../includes/plan_modules.php';
require_admin_access();
$pdo=db();$ready=bemora_plans_table_exists($pdo);$moduleReady=$ready&&bemora_modules_ready($pdo);
$plans=$ready?$pdo->query('SELECT * FROM subscription_plans ORDER BY active DESC,id DESC')->fetchAll(PDO::FETCH_ASSOC):[];
foreach($plans as &$plan){$plan['modules']=bemora_plan_modules($pdo,(int)$plan['id']);}unset($plan);
$publicActiveCount=0;foreach($plans as $plan)if((int)$plan['active']===1&&(int)$plan['public_sale']===1)$publicActiveCount++;
$flash=pull_flash();$csrf=csrf_token();
admin_shell_start('plans','Planos','Administre planos públicos e planos internos sem venda no site.');
?>
<link rel="stylesheet" href="/assets/bemora-plans-admin.css?v=3">
<div class="plans-admin">
<?php if($flash): ?><div class="notice" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
<?php if($ready&&!$moduleReady): ?><div class="notice">Execute a migration <strong>20261104_043_subscription_plan_modules</strong> em Configurações → Manutenção para editar módulos por plano.</div><?php endif; ?>
<?php if(!$ready): ?><div class="notice">A estrutura dos planos ainda não foi criada. Execute a migration <strong>20261030_023_subscription_plans</strong> em Configurações → Manutenção.</div><?php endif; ?>
<div class="plans-admin-header"><div><h2>Ofertas cadastradas</h2><p>Ofertas arquivadas continuam aqui para auditoria e podem ser republicadas individualmente, se necessário.</p></div><button class="plans-add" type="button" id="plan-new" <?= (!$ready||!$moduleReady)?'disabled':'' ?>><i class="bi bi-plus-lg"></i> Novo plano</button></div>
<section class="plans-catalog-tools" aria-labelledby="plans-catalog-title">
 <div class="plans-tool-header"><div><span class="plans-tool-eyebrow">NOVA ESTRUTURA COMERCIAL</span><h3 id="plans-catalog-title">Monte planos por módulos e integrantes</h3><p>Escolha o pacote de recursos, o número de pessoas e os valores. O Bemora gera três ofertas vinculadas ao checkout existente: mensal, semestral e anual.</p></div><span class="plans-tool-count"><?= $publicActiveCount ?> ofertas públicas ativas</span></div>
 <form method="post" action="/admin/plans_action.php" id="plan-bundle-form" class="plans-bundle-form">
  <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="create_bundle">
  <div class="plans-bundle-grid">
   <label class="wide">Nome do pacote<input name="bundle_name" maxlength="100" required placeholder="Ex.: Bemora Plus"></label>
   <label>Quantidade de integrantes<select name="bundle_members" required><?php foreach([1,2,4,6,8,10,15,20,30] as $size): ?><option value="<?= $size ?>" <?= $size===1?'selected':'' ?>>Até <?= $size ?> <?= $size===1?'pessoa':'pessoas' ?></option><?php endforeach; ?></select></label>
   <label>Preço base mensal (R$)<input name="bundle_monthly_base" inputmode="decimal" required placeholder="Ex.: 14,90"></label>
   <label>Adicional mensal por integrante (R$)<input name="bundle_seat_price" inputmode="decimal" value="0,00" required><small>O valor adicional é incorporado ao preço final da assinatura, sem cobrança separada no Asaas.</small></label>
   <label>Desconto semestral (%)<input type="number" name="bundle_semi_discount" min="0" max="80" step="0.01" value="0" required></label>
   <label>Desconto anual (%)<input type="number" name="bundle_annual_discount" min="0" max="80" step="0.01" value="0" required></label>
   <label class="wide">Descrição da oferta<textarea name="bundle_details" maxlength="3000" rows="3" required placeholder="Explique os benefícios desta modalidade."></textarea></label>
  </div>
  <fieldset class="plans-modules-fieldset" <?= !$moduleReady?'disabled':'' ?>><legend>Módulos deste pacote</legend><p>Painel, receitas, despesas, categorias e contas previstas são recursos básicos. O restante pode ser combinado:</p><div class="plans-modules-grid"><?php foreach(bemora_module_catalog() as $key=>$module): ?><label class="plans-module-option"><input type="checkbox" name="bundle_modules[]" value="<?= e($key) ?>" checked><span><strong><?= e($module['label']) ?></strong><small><?= e($module['description']) ?></small></span></label><?php endforeach; ?></div></fieldset>
  <div class="plans-bundle-preview" id="plans-bundle-preview" aria-live="polite">Informe o preço base para pré-visualizar as três cobranças.</div>
  <div class="plans-bundle-actions"><button type="submit" class="plans-add" <?= !$moduleReady?'disabled':'' ?>><i class="bi bi-layers"></i> Criar 3 ofertas</button><p>As ofertas novas serão publicadas no site. Você poderá ocultá-las ou editá-las individualmente depois.</p></div>
 </form>
 <details class="plans-archive-box"><summary><i class="bi bi-archive"></i> Recomeçar catálogo público com segurança</summary>
  <p>Retira da venda as <?= $publicActiveCount ?> ofertas públicas atualmente ativas. Não exclui planos, pedidos, cobranças Asaas, famílias nem histórico; assinaturas anteriores permanecem vinculadas aos planos originais. Planos internos não são alterados.</p>
  <form action="/admin/plans_action.php" method="post" id="plans-archive-form"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="archive_public_catalog"><label>Digite <strong>ARQUIVAR PLANOS</strong> para confirmar<input name="confirmation" required autocomplete="off" placeholder="ARQUIVAR PLANOS"></label><button type="submit" class="plans-archive-submit" <?= $publicActiveCount<1?'disabled':'' ?>>Arquivar catálogo público atual</button></form>
 </details>
</section>
<div class="plans-admin-grid">
<?php foreach($plans as $p): $promo=bemora_plan_promo_active($p); ?>
<article class="plans-admin-card <?= !$p['active']?'is-hidden':'' ?>">
<div class="plans-card-upper"><span class="plans-cycle"><?= e(bemora_plan_cycles()[$p['billing_cycle']]??$p['billing_cycle']) ?></span><span class="plans-state"><?= !$p['active']?'Inativo':((int)$p['public_sale']===1?'Venda pública':'Plano interno') ?></span></div>
<h3><?= e($p['name']) ?></h3><p class="plans-description"><?= nl2br(e($p['details'])) ?></p>
<div class="plans-price"><?php if($promo): ?><del><?= e(bemora_plan_price((float)$p['price'])) ?></del><strong><?= e(bemora_plan_price((float)$p['promo_price'])) ?></strong><small>Promoção até <?= e(date('d/m/Y H:i',strtotime((string)$p['promo_ends_at']))) ?></small><?php else: ?><strong><?= e(bemora_plan_price((float)$p['price'])) ?></strong><?php if($p['promo_price']!==null): ?><small>Promoção encerrada</small><?php endif; ?><?php endif; ?></div>
<div class="plans-members"><i class="bi bi-people"></i> Até <?= (int)$p['max_members'] ?> <?= (int)$p['max_members']===1?'membro':'membros' ?></div>
<div class="plans-module-pills" aria-label="Módulos habilitados"><?php foreach(bemora_module_catalog() as $key=>$module): ?><?php if($p['modules'][$key]): ?><span><?= e($module['label']) ?></span><?php endif; ?><?php endforeach; ?></div>
<div class="plans-card-actions"><button class="plan-edit" type="button" data-plan="<?= e(json_encode($p,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP)) ?>"><i class="bi bi-pencil"></i> Editar</button><form method="post" action="/admin/plans_action.php"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button type="submit"><?= $p['active']?'Ocultar':'Publicar' ?></button></form></div>
</article>
<?php endforeach; ?>
<?php if(!$plans): ?><div class="plans-empty"><i class="bi bi-tags"></i><strong>Nenhum plano cadastrado</strong><p>Crie o primeiro plano para começar a apresentar as ofertas no site público.</p></div><?php endif; ?>
</div>
<div class="plans-admin-note"><i class="bi bi-info-circle"></i> Alterar módulos pode modificar o acesso de famílias já vinculadas. O limite de pessoas continua configurado por oferta; valores adicionais por integrante ainda não são cobrados separadamente. Planos internos não aparecem no site, checkout nem catálogo de upgrade. Para conceder acesso sem pagamento, utilize os convites administrativos em Famílias; cadastrar o plano não ativa uma assinatura automaticamente.</div>
</div>
<dialog id="plan-dialog" class="plans-dialog" aria-labelledby="plan-dialog-title">
<form method="post" action="/admin/plans_action.php" id="plan-form">
<input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="plan-id" value="">
<div class="plans-dialog-head"><div><h2 id="plan-dialog-title">Novo plano</h2><p>Defina a oferta e suas condições comerciais.</p></div><button type="button" class="plan-dialog-close" aria-label="Fechar">×</button></div>
<div class="plans-form">
<label class="wide">Nome do plano<input name="name" maxlength="100" required placeholder="Ex.: Bemora Família"></label>
<label>Modalidade<select name="billing_cycle" required><option value="monthly">Mensal</option><option value="semiannual">Semestral</option><option value="annual">Anual</option></select></label>
<label>Máximo de membros<input type="number" name="max_members" min="1" max="30" value="2" required></label>
<label class="wide">Disponibilidade<select name="public_sale"><option value="1">Venda pública — aparece no site</option><option value="0">Plano interno — não aparece no site nem no checkout</option></select></label>
<fieldset class="plans-modules-fieldset wide" <?= !$moduleReady?'disabled':'' ?>><legend>Módulos incluídos</legend><p>Receitas, despesas, contas e painel financeiro permanecem disponíveis em todos os planos. Escolha os recursos adicionais:</p><div class="plans-modules-grid"><?php foreach(bemora_module_catalog() as $key=>$module): ?><label class="plans-module-option"><input type="checkbox" name="modules[]" value="<?= e($key) ?>" checked><span><strong><?= e($module['label']) ?></strong><small><?= e($module['description']) ?></small></span></label><?php endforeach; ?></div></fieldset>
<label class="wide">Detalhes do plano<textarea name="details" rows="4" maxlength="3000" required placeholder="Descreva os benefícios do plano. Separe cada benefício em uma linha."></textarea></label>
<label>Preço normal (R$)<input name="price" inputmode="decimal" placeholder="49,90" required><small>Para plano interno gratuito, informe 0,00.</small></label>
<label>Preço promocional (R$) <small>Opcional</small><input name="promo_price" inputmode="decimal" placeholder="39,90"></label>
<label class="wide">Duração da promoção<select name="promo_duration"><option value="">Sem promoção</option><option value="24h">24 horas</option><option value="1w">1 semana</option><option value="1m">1 mês</option><option value="3m">3 meses</option></select></label>
</div>
<p class="plans-form-hint">Planos internos podem ter preço zero. Neles, promoções ficam desabilitadas quando o valor é gratuito.</p>
<div class="plans-dialog-actions"><button type="button" class="plan-dialog-cancel">Cancelar</button><button type="submit" class="plans-add">Salvar plano</button></div>
</form></dialog>
<script src="/assets/bemora-plans-admin.js?v=4" defer></script>
<?php admin_shell_end(); ?>

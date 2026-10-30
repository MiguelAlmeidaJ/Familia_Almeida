<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/admin_layout.php';
require_once __DIR__.'/../includes/plans.php';
require_admin_access();
$pdo=db();$ready=bemora_plans_table_exists($pdo);
$plans=$ready?$pdo->query('SELECT * FROM subscription_plans ORDER BY active DESC,id DESC')->fetchAll(PDO::FETCH_ASSOC):[];
$flash=pull_flash();$csrf=csrf_token();
admin_shell_start('plans','Planos','Configure as ofertas comerciais apresentadas no site do Bemora.');
?>
<link rel="stylesheet" href="/assets/bemora-plans-admin.css?v=1">
<div class="plans-admin">
<?php if($flash): ?><div class="notice" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
<?php if(!$ready): ?><div class="notice">A estrutura dos planos ainda não foi criada. Execute a migration <strong>20261030_023_subscription_plans</strong> em Configurações → Manutenção.</div><?php endif; ?>
<div class="plans-admin-header"><div><h2>Planos cadastrados</h2><p>Organize preços, modalidades e campanhas promocionais.</p></div><button class="plans-add" type="button" id="plan-new" <?= !$ready?'disabled':'' ?>><i class="bi bi-plus-lg"></i> Novo plano</button></div>
<div class="plans-admin-grid">
<?php foreach($plans as $p): $promo=bemora_plan_promo_active($p); ?>
<article class="plans-admin-card <?= !$p['active']?'is-hidden':'' ?>">
<div class="plans-card-upper"><span class="plans-cycle"><?= e(bemora_plan_cycles()[$p['billing_cycle']]??$p['billing_cycle']) ?></span><span class="plans-state"><?= $p['active']?'Publicado':'Oculto' ?></span></div>
<h3><?= e($p['name']) ?></h3><p class="plans-description"><?= nl2br(e($p['details'])) ?></p>
<div class="plans-price"><?php if($promo): ?><del><?= e(bemora_plan_price((float)$p['price'])) ?></del><strong><?= e(bemora_plan_price((float)$p['promo_price'])) ?></strong><small>Promoção até <?= e(date('d/m/Y H:i',strtotime((string)$p['promo_ends_at']))) ?></small><?php else: ?><strong><?= e(bemora_plan_price((float)$p['price'])) ?></strong><?php if($p['promo_price']!==null): ?><small>Promoção encerrada</small><?php endif; ?><?php endif; ?></div>
<div class="plans-members"><i class="bi bi-people"></i> Até <?= (int)$p['max_members'] ?> <?= (int)$p['max_members']===1?'membro':'membros' ?></div>
<div class="plans-card-actions"><button class="plan-edit" type="button" data-plan="<?= e(json_encode($p,JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP)) ?>"><i class="bi bi-pencil"></i> Editar</button><form method="post" action="/admin/plans_action.php"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button type="submit"><?= $p['active']?'Ocultar':'Publicar' ?></button></form></div>
</article>
<?php endforeach; ?>
<?php if(!$plans): ?><div class="plans-empty"><i class="bi bi-tags"></i><strong>Nenhum plano cadastrado</strong><p>Crie o primeiro plano para começar a apresentar as ofertas no site público.</p></div><?php endif; ?>
</div>
<div class="plans-admin-note"><i class="bi bi-info-circle"></i> O catálogo de planos é comercial e não realiza cobrança automática nem altera os limites das famílias atuais. A contratação será tratada separadamente.</div>
</div>
<dialog id="plan-dialog" class="plans-dialog" aria-labelledby="plan-dialog-title">
<form method="post" action="/admin/plans_action.php" id="plan-form">
<input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="plan-id" value="">
<div class="plans-dialog-head"><div><h2 id="plan-dialog-title">Novo plano</h2><p>Defina a oferta e suas condições comerciais.</p></div><button type="button" class="plan-dialog-close" aria-label="Fechar">×</button></div>
<div class="plans-form">
<label class="wide">Nome do plano<input name="name" maxlength="100" required placeholder="Ex.: Bemora Família"></label>
<label>Modalidade<select name="billing_cycle" required><option value="monthly">Mensal</option><option value="semiannual">Semestral</option><option value="annual">Anual</option></select></label>
<label>Máximo de membros<input type="number" name="max_members" min="1" max="30" value="2" required></label>
<label class="wide">Detalhes do plano<textarea name="details" rows="4" maxlength="3000" required placeholder="Descreva os benefícios do plano. Separe cada benefício em uma linha."></textarea></label>
<label>Preço normal (R$)<input name="price" inputmode="decimal" placeholder="49,90" required></label>
<label>Preço promocional (R$) <small>Opcional</small><input name="promo_price" inputmode="decimal" placeholder="39,90"></label>
<label class="wide">Duração da promoção<select name="promo_duration"><option value="">Sem promoção</option><option value="24h">24 horas</option><option value="1w">1 semana</option><option value="1m">1 mês</option><option value="3m">3 meses</option></select></label>
</div>
<p class="plans-form-hint">A promoção começa ao salvar o plano e termina automaticamente após o período escolhido. Atualizações de nome e descrição não reiniciam a promoção.</p>
<div class="plans-dialog-actions"><button type="button" class="plan-dialog-cancel">Cancelar</button><button type="submit" class="plans-add">Salvar plano</button></div>
</form></dialog>
<script src="/assets/bemora-plans-admin.js?v=1" defer></script>
<?php admin_shell_end(); ?>

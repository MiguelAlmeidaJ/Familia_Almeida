<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/admin_layout.php';
require_once __DIR__.'/../includes/coupons.php';
require_once __DIR__.'/../includes/plans.php';
require_admin_access();
$pdo=db();$ready=bemora_coupons_ready($pdo);
$coupons=$ready?$pdo->query('SELECT c.*,p.name plan_name FROM subscription_coupons c LEFT JOIN subscription_plans p ON p.id=c.plan_id ORDER BY c.id DESC')->fetchAll(PDO::FETCH_ASSOC):[];
$plans=bemora_plans_table_exists($pdo)?$pdo->query('SELECT id,name,billing_cycle FROM subscription_plans ORDER BY name,billing_cycle')->fetchAll(PDO::FETCH_ASSOC):[];
$now=new DateTimeImmutable();$activeCount=0;$usageCount=0;
foreach($coupons as $coupon){$usageCount+=(int)$coupon['redeemed_count'];if((int)$coupon['enabled']&&(!$coupon['valid_until']||$now<=new DateTimeImmutable($coupon['valid_until']))&&(!$coupon['valid_from']||$now>=new DateTimeImmutable($coupon['valid_from'])))$activeCount++;}
$flash=pull_flash();$csrf=csrf_token();
admin_shell_start('coupons','Cupons','Crie campanhas e ofertas especiais para as assinaturas Bemora.');
?><link rel="stylesheet" href="/assets/bemora-coupons-admin.css?v=1">
<div class="cp-wrap">
<?php if($flash): ?><div class="notice" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
<?php if(!$ready): ?><div class="notice">Execute a migration <strong>20261102_025_subscription_coupons</strong> em Manutenção para habilitar os cupons.</div><?php endif; ?>
<div class="cp-metrics">
<div><span>Cupons cadastrados</span><strong><?= count($coupons) ?></strong></div>
<div><span>Campanhas ativas</span><strong><?= $activeCount ?></strong></div>
<div><span>Utilizações registradas</span><strong><?= $usageCount ?></strong></div>
</div>
<div class="cp-heading"><div><h2>Campanhas e benefícios</h2><p>Controle códigos, validade, elegibilidade e quantidade de utilizações.</p></div><button type="button" class="cp-primary" id="cp-new" <?= !$ready?'disabled':'' ?>><i class="bi bi-plus-lg"></i> Criar cupom</button></div>
<div class="cp-toolbar"><label><i class="bi bi-search"></i><input id="cp-search" type="search" placeholder="Buscar por código ou campanha…" aria-label="Buscar cupons"></label><select id="cp-filter" aria-label="Filtrar cupons"><option value="all">Todos os cupons</option><option value="enabled">Ativos</option><option value="disabled">Desativados</option></select></div>
<div class="cp-list">
<?php foreach($coupons as $c):
 $kindLabel=bemora_coupon_types()[$c['kind']]??$c['kind'];
 $benefit=$c['kind']==='free_months'?(int)$c['free_months'].' '.((int)$c['free_months']===1?'mês grátis':'meses grátis'):($c['discount_type']==='percent'?number_format((float)$c['discount_value'],2,',','.').'% de desconto':bemora_plan_price((float)$c['discount_value']).' de desconto');
 $expired=$c['valid_until']&&$now>new DateTimeImmutable($c['valid_until']);
 $limited=$c['max_redemptions']!==null&&(int)$c['redeemed_count']>=(int)$c['max_redemptions'];
 $enabled=(bool)$c['enabled']&&!$expired&&!$limited;
 $payload=json_encode($c,JSON_HEX_APOS|JSON_HEX_TAG|JSON_HEX_QUOT|JSON_HEX_AMP|JSON_THROW_ON_ERROR);
?>
<article class="cp-card" data-code="<?= e(mb_strtolower($c['code'].' '.$c['title'])) ?>" data-active="<?= $enabled?'enabled':'disabled' ?>">
<div class="cp-card-code"><span class="cp-ticket"><i class="bi bi-ticket-perforated"></i></span><div><code><?= e($c['code']) ?></code><h3><?= e($c['title']) ?></h3><p><?= e($c['description']??$kindLabel) ?></p></div></div>
<div class="cp-card-detail"><strong><?= e($benefit) ?></strong><span><?= e($kindLabel) ?></span></div>
<div class="cp-card-detail"><strong><?= e($c['plan_name']??'Todos os planos') ?></strong><span><?= e(['all'=>'Todas as modalidades','monthly'=>'Mensal','semiannual'=>'Semestral','annual'=>'Anual'][$c['cycle_scope']]??'') ?></span></div>
<div class="cp-card-detail"><strong><?= (int)$c['redeemed_count'] ?><?= $c['max_redemptions']!==null?' / '.(int)$c['max_redemptions']:'' ?></strong><span>Utilizações</span></div>
<div class="cp-card-state"><span class="cp-pill <?= $enabled?'is-active':'is-inactive' ?>"><?= !$c['enabled']?'Desativado':($expired?'Expirado':($limited?'Esgotado':'Ativo')) ?></span><small><?= $c['valid_until']?'Até '.e(date('d/m/Y',strtotime($c['valid_until']))):'Sem vencimento' ?></small></div>
<div class="cp-actions"><button type="button" class="cp-edit" data-coupon="<?= e($payload) ?>" title="Editar <?= e($c['code']) ?>"><i class="bi bi-pencil"></i></button><form method="post" action="/admin/coupons_action.php"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="action" value="toggle"><button title="<?= $c['enabled']?'Desativar':'Ativar' ?>" type="submit"><i class="bi bi-<?= $c['enabled']?'pause-circle':'play-circle' ?>"></i></button></form><form method="post" action="/admin/coupons_action.php" onsubmit="return confirm('Excluir definitivamente o cupom <?= e($c['code']) ?>? Esta ação não poderá ser desfeita.')"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="action" value="delete"><button type="submit" title="Excluir" class="cp-delete"><i class="bi bi-trash3"></i></button></form></div>
</article>
<?php endforeach; ?>
<?php if(!$coupons): ?><div class="cp-empty"><i class="bi bi-ticket-perforated"></i><h3>Sua primeira campanha começa aqui</h3><p>Crie cupons de acesso grátis ou desconto para apresentar ofertas especiais.</p></div><?php endif; ?>
<div class="cp-empty" id="cp-no-results" hidden>Nenhum cupom encontrado para este filtro.</div>
</div>
<div class="cp-info"><i class="bi bi-shield-check"></i><span><strong>Integração disponível no Sandbox:</strong> descontos iniciais, cupons anuais e meses de cortesia dependem da migration 027 e dos testes de cobrança no Asaas antes da liberação em produção.</span></div>
</div>
<dialog id="cp-dialog" class="cp-dialog" aria-labelledby="cp-dialog-title"><form method="post" action="/admin/coupons_action.php" id="cp-form"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="id" value=""><input type="hidden" name="action" value="save">
<div class="cp-dialog-head"><div><span class="cp-kicker">CAMPANHA BEMORA</span><h2 id="cp-dialog-title">Criar cupom</h2><p>Defina o benefício e quando ele pode ser utilizado.</p></div><button type="button" class="cp-close" aria-label="Fechar"><i class="bi bi-x-lg"></i></button></div>
<div class="cp-form-grid">
<label>Código do cupom<input name="code" required maxlength="32" minlength="3" pattern="[A-Za-z0-9_-]{3,32}" placeholder="BEMORA10" autocomplete="off"></label>
<label>Nome da campanha<input name="title" required maxlength="100" placeholder="Boas-vindas Bemora"></label>
<label class="wide">Descrição <small>Opcional</small><textarea name="description" maxlength="500" rows="2" placeholder="Explique brevemente as condições da campanha."></textarea></label>
<label class="wide">Tipo de benefício<select name="kind" required><option value="free_months">1 ou mais meses de acesso grátis</option><option value="first_payment_discount">Desconto na primeira cobrança</option><option value="annual_discount">Desconto na assinatura anual</option></select></label>
<label class="cp-free">Quantidade de meses grátis<input name="free_months" type="number" min="1" max="24" value="1"></label>
<label class="cp-discount" hidden>Tipo de desconto<select name="discount_type"><option value="percent">Percentual (%)</option><option value="fixed">Valor fixo (R$)</option></select></label>
<label class="cp-discount" hidden>Valor do desconto<input name="discount_value" inputmode="decimal" placeholder="Ex.: 20 ou 30,00"></label>
<label>Modalidade elegível<select name="cycle_scope"><option value="all">Todas as modalidades</option><option value="monthly">Mensal</option><option value="semiannual">Semestral</option><option value="annual">Anual</option></select></label>
<label>Plano elegível<select name="plan_id"><option value="">Todos os planos</option><?php foreach($plans as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name'].' — '.(bemora_plan_cycles()[$p['billing_cycle']]??'')) ?></option><?php endforeach; ?></select></label>
<label>Limite de utilizações <small>Opcional</small><input type="number" name="max_redemptions" min="1" max="100000" placeholder="Ilimitado"></label>
<label>Início da validade <small>Opcional</small><input type="datetime-local" name="valid_from"></label>
<label>Término da validade <small>Opcional</small><input type="datetime-local" name="valid_until"></label>
</div><div class="cp-dialog-footer"><button type="button" class="cp-cancel">Cancelar</button><button type="submit" class="cp-primary">Salvar cupom</button></div>
</form></dialog><script src="/assets/bemora-coupons-admin.js?v=1" defer></script>
<?php admin_shell_end(); ?>
<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/admin_layout.php';
require_once __DIR__.'/../includes/plans.php';
require_admin_access();
$pdo=db();$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT);
if(!$id||$id<1){http_response_code(400);exit('Família inválida.');}
$q=$pdo->prepare('SELECT id,name,slug,status,max_members FROM tenants WHERE id=?');$q->execute([$id]);$family=$q->fetch();
if(!$family){http_response_code(404);exit('Família não encontrada.');}
$q=$pdo->prepare("SELECT u.id,u.name,u.email,u.sex,u.birth_date,tu.role
FROM tenant_users tu INNER JOIN users u ON u.id=tu.user_id WHERE tu.tenant_id=? ORDER BY CASE WHEN tu.role='family_admin' THEN 0 ELSE 1 END,u.name");
$q->execute([$id]);$members=$q->fetchAll();
$latestOrder=null;
$ordersExist=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='subscription_orders'")->fetchColumn()===1;
if($ordersExist){
 $q=$pdo->prepare("SELECT plan_name,billing_cycle,max_members,recurring_amount,amount,coupon_free_until,status,asaas_subscription_id,created_at FROM subscription_orders WHERE activated_tenant_id=? AND status IN ('completed','paid','payment_attention') ORDER BY id DESC LIMIT 1");
 $q->execute([$id]);$latestOrder=$q->fetch(PDO::FETCH_ASSOC)?:null;
}
$internalReady=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='tenant_internal_plans'")->fetchColumn()===1;
$internalPlan=null;
if($internalReady){
 $q=$pdo->prepare("SELECT p.id,p.name,p.billing_cycle,p.max_members FROM tenant_internal_plans x JOIN subscription_plans p ON p.id=x.plan_id WHERE x.tenant_id=?");
 $q->execute([$id]);$internalPlan=$q->fetch(PDO::FETCH_ASSOC)?:null;
}
$availablePrivatePlans=$pdo->query("SELECT id,name,max_members FROM subscription_plans WHERE public_sale=0 AND active=1 AND price=0 ORDER BY name,id")->fetchAll(PDO::FETCH_ASSOC);
$hasAsaas=!empty($latestOrder['asaas_subscription_id']);
$flash=pull_flash();$csrf=csrf_token();
admin_shell_start('tenants','Gerenciar família','Plano, assinatura, membros e permissões da família.');
?><link rel="stylesheet" href="/assets/bemora-families-admin.css?v=2"><?php
?>
<a href="/admin/tenants" class="family-back"><i class="bi bi-arrow-left"></i> Voltar às famílias</a>
<?php if($flash): ?><div class="notice" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
<section class="panel"><div class="panel-head"><div><h2><?= e($family['name']) ?></h2><p class="sub">Identificador: <?= e($family['slug']) ?> · Situação: <?= e($family['status']) ?></p></div><span class="member"><?= count($members) ?> de <?= (int)$family['max_members'] ?> membros</span></div>
<div class="family-detail-kpis">
<div><small>Plano vinculado</small><strong><?= e($latestOrder['plan_name']??$internalPlan['name']??'Sem plano vinculado') ?></strong><p class="sub"><?= $latestOrder?e(bemora_plan_cycles()[$latestOrder['billing_cycle']]??$latestOrder['billing_cycle']):($internalPlan?'Plano interno gratuito':'Convite administrativo ou cadastro legado') ?></p></div>
<div><small>Valor recorrente registrado</small><strong><?= $latestOrder?e('R$ '.number_format((float)($latestOrder['recurring_amount']??$latestOrder['amount']),2,',','.')):'—' ?></strong><p class="sub">Não representa confirmação de pagamentos futuros</p></div>
<div><small>Vagas utilizadas</small><strong><?= count($members) ?> / <?= (int)$family['max_members'] ?></strong><p class="sub"><?= max(0,(int)$family['max_members']-count($members)) ?> vaga(s) disponível(is)</p></div>
</div>
<?php if($latestOrder && !empty($latestOrder['coupon_free_until'])): ?><p class="family-detail-note">Cortesia cadastrada até <?= e(date('d/m/Y',strtotime($latestOrder['coupon_free_until']))) ?>. A situação financeira deve ser conferida no Asaas.</p><?php endif; ?>
<div class="family-detail-grid">
<form method="post" action="/admin/family_action.php" class="family-detail-form"><h3>Nome da família</h3><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="rename"><input type="hidden" name="id" value="<?= $id ?>"><label for="family-name">Nome</label><input id="family-name" name="name" value="<?= e($family['name']) ?>" minlength="3" maxlength="160" required><button class="family-create-button" type="submit"><i class="bi bi-check-lg"></i> Salvar nome</button></form>
<form method="post" action="/admin/family_action.php" class="family-detail-form"><h3>Limite de membros</h3><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="set_limit"><input type="hidden" name="id" value="<?= $id ?>"><label for="max-members">Máximo permitido (1 a 30)</label><input id="max-members" name="max_members" type="number" min="<?= max(1,count($members)) ?>" max="30" value="<?= (int)$family['max_members'] ?>" required><p class="sub">Não é possível reduzir abaixo dos <?= count($members) ?> membros atuais.</p><button class="family-create-button" type="submit"><i class="bi bi-people"></i> Atualizar limite</button></form>
</div></section>
<section class="panel"><div class="panel-head"><div><h2>Plano interno da família</h2><p class="sub">Vincule um plano gratuito existente sem gerar cobranças no Asaas.</p></div><span class="member"><?= $internalPlan?'Plano interno vinculado':'Gestão de acesso' ?></span></div>
<?php if(!$internalReady): ?><p class="notice">Execute a migration 20261103_033_internal_family_plans em Manutenção para habilitar esta opção.</p>
<?php elseif($hasAsaas): ?><p class="notice">Esta família possui uma assinatura vinculada ao Asaas. Não é permitido substituí-la por um plano interno sem conciliar a assinatura financeira. Isso evita novas cobranças indevidas e divergências entre os sistemas.</p>
<?php elseif(!$availablePrivatePlans): ?><p class="notice">Ainda não existe um plano interno gratuito ativo. Crie um em Administração → Planos, com disponibilidade interna e preço R$ 0,00.</p>
<?php else: ?><form method="post" action="/admin/family_action.php" class="family-detail-form family-internal-form"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="assign_internal_plan"><input type="hidden" name="id" value="<?= $id ?>">
<label for="family-internal-plan">Plano interno gratuito</label><select name="plan_id" id="family-internal-plan" required>
<option value="">Selecione um plano</option>
<?php foreach($availablePrivatePlans as $plan): ?><option value="<?= (int)$plan['id'] ?>" <?= (int)($internalPlan['id']??0)===(int)$plan['id']?'selected':'' ?>><?= e($plan['name']) ?> · Até <?= (int)$plan['max_members'] ?> membros</option><?php endforeach; ?>
</select><p class="sub">A alteração atualiza o limite de membros e o vínculo interno. Não gera cobrança, não cria assinatura no Asaas e não altera os dados financeiros existentes.</p>
<button type="submit" class="family-create-button"><i class="bi bi-check-circle" aria-hidden="true"></i> Vincular plano interno</button></form><?php endif; ?>
</section>
<section class="panel"><div class="panel-head"><div><h2>Membros cadastrados</h2><p class="sub">Informações e papéis da família</p></div><span class="member"><?= count($members) ?> pessoas</span></div>
<div class="table-wrap"><table><thead><tr><th>Nome</th><th>E-mail</th><th>Sexo</th><th>Nascimento</th><th>Papel</th></tr></thead><tbody>
<?php foreach($members as $m): ?><tr><td><strong><?= e($m['name']) ?></strong></td><td><?= e($m['email']) ?></td><td><?= e(['M'=>'Masculino','F'=>'Feminino'][$m['sex']]??'Não informado') ?></td><td><?= e($m['birth_date']??'—') ?></td><td><span class="member"><?= $m['role']==='family_admin'?'Administrador familiar':'Membro' ?></span></td></tr><?php endforeach; ?>
<?php if(!$members): ?><tr><td colspan="5">Ainda não existem membros vinculados.</td></tr><?php endif; ?></tbody></table></div>
</section>
<section class="panel"><h2>Administrador da família</h2><p class="sub">Escolha o membro responsável pelo gerenciamento familiar. A transferência substitui os administradores familiares atuais.</p>
<?php if($members): ?><form method="post" action="/admin/family_action.php" class="family-detail-form family-admin-select" onsubmit="return confirm('Confirmar a troca do administrador da família?')"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="set_admin"><input type="hidden" name="id" value="<?= $id ?>"><label for="family-admin">Administrador familiar</label><select id="family-admin" name="user_id" required>
<?php foreach($members as $m): ?><option value="<?= (int)$m['id'] ?>" <?= $m['role']==='family_admin'?'selected':'' ?>><?= e($m['name']) ?> (<?= e($m['email']) ?>)</option><?php endforeach; ?>
</select><button type="submit" class="family-create-button"><i class="bi bi-shield-check"></i> Definir administrador</button></form>
<?php else: ?><p class="notice">Cadastre membros antes de definir um administrador.</p><?php endif; ?>
</section>
<?php admin_shell_end(); ?>

<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/admin_layout.php';
require_admin_access();
$pdo=db();$flash=pull_flash();
$ordersReady=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='subscription_orders'")->fetchColumn()===1;
$tenants=$pdo->query("SELECT t.id,t.name,t.slug,t.status,t.active,t.max_members,t.finance_mode,COUNT(tu.user_id) members FROM tenants t LEFT JOIN tenant_users tu ON tu.tenant_id=t.id GROUP BY t.id,t.name,t.slug,t.status,t.active,t.max_members,t.finance_mode ORDER BY t.name")->fetchAll(PDO::FETCH_ASSOC);
$latestOrders=[];
if($ordersReady){
 $rows=$pdo->query("SELECT o.activated_tenant_id,o.plan_name,o.billing_cycle,o.status,o.recurring_amount,o.amount,o.coupon_free_until,o.asaas_subscription_id,o.created_at FROM subscription_orders o INNER JOIN (SELECT activated_tenant_id,MAX(id) last_id FROM subscription_orders WHERE activated_tenant_id IS NOT NULL AND status IN ('completed','paid','payment_attention') GROUP BY activated_tenant_id) latest ON latest.last_id=o.id")->fetchAll(PDO::FETCH_ASSOC);
 foreach($rows as $row)$latestOrders[(int)$row['activated_tenant_id']]=$row;
}
$activeCount=count(array_filter($tenants,static fn($t)=>$t['status']==='active'&&(int)$t['active']===1));
$linkedCount=count($latestOrders);
$invitations=$pdo->query("SELECT id,max_members,expires_at,used_at,revoked_at FROM family_invitations ORDER BY id DESC LIMIT 20")->fetchAll();
$pending=$pdo->query("SELECT r.family_name,r.member_count,r.submitted_at,r.id FROM family_registrations r WHERE state='pending' ORDER BY r.id DESC")->fetchAll();
$token=$_SESSION['new_family_invite']??null;unset($_SESSION['new_family_invite']);
$origin=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'?'https':'http').'://'.($_SERVER['HTTP_HOST']??'localhost');
admin_shell_start('tenants','Famílias','Visão centralizada dos espaços, membros, planos e assinaturas do Bemora.');
?><link rel="stylesheet" href="/assets/bemora-families-admin.css?v=1"><?php
?>
<?php if($flash): ?><div class="notice" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
<div class="metrics family-metrics"><article class="metric"><span>Famílias cadastradas</span><strong><?= count($tenants) ?></strong><small>Todos os espaços</small></article><article class="metric"><span>Famílias ativas</span><strong><?= $activeCount ?></strong><small>Com acesso habilitado</small></article><article class="metric"><span>Assinaturas vinculadas</span><strong><?= $linkedCount ?></strong><small>Contratações identificadas</small></article><article class="metric"><span>Convites recentes</span><strong><?= count($invitations) ?></strong><small>Últimos registros exibidos</small></article></div>
<div class="family-toolbar"><div><h2>Gestão de famílias</h2><p class="sub">Consulte e administre os ambientes cadastrados.</p></div><button class="family-create-button" type="button" data-modal-open="invite-modal"><i class="bi bi-link-45deg" aria-hidden="true"></i> Criar link</button></div>
<?php if($token): ?><section class="panel family-new-link" role="status"><h2>Convite gerado</h2><p class="sub">Copie o link agora. Ele só será exibido nesta ocasião e expira em 72 horas.</p><div class="family-link-row"><input id="generated-invite-link" type="text" readonly value="<?= e($origin.'/convite-familia.php?token='.$token) ?>" aria-label="Link de convite"><button type="button" id="copy-invite-link" class="family-create-button"><i class="bi bi-copy" aria-hidden="true"></i> Copiar link</button></div></section><?php endif; ?>
<div class="family-modal-backdrop" id="invite-modal" hidden>
<div class="family-modal" role="dialog" aria-modal="true" aria-labelledby="invite-title" tabindex="-1">
<div class="family-modal-header"><div><h2 id="invite-title">Criar link de convite</h2><p class="sub">Convite de uso único, válido por 72 horas.</p></div><button type="button" class="family-modal-close" data-modal-close aria-label="Fechar"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div>
<form method="post" action="/admin/family_action.php" class="family-invite-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="invite">
<label for="max">Quantidade máxima de membros</label><input id="max" class="family-input" type="number" min="1" max="30" name="max_members" value="5" required><p class="sub">O convidado poderá cadastrar de 1 até esse limite.</p><label for="invite-email" style="display:block;margin-top:14px">E-mail (opcional)</label><input id="invite-email" class="family-input" name="invite_email" type="email" autocomplete="email" placeholder="nome@exemplo.com"><p class="sub">Se informado, enviaremos o convite por e-mail. Você também poderá copiar o link.</p>
<div class="family-modal-footer"><button type="button" class="family-cancel" data-modal-close>Cancelar</button><button type="submit" class="family-create-button"><i class="bi bi-link-45deg" aria-hidden="true"></i> Gerar link</button></div></form></div></div>
<section class="panel family-overview"><div class="panel-head"><div><h2>Famílias existentes</h2><p class="sub">Plano vinculado, utilização e situação de cada espaço financeiro.</p></div><span class="member"><?= count($tenants) ?> registros</span></div>
<div class="family-search"><label for="family-filter"><i class="bi bi-search" aria-hidden="true"></i><input id="family-filter" type="search" placeholder="Buscar por família, plano ou identificador…" autocomplete="off"></label></div>
<div class="table-wrap"><table class="family-data-table"><thead><tr><th>Família</th><th>Plano atual</th><th>Membros</th><th>Assinatura</th><th>Estado</th><th>Ações</th></tr></thead><tbody>
<?php foreach($tenants as $t): $o=$latestOrders[(int)$t['id']]??null;$activeFamily=$t['status']==='active'&&(int)$t['active']===1; ?>
<tr class="family-record" data-filter="<?= e(mb_strtolower($t['name'].' '.$t['slug'].' '.($o['plan_name']??''))) ?>">
<td><div class="family-identity"><span class="family-initial"><i class="bi bi-people" aria-hidden="true"></i></span><div><strong><?= e($t['name']) ?></strong><small><?= e($t['slug']) ?></small></div></div></td>
<td><?php if($o): ?><div class="family-plan"><strong><?= e($o['plan_name']) ?></strong><small><?= e(bemora_plan_cycles()[$o['billing_cycle']]??$o['billing_cycle']) ?><?php if($o['recurring_amount']!==null): ?> · <?= e(bemora_plan_price((float)$o['recurring_amount'])) ?><?php endif; ?></small></div><?php else: ?><span class="family-muted">Sem plano comercial vinculado</span><?php endif; ?></td>
<td><div class="family-members"><strong><?= (int)$t['members'] ?> <span>/ <?= (int)$t['max_members'] ?></span></strong><div class="family-meter"><span style="width:<?= (int)$t['max_members']>0?min(100,round(100*(int)$t['members']/(int)$t['max_members'])):0 ?>%"></span></div></div></td>
<td><?php if($o): ?><span class="family-tag <?= $o['status']==='completed'?'is-good':'is-neutral' ?>"><?= e(['completed'=>'Cadastrada','paid'=>'Pagamento confirmado','payment_attention'=>'Atenção ao pagamento'][$o['status']]??$o['status']) ?></span><?php if(!empty($o['coupon_free_until']) && $o['coupon_free_until']>date('Y-m-d')): ?><small class="family-trial">Cortesia até <?= e(date('d/m/Y',strtotime($o['coupon_free_until']))) ?></small><?php endif; ?><?php else: ?><span class="family-tag is-neutral">Sem vínculo Asaas</span><?php endif; ?></td>
<td><span class="family-tag <?= $activeFamily?'is-good':'is-neutral' ?>"><?= e(['active'=>'Ativa','archived'=>'Arquivada','maintenance'=>'Manutenção','pending'=>'Pendente'][$t['status']]??$t['status']) ?></span></td>
<td><div class="family-row-actions"><a class="family-manage" href="/admin/familia?id=<?= (int)$t['id'] ?>"><i class="bi bi-arrow-up-right-square" aria-hidden="true"></i> Detalhes</a>
<?php foreach(['archive'=>['Arquivar','bi-archive'],'maintenance'=>['Manutenção','bi-tools'],'activate'=>['Ativar','bi-check-circle']] as $action=>$config): ?>
<form method="post" action="/admin/family_action.php" onsubmit="return confirm('<?= e($config[0]) ?> esta família?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="<?= e($action) ?>"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><button class="family-icon-button <?= e($action) ?>" type="submit" title="<?= e($config[0]) ?>" aria-label="<?= e($config[0]) ?>"><i class="bi <?= e($config[1]) ?>" aria-hidden="true"></i></button></form>
<?php endforeach; ?></div></td></tr>
<?php endforeach; ?>
<?php if(!$tenants): ?><tr><td colspan="6">Nenhuma família cadastrada.</td></tr><?php endif; ?>
</tbody></table><p id="family-search-empty" class="sub" hidden>Nenhuma família corresponde à sua busca.</p></div></section>
<section class="panel"><h2>Convites recentes</h2><div class="table-wrap"><table><thead><tr><th>ID</th><th>Limite</th><th>Validade</th><th>Estado</th></tr></thead><tbody><?php foreach($invitations as $inv): ?><tr><td>#<?= (int)$inv['id'] ?></td><td><?= (int)$inv['max_members'] ?></td><td><?= e($inv['expires_at']) ?></td><td><?= $inv['used_at']?'Utilizado':($inv['revoked_at']?'Revogado':'Emitido') ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<script>
(()=>{
const backdrop=document.getElementById('invite-modal'),open=document.querySelector('[data-modal-open="invite-modal"]');
if(backdrop&&open){let previous=null;const close=()=>{backdrop.hidden=true;document.body.classList.remove('family-modal-open');previous?.focus()};open.addEventListener('click',()=>{previous=document.activeElement;backdrop.hidden=false;document.body.classList.add('family-modal-open');backdrop.querySelector('.family-modal-close')?.focus()});backdrop.querySelectorAll('[data-modal-close]').forEach(el=>el.addEventListener('click',close));backdrop.addEventListener('click',e=>{if(e.target===backdrop)close()});document.addEventListener('keydown',e=>{if(backdrop.hidden)return;if(e.key==='Escape')close();if(e.key==='Tab'){const elements=[...backdrop.querySelectorAll('button:not([disabled]),input:not([disabled])')];const first=elements[0],last=elements[elements.length-1];if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus()}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus()}}})}
document.querySelectorAll('[data-edit-family]').forEach(button=>button.addEventListener('click',()=>{let form=document.getElementById('edit-family-'+button.dataset.editFamily);if(form){form.hidden=!form.hidden;if(!form.hidden)form.querySelector('input[name="name"]')?.focus()}}));
document.querySelectorAll('[data-cancel-edit]').forEach(button=>button.addEventListener('click',()=>{const form=document.getElementById('edit-family-'+button.dataset.cancelEdit);if(form)form.hidden=true}));
document.getElementById('family-filter')?.addEventListener('input',event=>{const term=event.target.value.toLocaleLowerCase('pt-BR').trim();let count=0;document.querySelectorAll('.family-record').forEach(row=>{const visible=row.dataset.filter.includes(term);row.hidden=!visible;if(visible)count++});document.getElementById('family-search-empty').hidden=count>0});
document.getElementById('copy-invite-link')?.addEventListener('click',async()=>{const field=document.getElementById('generated-invite-link');try{await navigator.clipboard.writeText(field.value);document.getElementById('copy-invite-link').textContent='Copiado!'}catch(_){field.select()}});
})();
</script>
<?php admin_shell_end(); ?>

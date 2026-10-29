<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
$user=require_family_access(); $csrf=csrf_token(); $flash=pull_flash();
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 $current=(string)($_POST['current_password']??''); $next=(string)($_POST['new_password']??'');
 $stmt=db()->prepare('SELECT password_hash FROM users WHERE id=?');$stmt->execute([(int)$user['id']]);$hash=$stmt->fetchColumn();
 if(!$hash||!password_verify($current,$hash)){flash('error','Senha atual incorreta.');}
 elseif(strlen($next)<12){flash('error','A nova senha deve ter no mínimo 12 caracteres.');}
 elseif($next!==(string)($_POST['confirm_password']??'')){flash('error','A confirmação da senha não corresponde.');}
 else{db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($next,PASSWORD_DEFAULT),(int)$user['id']]);session_regenerate_id(true);flash('success','Sua senha foi atualizada.');}
 redirect_to('/minha-conta');
}
$pdo=db();$tenantId=(int)$user['tenant_id'];
$roleQuery=$pdo->prepare('SELECT role FROM tenant_users WHERE tenant_id=? AND user_id=?');$roleQuery->execute([$tenantId,(int)$user['id']]);$familyAdmin=$roleQuery->fetchColumn()==='family_admin';
$familyQuery=$pdo->prepare('SELECT name,finance_mode FROM tenants WHERE id=?');$familyQuery->execute([$tenantId]);$family=$familyQuery->fetch();
$members=[];$bills=[];
if($familyAdmin){
 $q=$pdo->prepare('SELECT u.id,u.name FROM tenant_users tu JOIN users u ON u.id=tu.user_id WHERE tu.tenant_id=? ORDER BY u.name');$q->execute([$tenantId]);$members=$q->fetchAll();
 $q=$pdo->prepare('SELECT b.id,b.name,r.user_id FROM fixed_bills b LEFT JOIN family_bill_responsibilities r ON r.fixed_bill_id=b.id AND r.tenant_id=b.tenant_id WHERE b.tenant_id=? ORDER BY b.name');$q->execute([$tenantId]);$bills=$q->fetchAll();
}
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Minha conta • Bemora</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"><link rel="stylesheet" href="/assets/style.css"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"><link rel="stylesheet" href="/assets/bemora.css?v=2"><link rel="stylesheet" href="/assets/bemora-finance.css?v=2"><link rel="stylesheet" href="/assets/bemora-finish.css?v=3"><?php brand_head_tags(); ?><link rel="stylesheet" href="/assets/bemora-sidebar.css?v=3"><link rel="stylesheet" href="/assets/bemora-family-settings.css?v=1"></head><body class="bemora-finance"><div class="shell"><?php render_sidebar('minha-conta',$csrf); ?><main><?php render_topbar($user); ?><div class="content"><?php page_header('MINHA CONTA','Configurações','Dados pessoais e segurança do seu acesso.'); ?>
<?php if($flash): ?><div class="alert <?= $flash['type']==='success'?'success':'' ?>"><?= e($flash['message']) ?></div><?php endif ?>
<div class="split equal"><section class="card" style="padding:24px"><h2><i class="bi bi-person-circle" aria-hidden="true"></i> Seus dados</h2><p><strong>Nome</strong></p><p><?= e($user['name']) ?></p><p><strong>E-mail</strong></p><p><?= e($user['email']) ?></p><p class="muted-copy">Esses dados são exclusivos da sua conta.</p></section>
<section class="card" style="padding:24px"><h2><i class="bi bi-shield-lock" aria-hidden="true"></i> Alterar senha</h2><form method="post" style="display:grid;gap:12px;margin-top:16px"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><label>Senha atual<input class="form-control" type="password" name="current_password" autocomplete="current-password" required></label><label>Nova senha<input class="form-control" type="password" name="new_password" autocomplete="new-password" minlength="12" required></label><label>Confirmar nova senha<input class="form-control" type="password" name="confirm_password" autocomplete="new-password" minlength="12" required></label><button type="submit" class="primary btn btn-primary"><i class="bi bi-shield-lock" aria-hidden="true"></i> Salvar nova senha</button></form></section></div>
<?php if($familyAdmin): ?>
<section class="card family-preferences" style="padding:26px;margin-top:20px">
<h2><i class="bi bi-people" aria-hidden="true"></i> Configurações da família</h2>
<p class="text-secondary">Família <?= e($family['name']??'') ?> · Apenas o administrador familiar pode alterar estas opções. O nome da família é gerenciado exclusivamente pelo administrador do sistema.</p>
<h3>Organização das finanças</h3>
<div class="finance-mode-options">
<div class="finance-mode-option selected"><i class="bi bi-people-fill" aria-hidden="true"></i><div><strong>Finanças compartilhadas</strong><p>Todos os membros da família continuam visualizando as informações financeiras em conjunto.</p></div><span class="badge">Ativo</span></div>
<div class="finance-mode-option unavailable"><i class="bi bi-person-lock" aria-hidden="true"></i><div><strong>Finanças individuais</strong><p>Separar salários, lançamentos e visualização de cada integrante.</p></div><span class="badge">Em preparação</span></div>
</div>
<p class="alert alert-warning mt-3">A separação individual exige filtros de acesso por membro em todas as consultas e movimentações. Essa opção permanece bloqueada para proteger os dados da família.</p>
<h3 class="mt-4">Responsáveis pelas contas previstas</h3>
<p class="text-secondary">Defina quem acompanha cada conta fixa. Em finanças compartilhadas, isso não oculta valores nem restringe o acesso dos demais membros.</p>
<?php if(!$bills): ?><p class="text-secondary">Nenhuma conta prevista cadastrada.</p><?php endif; ?>
<div class="family-bill-list">
<?php foreach($bills as $bill): ?>
<form method="post" action="/pages/family_settings_action.php" class="family-bill-row">
<input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
<input type="hidden" name="action" value="bill_responsibility">
<input type="hidden" name="bill_id" value="<?= (int)$bill['id'] ?>">
<strong><?= e($bill['name']) ?></strong>
<label>Responsável
<select name="user_id" class="form-select">
<option value="0">Sem responsável individual</option>
<?php foreach($members as $member): ?><option value="<?= (int)$member['id'] ?>" <?= (int)$bill['user_id']===(int)$member['id']?'selected':'' ?>><?= e($member['name']) ?></option><?php endforeach; ?>
</select></label>
<button class="btn btn-primary" type="submit">Salvar</button>
</form>
<?php endforeach; ?>
</div></section>
<?php endif; ?>
</div></main></div></body></html>

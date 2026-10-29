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
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Minha conta • Bemora</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"><link rel="stylesheet" href="/assets/style.css"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"><link rel="stylesheet" href="/assets/bemora.css?v=2"><link rel="stylesheet" href="/assets/bemora-finance.css?v=2"><link rel="stylesheet" href="/assets/bemora-finish.css?v=3"><?php brand_head_tags(); ?></head><body class="bemora-finance"><div class="shell"><?php render_sidebar('minha-conta',$csrf); ?><main><?php render_topbar($user); ?><div class="content"><?php page_header('MINHA CONTA','Configurações','Dados pessoais e segurança do seu acesso.'); ?>
<?php if($flash): ?><div class="alert <?= $flash['type']==='success'?'success':'' ?>"><?= e($flash['message']) ?></div><?php endif ?>
<div class="split equal"><section class="card" style="padding:24px"><h2><i class="bi bi-person-circle" aria-hidden="true"></i> Seus dados</h2><p><strong>Nome</strong></p><p><?= e($user['name']) ?></p><p><strong>E-mail</strong></p><p><?= e($user['email']) ?></p><p class="muted-copy">Esses dados são exclusivos da sua conta.</p></section>
<section class="card" style="padding:24px"><h2><i class="bi bi-shield-lock" aria-hidden="true"></i> Alterar senha</h2><form method="post" style="display:grid;gap:12px;margin-top:16px"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><label>Senha atual<input class="form-control" type="password" name="current_password" autocomplete="current-password" required></label><label>Nova senha<input class="form-control" type="password" name="new_password" autocomplete="new-password" minlength="12" required></label><label>Confirmar nova senha<input class="form-control" type="password" name="confirm_password" autocomplete="new-password" minlength="12" required></label><button type="submit" class="primary btn btn-primary"><i class="bi bi-shield-lock" aria-hidden="true"></i> Salvar nova senha</button></form></section></div>
</div></main></div></body></html>

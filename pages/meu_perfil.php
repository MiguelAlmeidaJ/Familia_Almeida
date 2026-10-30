<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/layout.php';
$user=require_family_access();
$pdo=db();$csrf=csrf_token();$flash=pull_flash();$id=(int)$user['id'];
$q=$pdo->prepare('SELECT name,email,phone,avatar_path FROM users WHERE id=?');$q->execute([$id]);$profile=$q->fetch();
$q=$pdo->prepare('SELECT id,job_title,salary,payday FROM user_income_sources WHERE user_id=? AND ended_month IS NULL ORDER BY id');$q->execute([$id]);$sources=$q->fetchAll();
$hasAvatar=!empty($profile['avatar_path']);
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Meu perfil • Bemora</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"><link rel="stylesheet" href="/assets/style.css"><link rel="stylesheet" href="/assets/bemora.css?v=2"><link rel="stylesheet" href="/assets/bemora-finance.css?v=2"><link rel="stylesheet" href="/assets/bemora-finish.css?v=3"><?php brand_head_tags(); ?><link rel="stylesheet" href="/assets/bemora-sidebar.css?v=3"><link rel="stylesheet" href="/assets/bemora-profile.css?v=6"></head>
<body class="bemora-finance"><div class="shell ref-shell"><?php render_sidebar('meu-perfil',$csrf); ?><main><?php render_topbar($user,'Seu espaço pessoal'); ?>
<div class="content bemora-profile"><?php page_header('SUA CONTA','Meu perfil','Suas informações pessoais e suas fontes de renda, em um só lugar.'); ?>
<?php if($flash): ?><div class="alert <?= ($flash['type']??'')==='success'?'alert-success':'alert-danger' ?>"><?= e($flash['message']) ?></div><?php endif; ?>
<div class="profile-layout">
<section class="profile-panel profile-photo-panel"><h2><i class="bi bi-person-bounding-box"></i> Sua foto</h2><div class="profile-avatar-preview" id="current-avatar">
<?php if($hasAvatar): ?><img src="/meu-perfil/foto?v=<?= e(substr((string)$profile['avatar_path'],0,12)) ?>" alt="Sua foto de perfil"><?php else: ?><span><?= e(mb_strtoupper(mb_substr((string)$profile['name'],0,1))) ?></span><?php endif; ?></div>
<p>Escolha uma foto ou use a câmera do celular. Você poderá ajustar o enquadramento antes de salvar.</p>
<div class="profile-photo-actions"><button type="button" id="photo-choose" class="btn btn-success"><i class="bi bi-image"></i> Escolher foto</button><button type="button" id="photo-camera" class="btn btn-outline-success"><i class="bi bi-camera"></i> Tirar foto</button></div>
<input id="photo-file" type="file" accept="image/jpeg,image/png,image/webp,image/*" hidden>
<input id="photo-capture" type="file" accept="image/*" capture="user" hidden>
<?php if($hasAvatar): ?><form method="post" action="/meu-perfil/acao"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="remove_avatar"><button type="submit" class="btn btn-link profile-remove-photo">Remover foto atual</button></form><?php endif; ?>
<div class="profile-photo-note"><i class="bi bi-shield-check"></i> A foto é salva em 1080 × 1080 px e não fica em uma galeria pública.</div></section>
<section class="profile-panel profile-data-panel"><h2><i class="bi bi-person-vcard"></i> Dados pessoais</h2>
<form method="post" action="/meu-perfil/acao" id="profile-data-form">
<input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="save_profile">
<div class="profile-fields"><label>Nome completo<input type="text" class="form-control" name="name" maxlength="80" required autocomplete="name" value="<?= e($profile['name']) ?>"></label>
<label>E-mail<input type="email" class="form-control" name="email" maxlength="160" required autocomplete="email" value="<?= e($profile['email']) ?>"></label>
<label>Telefone<input type="tel" class="form-control" name="phone" id="profile-phone" inputmode="numeric" maxlength="16" autocomplete="tel" placeholder="(22) 99999-9999" value="<?= e($profile['phone']??'') ?>"></label></div>
<div class="profile-sources-header"><div><h2><i class="bi bi-briefcase"></i> Fontes de renda</h2><p>Cadastre seus empregos e trabalhos. Você pode ter mais de uma fonte.</p></div><button type="button" id="add-income-source" class="btn btn-outline-success"><i class="bi bi-plus-lg"></i> Adicionar fonte</button></div>
<div class="income-effective-panel"><div class="income-effective-icon"><i class="bi bi-calendar2-check"></i></div><div class="income-effective-copy"><strong>Quando as alterações começam?</strong><span>O histórico anterior será preservado. Novos valores valem a partir do mês escolhido.</span></div><label class="profile-effective-date"><span>Mês de início</span><input type="month" class="form-control" name="effective_month" id="income-effective-month" value="<?= e(date('Y-m')) ?>" min="<?= e(date('Y-m')) ?>" required></label></div><div class="income-overview"><div><span>Total mensal cadastrado</span><strong id="profile-income-total">R$ 0,00</strong></div><span id="profile-income-count">0 fontes</span></div><div id="income-sources" class="income-source-list">
<?php foreach($sources as $i=>$source): ?><div class="income-source-row"><div class="income-source-title"><div class="income-source-label"><i class="bi bi-briefcase"></i><strong>Fonte de renda <?= (int)$i+1 ?></strong><span class="income-source-status">Mensal</span></div><button type="button" class="source-remove" aria-label="Remover fonte"><i class="bi bi-trash3"></i></button></div><input type="hidden" name="source_id[]" value="<?= (int)$source['id'] ?>"><div class="income-source-fields">
<label>Emprego ou atividade<input name="job_title[]" class="form-control" maxlength="120" required placeholder="Ex.: Administrador" value="<?= e($source['job_title']) ?>"></label>
<label>Salário / renda mensal (R$)<input name="salary[]" class="form-control profile-currency" inputmode="numeric" required placeholder="R$ 1.850,00" value="<?= e('R$ '.number_format((float)$source['salary'],2,',','.')) ?>"></label>
<label>Dia de recebimento<input name="payday[]" class="form-control" type="number" min="1" max="31" required value="<?= (int)$source['payday'] ?>"></label>
</div></div><?php endforeach; ?>
</div>
<p class="profile-help"><i class="bi bi-info-circle"></i> O dia 31 pode não existir em todos os meses. As alterações de salário valem somente a partir do mês escolhido, preservando as projeções anteriores. Não geram lançamentos bancários nem confirmam recebimento; evite cadastrar o mesmo salário manualmente para não duplicar a previsão.</p>
<button class="btn btn-success profile-save" type="submit"><i class="bi bi-check-lg"></i> Salvar meu perfil</button>
</form>
</section></div>
<section class="profile-panel profile-security-panel">
<div class="profile-security-heading"><div><h2><i class="bi bi-shield-lock"></i> Segurança da conta</h2><p>Atualize sua senha sempre que precisar proteger seu acesso.</p></div>
<button type="button" class="btn btn-outline-success" id="profile-password-toggle" aria-haspopup="dialog" aria-controls="profile-password-dialog"><i class="bi bi-key"></i> Alterar senha</button></div>
</section>
<dialog id="profile-password-dialog" class="profile-password-dialog" aria-labelledby="profile-password-title">
<div class="profile-password-dialog-head"><div><h2 id="profile-password-title"><i class="bi bi-shield-lock"></i> Alterar senha</h2><p>Use 8 a 16 caracteres, combinando letras, número e símbolo.</p></div><button type="button" class="profile-password-close" id="profile-password-close" aria-label="Fechar"><i class="bi bi-x-lg"></i></button></div>
<form method="post" action="/meu-perfil/acao" id="profile-password-panel">
<input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="change_password">
<div class="profile-password-fields">
<label>Senha atual<span class="password-input-wrap"><input type="password" name="current_password" class="form-control" autocomplete="current-password" required><button type="button" class="password-eye" aria-label="Mostrar senha atual" aria-pressed="false"><i class="bi bi-eye"></i></button></span><span class="password-validation" id="current-password-feedback" role="status">Informe a senha atual para validá-la.</span></label>
<label>Nova senha<span class="password-input-wrap"><input type="password" name="new_password" class="form-control" autocomplete="new-password" minlength="8" maxlength="16" required><button type="button" class="password-eye" aria-label="Mostrar nova senha" aria-pressed="false"><i class="bi bi-eye"></i></button></span></label>
<div class="password-strength" aria-live="polite"><div class="password-strength-header"><span>Força da senha</span><strong id="password-strength-label">Não informada</strong></div><div class="password-strength-track"><span id="password-strength-fill"></span></div><ul id="password-rules"><li data-rule="length">8 a 16 caracteres</li><li data-rule="upper">Letra maiúscula</li><li data-rule="lower">Letra minúscula</li><li data-rule="digit">Número</li><li data-rule="special">Caractere especial</li></ul></div>
<label>Confirmar nova senha<span class="password-input-wrap"><input type="password" name="confirm_password" class="form-control" autocomplete="new-password" minlength="8" maxlength="16" required><button type="button" class="password-eye" aria-label="Mostrar confirmação de senha" aria-pressed="false"><i class="bi bi-eye"></i></button></span><span id="password-confirm-feedback" class="password-validation" role="status"></span></label>
</div>
<div class="profile-password-actions"><button type="button" class="btn btn-outline-secondary" id="profile-password-cancel">Cancelar</button><button class="btn btn-success" type="submit" id="password-change-submit" disabled><i class="bi bi-check-lg"></i> Salvar nova senha</button></div>
</form></dialog>
</div></main></div>
<dialog id="profile-crop-dialog" class="profile-crop-dialog"><form method="dialog"><button class="profile-crop-close" aria-label="Fechar" value="cancel"><i class="bi bi-x-lg"></i></button></form><h2>Ajustar foto</h2><p>Arraste a imagem para enquadrar o rosto e ajuste o zoom.</p>
<div class="crop-viewport" id="crop-viewport"><img id="crop-image" alt="Prévia da imagem selecionada" draggable="false"></div>
<label class="crop-zoom-label">Zoom <input id="crop-zoom" type="range" min="1" max="3" value="1" step=".01"></label>
<form method="post" action="/meu-perfil/acao" id="profile-avatar-form"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="save_avatar"><input type="hidden" name="avatar_image" id="avatar-image-data"><div class="crop-actions"><button class="btn btn-outline-secondary" type="button" id="crop-cancel">Cancelar</button><button class="btn btn-success" id="crop-save" type="submit">Salvar foto 1080 × 1080</button></div></form></dialog>
<template id="income-source-template"><div class="income-source-row"><div class="income-source-title"><div class="income-source-label"><i class="bi bi-briefcase"></i><strong>Fonte de renda</strong><span class="income-source-status">Mensal</span></div><button type="button" class="source-remove" aria-label="Remover fonte"><i class="bi bi-trash3"></i></button></div><input type="hidden" name="source_id[]" value="0"><div class="income-source-fields"><label>Emprego ou atividade<input name="job_title[]" class="form-control" maxlength="120" required placeholder="Ex.: Autônomo"></label><label>Salário / renda mensal (R$)<input name="salary[]" class="form-control profile-currency" inputmode="numeric" required placeholder="R$ 1.500,00"></label><label>Dia de recebimento<input name="payday[]" class="form-control" type="number" min="1" max="31" required placeholder="Ex.: 15"></label></div></div></template>
<script src="/assets/bemora-profile.js?v=5" defer></script></body></html>
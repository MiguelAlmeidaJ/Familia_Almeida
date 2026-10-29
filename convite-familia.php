<?php
declare(strict_types=1);
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/family_names.php';
$token=(string)($_GET['token']??$_POST['token']??'');
$valid=preg_match('/^[a-f0-9]{64}$/D',$token)===1;
$invitation=null;$error='';
if($valid){
 $stmt=db()->prepare("SELECT id,max_members,expires_at FROM family_invitations WHERE token_hash=? AND used_at IS NULL AND revoked_at IS NULL AND expires_at>NOW()");
 $stmt->execute([hash('sha256',$token)]);$invitation=$stmt->fetch();
}
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 if(!$invitation){$error='Convite expirado ou já utilizado.';}
 else{
  try{
   [$suffix,$name,$nameKey]=family_name_parts((string)($_POST['family_name']??''));$count=filter_var($_POST['member_count']??0,FILTER_VALIDATE_INT);
   $admin=filter_var($_POST['family_admin']??-1,FILTER_VALIDATE_INT);
   if(mb_strlen($name)<3||mb_strlen($name)>160)throw new RuntimeException('Nome da família deve ter entre 3 e 160 caracteres.');
   if(!$count||$count<1||$count>(int)$invitation['max_members'])throw new RuntimeException('Quantidade de membros inválida.');
   if($admin===false||$admin<0||$admin>=$count)throw new RuntimeException('Escolha um administrador da família.');
   $members=[];$emails=[];
   for($i=0;$i<$count;$i++){
    $m=$_POST['members'][$i]??[];$mn=trim((string)($m['name']??''));$email=mb_strtolower(trim((string)($m['email']??'')));
    $pw=(string)($m['password']??'');$sex=(string)($m['sex']??'');$dob=(string)($m['birth_date']??'');
    $date=DateTimeImmutable::createFromFormat('!Y-m-d',$dob);
    if(mb_strlen($mn)<2||mb_strlen($mn)>80||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($pw)<12||!in_array($sex,['M','F'],true)||!$date||$date->format('Y-m-d')!==$dob||$date>new DateTimeImmutable('today'))throw new RuntimeException('Confira os dados de cada membro. Senhas devem ter pelo menos 12 caracteres.');
    if(in_array($email,$emails,true))throw new RuntimeException('Não repita e-mails entre os membros.');
    $emails[]=$email;$members[]=['name'=>$mn,'email'=>$email,'password_hash'=>password_hash($pw,PASSWORD_DEFAULT),'sex'=>$sex,'birth_date'=>$dob];
   }
   $pdo=db();$pdo->beginTransaction();
   $check=$pdo->prepare("SELECT id FROM family_invitations WHERE id=? AND token_hash=? AND used_at IS NULL AND revoked_at IS NULL AND expires_at>NOW() FOR UPDATE");
   $check->execute([(int)$invitation['id'],hash('sha256',$token)]);if(!$check->fetch())throw new RuntimeException('Convite já utilizado.');
   $key=$nameKey;
   if(family_name_taken($pdo,$key))throw new RuntimeException('Esse nome de família já existe. Escolha uma das sugestões disponíveis.');
   $check=$pdo->prepare('SELECT 1 FROM users WHERE email=? LIMIT 1');
   foreach($emails as $email){$check->execute([$email]);if($check->fetchColumn())throw new RuntimeException('Um dos e-mails já possui cadastro. Use outro e-mail.');}
   $check=$pdo->prepare("INSERT INTO family_registrations(invitation_id,family_name,family_name_key,member_count,admin_member,members_json) VALUES(?,?,?,?,?,?)");
   $check->execute([(int)$invitation['id'],$name,$key,$count,$admin,json_encode($members,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
   $pdo->prepare('UPDATE family_invitations SET used_at=NOW() WHERE id=?')->execute([(int)$invitation['id']]);
   $pdo->commit();redirect_to('/convite-familia.php?status=received');
  }catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();$error=$e instanceof PDOException?'Nome da família já reservado ou dados duplicados.':$e->getMessage();}
  }
}
?><!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="referrer" content="no-referrer"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Cadastro da família • Bemora</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="/assets/bemora-invite.css?v=1">
<?php require_once __DIR__.'/includes/branding.php'; brand_head_tags(); ?>
</head><body class="invite-page"><main class="invite-shell">
<header class="invite-brand">
<?php if(brand_asset_url('logo')): ?><img class="invite-logo" src="<?= e(brand_asset_url('logo')) ?>" alt="Bemora">
<?php else: ?><span class="invite-wordmark">bemora</span><?php endif; ?>
</header>
<?php if(($_GET['status']??'')==='received'): ?>
<section class="invite-card invite-state"><i class="bi bi-check-circle" aria-hidden="true"></i><h1 class="invite-title">Cadastro recebido!</h1><p class="invite-subtitle">Sua família foi enviada para análise. O acesso financeiro só será liberado após aprovação e ativação segura.</p></section>
<?php elseif(!$invitation): ?>
<section class="invite-card invite-state"><i class="bi bi-link-45deg" aria-hidden="true"></i><h1 class="invite-title">Convite indisponível</h1><p class="invite-subtitle">Este link é inválido, expirou ou já foi utilizado. Solicite outro convite ao responsável.</p></section>
<?php else: ?>
<section class="invite-card">
<h2 class="invite-title">Cadastro da família</h2><p class="invite-subtitle">Preencha as informações abaixo para enviar seu cadastro.</p>
<?php if($error): ?><div class="invite-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" id="join-form" novalidate>
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="token" value="<?= e($token) ?>"><input type="hidden" name="family_admin" id="family-admin" value="0">
<nav class="invite-steps" aria-label="Etapas do cadastro">
<div class="invite-step active" data-indicator="0" aria-current="step"><span class="invite-step-number">1</span><span>Família</span></div>
<div class="invite-step" data-indicator="1"><span class="invite-step-number">2</span><span>Membros</span></div>
<div class="invite-step" data-indicator="2"><span class="invite-step-number">3</span><span>Administrador</span></div>
</nav>
<section class="step" data-step="0"><h3 class="invite-section-title">Conheça sua família</h3><div class="invite-form-grid">
<div class="invite-field wide"><label for="family_name">Nome da família</label><div class="family-name-input"><span>Família</span><input id="family_name" name="family_name" maxlength="150" minlength="2" required placeholder="Ex.: Oliveira" autocomplete="off" aria-describedby="family-name-feedback"></div><div id="family-name-feedback" class="family-name-feedback" aria-live="polite"></div><div id="family-name-suggestions" class="family-name-suggestions"></div></div>
<div class="invite-field"><label for="member_count">Quantidade de membros</label><input id="member_count" name="member_count" type="number" min="1" max="<?= (int)$invitation['max_members'] ?>" value="1" required></div>
</div><p class="invite-help">O nome da família precisa ser único na plataforma.</p></section>
<section class="step" data-step="1" hidden><h3 class="invite-section-title">Quem faz parte da família?</h3><p class="invite-subtitle">Preencha uma aba para cada pessoa. Os dados serão utilizados para criar os acessos após aprovação.</p><div class="member-tabs" id="member-tabs" role="tablist" aria-label="Membros"></div><div id="member-fields"></div></section>
<section class="step" data-step="2" hidden><h3 class="invite-section-title">Quem será o administrador?</h3><p class="invite-subtitle">Essa pessoa ficará responsável pelo gerenciamento da família.</p><div id="admin-options" style="margin-top:22px"></div><p class="invite-help">O envio não libera acesso imediato. O cadastro ficará pendente de análise.</p></section>
<div class="invite-actions"><button type="button" class="invite-btn secondary" id="back" hidden><i class="bi bi-arrow-left" aria-hidden="true"></i> Voltar</button><button type="button" class="invite-btn primary" id="next">Continuar <i class="bi bi-arrow-right" aria-hidden="true"></i></button><button type="submit" class="invite-btn primary" id="send" hidden><i class="bi bi-check2-circle" aria-hidden="true"></i> Enviar cadastro</button></div>
</form></section>
<script>
(()=>{
const max=<?= (int)$invitation['max_members'] ?>,form=document.getElementById('join-form'),count=document.getElementById('member_count'),fields=document.getElementById('member-fields'),tabs=document.getElementById('member-tabs'),options=document.getElementById('admin-options'),admin=document.getElementById('family-admin');
const nameInput=document.getElementById('family_name'),nameFeedback=document.getElementById('family-name-feedback'),nameSuggestions=document.getElementById('family-name-suggestions');let availability=null,checkController=null,checkTimer=null;
const steps=[...document.querySelectorAll('[data-step]')],indicators=[...document.querySelectorAll('[data-indicator]')];let step=0,selected=0;
function checkName(){
 const value=nameInput.value.trim();availability=null;nameSuggestions.replaceChildren();nameFeedback.textContent='';nameInput.classList.remove('is-invalid','is-valid');
 if(value.length<2)return;
 checkController?.abort();checkController=new AbortController();
 fetch('/family_name_check.php?token='+encodeURIComponent(document.querySelector('input[name="token"]').value)+'&name='+encodeURIComponent(value),{signal:checkController.signal,cache:'no-store',referrerPolicy:'no-referrer'})
 .then(r=>{if(!r.ok)throw new Error('Não foi possível verificar o nome.');return r.json()})
 .then(data=>{
  if(nameInput.value.trim()!==value)return;
  availability=data.available===true;
  nameInput.classList.toggle('is-invalid',!availability);nameInput.classList.toggle('is-valid',availability);
  if(availability){nameFeedback.textContent='Nome disponível: '+data.display_name;return;}
  nameFeedback.textContent='Esse nome de família já existe. Experimente:';
  (data.suggestions||[]).forEach(s=>{const button=document.createElement('button');button.type='button';button.className='family-suggestion';button.textContent=s;button.addEventListener('click',()=>{nameInput.value=s;checkName()});nameSuggestions.append(button)});
 }).catch(e=>{if(e.name!=='AbortError'){availability=null;nameFeedback.textContent='Não foi possível verificar o nome. Tente novamente.'}});
}
nameInput.addEventListener('input',()=>{clearTimeout(checkTimer);checkController?.abort();availability=null;nameInput.classList.remove('is-invalid','is-valid');nameFeedback.textContent='';nameSuggestions.replaceChildren();checkTimer=setTimeout(checkName,350)});
function size(){return Math.min(max,Math.max(1,Number(count.value)||1))}
function build(){
 const n=size();while(fields.children.length<n){const i=fields.children.length,d=document.createElement('div');d.className='member-fields';d.dataset.index=i;d.id='person-panel-'+i;d.setAttribute('role','tabpanel');
 d.innerHTML=`<div class="invite-form-grid">
 <div class="invite-field"><label for="person-${i}-name">Nome</label><input id="person-${i}-name" name="members[${i}][name]" maxlength="80" minlength="2" required></div>
 <div class="invite-field"><label for="person-${i}-email">E-mail</label><input id="person-${i}-email" type="email" name="members[${i}][email]" required></div>
 <div class="invite-field"><label for="person-${i}-password">Senha</label><input id="person-${i}-password" type="password" name="members[${i}][password]" minlength="12" autocomplete="new-password" required></div>
 <div class="invite-field"><label for="person-${i}-sex">Sexo</label><select id="person-${i}-sex" name="members[${i}][sex]" required><option value="">Selecione</option><option value="M">Masculino</option><option value="F">Feminino</option></select></div>
 <div class="invite-field"><label for="person-${i}-birth">Data de nascimento</label><input id="person-${i}-birth" type="date" name="members[${i}][birth_date]" max="<?= date('Y-m-d') ?>" required></div>
 </div>`;fields.append(d)}
 while(fields.children.length>n)fields.lastElementChild.remove();selected=Math.min(n-1,selected);if(Number(admin.value)>=n)admin.value='0';renderMembers();
}
function renderMembers(){
 const n=fields.children.length;tabs.replaceChildren();
 for(let i=0;i<n;i++){const b=document.createElement('button');b.type='button';b.className='member-tab'+(i===selected?' active':'');b.id='person-tab-'+i;b.setAttribute('role','tab');b.setAttribute('aria-controls','person-panel-'+i);b.setAttribute('aria-selected',String(i===selected));b.textContent='Pessoa '+(i+1);b.addEventListener('click',()=>{selected=i;renderMembers()});tabs.append(b)}
 [...fields.children].forEach((panel,i)=>{panel.hidden=i!==selected;panel.setAttribute('aria-labelledby','person-tab-'+i)});
}
function adminOptions(){
 options.replaceChildren();[...fields.children].forEach((panel,i)=>{
 const name=panel.querySelector('input[name$="[name]"]').value.trim()||'Pessoa '+(i+1);
 const label=document.createElement('label');label.className='admin-choice';const radio=document.createElement('input');radio.type='radio';radio.name='admin_choice';radio.value=String(i);radio.checked=Number(admin.value)===i;radio.addEventListener('change',()=>{admin.value=String(i)});const title=document.createElement('strong');title.textContent=name;label.append(radio,title);options.append(label);
 });
}
function validateFirst(){for(const input of steps[0].querySelectorAll('input'))if(!input.reportValidity())return false;if(availability!==true){nameInput.classList.add('is-invalid');nameFeedback.textContent=availability===false?'Esse nome de família já existe. Escolha uma sugestão.':'Verifique a disponibilidade do nome antes de continuar.';nameInput.focus();return false;}return true}
function validateMembers(){for(const [i,panel] of [...fields.children].entries()){for(const input of panel.querySelectorAll('input,select')){if(!input.checkValidity()){selected=i;renderMembers();input.reportValidity();return false}}}return true}
function show(){steps.forEach((panel,i)=>{panel.hidden=i!==step});indicators.forEach((indicator,i)=>{indicator.classList.toggle('active',i===step);indicator.classList.toggle('done',i<step);if(i===step)indicator.setAttribute('aria-current','step');else indicator.removeAttribute('aria-current')});document.getElementById('back').hidden=step===0;document.getElementById('next').hidden=step===2;document.getElementById('send').hidden=step!==2;if(step===1)renderMembers();if(step===2)adminOptions()}
document.getElementById('next').addEventListener('click',()=>{if(step===0){if(!validateFirst())return;build()}else if(step===1&&!validateMembers())return;step=Math.min(2,step+1);show()});
document.getElementById('back').addEventListener('click',()=>{step=Math.max(0,step-1);show()});
form.addEventListener('submit',e=>{if(!validateFirst()||!validateMembers()){e.preventDefault();step=!validateFirst()?0:1;show();return}document.getElementById('send').disabled=true;document.getElementById('send').textContent='Enviando...'});
show();
})();
</script>
<?php endif; ?>
</main></body></html>

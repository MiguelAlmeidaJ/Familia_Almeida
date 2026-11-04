<?php
declare(strict_types=1);
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/onboarding_address.php';
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
   $count=(int)$invitation['max_members']; // Paid invitation owns the number of accesses.
   $address=bemora_validate_onboarding_address(is_array($_POST['address']??null)?$_POST['address']:[]);
   $selectedAdmin=filter_var($_POST['family_admin']??-1,FILTER_VALIDATE_INT);
   $financeMode=$count>1?(string)($_POST['finance_mode']??''):'shared';
   if(!in_array($financeMode,['shared','separate'],true))throw new RuntimeException('Escolha entre finanças compartilhadas e separadas.');
   if($count<1||$count>30)throw new RuntimeException('Quantidade de acessos contratados inválida.');
   if($selectedAdmin===false||$selectedAdmin<0||$selectedAdmin>=$count)throw new RuntimeException('Escolha um administrador entre os integrantes cadastrados.');
   $members=[];$emails=[];$admin=null;$emptySlots=0;
   for($i=0;$i<$count;$i++){
    $m=$_POST['members'][$i]??[];
    if(!is_array($m))throw new RuntimeException('Dados de integrante inválidos.');
    if((string)($m['empty']??'')==='1'){
     // An unoccupied paid seat is never inserted as a user.
     // Reject inconsistent payloads instead of silently discarding credentials.
     foreach(['name','email','password','sex','birth_date'] as $field){
      if(trim((string)($m[$field]??''))!=='')throw new RuntimeException('Uma vaga vazia não pode conter dados de cadastro.');
     }
     $emptySlots++;continue;
    }
    $mn=trim((string)($m['name']??''));$email=mb_strtolower(trim((string)($m['email']??'')));
    $pw=(string)($m['password']??'');$sex=(string)($m['sex']??'');$dob=(string)($m['birth_date']??'');
    $date=DateTimeImmutable::createFromFormat('!Y-m-d',$dob);
    if(mb_strlen($mn)<2||mb_strlen($mn)>80||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($pw)<12||!in_array($sex,['M','F'],true)||!$date||$date->format('Y-m-d')!==$dob||$date>new DateTimeImmutable('today'))throw new RuntimeException('Confira os dados de cada membro. Senhas devem ter pelo menos 12 caracteres.');
    if(in_array($email,$emails,true))throw new RuntimeException('Não repita e-mails entre os membros.');
    $emails[]=$email;
    if($i===$selectedAdmin)$admin=count($members);
    $members[]=['name'=>$mn,'email'=>$email,'password_hash'=>password_hash($pw,PASSWORD_DEFAULT),'sex'=>$sex,'birth_date'=>$dob];
   }
   if(count($members)<1)throw new RuntimeException('Cadastre pelo menos um integrante antes de continuar.');
   if($admin===null)throw new RuntimeException('O administrador deve ser um integrante cadastrado, não uma vaga vazia.');
   $pdo=db();
   if(!bemora_onboarding_address_ready($pdo))throw new RuntimeException('Cadastro de endereço ainda não disponível. Execute a migration 047 em Manutenção.');
   $pdo->beginTransaction();
   $check=$pdo->prepare("SELECT id FROM family_invitations WHERE id=? AND token_hash=? AND used_at IS NULL AND revoked_at IS NULL AND expires_at>NOW() FOR UPDATE");
   $check->execute([(int)$invitation['id'],hash('sha256',$token)]);if(!$check->fetch())throw new RuntimeException('Convite já utilizado.');
   // Internal tenant name is generated automatically; no buyer-facing family name.
   $name='Espaço de '.mb_substr($members[$admin]['name'],0,140);
   $key='bemora-espaco-'.(int)$invitation['id'];
   $check=$pdo->prepare('SELECT 1 FROM users WHERE email=? LIMIT 1');
   foreach($emails as $email){$check->execute([$email]);if($check->fetchColumn())throw new RuntimeException('Um dos e-mails já possui cadastro. Use outro e-mail.');}
   require_once __DIR__.'/includes/finance_scope.php';
   if(!bemora_multi_tenant_released()||!finance_separate_schema_ready($pdo))throw new RuntimeException('Cadastro temporariamente indisponível durante a validação de segurança.');
   $check=$pdo->prepare("INSERT INTO family_registrations(invitation_id,family_name,family_name_key,member_count,admin_member,members_json) VALUES(?,?,?,?,?,?)");
   $check->execute([(int)$invitation['id'],$name,$key,count($members),$admin,json_encode($members,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
   require_once __DIR__.'/includes/family_onboarding.php';
   $login=bemora_activate_registration($pdo,(int)$pdo->lastInsertId(),$members,$admin,$name,$key,(int)$invitation['max_members'],$financeMode);
   bemora_store_onboarding_address($pdo,(int)$login['tenant_id'],$address);
   // Bind a completed checkout to the newly created isolated family.
   if((int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='subscription_orders'")->fetchColumn()===1){
    $orderLink=$pdo->prepare("UPDATE subscription_orders SET activated_tenant_id=?,status='completed' WHERE invite_id=? AND status='paid' AND activated_tenant_id IS NULL");
    $orderLink->execute([(int)$login['tenant_id'],(int)$invitation['id']]);
   }
   $pdo->prepare('UPDATE family_invitations SET used_at=NOW() WHERE id=?')->execute([(int)$invitation['id']]);
   $pdo->commit();
   session_regenerate_id(true);
   $_SESSION['user_id']=$login['user_id'];
   $_SESSION['tenant_id']=$login['tenant_id'];
   unset($_SESSION['selected_month']);
   redirect_to('/painel');
  }catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();$error=$e instanceof PDOException?'Não foi possível concluir o cadastro. Verifique os dados informados.':$e->getMessage();}
  }
}
?><!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="referrer" content="no-referrer"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Crie seu acesso • Bemora</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="/assets/bemora-invite.css?v=7">
<?php require_once __DIR__.'/includes/branding.php'; brand_head_tags(); ?>
</head><body class="invite-page"><main class="invite-shell">
<header class="invite-brand">
<?php if(brand_asset_url('logo')): ?><img class="invite-logo" src="<?= e(brand_asset_url('logo')) ?>" alt="Bemora">
<?php else: ?><span class="invite-wordmark">bemora</span><?php endif; ?>
</header>
<?php if(!$invitation): ?>
<section class="invite-card invite-state"><i class="bi bi-link-45deg" aria-hidden="true"></i><h1 class="invite-title">Convite indisponível</h1><p class="invite-subtitle">Este link é inválido, expirou ou já foi utilizado. Solicite outro convite ao responsável.</p></section>
<?php else: ?>
<section class="invite-card">
<h2 class="invite-title">Seu espaço Bemora começa aqui</h2>
<p class="invite-subtitle">Sua assinatura inclui <strong><?= (int)$invitation['max_members'] ?> <?= (int)$invitation['max_members']===1?'acesso':'acessos' ?></strong>. Cadastre <?= (int)$invitation['max_members']===1?'seus dados':'os integrantes' ?> para começar.</p>
<?php if($error): ?><div class="invite-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" id="join-form" novalidate>
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="token" value="<?= e($token) ?>"><input type="hidden" name="family_admin" id="family-admin" value="0">
<input type="hidden" id="member_count" value="<?= (int)$invitation['max_members'] ?>">
<nav class="invite-steps" aria-label="Etapas do cadastro">
<div class="invite-step active" data-indicator="0" aria-current="step"><span class="invite-step-number">1</span><span><?= (int)$invitation['max_members']===1?'Seus dados':'Integrantes' ?></span></div>
<?php if((int)$invitation['max_members']>1): ?><div class="invite-step" data-indicator="1"><span class="invite-step-number">2</span><span>Administrador</span></div><?php endif; ?>
<div class="invite-step" data-indicator="<?= (int)$invitation['max_members']>1?2:1 ?>"><span class="invite-step-number"><?= (int)$invitation['max_members']>1?3:2 ?></span><span>Localização</span></div>
</nav>
<section class="step" data-step="0"><div class="invite-section-head"><h3 class="invite-section-title"><?= (int)$invitation['max_members']===1?'Crie sua conta':'Cadastre os integrantes' ?></h3><span class="invite-seats-badge"><i class="bi bi-people" aria-hidden="true"></i> <?= (int)$invitation['max_members'] ?> <?= (int)$invitation['max_members']===1?'pessoa':'pessoas' ?></span></div>
<p class="invite-subtitle"><?= (int)$invitation['max_members']===1?'Informe seus dados para acessar o Bemora.':'Cadastre agora quem vai usar o sistema. As vagas deixadas vazias poderão ser preenchidas depois pelo administrador.' ?></p>
<div class="member-tabs" id="member-tabs" role="tablist" aria-label="Integrantes"></div><div id="member-fields"></div></section>
<?php if((int)$invitation['max_members']>1): ?><section class="step" data-step="1" hidden><h3 class="invite-section-title">Quem vai administrar os acessos?</h3><p class="invite-subtitle">Escolha quem poderá gerenciar os integrantes da conta.</p><div id="admin-options" style="margin-top:22px"></div>
 <fieldset class="invite-finance-mode"><legend>Como vocês vão organizar as finanças?</legend><p class="invite-subtitle">Escolha como os dados financeiros serão apresentados aos integrantes.</p><div class="invite-finance-options">
 <label class="invite-finance-choice"><input type="radio" name="finance_mode" value="shared" checked required><span><strong><i class="bi bi-people" aria-hidden="true"></i> Compartilhado</strong><small>Os integrantes usam o mesmo espaço financeiro, com uma visão conjunta das receitas, despesas e contas.</small></span></label>
 <label class="invite-finance-choice"><input type="radio" name="finance_mode" value="separate" required><span><strong><i class="bi bi-person-lock" aria-hidden="true"></i> Separado</strong><small>Cada integrante organiza as próprias finanças no seu acesso, respeitando as permissões de titularidade.</small></span></label></div></fieldset></section><?php endif; ?>
<section class="step" data-step="<?= (int)$invitation['max_members']>1?2:1 ?>" hidden>
 <div class="invite-section-head"><h3 class="invite-section-title">Onde você está?</h3><span class="invite-seats-badge"><i class="bi bi-geo-alt" aria-hidden="true"></i> Endereço do seu espaço</span></div>
 <p class="invite-subtitle">Vamos completar a localização da sua conta. Digite o CEP para preencher os dados automaticamente ou informe-os manualmente.</p>
 <div class="invite-address-grid">
  <div class="invite-field invite-address-cep"><label for="address-postal">CEP</label><div class="invite-postal-entry"><input id="address-postal" name="address[postal_code]" type="text" placeholder="00000-000" inputmode="numeric" autocomplete="postal-code" maxlength="9" pattern="[0-9]{5}-?[0-9]{3}" required><button type="button" id="address-lookup"><i class="bi bi-search" aria-hidden="true"></i> Buscar CEP</button></div><small id="address-lookup-status" role="status" aria-live="polite">Consulta automática pelo CEP.</small></div>
  <div class="invite-field invite-address-wide"><label for="address-street">Endereço</label><input id="address-street" name="address[street]" type="text" placeholder="Rua, avenida ou logradouro" autocomplete="street-address" minlength="3" maxlength="180" required></div>
  <div class="invite-field"><label for="address-state">Estado</label><select id="address-state" name="address[state]" autocomplete="address-level1" required><option value="">Selecione o estado</option><?php foreach(bemora_brazil_states() as $uf=>$stateName): ?><option value="<?= e($uf) ?>"><?= e($stateName) ?> (<?= e($uf) ?>)</option><?php endforeach; ?></select></div>
  <div class="invite-field"><label for="address-city">Cidade</label><select id="address-city" name="address[city]" autocomplete="address-level2" required disabled><option value="">Selecione primeiro um estado</option></select><input id="address-city-manual" name="address[city_manual]" type="text" autocomplete="address-level2" placeholder="Informe sua cidade" minlength="2" maxlength="120" hidden disabled><small id="address-city-status" aria-live="polite">Escolha o estado para carregar os municípios.</small></div>
 </div>
 <p class="invite-help">Não conseguiu consultar o CEP? Você pode preencher o endereço e escolher estado e cidade manualmente.</p>
</section>
<div class="invite-actions"><button type="button" class="invite-btn secondary" id="back" hidden><i class="bi bi-arrow-left" aria-hidden="true"></i> Voltar</button>
<button type="button" class="invite-btn primary" id="next" <?= (int)$invitation['max_members']===1?'hidden':'' ?>>Continuar <i class="bi bi-arrow-right" aria-hidden="true"></i></button>
<button type="submit" class="invite-btn primary" id="send" hidden><i class="bi bi-check2-circle" aria-hidden="true"></i> Criar acesso<?= (int)$invitation['max_members']>1?'s':'' ?></button></div>
</form></section>
<script>
(()=>{
const max=<?= (int)$invitation['max_members'] ?>,form=document.getElementById('join-form'),count=document.getElementById('member_count'),fields=document.getElementById('member-fields'),tabs=document.getElementById('member-tabs'),options=document.getElementById('admin-options'),admin=document.getElementById('family-admin');
const steps=[...document.querySelectorAll('[data-step]')],indicators=[...document.querySelectorAll('[data-indicator]')];const locationStep=max>1?2:1;let step=0,selected=0;
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
function validateMembers(){for(const [i,panel] of [...fields.children].entries()){for(const input of panel.querySelectorAll('input,select')){if(!input.checkValidity()){selected=i;renderMembers();input.reportValidity();return false}}}return true}
function show(){steps.forEach((panel,i)=>{panel.hidden=i!==step});indicators.forEach((indicator,i)=>{indicator.classList.toggle('active',i===step);indicator.classList.toggle('done',i<step);if(i===step)indicator.setAttribute('aria-current','step');else indicator.removeAttribute('aria-current')});document.getElementById('back').hidden=step===0;document.getElementById('next').hidden=step===locationStep;document.getElementById('send').hidden=step!==locationStep;if(step===0)renderMembers();if(step===1&&max>1)adminOptions()}
document.getElementById('next').addEventListener('click',()=>{
 if(step===0&&!validateMembers())return;
 if(step>=locationStep)return;
 step+=1;show();
});
document.getElementById('back').addEventListener('click',()=>{step=Math.max(0,step-1);show()});
form.addEventListener('submit',e=>{
 if(!validateMembers()){e.preventDefault();step=0;show();return;}
 if(typeof window.bemoraAddressValidate!=='function'||!window.bemoraAddressValidate()){
  e.preventDefault();step=locationStep;show();return;
 }
 if(max===1)admin.value='0';
 document.getElementById('send').disabled=true;
 document.getElementById('send').textContent='Criando acessos...';
});
build();show();
})();
</script>
<script src="/assets/bemora-onboarding-address.js?v=1"></script>
<?php endif; ?>
</main></body></html>

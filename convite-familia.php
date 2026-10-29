<?php
declare(strict_types=1);
require_once __DIR__.'/includes/bootstrap.php';
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
   $name=trim((string)($_POST['family_name']??''));$count=filter_var($_POST['member_count']??0,FILTER_VALIDATE_INT);
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
   $key=mb_strtolower($name);
   $check=$pdo->prepare('SELECT 1 FROM tenants WHERE name_key=? LIMIT 1');$check->execute([$key]);
   if($check->fetchColumn())throw new RuntimeException('Este nome de família já está cadastrado.');
   $check=$pdo->prepare('SELECT 1 FROM users WHERE email=? LIMIT 1');
   foreach($emails as $email){$check->execute([$email]);if($check->fetchColumn())throw new RuntimeException('Um dos e-mails já possui cadastro. Use outro e-mail.');}
   $check=$pdo->prepare("INSERT INTO family_registrations(invitation_id,family_name,family_name_key,member_count,admin_member,members_json) VALUES(?,?,?,?,?,?)");
   $check->execute([(int)$invitation['id'],$name,$key,$count,$admin,json_encode($members,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
   $pdo->prepare('UPDATE family_invitations SET used_at=NOW() WHERE id=?')->execute([(int)$invitation['id']]);
   $pdo->commit();redirect_to('/convite-familia.php?status=received');
  }catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();$error=$e instanceof PDOException?'Nome da família já reservado ou dados duplicados.':$e->getMessage();}
  }
}
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="referrer" content="no-referrer"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Cadastro da família • Bemora</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"><link rel="stylesheet" href="/assets/bemora.css"><style>body{background:#f7faf8}.join{max-width:860px;margin:48px auto;padding:24px}.join-card{background:white;border:1px solid #dce9e1;border-radius:22px;padding:30px}.step{display:none}.step.active{display:block}.nav-pills .nav-link.active,.btn-primary{background:#06463f!important;border-color:#06463f!important}.nav-pills .nav-link{color:#06463f}label{font-weight:600;margin-bottom:5px}.form-control,.form-select{margin-bottom:16px}.member-tab{border:1px solid #dce9e1;border-radius:10px;margin:4px;padding:10px 14px;background:white}.member-tab.active{background:#e5f5eb}.actions{display:flex;justify-content:space-between;margin-top:24px;gap:12px}@media(max-width:600px){.join{margin:10px auto;padding:12px}.join-card{padding:18px}}</style></head><body><main class="join"><header class="mb-4"><h1>bemora</h1><p>Seu dinheiro em ordem. Sua vida mais leve.</p></header><div class="join-card">
<?php if(($_GET['status']??'')==='received'): ?><h2>Cadastro recebido</h2><p>Os dados da família foram enviados para análise do administrador. O acesso financeiro será liberado somente após aprovação e ativação segura.</p>
<?php elseif(!$invitation): ?><h2>Convite indisponível</h2><p>O link expirou, já foi utilizado ou é inválido. Solicite um novo convite.</p>
<?php else: ?>
<h2>Cadastro da família</h2><p class="text-secondary">Convite para até <?= (int)$invitation['max_members'] ?> membros. Uso único.</p>
<?php if($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" id="join-form" novalidate><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="token" value="<?= e($token) ?>"><input type="hidden" name="family_admin" id="family-admin" value="0">
<div class="d-flex gap-2 mb-4"><span class="badge text-bg-success">1. Família</span><span class="badge text-bg-light">2. Membros</span><span class="badge text-bg-light">3. Administrador</span></div>
<section class="step active" data-step="0"><h3>1. Informações da família</h3><label for="family_name">Nome da família</label><input class="form-control" id="family_name" name="family_name" maxlength="160" minlength="3" required placeholder="Ex.: Família Oliveira"><label for="member_count">Quantidade de membros</label><input class="form-control" id="member_count" name="member_count" type="number" min="1" max="<?= (int)$invitation['max_members'] ?>" value="1" required><small class="text-secondary">O nome deve ser único na plataforma.</small></section>
<section class="step" data-step="1"><h3>2. Dados dos membros</h3><p class="text-secondary">Preencha uma aba para cada pessoa.</p><div id="member-tabs" class="d-flex flex-wrap mb-3"></div><div id="member-fields"></div></section>
<section class="step" data-step="2"><h3>3. Administrador familiar</h3><p>Escolha a pessoa que será responsável por administrar esta família.</p><div id="admin-options"></div><p class="text-secondary mt-3">Ao concluir, o convite será consumido e o cadastro ficará pendente de aprovação.</p></section>
<div class="actions"><button type="button" id="back" class="btn btn-outline-secondary" hidden>Voltar</button><button type="button" id="next" class="btn btn-primary ms-auto">Continuar</button><button type="submit" id="send" class="btn btn-primary" hidden>Enviar cadastro</button></div></form>
<script>
const max=<?= (int)$invitation['max_members'] ?>, steps=[...document.querySelectorAll('.step')],cnt=document.querySelector('#member_count');let step=0,selected=0;
const tabs=document.querySelector('#member-tabs'),fields=document.querySelector('#member-fields'),options=document.querySelector('#admin-options');
function make(){const n=Math.max(1,Math.min(max,Number(cnt.value)||1));while(fields.children.length<n){const i=fields.children.length,d=document.createElement('div');d.className='member-fields';d.dataset.index=i;d.innerHTML=`<div class="row"><div class="col-md-6"><label>Nome</label><input class="form-control" name="members[${i}][name]" maxlength="80" required></div><div class="col-md-6"><label>E-mail</label><input class="form-control" type="email" name="members[${i}][email]" required></div><div class="col-md-6"><label>Senha</label><input class="form-control" type="password" name="members[${i}][password]" minlength="12" autocomplete="new-password" required></div><div class="col-md-6"><label>Sexo</label><select class="form-select" name="members[${i}][sex]" required><option value="">Selecione</option><option value="M">Masculino</option><option value="F">Feminino</option></select></div><div class="col-md-6"><label>Data de nascimento</label><input class="form-control" type="date" name="members[${i}][birth_date]" max="<?= date('Y-m-d') ?>" required></div></div>`;fields.append(d)}
while(fields.children.length>n)fields.lastElementChild.remove();selected=Math.min(selected,n-1);tabs.innerHTML='';
for(let i=0;i<n;i++){const b=document.createElement('button');b.type='button';b.className='member-tab'+(i===selected?' active':'');b.textContent='Pessoa '+(i+1);b.onclick=()=>{selected=i;make()};tabs.append(b)}
[...fields.children].forEach((x,i)=>{x.hidden=i!==selected;x.querySelectorAll('input,select').forEach(y=>y.disabled=i!==selected)});options.innerHTML='';
for(let i=0;i<n;i++){let name=fields.children[i].querySelector('input').value||'Pessoa '+(i+1),div=document.createElement('div');div.className='form-check my-2';div.innerHTML=`<input type="radio" class="form-check-input" name="admin_choice" value="${i}" id="admin-${i}" ${i===Number(document.querySelector('#family-admin').value)?'checked':''}><label class="form-check-label" for="admin-${i}"></label>`;div.querySelector('label').textContent=name;div.querySelector('input').onchange=()=>document.querySelector('#family-admin').value=i;options.append(div)}
}
function validCurrent(){if(step===0){return [...steps[0].querySelectorAll('input')].every(x=>x.reportValidity())&&Number(cnt.value)<=max}
if(step===1){for(const panel of fields.children){for(const input of panel.querySelectorAll('input,select')){input.disabled=false;if(!input.reportValidity()){selected=Number(panel.dataset.index);make();return false}}}make();}return true}
function show(){steps.forEach((x,i)=>x.classList.toggle('active',i===step));document.querySelector('#back').hidden=step===0;document.querySelector('#next').hidden=step===2;document.querySelector('#send').hidden=step!==2;if(step>0)make()}
document.querySelector('#next').onclick=()=>{if(validCurrent()){step=Math.min(2,step+1);show()}};document.querySelector('#back').onclick=()=>{step=Math.max(0,step-1);show()};document.querySelector('#join-form').onsubmit=e=>{if(!validCurrent()){e.preventDefault();return}for(const panel of fields.children)panel.querySelectorAll('input,select').forEach(x=>x.disabled=false)};
show();
</script>
<?php endif; ?></div></main></body></html>

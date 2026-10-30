<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/finance_scope.php';
require_admin_access();
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);exit('Método não permitido.');}
verify_csrf();
$pdo=db();
$id=filter_var($_POST['registration_id']??null,FILTER_VALIDATE_INT);
$action=(string)($_POST['action']??'');
try{
 if(!$id||$id<1||!in_array($action,['approve','reject'],true))throw new RuntimeException('Solicitação inválida.');
 $pdo->beginTransaction();
 $q=$pdo->prepare("SELECT r.*,i.max_members FROM family_registrations r INNER JOIN family_invitations i ON i.id=r.invitation_id WHERE r.id=? FOR UPDATE");
 $q->execute([$id]);$reg=$q->fetch(PDO::FETCH_ASSOC);
 if(!$reg||$reg['state']!=='pending')throw new RuntimeException('Cadastro não está pendente.');
 if($action==='reject'){
  $pdo->prepare("UPDATE family_registrations SET state='rejected',members_json='[]' WHERE id=?")->execute([$id]);
  $pdo->commit();
  flash('success','Cadastro recusado.');
 }else{
  if(!finance_separate_schema_ready($pdo))throw new RuntimeException('Execute as migrations de isolamento antes da aprovação.');
  if(getenv('BEMORA_MULTI_TENANT_RELEASE')!=='1')throw new RuntimeException('Aprovação bloqueada até a liberação final do isolamento multi-tenant.');
  $members=json_decode((string)$reg['members_json'],true,512,JSON_THROW_ON_ERROR);
  $count=(int)$reg['member_count'];$admin=(int)$reg['admin_member'];
  if(!is_array($members)||count($members)!==$count||$count<1||$count>(int)$reg['max_members']||$admin<0||$admin>=$count)
   throw new RuntimeException('Os dados dos membros estão inconsistentes.');
  $duplicate=$pdo->prepare('SELECT 1 FROM users WHERE email=? LIMIT 1');
  $emails=[];
  foreach($members as $m){
   $email=mb_strtolower(trim((string)($m['email']??'')));
   if(!filter_var($email,FILTER_VALIDATE_EMAIL)||isset($emails[$email])||
      !preg_match('/^\$2[aby]\$|^\$argon2/',(string)($m['password_hash']??''))||
      mb_strlen((string)($m['name']??''))<2)
    throw new RuntimeException('Dados de cadastro inválidos.');
   $emails[$email]=true;$duplicate->execute([$email]);
   if($duplicate->fetchColumn())throw new RuntimeException('Um dos e-mails já foi cadastrado. Peça um novo cadastro.');
  }
  $name=(string)$reg['family_name'];$key=(string)$reg['family_name_key'];
  $q=$pdo->prepare('SELECT 1 FROM tenants WHERE name_key=? LIMIT 1');$q->execute([$key]);
  if($q->fetchColumn())throw new RuntimeException('O nome da família já está em uso.');
  // Slugs are immutable and unique without exposing names or email addresses.
  $slug='familia-'.(int)$reg['id'].'-'.bin2hex(random_bytes(4));
  $insert=$pdo->prepare("INSERT INTO tenants(name,slug,name_key,active,status,max_members,finance_mode) VALUES(?,?,?,1,'active',?,'shared')");
  $insert->execute([$name,$slug,$key,(int)$reg['max_members']]);
  $tenantId=(int)$pdo->lastInsertId();
  $userInsert=$pdo->prepare('INSERT INTO users(name,email,password_hash,sex,birth_date) VALUES(?,?,?,?,?)');
  $memberInsert=$pdo->prepare('INSERT INTO tenant_users(tenant_id,user_id,role) VALUES(?,?,?)');
  foreach($members as $index=>$m){
   $userInsert->execute([(string)$m['name'],mb_strtolower((string)$m['email']),(string)$m['password_hash'],$m['sex']??null,$m['birth_date']??null]);
   $memberInsert->execute([$tenantId,(int)$pdo->lastInsertId(),$index===$admin?'family_admin':'member']);
  }
  $pdo->prepare("UPDATE family_registrations SET state='approved',members_json='[]' WHERE id=? AND state='pending'")->execute([$id]);
  $pdo->commit();
  flash('success','Família aprovada e acessos criados. Os membros já podem entrar com suas credenciais.');
 }
}catch(Throwable $e){
 if($pdo->inTransaction())$pdo->rollBack();
 flash('error',$e instanceof PDOException?'Não foi possível aprovar: dados duplicados ou conflito no banco.':$e->getMessage());
}
redirect_to('/admin/tenants');

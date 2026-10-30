<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/admin_layout.php';
require_admin_access();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
verify_csrf();
$pdo=db();$action=(string)($_POST['action']??'');
try{
 if($action==='invite'){
  $max=filter_var($_POST['max_members']??null,FILTER_VALIDATE_INT);
  if(!$max||$max<1||$max>30)throw new RuntimeException('Limite permitido: de 1 a 30 membros.');
  $recipient=mb_strtolower(trim((string)($_POST['invite_email']??'')));
  if($recipient!=='' && (!filter_var($recipient,FILTER_VALIDATE_EMAIL)||preg_match('/[\\r\\n]/',$recipient)))throw new RuntimeException('Informe um e-mail válido.');
  $hours=72;$token=bin2hex(random_bytes(32));
  $stmt=$pdo->prepare('INSERT INTO family_invitations(token_hash,max_members,expires_at,created_by) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 72 HOUR),?)');
  $stmt->execute([hash('sha256',$token),$max,(int)$_SESSION['user_id']]);
  $_SESSION['new_family_invite']=$token;
  $message='Link gerado. Ele expira em 72 horas e só pode ser utilizado uma vez.';
  if($recipient!==''){
   $appUrl=rtrim((string)config('app','base_url',''),'/' );
   if(!preg_match('#^https://[a-z0-9.-]+(?::[0-9]+)?$#i',$appUrl)){
    $appUrl='https://familia.anoar.com.br';
   }
   $link=$appUrl.'/convite-familia.php?token='.$token;
   $subject='Convite para criar sua família no Bemora';
   $body="Olá!\n\nVocê recebeu um convite para criar sua família no Bemora.\n\nAcesse: ".$link."\n\nO link é de uso único e expira em 72 horas.\n\nSe não esperava esta mensagem, ignore-a.";
   try{
    require_once __DIR__.'/../includes/mailer.php';
    require_once __DIR__.'/../includes/email_templates.php';
    bemora_send_email($recipient,$subject,$body,bemora_invitation_email($link,$max));
    $message.=' Convite entregue ao servidor SMTP para o e-mail informado.';
   }catch(Throwable $mailError){
    error_log('Bemora: falha ao encaminhar convite via SMTP.');
    $message.=' Não foi possível enviar o e-mail via SMTP. Copie o link exibido abaixo.';
   }
  }
  flash('success',$message);
 } elseif(in_array($action,['archive','maintenance','activate','rename','set_limit','set_admin'],true)){
  $id=(int)($_POST['id']??0);if($id<1)throw new RuntimeException('Família inválida.');
  $pdo->beginTransaction();
  $stmt=$pdo->prepare('SELECT id,name,status FROM tenants WHERE id=? FOR UPDATE');$stmt->execute([$id]);$family=$stmt->fetch();
  if(!$family)throw new RuntimeException('Família não encontrada.');
  if($action==='set_limit'){
   $limit=filter_var($_POST['max_members']??null,FILTER_VALIDATE_INT);
   $membersStmt=$pdo->prepare('SELECT COUNT(*) FROM tenant_users WHERE tenant_id=?');
   $membersStmt->execute([$id]);$currentMembers=(int)$membersStmt->fetchColumn();
   if($limit===false||$limit<1||$limit>30)throw new RuntimeException('O limite deve ser de 1 a 30 membros.');
   if($limit<$currentMembers)throw new RuntimeException('O limite não pode ser menor que a quantidade de membros existentes.');
   $pdo->prepare('UPDATE tenants SET max_members=? WHERE id=?')->execute([$limit,$id]);
  }elseif($action==='set_admin'){
   $memberId=filter_var($_POST['user_id']??null,FILTER_VALIDATE_INT);
   if(!$memberId || $memberId<1)throw new RuntimeException('Escolha um membro válido.');
   $member=$pdo->prepare('SELECT role FROM tenant_users WHERE tenant_id=? AND user_id=? FOR UPDATE');
   $member->execute([$id,$memberId]);
   if($member->fetchColumn()===false)throw new RuntimeException('O usuário não pertence a esta família.');
   $pdo->prepare("UPDATE tenant_users SET role='member' WHERE tenant_id=? AND role='family_admin'")->execute([$id]);
   $pdo->prepare("UPDATE tenant_users SET role='family_admin' WHERE tenant_id=? AND user_id=?")->execute([$id,$memberId]);
  }elseif($action==='rename'){
   $name=trim((string)($_POST['name']??''));
   if(mb_strlen($name)<3||mb_strlen($name)>160)throw new RuntimeException('Nome deve ter de 3 a 160 caracteres.');
   $stmt=$pdo->prepare('UPDATE tenants SET name=?,name_key=? WHERE id=?');$stmt->execute([$name,mb_strtolower($name),$id]);
  }else{
   $state=['archive'=>'archived','maintenance'=>'maintenance','activate'=>'active'][$action];
   // Activation is permitted only for legacy tenants; pending isolated tenants are blocked.
   if($state==='active' && $family['status']==='pending')throw new RuntimeException('Ativação bloqueada até a auditoria de isolamento.');
   $stmt=$pdo->prepare('UPDATE tenants SET status=?,active=? WHERE id=?');$stmt->execute([$state,$state==='active'?1:0,$id]);
  }
  $pdo->commit();flash('success','Família atualizada.');
 }else throw new RuntimeException('Operação não reconhecida.');
}catch(Throwable $e){
 if($pdo->inTransaction())$pdo->rollBack();
 flash('error',$e instanceof PDOException?'Conflito de dados ou nome de família já utilizado.':$e->getMessage());
}
if(in_array($action,['set_limit','set_admin','rename'],true) && (int)($_POST['id']??0)>0)redirect_to('/admin/familia?id='.(int)$_POST['id']);
redirect_to('/admin/tenants');

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
  $hours=72;$token=bin2hex(random_bytes(32));
  $stmt=$pdo->prepare('INSERT INTO family_invitations(token_hash,max_members,expires_at,created_by) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 72 HOUR),?)');
  $stmt->execute([hash('sha256',$token),$max,(int)$_SESSION['user_id']]);
  $_SESSION['new_family_invite']=$token;
  flash('success','Link gerado. Ele expira em 72 horas e só pode ser utilizado uma vez.');
 } elseif(in_array($action,['archive','maintenance','activate','rename'],true)){
  $id=(int)($_POST['id']??0);if($id<1)throw new RuntimeException('Família inválida.');
  $pdo->beginTransaction();
  $stmt=$pdo->prepare('SELECT id,name,status FROM tenants WHERE id=? FOR UPDATE');$stmt->execute([$id]);$family=$stmt->fetch();
  if(!$family)throw new RuntimeException('Família não encontrada.');
  if($action==='rename'){
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
redirect_to('/admin/tenants');

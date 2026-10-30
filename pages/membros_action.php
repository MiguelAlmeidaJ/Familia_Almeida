<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';
$user=require_family_access();
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);exit('Método não permitido.');}
verify_csrf();
$pdo=db();$tenantId=(int)$user['tenant_id'];$actor=(int)$user['id'];
$action=(string)($_POST['action']??'');
try{
 $pdo->beginTransaction();
 $q=$pdo->prepare('SELECT id,max_members FROM tenants WHERE id=? AND active=1 FOR UPDATE');
 $q->execute([$tenantId]);$family=$q->fetch();
 if(!$family)throw new RuntimeException('Família indisponível.');
 $q=$pdo->prepare("SELECT role FROM tenant_users WHERE tenant_id=? AND user_id=?");
 $q->execute([$tenantId,$actor]);
 if($q->fetchColumn()!=='family_admin')throw new RuntimeException('Apenas o administrador da família pode executar esta ação.');
 if($action==='add'){
  $q=$pdo->prepare('SELECT COUNT(*) FROM tenant_users WHERE tenant_id=?');
  $q->execute([$tenantId]);$total=(int)$q->fetchColumn();
  if($total>=(int)$family['max_members'])throw new RuntimeException('O limite de membros desta família foi atingido.');
  $name=trim((string)($_POST['name']??''));$email=mb_strtolower(trim((string)($_POST['email']??'')));
  if(mb_strlen($name)<2||mb_strlen($name)>80||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>160)
    throw new RuntimeException('Informe nome e e-mail válidos.');
  $q=$pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1');$q->execute([$email]);
  if($q->fetchColumn())throw new RuntimeException('Este e-mail já possui cadastro. Não é possível vincular contas existentes por este formulário.');
  $password=bin2hex(random_bytes(12));
  $stmt=$pdo->prepare("INSERT INTO users(name,email,password_hash) VALUES(?,?,?)");
  $stmt->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
  $newId=(int)$pdo->lastInsertId();
  $pdo->prepare("INSERT INTO tenant_users(tenant_id,user_id,role) VALUES(?,?,'member')")->execute([$tenantId,$newId]);
  $pdo->commit();
  $_SESSION['members_temporary_password']=['name'=>$name,'email'=>$email,'password'=>$password];
  flash('success','Membro adicionado. Copie a senha temporária abaixo e compartilhe de forma segura.');
 }elseif($action==='remove' || $action==='reset_password'){
  $target=filter_var($_POST['user_id']??null,FILTER_VALIDATE_INT);
  if(!$target || $target<1 || $target===$actor)throw new RuntimeException('Selecione outro membro válido.');
  $q=$pdo->prepare('SELECT tu.role,u.name,u.email FROM tenant_users tu JOIN users u ON u.id=tu.user_id WHERE tu.tenant_id=? AND tu.user_id=? FOR UPDATE');
  $q->execute([$tenantId,$target]);$member=$q->fetch();
  if(!$member)throw new RuntimeException('Membro não encontrado nesta família.');
  if($member['role']==='family_admin')throw new RuntimeException('Não é possível alterar outro administrador familiar. Altere o responsável na administração global.');
  if($action==='remove'){
   if((string)($_POST['confirm_remove']??'')!=='REMOVE')throw new RuntimeException('Confirme a remoção do membro.');
   // Prevent orphaned private records or dangling payment responsibilities.
   foreach(['transactions','fixed_bills','recurring_incomes','monthly_allocations','spending_goals','debts'] as $table){
    $q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name='owner_user_id'");
    $q->execute([$table]);if(!(int)$q->fetchColumn())continue;
    $q=$pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE tenant_id=? AND owner_user_id=?");
    $q->execute([$tenantId,$target]);
    if((int)$q->fetchColumn()>0)throw new RuntimeException('Este membro possui registros financeiros pessoais. Reatribua-os antes de removê-lo.');
   }
   $q=$pdo->prepare('SELECT COUNT(*) FROM family_bill_responsibilities WHERE tenant_id=? AND user_id=?');
   $q->execute([$tenantId,$target]);
   if((int)$q->fetchColumn()>0)throw new RuntimeException('O membro é responsável por contas previstas. Altere as responsabilidades antes de removê-lo.');
   $pdo->prepare('DELETE FROM tenant_users WHERE tenant_id=? AND user_id=?')->execute([$tenantId,$target]);
   // Do not delete the global account or any history in other families.
   $pdo->commit();flash('success','Membro removido desta família. Os dados financeiros foram preservados.');
  }else{
   $password=bin2hex(random_bytes(12));
   $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$target]);
   $pdo->commit();
   $_SESSION['members_temporary_password']=['name'=>$member['name'],'email'=>$member['email'],'password'=>$password];
   flash('success','Senha redefinida. Copie e entregue a nova senha ao membro por um canal seguro.');
  }
 }else throw new RuntimeException('Operação inválida.');
}catch(Throwable $e){
 if($pdo->inTransaction())$pdo->rollBack();
 flash('error',$e instanceof PDOException?'Não foi possível concluir a operação. Tente novamente.':$e->getMessage());
}
redirect_to('/membros');

<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';
$user=require_family_access();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Método inválido');}
verify_csrf();
$pdo=db();$tid=(int)$user['tenant_id'];
$q=$pdo->prepare("SELECT role FROM tenant_users WHERE tenant_id=? AND user_id=?");
$q->execute([$tid,(int)$user['id']]);
if($q->fetchColumn()!=='family_admin'){http_response_code(403);exit('Apenas o administrador familiar pode alterar estas configurações.');}
$action=(string)($_POST['action']??'');
try {
 if($action==='finance_mode'){
  if(($_POST['finance_mode']??'')!=='shared')throw new RuntimeException('O modo individual será disponibilizado após o isolamento financeiro por membro.');
  $pdo->prepare("UPDATE tenants SET finance_mode='shared' WHERE id=?")->execute([$tid]);
  flash('success','Finanças compartilhadas mantidas.');
 }elseif($action==='assign_financial_owner'){
  $kind=(string)($_POST['record_type']??'');
  $tables=['transaction'=>'transactions','bill'=>'fixed_bills','income'=>'recurring_incomes'];
  if(!isset($tables[$kind]))throw new RuntimeException('Tipo de registro inválido.');
  $id=filter_var($_POST['record_id']??null,FILTER_VALIDATE_INT);
  $owner=filter_var($_POST['owner_user_id']??null,FILTER_VALIDATE_INT);
  if($id===false||$id<1||$owner===false||$owner<0)throw new RuntimeException('Dados de titularidade inválidos.');
  $table=$tables[$kind];
  $col=$pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name='owner_user_id'");
  $col->execute([$table]);
  if(!(int)$col->fetchColumn())throw new RuntimeException('Execute a migration de titularidade antes de atribuir registros.');
  $pdo->beginTransaction();
  $record=$pdo->prepare("SELECT id FROM `{$table}` WHERE tenant_id=? AND id=? FOR UPDATE");
  $record->execute([$tid,$id]);if(!$record->fetchColumn())throw new RuntimeException('Registro não pertence à família.');
  if($owner>0){
    $member=$pdo->prepare('SELECT 1 FROM tenant_users WHERE tenant_id=? AND user_id=?');
    $member->execute([$tid,$owner]);
    if(!$member->fetchColumn())throw new RuntimeException('Titular não pertence à família.');
  }
  $pdo->prepare("UPDATE `{$table}` SET owner_user_id=? WHERE tenant_id=? AND id=?")->execute([$owner?:null,$tid,$id]);
  $pdo->commit();
  flash('success','Titularidade registrada. No modo compartilhado, todos continuam com acesso.');
 }elseif($action==='bill_responsibility'){
  $bill=(int)($_POST['bill_id']??0);$member=(int)($_POST['user_id']??0);
  if($bill<1)throw new RuntimeException('Conta inválida.');
  $q=$pdo->prepare('SELECT 1 FROM fixed_bills WHERE id=? AND tenant_id=?');$q->execute([$bill,$tid]);
  if(!$q->fetchColumn())throw new RuntimeException('Conta não pertence à família.');
  if($member===0){
   $pdo->prepare('DELETE FROM family_bill_responsibilities WHERE tenant_id=? AND fixed_bill_id=?')->execute([$tid,$bill]);
  }else{
   $q=$pdo->prepare('SELECT 1 FROM tenant_users WHERE tenant_id=? AND user_id=?');$q->execute([$tid,$member]);
   if(!$q->fetchColumn())throw new RuntimeException('Membro não pertence à família.');
   $pdo->prepare('INSERT INTO family_bill_responsibilities(tenant_id,fixed_bill_id,user_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id)')->execute([$tid,$bill,$member]);
  }
  flash('success','Responsabilidade atualizada. A responsabilidade é informativa e não limita o acesso aos dados.');
 }else throw new RuntimeException('Ação inválida.');
}catch(Throwable $e){flash('error',$e->getMessage());}
redirect_to('/minha-conta');

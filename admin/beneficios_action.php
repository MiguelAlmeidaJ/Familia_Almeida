<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/admin_layout.php';
require_once __DIR__.'/../includes/plan_benefits.php';
require_admin_access();
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);exit('Método não permitido.');}
verify_csrf();
$pdo=db();
try{
 if(!bemora_benefits_ready($pdo))throw new RuntimeException('Execute a migration 044 em Manutenção.');
 if((string)($_POST['action']??'')!=='save')throw new RuntimeException('Ação inválida.');
 $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);
 $title=trim((string)($_POST['title']??''));
 $sort=filter_var($_POST['sort_order']??null,FILTER_VALIDATE_INT);
 if(mb_strlen($title)<3||mb_strlen($title)>160)throw new RuntimeException('Informe um benefício entre 3 e 160 caracteres.');
 if($sort===false||$sort===null||$sort<0||$sort>99999)throw new RuntimeException('Ordem inválida.');
 $active=($_POST['active']??'')==='1'?1:0;
 // New benefits are active by default; disabled is explicitly chosen when editing.
 if(!$id)$active=1;
 if($id && $id>0){
  $stmt=$pdo->prepare('UPDATE plan_benefits SET title=?,sort_order=?,active=? WHERE id=?');
  $stmt->execute([$title,$sort,$active,$id]);
  if(!$stmt->rowCount()){
   $check=$pdo->prepare('SELECT id FROM plan_benefits WHERE id=?');$check->execute([$id]);
   if(!$check->fetchColumn())throw new RuntimeException('Benefício não encontrado.');
  }
  flash('success','Benefício atualizado.');
 }else{
  $pdo->prepare('INSERT INTO plan_benefits(title,sort_order,active) VALUES(?,?,1)')->execute([$title,$sort]);
  flash('success','Benefício cadastrado. Agora selecione-o nos planos desejados.');
 }
}catch(Throwable $e){flash('error',$e instanceof PDOException?'Não foi possível salvar. Confira se não existe outro benefício com o mesmo nome.':$e->getMessage());}
redirect_to('/admin/beneficios');

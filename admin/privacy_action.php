<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/admin_layout.php';
$admin=require_admin_access();
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);exit;}
verify_csrf();
try{
 $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);
 $status=(string)($_POST['status']??'');
 $note=trim((string)($_POST['response_note']??''));
 if(!$id||$id<1||!in_array($status,['received','identity_verification','in_progress','completed','denied'],true)||mb_strlen($note)<10||mb_strlen($note)>3000)
  throw new RuntimeException('Confira a solicitação, o status e a resposta (mínimo de 10 caracteres).');
 $pdo=db();
 $q=$pdo->prepare("UPDATE privacy_requests SET status=?,response_note=?,resolved_by=?,resolved_at=IF(? IN ('completed','denied'),NOW(),NULL) WHERE id=?");
 $q->execute([$status,$note,(int)$admin['id'],$status,$id]);
 if(!$q->rowCount())throw new RuntimeException('Solicitação não encontrada.');
 flash('success','Análise da solicitação #'.$id.' registrada.');
}catch(Throwable $error){flash('error',$error instanceof PDOException?'Não foi possível registrar a decisão.':$error->getMessage());}
redirect_to('/admin/privacidade');
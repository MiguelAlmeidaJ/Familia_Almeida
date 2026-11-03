<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/admin_layout.php';
require_admin_access();
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);exit;}
verify_csrf();
try{
 $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);
 $status=(string)($_POST['status']??'');
 if(!$id||!in_array($status,['open','in_progress','resolved'],true))throw new RuntimeException('Dados de atendimento inválidos.');
 $q=db()->prepare('UPDATE support_tickets SET status=? WHERE id=?');
 $q->execute([$status,$id]);
 if(!$q->rowCount())throw new RuntimeException('Relato não encontrado ou já está com esta situação.');
 flash('success','Situação do relato #'.$id.' atualizada.');
}catch(Throwable $e){flash('error',$e->getMessage());}
redirect_to('/admin/suporte');

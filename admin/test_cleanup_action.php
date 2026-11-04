<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/admin_layout.php';
require_once __DIR__.'/../includes/admin_test_cleanup.php';
require_admin_access();
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);exit('Método não permitido.');}
verify_csrf();
$pdo=db();$action=(string)($_POST['action']??'');
$id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);
$labels=[
 'cancel_subscription'=>'CANCELAR ASSINATURA',
 'delete_order'=>'EXCLUIR PEDIDO',
 'delete_tenant'=>'EXCLUIR ESPAÇO',
];
$destination=$action==='delete_tenant'?'/admin/tenants':'/admin/assinaturas';
try{
 if(!isset($labels[$action]))throw new RuntimeException('Ação desconhecida.');
 if($id===false||$id===null||$id<1)throw new RuntimeException('Identificador inválido.');
 $expected=$labels[$action].' '.$id;
 if(trim((string)($_POST['confirmation']??''))!==$expected)throw new RuntimeException('Confirmação incorreta. Digite exatamente: '.$expected);
 $actor=(int)($_SESSION['user_id']??0);
 if($actor<1)throw new RuntimeException('Administrador inválido.');
 if($action==='cancel_subscription'){
  bemora_cleanup_cancel_subscription($pdo,$id,$actor);
  flash('success','Recorrência removida no Asaas. Faturas pagas e histórico financeiro permanecem no provedor.');
 }elseif($action==='delete_order'){
  bemora_cleanup_delete_order($pdo,$id,$actor);
  flash('success','Pedido de teste não pago excluído do banco. Nenhuma cobrança paga foi apagada.');
 }else{
  bemora_cleanup_delete_tenant($pdo,$id,$actor);
  flash('success','Espaço de teste e seus dados locais excluídos. Usuários exclusivos foram removidos.');
 }
}catch(Throwable $e){
 error_log('Bemora admin cleanup error, action='.$action.', target='.(int)$id.', detail='.get_class($e));
 flash('error',$e instanceof PDOException?'Exclusão bloqueada por dependências do banco. Nenhuma exclusão parcial foi realizada.':$e->getMessage());
}
redirect_to($destination);

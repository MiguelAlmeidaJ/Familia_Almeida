<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/market_lists.php';
$user=require_family_access();
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);exit;}
header('Cache-Control: no-store');
$ajax=(string)($_POST['ajax']??'')==='1';
if($ajax)header('Content-Type: application/json; charset=utf-8');
function market_reply(bool $ok,string $message,array $data=[]):never{
 global $ajax;
 if($ajax){http_response_code($ok?200:($message==='Sessão expirada.'?419:422));echo json_encode(['ok'=>$ok,'message'=>$message]+$data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;}
 flash($ok?'success':'error',$message);
 $list=filter_var($_POST['list_id']??null,FILTER_VALIDATE_INT);
 redirect_to('/compras'.($list?'?lista='.$list:''));
}
try{
 $submitted=$_POST['csrf_token']??'';
 if(!is_string($submitted)||!hash_equals(csrf_token(),$submitted))throw new RuntimeException('Sessão expirada.');
 $pdo=db();if(!bemora_market_ready($pdo))throw new RuntimeException('Execute a migration 041 para utilizar listas de compras.');
 $tenant=(int)$user['tenant_id'];$uid=(int)$user['id'];
 $action=(string)($_POST['action']??'');
 if($action==='create_list'){
  $name=bemora_market_name($_POST['name']??'',120);
  $q=$pdo->prepare("SELECT COUNT(*) FROM bemora_market_lists WHERE tenant_id=? AND status!='completed'");
  $q->execute([$tenant]);if((int)$q->fetchColumn()>=40)throw new RuntimeException('Você atingiu o limite de 40 listas em andamento.');
  $pdo->prepare('INSERT INTO bemora_market_lists(tenant_id,created_by,name) VALUES(?,?,?)')->execute([$tenant,$uid,$name]);
  $_POST['list_id']=(int)$pdo->lastInsertId();
  market_reply(true,'Lista criada com sucesso.');
 }
 $listId=filter_var($_POST['list_id']??null,FILTER_VALIDATE_INT);
 if(!$listId||$listId<1)throw new RuntimeException('Escolha uma lista válida.');
 $pdo->beginTransaction();
 $list=bemora_market_list($pdo,$tenant,$listId,true);
 if($action==='set_market'){
  $name=trim((string)($_POST['market_name']??''));
  if(mb_strlen($name)>120)throw new RuntimeException('O nome do mercado é muito longo.');
  $pdo->prepare('UPDATE bemora_market_lists SET market_name=? WHERE tenant_id=? AND id=?')->execute([$name===''?null:$name,$tenant,$listId]);
 }elseif($action==='set_status'){
  $status=(string)($_POST['status']??'');
  if(!in_array($status,['planned','shopping','completed'],true))throw new RuntimeException('Situação inválida.');
  $pdo->prepare('UPDATE bemora_market_lists SET status=?,completed_at=IF(?="completed",NOW(),NULL) WHERE id=? AND tenant_id=?')->execute([$status,$status,$listId,$tenant]);
 }elseif($action==='add_item'){
  if($list['status']==='completed')throw new RuntimeException('Reabra a lista para adicionar produtos.');
  $name=bemora_market_name($_POST['name']??'');
  $qty=bemora_market_quantity($_POST['quantity']??'');
  $unit=bemora_market_unit($_POST['unit']??'un');
  $price=bemora_market_price($_POST['price']??null);
  $purchased=($_POST['purchased']??'')==='1';
  if($purchased&&$price===null)throw new RuntimeException('Informe o preço antes de marcar como comprado.');
  $source=($_POST['price_source']??'')==='label'?'label':'manual';
  $q=$pdo->prepare('SELECT id,name FROM bemora_market_items WHERE tenant_id=? AND list_id=? FOR UPDATE');
  $q->execute([$tenant,$listId]);$existing=$q->fetchAll(PDO::FETCH_ASSOC);
  foreach($existing as $row)if(bemora_market_normalize($row['name'])===bemora_market_normalize($name))
    throw new RuntimeException('Este produto já está na lista. Atualize a quantidade ou selecione-o no modo mercado.');
  if(count($existing)>=300)throw new RuntimeException('Limite de 300 produtos por lista.');
  $pdo->prepare('INSERT INTO bemora_market_items(tenant_id,list_id,name,quantity,unit,unit_price,purchased,price_source,created_by) VALUES(?,?,?,?,?,?,?,?,?)')
   ->execute([$tenant,$listId,$name,$qty,$unit,$price,$purchased?1:0,$source,$uid]);
 }elseif($action==='update_item'){
  if($list['status']==='completed')throw new RuntimeException('Reabra a lista para editar.');
  $itemId=filter_var($_POST['item_id']??null,FILTER_VALIDATE_INT);
  if(!$itemId)throw new RuntimeException('Produto inválido.');
  $item=bemora_market_item($pdo,$tenant,$listId,(int)$itemId,true);
  $name=bemora_market_name($_POST['name']??$item['name']);
  $quantity=bemora_market_quantity($_POST['quantity']??$item['quantity']);
  $unit=bemora_market_unit($_POST['unit']??$item['unit']);
  $price=bemora_market_price($_POST['price']??$item['unit_price']);
  $purchased=(string)($_POST['purchased']??'')==='1';
  if($purchased&&$price===null)throw new RuntimeException('Confira o preço antes de marcar como comprado.');
  $source=($_POST['price_source']??'')==='label'?'label':'manual';
  $q=$pdo->prepare('SELECT id,name FROM bemora_market_items WHERE tenant_id=? AND list_id=? AND id<>?');
  $q->execute([$tenant,$listId,$itemId]);
  foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row)if(bemora_market_normalize($row['name'])===bemora_market_normalize($name))
    throw new RuntimeException('Outro produto nesta lista já possui esse nome.');
  $pdo->prepare('UPDATE bemora_market_items SET name=?,quantity=?,unit=?,unit_price=?,purchased=?,price_source=? WHERE id=? AND tenant_id=? AND list_id=?')
   ->execute([$name,$quantity,$unit,$price,$purchased?1:0,$source,$itemId,$tenant,$listId]);
 }elseif($action==='toggle_item'){
  if($list['status']==='completed')throw new RuntimeException('Reabra a lista antes de alterar os produtos.');
  $itemId=filter_var($_POST['item_id']??null,FILTER_VALIDATE_INT);
  if(!$itemId||$itemId<1)throw new RuntimeException('Produto inválido.');
  $item=bemora_market_item($pdo,$tenant,$listId,(int)$itemId,true);
  $purchased=(string)($_POST['purchased']??'')==='1';
  if($purchased&&$item['unit_price']===null)throw new RuntimeException('Confira o preço antes de marcar como comprado.');
  $pdo->prepare('UPDATE bemora_market_items SET purchased=? WHERE tenant_id=? AND list_id=? AND id=?')
   ->execute([$purchased?1:0,$tenant,$listId,$itemId]);
 }elseif($action==='remove_item'){
  if($list['status']==='completed')throw new RuntimeException('Reabra a lista para remover produtos.');
  $itemId=filter_var($_POST['item_id']??null,FILTER_VALIDATE_INT);
  if(!$itemId)throw new RuntimeException('Produto inválido.');
  bemora_market_item($pdo,$tenant,$listId,(int)$itemId,true);
  $pdo->prepare('DELETE FROM bemora_market_items WHERE tenant_id=? AND list_id=? AND id=?')->execute([$tenant,$listId,$itemId]);
 }else{throw new RuntimeException('Ação de compras inválida.');}
 $pdo->commit();
 if($ajax)market_reply(true,'Alteração salva.', ['list'=>bemora_market_response($pdo,$tenant,$listId)]);
 market_reply(true,'Alteração salva.');
}catch(Throwable $error){
 if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();
 market_reply(false,$error instanceof PDOException?'Não foi possível salvar a lista.':$error->getMessage());
}

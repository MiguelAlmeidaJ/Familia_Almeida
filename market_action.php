<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/market_lists.php';
require_once __DIR__.'/includes/finance_scope.php';
require_once __DIR__.'/includes/categories.php';
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
 if($action==='finish_purchase'){
  // A locked row plus transaction reference ensures retries cannot charge twice.
  $columns=$pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='bemora_market_lists'")->fetchAll(PDO::FETCH_COLUMN);
  if(!in_array('transaction_id',$columns,true))throw new RuntimeException('Execute a migration 042 antes de finalizar compras.');
  if($list['transaction_id']!==null)throw new RuntimeException('Esta compra já foi finalizada e lançada nas finanças.');
  if($list['status']==='completed')throw new RuntimeException('Esta lista já está concluída.');
  $query=$pdo->prepare('SELECT COUNT(*) purchased_count, SUM(CASE WHEN unit_price IS NULL THEN 1 ELSE 0 END) missing_prices, ROUND(SUM(quantity*unit_price),2) total FROM bemora_market_items WHERE tenant_id=? AND list_id=? AND purchased=1');
  $query->execute([$tenant,$listId]);$values=$query->fetch(PDO::FETCH_ASSOC);
  $purchased=(int)($values['purchased_count']??0);
  $missing=(int)($values['missing_prices']??0);
  $amount=(float)($values['total']??0);
  if($purchased<1||$missing>0||$amount<=0||$amount>9999999999.99)
   throw new RuntimeException('Confirme ao menos um produto comprado com preço válido antes de finalizar.');
  $scope=finance_scope($pdo,$user);
  // Shopping lists are family-shared. The corresponding transaction is shared,
  // including when family finances use individual mode (NULL owner).
  $category=category_ensure($pdo,'Supermercado','expense');
  $market=trim((string)($list['market_name']??''));
  $description='Compras · '.$list['name'].($market!==''?' · '.$market:'');
  $description=mb_substr($description,0,180);
  $pdo->prepare('INSERT INTO transactions(tenant_id,owner_user_id,created_by,type,description,category,amount,occurred_on) VALUES(?,?,?,\'expense\',?,?,?,CURRENT_DATE())')
    ->execute([$tenant,null,$uid,$description,$category,number_format($amount,2,'.','')]);
  $transactionId=(int)$pdo->lastInsertId();
  $pdo->prepare("UPDATE bemora_market_lists SET status='completed',completed_at=NOW(),transaction_id=? WHERE tenant_id=? AND id=? AND transaction_id IS NULL")
    ->execute([$transactionId,$tenant,$listId]);
  $pdo->commit();
  $_POST['modo']='mercado';
  if($ajax)market_reply(true,'Compra finalizada e gasto registrado em Movimentações.',['transaction_id'=>$transactionId,'list'=>bemora_market_response($pdo,$tenant,$listId)]);
  flash('success','Compra finalizada. Gasto de R$ '.number_format($amount,2,',','.').' registrado nas Movimentações.');
  redirect_to('/compras?lista='.$listId.'&modo=mercado');
 }
 if($action==='set_market'){
  if($list['status']==='completed')throw new RuntimeException('Compra finalizada não pode ser modificada.');
  $name=trim((string)($_POST['market_name']??''));
  if(mb_strlen($name)>120)throw new RuntimeException('O nome do mercado é muito longo.');
  $pdo->prepare('UPDATE bemora_market_lists SET market_name=? WHERE tenant_id=? AND id=?')->execute([$name===''?null:$name,$tenant,$listId]);
 }elseif($action==='set_status'){
  $status=(string)($_POST['status']??'');
  if(!in_array($status,['planned','shopping'],true))throw new RuntimeException('Para concluir e lançar a compra como gasto, use Finalizar compra.');
  if($list['status']==='completed')throw new RuntimeException('Compra finalizada não pode ser reaberta ou editada.');
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
  if($source==='label'){
   require_once __DIR__.'/includes/plan_modules.php';
   if(!bemora_has_module($pdo,(int)$user['tenant_id'],'scanner'))throw new RuntimeException('O leitor de etiquetas não está incluído no plano. Informe o preço manualmente.');
  }
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
  if($source==='label'){
   require_once __DIR__.'/includes/plan_modules.php';
   if(!bemora_has_module($pdo,(int)$user['tenant_id'],'scanner'))throw new RuntimeException('O leitor de etiquetas não está incluído no plano. Informe o preço manualmente.');
  }
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

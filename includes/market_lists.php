<?php
declare(strict_types=1);

function bemora_market_ready(PDO $pdo):bool {
 $q=$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('bemora_market_lists','bemora_market_items')");
 return (int)$q->fetchColumn()===2;
}
function bemora_market_name(mixed $raw,int $max=160):string {
 if(!is_string($raw))throw new InvalidArgumentException('Nome inválido.');
 $name=preg_replace('/\s+/u',' ',trim($raw));
 if(!is_string($name)||mb_strlen($name)<2||mb_strlen($name)>$max)throw new InvalidArgumentException('Informe um nome entre 2 e '.$max.' caracteres.');
 return $name;
}
function bemora_market_quantity(mixed $raw):string {
 if(!is_scalar($raw))throw new InvalidArgumentException('Quantidade inválida.');
 $s=str_replace(',','.',trim((string)$raw));
 if(!preg_match('/^\d{1,7}(?:\.\d{1,3})?$/D',$s)||(float)$s<=0||(float)$s>9999999)throw new InvalidArgumentException('Informe uma quantidade maior que zero.');
 return number_format((float)$s,3,'.','');
}
function bemora_market_price(mixed $raw):?string {
 if($raw===null||trim((string)$raw)==='')return null;
 if(!is_scalar($raw))throw new InvalidArgumentException('Preço inválido.');
 $s=trim((string)$raw);
 if(str_contains($s,','))$s=str_replace(',','.',str_replace('.','',$s));
 if(!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D',$s)||(float)$s<=0||(float)$s>99999999)throw new InvalidArgumentException('Informe um preço unitário válido.');
 return number_format((float)$s,2,'.','');
}
function bemora_market_unit(mixed $raw):string {
 $unit=(string)$raw;
 if(!in_array($unit,['un','kg','g','L','ml','pct','cx'],true))throw new InvalidArgumentException('Unidade inválida.');
 return $unit;
}
function bemora_market_list(PDO $pdo,int $tenant,int $id,bool $lock=false):array {
 $q=$pdo->prepare('SELECT * FROM bemora_market_lists WHERE tenant_id=? AND id=?'.($lock?' FOR UPDATE':''));
 $q->execute([$tenant,$id]);$row=$q->fetch(PDO::FETCH_ASSOC);
 if(!$row)throw new RuntimeException('Lista não encontrada nesta família.');
 return $row;
}
function bemora_market_item(PDO $pdo,int $tenant,int $listId,int $itemId,bool $lock=false):array {
 $q=$pdo->prepare('SELECT * FROM bemora_market_items WHERE tenant_id=? AND list_id=? AND id=?'.($lock?' FOR UPDATE':''));
 $q->execute([$tenant,$listId,$itemId]);$row=$q->fetch(PDO::FETCH_ASSOC);
 if(!$row)throw new RuntimeException('Produto não encontrado nesta lista.');
 return $row;
}
function bemora_market_normalize(string $name):string {
 $name=mb_strtolower(trim($name),'UTF-8');
 $name=preg_replace('/[^\p{L}\p{N}]+/u',' ',$name);
 return trim(preg_replace('/\s+/u',' ',(string)$name));
}
function bemora_market_response(PDO $pdo,int $tenant,int $listId):array {
 $list=bemora_market_list($pdo,$tenant,$listId);
 $q=$pdo->prepare('SELECT id,name,quantity,unit,unit_price,purchased,price_source FROM bemora_market_items WHERE tenant_id=? AND list_id=? ORDER BY purchased ASC,id ASC');
 $q->execute([$tenant,$listId]);$items=$q->fetchAll(PDO::FETCH_ASSOC);
 $total=0.0;$purchased=0;$missing=0;
 foreach($items as &$item){
   $item['id']=(int)$item['id'];$item['quantity']=(float)$item['quantity'];
   $item['unit_price']=$item['unit_price']===null?null:(float)$item['unit_price'];
   $item['purchased']=(bool)$item['purchased'];
   if($item['purchased']){$purchased++;if($item['unit_price']!==null)$total+=$item['quantity']*$item['unit_price'];else $missing++;}
 }unset($item);
 return ['id'=>(int)$list['id'],'name'=>$list['name'],'market_name'=>$list['market_name'],'status'=>$list['status'],'items'=>$items,'total'=>round($total,2),'purchased'=>$purchased,'count'=>count($items),'missing_prices'=>$missing];
}

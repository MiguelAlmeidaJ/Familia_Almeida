<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/admin_layout.php';
require_once __DIR__.'/../includes/plans.php';
require_once __DIR__.'/../includes/plan_modules.php';
require_admin_access();
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);exit('Método não permitido.');}
verify_csrf();
$pdo=db();
try{
 if(!bemora_plans_table_exists($pdo))throw new RuntimeException('Execute a migration 023 em Manutenção.');
 $action=(string)($_POST['action']??'');
 $id=filter_var($_POST['id']??0,FILTER_VALIDATE_INT);

 if($action==='archive_public_catalog'){
  if((string)($_POST['confirmation']??'')!=='ARQUIVAR PLANOS')throw new RuntimeException('Confirmação inválida. Digite ARQUIVAR PLANOS exatamente.');
  $pdo->beginTransaction();
  $changed=$pdo->exec('UPDATE subscription_plans SET active=0 WHERE public_sale=1 AND active=1');
  $pdo->commit();
  flash('success','Catálogo público arquivado: '.(int)$changed.' ofertas retiradas da venda. Pedidos, famílias e assinaturas Asaas preservados.');
 }elseif($action==='create_bundle'){
  if(!bemora_modules_ready($pdo))throw new RuntimeException('Execute primeiro a migration 043 em Manutenção.');
  $name=trim((string)($_POST['bundle_name']??''));
  $details=trim((string)($_POST['bundle_details']??''));
  $members=filter_var($_POST['bundle_members']??null,FILTER_VALIDATE_INT);
  if(mb_strlen($name)<3||mb_strlen($name)>100)throw new RuntimeException('O nome deve ter entre 3 e 100 caracteres.');
  if($details===''||mb_strlen($details)>3000)throw new RuntimeException('Informe a descrição do pacote.');
  if(!$members||$members<1||$members>30||!in_array($members,[1,2,4,6,8,10,15,20,30],true))throw new RuntimeException('Quantidade de integrantes inválida.');
  $moneyCents=static function(mixed $raw):int{
   $value=trim((string)$raw);
   $value=preg_replace('/^R\$\s*/u','',$value);
   $value=preg_replace('/\s+/u','',$value);
   if(str_contains($value,','))$value=str_replace(',','.',str_replace('.','',$value));
   if(!preg_match('/^\d+(?:\.\d{1,2})?$/D',$value))throw new RuntimeException('Informe valores monetários válidos.');
   [$whole,$frac]=array_pad(explode('.',$value,2),2,'');
   if(strlen($whole)>8)throw new RuntimeException('Preço muito alto.');
   return (int)$whole*100+(int)str_pad($frac,2,'0');
  };
  $base=$moneyCents($_POST['bundle_monthly_base']??'');
  $seat=$moneyCents($_POST['bundle_seat_price']??'');
  if($base<=0)throw new RuntimeException('O preço base mensal público precisa ser maior que zero.');
  $monthly=$base+$seat*($members-1);
  if($monthly<100||$monthly>9999999900)throw new RuntimeException('Valor mensal fora dos limites aceitos.');
  $discountCents=static function(mixed $raw):int{
   $value=trim((string)$raw);
   if(!preg_match('/^\d{1,2}(?:\.\d{1,2})?$/D',$value))throw new RuntimeException('Desconto inválido; use percentual entre 0 e 80.');
   $pct=(int)round(((float)$value)*100,0,PHP_ROUND_HALF_UP);
   if($pct<0||$pct>8000)throw new RuntimeException('O desconto máximo por período é 80%.');
   return $pct;
  };
  $semi=$discountCents($_POST['bundle_semi_discount']??'');
  $annual=$discountCents($_POST['bundle_annual_discount']??'');
  $visibility=(string)($_POST['bundle_visibility']??'draft');
  if(!in_array($visibility,['draft','publish'],true))throw new RuntimeException('Visibilidade inválida.');
  $active=$visibility==='publish'?1:0;
  $modules=$_POST['bundle_modules']??[];
  if(!is_array($modules))throw new RuntimeException('Lista de módulos inválida.');
  $catalog=bemora_module_catalog();
  foreach($modules as $key)if(!is_string($key)||!array_key_exists($key,$catalog))throw new RuntimeException('Módulo inválido.');
  $modules=array_values(array_unique($modules));
  if(in_array('scanner',$modules,true)&&!in_array('shopping',$modules,true))throw new RuntimeException('O scanner exige o módulo de compras.');
  // All prices below are TOTAL per billing period, not a per-month installment.
  $amounts=[
   'monthly'=>$monthly,
   'semiannual'=>(int)round($monthly*6*(10000-$semi)/10000,0,PHP_ROUND_HALF_UP),
   'annual'=>(int)round($monthly*12*(10000-$annual)/10000,0,PHP_ROUND_HALF_UP)
  ];
  foreach($amounts as $amount)if($amount<100||$amount>9999999900)throw new RuntimeException('Valor de um dos períodos fora dos limites.');
  $pdo->beginTransaction();
  $check=$pdo->prepare('SELECT id FROM subscription_plans WHERE name=? AND max_members=? AND billing_cycle=? AND active=1 LIMIT 1');
  $insert=$pdo->prepare('INSERT INTO subscription_plans(name,billing_cycle,details,max_members,price,public_sale,active) VALUES(?,?,?,?,?,1,?)');
  $moduleInsert=$pdo->prepare('INSERT INTO subscription_plan_modules(plan_id,module_key,enabled) VALUES(?,?,?)');
  foreach($amounts as $cycle=>$amount){
   $check->execute([$name,$members,$cycle]);
   if($check->fetchColumn())throw new RuntimeException('Já existe uma oferta ativa com esse nome, integrantes e período: '.bemora_plan_cycles()[$cycle].'.');
   $insert->execute([$name,$cycle,$details,$members,$amount/100,$active]);
   $planId=(int)$pdo->lastInsertId();
   foreach(array_keys($catalog) as $key)$moduleInsert->execute([$planId,$key,in_array($key,$modules,true)?1:0]);
  }
  $pdo->commit();
  flash('success','Pacote '. $name .' criado com três ofertas '.($active?'publicadas':'em rascunho').'. Confira os valores por período antes de publicar.');
  $bundleCreated=true;
 }elseif($action==='toggle'){

  if(!$id || $id<1)throw new RuntimeException('Plano inválido.');
  $stmt=$pdo->prepare('UPDATE subscription_plans SET active=1-active WHERE id=?');
  $stmt->execute([$id]);
  if(!$stmt->rowCount())throw new RuntimeException('Plano não encontrado.');
  flash('success','Visibilidade do plano atualizada.');
 }elseif($action==='save'){
  if(!bemora_modules_ready($pdo))throw new RuntimeException('Execute a migration 043 em Manutenção para configurar módulos.');
  $selectedModules=$_POST['modules']??[];
  if(!is_array($selectedModules))throw new RuntimeException('Seleção de módulos inválida.');
  $validModules=bemora_module_catalog();
  foreach($selectedModules as $key){if(!is_string($key)||!array_key_exists($key,$validModules))throw new RuntimeException('Módulo inválido.');}
  $selectedModules=array_values(array_unique($selectedModules));
  if(in_array('scanner',$selectedModules,true)&&!in_array('shopping',$selectedModules,true))throw new RuntimeException('O leitor de etiquetas exige o módulo de compras.');
  $name=trim((string)($_POST['name']??''));
  $cycle=(string)($_POST['billing_cycle']??'');
  $details=trim((string)($_POST['details']??''));
  $members=filter_var($_POST['max_members']??null,FILTER_VALIDATE_INT);
  $priceRaw=trim((string)($_POST['price']??''));
  $promoRaw=trim((string)($_POST['promo_price']??''));
  $duration=(string)($_POST['promo_duration']??'');
  $publicSale=($_POST['public_sale']??'0')==='1'?1:0;
  $parseMoney=static function(string $value):float {
   $value=preg_replace('/^R\$\s*/u','',$value);
   $value=preg_replace('/\s+/u','',$value);
   if(str_contains($value,','))$value=str_replace(',','.',str_replace('.','',$value));
   if(!preg_match('/^\d+(?:\.\d{1,2})?$/D',$value))throw new RuntimeException('Preço inválido.');
   return (float)$value;
  };
  if(mb_strlen($name)<3||mb_strlen($name)>100)throw new RuntimeException('O nome deve ter entre 3 e 100 caracteres.');
  if(!isset(bemora_plan_cycles()[$cycle]))throw new RuntimeException('Modalidade inválida.');
  if($details===''||mb_strlen($details)>3000)throw new RuntimeException('Informe detalhes do plano (até 3000 caracteres).');
  if(!$members||$members<1||$members>30)throw new RuntimeException('O limite de membros deve ser de 1 a 30.');
  $price=$parseMoney($priceRaw);
  if(!is_finite($price)||$price<0||$price>99999999||($publicSale===1&&$price<=0))throw new RuntimeException('Planos públicos exigem preço maior que zero. Planos internos podem ser gratuitos.');
  $promo=null;$start=null;$end=null;
  if($publicSale===0 && $price===0.0){$promoRaw='';$duration='';}
  if($promoRaw!==''){
   $promo=$parseMoney($promoRaw);
   if(!is_finite($promo)||$promo<=0||$promo>=$price)throw new RuntimeException('A promoção deve ser maior que zero e menor que o preço normal.');
   if(!isset(bemora_promo_durations()[$duration]))throw new RuntimeException('Selecione a duração da promoção.');
  }elseif($duration!=='')throw new RuntimeException('Informe o preço promocional ou deixe a duração vazia.');
  $pdo->beginTransaction();
  if($id && $id>0){
   $old=$pdo->prepare('SELECT promo_price,promo_duration,promo_starts_at,promo_ends_at,price FROM subscription_plans WHERE id=?');
   $old->execute([$id]);$existing=$old->fetch(PDO::FETCH_ASSOC);
   if(!$existing)throw new RuntimeException('Plano não encontrado.');
   // Preserve an existing deadline unless the promotion configuration was changed.
   if($promo!==null){
    $same=abs((float)$existing['promo_price']-$promo)<0.001 && $existing['promo_duration']===$duration && abs((float)$existing['price']-$price)<0.001;
    if($same && $existing['promo_starts_at'] && $existing['promo_ends_at']){
     $start=$existing['promo_starts_at'];$end=$existing['promo_ends_at'];
    }else{$start=date('Y-m-d H:i:s');$end=bemora_plan_ends_at($duration);}
   }
   $stmt=$pdo->prepare('UPDATE subscription_plans SET name=?,billing_cycle=?,details=?,max_members=?,price=?,promo_price=?,promo_duration=?,promo_starts_at=?,promo_ends_at=?,public_sale=? WHERE id=?');
   $stmt->execute([$name,$cycle,$details,$members,$price,$promo,$promo===null?null:$duration,$start,$end,$publicSale,$id]);
   $savedPlanId=(int)$id;
   flash('success','Plano atualizado com sucesso.');
  }else{
   if($promo!==null){$start=date('Y-m-d H:i:s');$end=bemora_plan_ends_at($duration);}
   $stmt=$pdo->prepare('INSERT INTO subscription_plans(name,billing_cycle,details,max_members,price,promo_price,promo_duration,promo_starts_at,promo_ends_at,public_sale) VALUES (?,?,?,?,?,?,?,?,?,?)');
   $stmt->execute([$name,$cycle,$details,$members,$price,$promo,$promo===null?null:$duration,$start,$end,$publicSale]);
   $savedPlanId=(int)$pdo->lastInsertId();
   flash('success','Plano cadastrado com a visibilidade escolhida.');
  }
  $moduleStmt=$pdo->prepare('INSERT INTO subscription_plan_modules(plan_id,module_key,enabled) VALUES(?,?,?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled)');
  foreach(array_keys($validModules) as $key)$moduleStmt->execute([$savedPlanId,$key,in_array($key,$selectedModules,true)?1:0]);
  $pdo->commit();
 }else{throw new RuntimeException('Ação inválida.');}
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();flash('error',$e instanceof PDOException?'Erro ao gravar o plano no banco de dados.':$e->getMessage());}
redirect_to((($action??'')==='create_bundle' && empty($bundleCreated))?'/admin/planos/novo':'/admin/planos');

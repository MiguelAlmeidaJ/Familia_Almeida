<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/admin_layout.php';
require_once __DIR__.'/../includes/plans.php';
require_admin_access();
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);exit('Método não permitido.');}
verify_csrf();
$pdo=db();
try{
 if(!bemora_plans_table_exists($pdo))throw new RuntimeException('Execute a migration 023 em Manutenção.');
 $action=(string)($_POST['action']??'');
 $id=filter_var($_POST['id']??0,FILTER_VALIDATE_INT);
 if($action==='toggle'){
  if(!$id || $id<1)throw new RuntimeException('Plano inválido.');
  $stmt=$pdo->prepare('UPDATE subscription_plans SET active=1-active WHERE id=?');
  $stmt->execute([$id]);
  if(!$stmt->rowCount())throw new RuntimeException('Plano não encontrado.');
  flash('success','Visibilidade do plano atualizada.');
 }elseif($action==='save'){
  $name=trim((string)($_POST['name']??''));
  $cycle=(string)($_POST['billing_cycle']??'');
  $details=trim((string)($_POST['details']??''));
  $members=filter_var($_POST['max_members']??null,FILTER_VALIDATE_INT);
  $priceRaw=trim((string)($_POST['price']??''));
  $promoRaw=trim((string)($_POST['promo_price']??''));
  $duration=(string)($_POST['promo_duration']??'');
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
  if(!is_finite($price)||$price<=0||$price>99999999)throw new RuntimeException('Informe um preço válido.');
  $promo=null;$start=null;$end=null;
  if($promoRaw!==''){
   $promo=$parseMoney($promoRaw);
   if(!is_finite($promo)||$promo<=0||$promo>=$price)throw new RuntimeException('A promoção deve ser maior que zero e menor que o preço normal.');
   if(!isset(bemora_promo_durations()[$duration]))throw new RuntimeException('Selecione a duração da promoção.');
  }elseif($duration!=='')throw new RuntimeException('Informe o preço promocional ou deixe a duração vazia.');
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
   $stmt=$pdo->prepare('UPDATE subscription_plans SET name=?,billing_cycle=?,details=?,max_members=?,price=?,promo_price=?,promo_duration=?,promo_starts_at=?,promo_ends_at=? WHERE id=?');
   $stmt->execute([$name,$cycle,$details,$members,$price,$promo,$promo===null?null:$duration,$start,$end,$id]);
   flash('success','Plano atualizado com sucesso.');
  }else{
   if($promo!==null){$start=date('Y-m-d H:i:s');$end=bemora_plan_ends_at($duration);}
   $stmt=$pdo->prepare('INSERT INTO subscription_plans(name,billing_cycle,details,max_members,price,promo_price,promo_duration,promo_starts_at,promo_ends_at) VALUES (?,?,?,?,?,?,?,?,?)');
   $stmt->execute([$name,$cycle,$details,$members,$price,$promo,$promo===null?null:$duration,$start,$end]);
   flash('success','Plano cadastrado e publicado.');
  }
 }else{throw new RuntimeException('Ação inválida.');}
}catch(Throwable $e){flash('error',$e instanceof PDOException?'Erro ao gravar o plano no banco de dados.':$e->getMessage());}
redirect_to('/admin/planos');

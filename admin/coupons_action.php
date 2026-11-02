<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/admin_layout.php';
require_once __DIR__.'/../includes/coupons.php';
require_admin_access();
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);exit;}
verify_csrf();$pdo=db();
try{
 if(!bemora_coupons_ready($pdo))throw new RuntimeException('Execute primeiro a migration 025.');
 $action=(string)($_POST['action']??'');$id=filter_var($_POST['id']??0,FILTER_VALIDATE_INT)?:0;
 if($action==='delete'){
  if(!$id)throw new RuntimeException('Cupom inválido.');
  $q=$pdo->prepare('SELECT redeemed_count FROM subscription_coupons WHERE id=?');$q->execute([$id]);$used=$q->fetchColumn();
  if($used===false)throw new RuntimeException('Cupom não encontrado.');
  if((int)$used>0)throw new RuntimeException('Cupons já utilizados devem ser desativados para preservar o histórico.');
  $pdo->prepare('DELETE FROM subscription_coupons WHERE id=?')->execute([$id]);flash('success','Cupom excluído.');
 }elseif($action==='toggle'){
  if(!$id)throw new RuntimeException('Cupom inválido.');
  $q=$pdo->prepare('UPDATE subscription_coupons SET enabled=1-enabled WHERE id=?');$q->execute([$id]);
  if(!$q->rowCount())throw new RuntimeException('Cupom não encontrado.');
  flash('success','Situação do cupom atualizada.');
 }elseif($action==='save'){
  $code=strtoupper(trim((string)($_POST['code']??'')));
  $title=trim((string)($_POST['title']??''));
  $description=trim((string)($_POST['description']??''));
  $kind=(string)($_POST['kind']??'');
  $scope=(string)($_POST['cycle_scope']??'all');
  $planId=filter_var($_POST['plan_id']??0,FILTER_VALIDATE_INT)?:null;
  $max=trim((string)($_POST['max_redemptions']??''));$max=$max===''?null:filter_var($max,FILTER_VALIDATE_INT);
  $months=filter_var($_POST['free_months']??null,FILTER_VALIDATE_INT);
  $type=(string)($_POST['discount_type']??'percent');
  $amountRaw=trim((string)($_POST['discount_value']??''));
  $amount=(float)str_replace(',','.',str_replace('.','',$amountRaw));
  if(!preg_match('/^[A-Z0-9][A-Z0-9_-]{2,31}$/D',$code))throw new RuntimeException('Código: 3 a 32 caracteres, letras, números, traço ou sublinhado.');
  if(mb_strlen($title)<3||mb_strlen($title)>100||mb_strlen($description)>500)throw new RuntimeException('Revise o nome e a descrição.');
  if(!isset(bemora_coupon_types()[$kind]))throw new RuntimeException('Tipo de benefício inválido.');
  if(!in_array($scope,['all','monthly','semiannual','annual'],true))throw new RuntimeException('Modalidade inválida.');
  if($kind==='annual_discount')$scope='annual';
  if($max!==null && ($max===false||$max<1||$max>100000))throw new RuntimeException('Limite de usos inválido.');
  if($planId){$q=$pdo->prepare('SELECT 1 FROM subscription_plans WHERE id=?');$q->execute([$planId]);if(!$q->fetchColumn())throw new RuntimeException('Plano não encontrado.');}
  if($kind==='free_months'){
   if(!$months||$months<1||$months>24)throw new RuntimeException('Informe de 1 a 24 meses gratuitos.');
   $type=null;$amount=null;
  }else{
   $months=null;
   if(!in_array($type,['percent','fixed'],true)||!preg_match('/^\d+(?:[,.]\d{1,2})?$/D',$amountRaw)||$amount<=0||($type==='percent'&&$amount>100))throw new RuntimeException('Desconto inválido.');
  }
  $from=(string)($_POST['valid_from']??'');$until=(string)($_POST['valid_until']??'');
  $parse=static function(string $v):?string {if($v==='')return null;$d=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$v);if(!$d||$d->format('Y-m-d\TH:i')!==$v)throw new RuntimeException('Data inválida.');return $d->format('Y-m-d H:i:s');};
  $from=$parse($from);$until=$parse($until);if($from&&$until&&$from>=$until)throw new RuntimeException('O término deve ser posterior ao início.');
  $params=[$code,$title,$description?:null,$kind,$months,$type,$amount,$scope,$planId,$max,$from,$until];
  if($id){
   $stmt=$pdo->prepare('UPDATE subscription_coupons SET code=?,title=?,description=?,kind=?,free_months=?,discount_type=?,discount_value=?,cycle_scope=?,plan_id=?,max_redemptions=?,valid_from=?,valid_until=? WHERE id=?');
   $stmt->execute([...$params,$id]);flash('success','Cupom atualizado.');
  }else{
   $stmt=$pdo->prepare('INSERT INTO subscription_coupons (code,title,description,kind,free_months,discount_type,discount_value,cycle_scope,plan_id,max_redemptions,valid_from,valid_until) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
   $stmt->execute($params);flash('success','Cupom criado.');
  }
 }else throw new RuntimeException('Ação não reconhecida.');
}catch(Throwable $e){flash('error',$e instanceof PDOException?'Não foi possível salvar. Confira se o código já está em uso.':$e->getMessage());}
redirect_to('/admin/cupons');

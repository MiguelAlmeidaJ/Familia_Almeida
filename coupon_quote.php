<?php
declare(strict_types=1);
require_once __DIR__.'/includes/asaas.php';
require_once __DIR__.'/includes/coupons.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);echo json_encode(['error'=>'Método inválido']);exit;}
$token=$_POST['csrf_token']??'';
if(!is_string($token)||!hash_equals(csrf_token(),$token)){http_response_code(419);echo json_encode(['error'=>'Sessão expirada. Atualize a página.']);exit;}
try{
 $pdo=db();$id=filter_var($_POST['plan_id']??0,FILTER_VALIDATE_INT);
 $q=$pdo->prepare('SELECT * FROM subscription_plans WHERE id=? AND active=1');$q->execute([$id?:0]);$plan=$q->fetch(PDO::FETCH_ASSOC);
 if(!$plan)throw new RuntimeException('Plano indisponível.');
 $base=bemora_plan_promo_active($plan)?(float)$plan['promo_price']:(float)$plan['price'];
 $code=trim((string)($_POST['coupon_code']??''));
 if($code===''){$data=['amount'=>$base,'discount'=>0,'label'=>'Sem cupom','supported'=>true];}
 else{
  $coupon=bemora_coupon_find($pdo,$code);
  if(!$coupon)throw new RuntimeException('Cupom não encontrado.');
  $data=bemora_coupon_quote($coupon,$plan,$base);
 }
 echo json_encode(['ok'=>true,'amount'=>number_format($data['amount'],2,'.',''),'display_amount'=>bemora_coupon_money((float)$data['amount']),'discount'=>bemora_coupon_money((float)$data['discount']),'recurring_amount'=>bemora_coupon_money((float)($data['recurring_amount']??$base)),'label'=>$data['label'],'supported'=>$data['supported'],'message'=>$data['supported']?'Benefício aplicado à simulação.':'Cupom válido, mas sua modalidade requer ativação das regras de cobrança.'],JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){http_response_code(422);echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}

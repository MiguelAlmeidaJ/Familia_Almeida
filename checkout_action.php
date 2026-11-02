<?php
declare(strict_types=1);
require_once __DIR__.'/includes/asaas.php';
require_once __DIR__.'/includes/coupons.php';
require_once __DIR__.'/includes/checkout_validation.php';
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);exit;}
verify_csrf();
if(!bemora_asaas_enabled() || (bemora_asaas_mode()==='production' && (string)config('asaas','production_enabled','0')!=='1')){http_response_code(503);exit('Checkout ainda não liberado.');}
if(!bemora_multi_tenant_released()){http_response_code(503);exit('Ativação de novas famílias temporariamente indisponível.');}
$pdo=db();$couponReservedId=null;$couponOrderId=null;
try{
 if(!bemora_plans_table_exists($pdo))throw new RuntimeException('Planos não configurados.');
 $id=filter_var($_POST['plan_id']??null,FILTER_VALIDATE_INT);
 $q=$pdo->prepare('SELECT * FROM subscription_plans WHERE id=? AND active=1');$q->execute([$id?:0]);$plan=$q->fetch(PDO::FETCH_ASSOC);
 if(!$plan)throw new RuntimeException('Plano indisponível.');
 $name=trim((string)($_POST['name']??''));$email=mb_strtolower(trim((string)($_POST['email']??'')));
 $doc=preg_replace('/\D/','',(string)($_POST['cpf_cnpj']??''));$phone=preg_replace('/\D/','',(string)($_POST['phone']??''));
 if(mb_strlen($name)<3||mb_strlen($name)>120||!filter_var($email,FILTER_VALIDATE_EMAIL)||!bemora_checkout_valid_document($doc)||!bemora_checkout_valid_phone($phone))throw new RuntimeException('Revise os dados do responsável.');
 $q=$pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1');$q->execute([$email]);if($q->fetchColumn())throw new RuntimeException('Este e-mail já possui cadastro. Entre em contato com o suporte para contratar uma nova assinatura.');
 $promo=bemora_plan_promo_active($plan);$amount=$promo?(float)$plan['promo_price']:(float)$plan['price'];
 $baseAmount=$amount;
 $coupon=null;$couponId=null;$couponKind=null;
 $code=trim((string)($_POST['coupon_code']??''));
 if($code!==''){
  $coupon=bemora_coupon_find($pdo,$code);
  if(!$coupon)throw new RuntimeException('Cupom não encontrado.');
  $quote=bemora_coupon_quote($coupon,$plan,$amount);
  if(!$quote['supported'])throw new RuntimeException('Este tipo de cupom ainda não está habilitado para pagamento automático. Escolha outro cupom ou fale com o suporte.');
  $couponId=(int)$coupon['id'];$couponKind=$coupon['kind'];$amount=(float)$quote['amount'];
 }
 $quoted=(string)($_POST['quoted_amount']??'');
 if(!preg_match('/^\\d+\\.\\d{2}$/D',$quoted)||abs((float)$quoted-$baseAmount)>0.001)throw new RuntimeException('O preço deste plano foi atualizado. Confira o valor exibido antes de prosseguir.');
 if(time()-(int)($_SESSION['last_checkout_created_at']??0)<60)throw new RuntimeException('Aguarde um minuto antes de iniciar outra contratação.');
 $_SESSION['last_checkout_created_at']=time();
 $publicToken=bin2hex(random_bytes(32));$hash=hash('sha256',$publicToken);
 $pdo->beginTransaction();
 if($couponId!==null){
  $lock=$pdo->prepare('SELECT * FROM subscription_coupons WHERE id=? FOR UPDATE');$lock->execute([$couponId]);$locked=$lock->fetch(PDO::FETCH_ASSOC);
  if(!$locked || !bemora_coupon_is_eligible($locked,$plan))throw new RuntimeException('Este cupom expirou ou atingiu o limite de utilização.');
  $verified=bemora_coupon_quote($locked,$plan,$baseAmount);
  if(abs((float)$verified['amount']-$amount)>0.001)throw new RuntimeException('O valor do cupom mudou. Recarregue o checkout.');
 }
 $pdo->prepare('INSERT INTO subscription_orders(public_token_hash,plan_id,plan_name,billing_cycle,max_members,amount,buyer_name,buyer_email,buyer_cpf_cnpj,buyer_phone,coupon_id,list_amount,coupon_kind) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$hash,(int)$plan['id'],$plan['name'],$plan['billing_cycle'],(int)$plan['max_members'],$amount,$name,$email,$doc,$phone,$couponId,$baseAmount,$couponKind]);
 $orderId=(int)$pdo->lastInsertId();
 if($couponId!==null){
  $pdo->prepare('INSERT INTO subscription_coupon_redemptions(coupon_id,order_id) VALUES (?,?)')->execute([$couponId,$orderId]);
  $couponReservedId=$couponId;$couponOrderId=$orderId;
  $pdo->prepare('UPDATE subscription_coupons SET redeemed_count=redeemed_count+1 WHERE id=?')->execute([$couponId]);
 }
 $pdo->commit();
 $customer=bemora_asaas_request('POST','/customers',['name'=>$name,'email'=>$email,'cpfCnpj'=>$doc,'mobilePhone'=>$phone,'externalReference'=>'bemora-order-'.$orderId]);
 $customerId=(string)($customer['id']??'');if($customerId==='')throw new RuntimeException('Cliente não confirmado pelo Asaas.');
 $pdo->prepare('UPDATE subscription_orders SET asaas_customer_id=?,status=? WHERE id=?')->execute([$customerId,'customer_created',$orderId]);
 $subscription=bemora_asaas_request('POST','/subscriptions',['customer'=>$customerId,'billingType'=>'UNDEFINED','value'=>$amount,'nextDueDate'=>date('Y-m-d'),'cycle'=>bemora_asaas_cycle($plan['billing_cycle']),'description'=>'Bemora - '.$plan['name'],'externalReference'=>'bemora-order-'.$orderId]);
 $subId=(string)($subscription['id']??'');if($subId==='')throw new RuntimeException('Assinatura não confirmada pelo Asaas.');
 $pdo->prepare('UPDATE subscription_orders SET asaas_subscription_id=?,status=? WHERE id=?')->execute([$subId,'awaiting_payment',$orderId]);
 $invoice=null;$paymentId=null;
 try{
  $payments=bemora_asaas_request('GET','/subscriptions/'.rawurlencode($subId).'/payments?limit=10');
  foreach(($payments['data']??[]) as $payment){$url=bemora_asaas_invoice_url($payment);if($url){$invoice=$url;$paymentId=(string)($payment['id']??'');break;}}
 }catch(Throwable $e){error_log('Bemora: subscription payment URL not yet available.');}
 if($invoice)$pdo->prepare('UPDATE subscription_orders SET asaas_payment_id=?,invoice_url=? WHERE id=?')->execute([$paymentId,$invoice,$orderId]);
 $_SESSION['checkout_order_id']=$orderId;$_SESSION['checkout_order_token']=$publicToken;
 header('Location: /checkout/aguardando',true,303);exit;
}catch(Throwable $e){
 if($pdo->inTransaction())$pdo->rollBack();
 // A failed remote request must not consume a voucher when no Asaas subscription exists.
 if($couponReservedId && $couponOrderId){
  try{
   $q=$pdo->prepare('SELECT asaas_subscription_id FROM subscription_orders WHERE id=?');
   $q->execute([$couponOrderId]);
   if(!$q->fetchColumn()){
    $pdo->beginTransaction();
    $removed=$pdo->prepare('DELETE FROM subscription_coupon_redemptions WHERE order_id=? AND coupon_id=?');
    $removed->execute([$couponOrderId,$couponReservedId]);
    if($removed->rowCount())$pdo->prepare('UPDATE subscription_coupons SET redeemed_count=GREATEST(0,redeemed_count-1) WHERE id=?')->execute([$couponReservedId]);
    $pdo->commit();
   }
  }catch(Throwable $ignored){if($pdo->inTransaction())$pdo->rollBack();error_log('Bemora: voucher release requires reconciliation.');}
 }
 $_SESSION['checkout_error']=$e instanceof PDOException?'Não foi possível criar o pedido. Tente novamente.':$e->getMessage();
 header('Location: /checkout?plano='.(int)($_POST['plan_id']??0),true,303);exit;
}

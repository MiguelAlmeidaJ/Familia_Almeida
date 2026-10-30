<?php
declare(strict_types=1);
require_once __DIR__.'/includes/asaas.php';
require_once __DIR__.'/includes/mailer.php';
require_once __DIR__.'/includes/email_templates.php';
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);exit;}
$secret=(string)config('asaas','webhook_token','');
$given=(string)($_SERVER['HTTP_ASAAS_ACCESS_TOKEN']??'');
if(strlen($secret)<32||!hash_equals($secret,$given)){http_response_code(401);exit;}
$raw=file_get_contents('php://input');
if(!is_string($raw)||strlen($raw)>150000){http_response_code(413);exit;}
try{
 $payload=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
 $eventId=(string)($payload['id']??'');$event=(string)($payload['event']??'');
 if(!preg_match('/^[a-zA-Z0-9_\-]{4,150}$/D',$eventId)||strlen($event)>100)throw new RuntimeException('Evento inválido.');
 $payment=$payload['payment']??[];
 if(!is_array($payment))$payment=[];
 $sub=(string)($payment['subscription']??'');
 $pdo=db();$pdo->beginTransaction();
 $ins=$pdo->prepare('INSERT IGNORE INTO asaas_webhook_events(event_id,event_type,subscription_id) VALUES (?,?,?)');
 $ins->execute([$eventId,$event,$sub?:null]);
 if(!$ins->rowCount()){$pdo->commit();http_response_code(200);echo 'OK';exit;}
 $notify=null;
 if($sub!=='' && in_array($event,['PAYMENT_CONFIRMED','PAYMENT_RECEIVED'],true)){
  $q=$pdo->prepare('SELECT * FROM subscription_orders WHERE asaas_subscription_id=? FOR UPDATE');$q->execute([$sub]);$order=$q->fetch(PDO::FETCH_ASSOC);
  if($order && $order['status']!=='paid' && $order['status']!=='completed'){
   if((string)($payment['customer']??'')!==(string)$order['asaas_customer_id'])throw new RuntimeException('Cliente da cobrança não corresponde ao pedido.');
   if(abs((float)($payment['value']??0)-(float)$order['amount'])>0.01)throw new RuntimeException('Valor de pagamento divergente.');
   // A paid subscription never creates a tenant directly. Buyer must finish
   // member registration using the one-use invitation created here.
   $inviteSecret=(string)config('asaas','invite_secret','');
   if(strlen($inviteSecret)<32)throw new RuntimeException('Segredo de convite não configurado.');
   $token=hash_hmac('sha256','bemora-paid-order-'.(int)$order['id'],$inviteSecret);
   $inviteHash=hash('sha256',$token);
   $q=$pdo->prepare('INSERT INTO family_invitations(token_hash,max_members,expires_at,created_by) VALUES (?, ?, DATE_ADD(NOW(),INTERVAL 72 HOUR), ?)');
   // Created by the designated system administrator; never default to a family account.
   $admin=(int)config('asaas','system_admin_user_id',0);
   if($admin<1)throw new RuntimeException('Administrador responsável pelo convite não configurado.');
   $q->execute([$inviteHash,(int)$order['max_members'],$admin]);$inviteId=(int)$pdo->lastInsertId();
   $pdo->prepare("UPDATE subscription_orders SET status='paid',paid_at=NOW(),invite_id=? WHERE id=?")->execute([$inviteId,(int)$order['id']]);
   $notify=['email'=>$order['buyer_email'],'token'=>$token,'members'=>(int)$order['max_members']];
  }
 }
 if($sub!=='' && in_array($event,['PAYMENT_OVERDUE','PAYMENT_REFUNDED','PAYMENT_CHARGEBACK_REQUESTED'],true)){
  // Do not automatically destroy tenant data or invert an already paid order.
  $pdo->prepare("UPDATE subscription_orders SET status='payment_attention' WHERE asaas_subscription_id=? AND status='awaiting_payment'")->execute([$sub]);
 }
 $pdo->commit();
 if($notify){
  $url=rtrim((string)config('app','base_url','https://bemora.anoar.com.br'),'/').'/convite-familia.php?token='.$notify['token'];
  try{bemora_send_email($notify['email'],'Bemora — pagamento confirmado e convite disponível',"Pagamento confirmado!\n\nFinalize o cadastro da sua família: ".$url."\n\nO convite expira em 72 horas.",bemora_invitation_email($url,$notify['members']));}
  catch(Throwable $mailError){error_log('Bemora: convite pós-pagamento não enviado. O comprador pode recuperá-lo na página do pedido autenticada pela sessão.');}
 }
 http_response_code(200);echo 'OK';
}catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();error_log('Bemora: webhook Asaas não processado.');http_response_code(500);echo 'PROCESSING_ERROR';}

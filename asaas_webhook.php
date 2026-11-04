<?php
declare(strict_types=1);
require_once __DIR__.'/includes/asaas.php';
require_once __DIR__.'/includes/mailer.php';
require_once __DIR__.'/includes/email_templates.php';
require_once __DIR__.'/includes/paid_plan_changes.php';
require_once __DIR__.'/includes/paid_addon_changes.php';
require_once __DIR__.'/includes/billing_access.php';
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
 $subscriptionEvent=is_array($payload['subscription']??null)?$payload['subscription']:[];
 $sub=(string)($payment['subscription']??($subscriptionEvent['id']??''));
 // Standalone upgrade payments have no subscription field. Confirm through Asaas before activation.
 if($sub==='' && in_array($event,['PAYMENT_CONFIRMED','PAYMENT_RECEIVED'],true)){
  $id=(string)($payment['id']??'');
  if(preg_match('/^[A-Za-z0-9_-]{3,100}$/D',$id)){bemora_apply_paid_plan_change(db(),$id);bemora_apply_paid_addon_change(db(),$id);}
 }

 // Reversed add-on charges require reconciliation; access is not silently removed
 // because the existing Asaas subscription may have renewed already.
 if($sub==='' && in_array($event,['PAYMENT_REFUNDED','PAYMENT_PARTIALLY_REFUNDED','PAYMENT_REFUND_IN_PROGRESS','PAYMENT_CHARGEBACK_REQUESTED','PAYMENT_CHARGEBACK_DISPUTE','PAYMENT_AWAITING_CHARGEBACK_REVERSAL'],true)){
  $paymentId=(string)($payment['id']??'');
  if(preg_match('/^[A-Za-z0-9_-]{3,100}$/D',$paymentId)){
   $db=db();
   $exists=$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='bemora_addon_changes'");
   if((int)$exists->fetchColumn()===1){
    $q=$db->prepare('SELECT c.id,c.activated_at,o.asaas_customer_id FROM bemora_addon_changes c JOIN subscription_orders o ON o.id=c.order_id WHERE c.asaas_payment_id=? LIMIT 1');
    $q->execute([$paymentId]);$change=$q->fetch(PDO::FETCH_ASSOC);
    if($change && !empty($change['activated_at'])){
     $verified=bemora_asaas_request('GET','/payments/'.rawurlencode($paymentId));
     if((string)($verified['customer']??'')===(string)$change['asaas_customer_id'] && (string)($verified['externalReference']??'')==='bemora-addon-'.(int)$change['id']
        && in_array((string)($verified['status']??''),['REFUNDED','PARTIALLY_REFUNDED','REFUND_IN_PROGRESS','CHARGEBACK_REQUESTED','CHARGEBACK_DISPUTE','AWAITING_CHARGEBACK_REVERSAL'],true))
      $db->prepare("UPDATE bemora_addon_changes SET status='reconciliation_required' WHERE id=?")->execute([(int)$change['id']]);
    }
   }
  }
 }
 // Reversed standalone upgrade charges require manual billing reconciliation.
 // Do not silently revert a subscribed family's limits after a chargeback.
 if($sub==='' && in_array($event,['PAYMENT_REFUNDED','PAYMENT_PARTIALLY_REFUNDED','PAYMENT_REFUND_IN_PROGRESS','PAYMENT_CHARGEBACK_REQUESTED','PAYMENT_CHARGEBACK_DISPUTE','PAYMENT_AWAITING_CHARGEBACK_REVERSAL'],true)){
  $paymentId=(string)($payment['id']??'');
  if(preg_match('/^[A-Za-z0-9_-]{3,100}$/D',$paymentId)){
   $db=db();
   $q=$db->prepare("SELECT c.id,c.status,c.activated_at,o.asaas_customer_id FROM subscription_change_requests c JOIN subscription_orders o ON o.id=c.order_id WHERE c.asaas_payment_id=? LIMIT 1");
   $q->execute([$paymentId]);$change=$q->fetch(PDO::FETCH_ASSOC);
   if($change && !empty($change['activated_at'])){
    $verified=bemora_asaas_request('GET','/payments/'.rawurlencode($paymentId));
    if((string)($verified['id']??'')===$paymentId
       && (string)($verified['customer']??'')===(string)$change['asaas_customer_id']
       && (string)($verified['externalReference']??'')==='bemora-change-'.(int)$change['id']
       && in_array((string)($verified['status']??''),['REFUNDED','PARTIALLY_REFUNDED','REFUND_IN_PROGRESS','CHARGEBACK_REQUESTED','CHARGEBACK_DISPUTE','AWAITING_CHARGEBACK_REVERSAL'],true)){
     $db->prepare("UPDATE subscription_change_requests SET status='reconciliation_required',failure_reason='upgrade_payment_reversed' WHERE id=? AND activated_at IS NOT NULL")->execute([(int)$change['id']]);
    }
   }
  }
 }
 $pdo=db();$pdo->beginTransaction();
 $ins=$pdo->prepare('INSERT IGNORE INTO asaas_webhook_events(event_id,event_type,subscription_id) VALUES (?,?,?)');
 $ins->execute([$eventId,$event,$sub?:null]);
 if(!$ins->rowCount()){$pdo->commit();http_response_code(200);echo 'OK';exit;}
 $notify=null;
 if($sub!=='' && in_array($event,['PAYMENT_CONFIRMED','PAYMENT_RECEIVED'],true)){
  $q=$pdo->prepare('SELECT * FROM subscription_orders WHERE asaas_subscription_id=? FOR UPDATE');$q->execute([$sub]);$order=$q->fetch(PDO::FETCH_ASSOC);
  if($order && $order['status']!=='paid' && $order['status']!=='completed'){
   $paymentId=(string)($payment['id']??'');
   if(!preg_match('/^[a-zA-Z0-9_\\-]{3,100}$/D',$paymentId))throw new RuntimeException('Identificador de pagamento inválido.');
   $verified=bemora_asaas_request('GET','/payments/'.rawurlencode($paymentId));
   if(!in_array((string)($verified['status']??''),['CONFIRMED','RECEIVED'],true)
      ||(string)($verified['subscription']??'')!==$sub)throw new RuntimeException('Cobrança ainda não confirmada pela API.');
   $payment=$verified;
   if((string)($payment['customer']??'')!==(string)$order['asaas_customer_id'])throw new RuntimeException('Cliente da cobrança não corresponde ao pedido.');
   if($order['coupon_kind']==='first_payment_discount' && (!(int)$order['first_payment_adjusted'] || (string)$paymentId!==(string)$order['asaas_payment_id']))throw new RuntimeException('Primeira cobrança promocional ainda não ajustada ou divergente.');
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
 if($sub!=='' && in_array($event,['PAYMENT_OVERDUE','PAYMENT_REFUNDED','PAYMENT_PARTIALLY_REFUNDED','PAYMENT_REFUND_IN_PROGRESS','PAYMENT_CHARGEBACK_REQUESTED','PAYMENT_CHARGEBACK_DISPUTE','PAYMENT_AWAITING_CHARGEBACK_REVERSAL'],true)){
  // Do not automatically destroy tenant data or invert an already paid order.
  $pdo->prepare("UPDATE subscription_orders SET status='payment_attention' WHERE asaas_subscription_id=? AND status='awaiting_payment'")->execute([$sub]);
 }
 // Prevent a canceled, not-yet-activated subscription from generating
 // a new family invitation after payment confirmation.
 if(in_array($event,['SUBSCRIPTION_DELETED','SUBSCRIPTION_INACTIVATED'],true) && $sub!=='' && $subscriptionEvent){
  $pdo->prepare("UPDATE subscription_orders SET status='payment_attention' WHERE asaas_subscription_id=? AND asaas_customer_id=? AND activated_tenant_id IS NULL AND status IN ('paid','awaiting_payment')")
   ->execute([$sub,(string)($subscriptionEvent['customer']??'')]);
 }
 $pdo->commit();
 // Subscription events are distinct from payment events. On deletion the
 // subscription cannot be fetched again; the authenticated event is verified
 // against the order's subscription/customer binding before access is restricted.
 if($event==='SUBSCRIPTION_DELETED'){
  try{bemora_mark_asaas_subscription_removed($pdo,$sub,(string)($subscriptionEvent['customer']??''));}
  catch(Throwable $lifecycleError){error_log('Bemora: subscription deletion requires reconciliation.');}
 }
 if(in_array($event,['SUBSCRIPTION_UPDATED','SUBSCRIPTION_INACTIVATED'],true)&&bemora_billing_access_ready($pdo)){
  try{
   $q=$pdo->prepare('SELECT activated_tenant_id FROM subscription_orders WHERE asaas_subscription_id=? AND asaas_customer_id=? LIMIT 1');
   $q->execute([$sub,(string)($subscriptionEvent['customer']??'')]);
   $tenantId=(int)$q->fetchColumn();
   if($tenantId>0)bemora_reconcile_billing_access($pdo,$tenantId);
  }catch(Throwable $lifecycleError){error_log('Bemora: subscription lifecycle reconciliation deferred to cron.');}
 }
 // Always reconcile against Asaas, not the untrusted event body. Cron is fallback.
 if($sub!=='' && in_array($event,['PAYMENT_OVERDUE','PAYMENT_CONFIRMED','PAYMENT_RECEIVED','PAYMENT_REFUNDED','PAYMENT_PARTIALLY_REFUNDED','PAYMENT_REFUND_IN_PROGRESS','PAYMENT_REFUND_DENIED','PAYMENT_RECEIVED_IN_CASH_UNDONE','PAYMENT_CHARGEBACK_REQUESTED','PAYMENT_CHARGEBACK_DISPUTE','PAYMENT_AWAITING_CHARGEBACK_REVERSAL','PAYMENT_DELETED','PAYMENT_RESTORED'],true) && bemora_billing_access_ready($pdo)){
  try{
   $q=$pdo->prepare("SELECT activated_tenant_id FROM subscription_orders WHERE asaas_subscription_id=? AND activated_tenant_id IS NOT NULL LIMIT 1");
   $q->execute([$sub]);$tenantId=(int)$q->fetchColumn();
   if($tenantId>0)bemora_reconcile_billing_access($pdo,$tenantId);
  }catch(Throwable $billingError){error_log('Bemora: webhook billing access reconciliation deferred to cron.');}
 }
 if($notify){
  $url=rtrim((string)config('app','base_url','https://bemora.anoar.com.br'),'/').'/convite-familia.php?token='.$notify['token'];
  try{bemora_send_email($notify['email'],'Bemora — pagamento confirmado e convite disponível',"Pagamento confirmado!\n\nFinalize o cadastro da sua família: ".$url."\n\nO convite expira em 72 horas.",bemora_invitation_email($url,$notify['members']));}
  catch(Throwable $mailError){error_log('Bemora: convite pós-pagamento não enviado. O comprador pode recuperá-lo na página do pedido autenticada pela sessão.');}
 }
 http_response_code(200);echo 'OK';
}catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();error_log('Bemora: webhook Asaas não processado.');http_response_code(500);echo 'PROCESSING_ERROR';}

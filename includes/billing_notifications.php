<?php
declare(strict_types=1);
require_once __DIR__.'/billing_access.php';
require_once __DIR__.'/mailer.php';
require_once __DIR__.'/email_templates.php';

/**
 * Sends at most one reminder of each type per Asaas invoice. Run via daily CLI.
 * Failures are logged and retried, but never influence access suspension.
 */
function bemora_send_billing_reminders(PDO $pdo,int $tenantId):int {
 $access=bemora_billing_access($pdo,$tenantId);
 if(in_array((string)($access['reason']??''),['subscription_deleted','subscription_inactive','subscription_missing','payment_reversed','upgrade_payment_reversed'],true))return 0;
 $ready=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='billing_notification_log'")->fetchColumn();
 if(!$ready)throw new RuntimeException('Execute a migration 035.');
 $q=$pdo->prepare("SELECT o.asaas_subscription_id,o.asaas_customer_id,o.coupon_free_until,
  (SELECT u.email FROM tenant_users tu JOIN users u ON u.id=tu.user_id WHERE tu.tenant_id=o.activated_tenant_id AND tu.role='family_admin' ORDER BY tu.created_at,u.id LIMIT 1) AS recipient
  FROM subscription_orders o WHERE o.activated_tenant_id=? AND o.asaas_subscription_id IS NOT NULL AND o.status IN ('completed','paid','payment_attention') ORDER BY o.id DESC LIMIT 1");
 $q->execute([$tenantId]);$order=$q->fetch(PDO::FETCH_ASSOC);
 if(!$order||!filter_var($order['recipient'],FILTER_VALIDATE_EMAIL))return 0;
 $today=new DateTimeImmutable('today');
 $trial=(string)($order['coupon_free_until']??'');
 if(preg_match('/^\d{4}-\d{2}-\d{2}$/D',$trial)&&$trial>=$today->format('Y-m-d'))return 0;
 $sid=(string)$order['asaas_subscription_id'];$sent=0;$offset=0;
 do{
  $page=bemora_asaas_request('GET','/subscriptions/'.rawurlencode($sid).'/payments?limit=100&offset='.$offset);
  $items=$page['data']??null;
  if(!is_array($items))throw new RuntimeException('Resposta inválida de cobranças.');
  foreach($items as $p){
   if(!is_array($p)||(string)($p['subscription']??'')!==$sid||(string)($p['customer']??'')!==(string)$order['asaas_customer_id'])continue;
   if(!in_array((string)($p['status']??''),['PENDING','OVERDUE'],true))continue;
   $pid=(string)($p['id']??'');$due=(string)($p['dueDate']??'');
   if(!preg_match('/^[a-zA-Z0-9_-]{3,100}$/D',$pid)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$due))continue;
   $days=(int)$today->diff(new DateTimeImmutable($due))->format('%r%a');
   $stage=match(true){$days===0=>'due_today',$days<=-2&&$days>=-3=>'late_two',$days<=-4=>'suspended',default=>null};
   if(!$stage)continue;
   $recipient=(string)$order['recipient'];
   $insert=$pdo->prepare("INSERT INTO billing_notification_log(tenant_id,payment_id,notification_type,recipient,status) VALUES(?,?,?,?,'sending')
    ON DUPLICATE KEY UPDATE id=id");
   $insert->execute([$tenantId,$pid,$stage,$recipient]);
   $claim=$pdo->prepare("UPDATE billing_notification_log SET status='sending',attempted_at=NOW() WHERE tenant_id=? AND payment_id=? AND notification_type=? AND status='failed'");
   $claim->execute([$tenantId,$pid,$stage]);
   // New claims have sending status; previously claimed rows must not send again.
   $check=$pdo->prepare("SELECT id,status,attempted_at,sent_at FROM billing_notification_log WHERE tenant_id=? AND payment_id=? AND notification_type=?");
   $check->execute([$tenantId,$pid,$stage]);$log=$check->fetch(PDO::FETCH_ASSOC);
   // INSERT rowCount=1 means newly claimed; update rowCount=1 means failed retry claimed.
   if($insert->rowCount()!==1 && $claim->rowCount()!==1)continue;
   $url=bemora_asaas_invoice_url($p);
   $message=match($stage){
    'due_today'=>'Sua fatura do Bemora vence hoje. Você pode conferir o pagamento e manter seu acesso sem interrupções.',
    'late_two'=>'Sua fatura está vencida há pelo menos dois dias. Regularize o pagamento para evitar a suspensão do acesso financeiro após três dias completos de atraso.',
    default=>'Sua fatura está vencida há mais de três dias e o acesso financeiro poderá estar suspenso. Seus dados continuam preservados. Regularize o pagamento para restabelecer o acesso após confirmação do Asaas.'
   };
   $subject=match($stage){'due_today'=>'Bemora · Sua fatura vence hoje','late_two'=>'Bemora · Aviso de pagamento em atraso',default=>'Bemora · Regularize sua assinatura'};
   $link=$url?:rtrim((string)config('app','base_url','https://bemora.anoar.com.br'),'/').'/minha-assinatura';
   $html=bemora_email_layout('Minha assinatura',$subject,$message,'Ver minha fatura',$link,'Esta mensagem é automática. Caso tenha efetuado o pagamento recentemente, aguarde a confirmação do Asaas.');
   try{
    bemora_send_email($recipient,$subject,$message."\n".$link,$html);
    $pdo->prepare("UPDATE billing_notification_log SET status='sent',sent_at=NOW() WHERE id=?")->execute([(int)$log['id']]);$sent++;
   }catch(Throwable $e){
    $pdo->prepare("UPDATE billing_notification_log SET status='failed' WHERE id=?")->execute([(int)$log['id']]);
    error_log('Bemora billing email failed for tenant '.$tenantId.' and stage '.$stage);
   }
  }
  $offset+=count($items);$more=($page['hasMore']??false)===true;
  if($more&&count($items)===0)throw new RuntimeException('Paginação incompleta.');
  if($offset>10000)throw new RuntimeException('Paginação excedeu limite de segurança.');
 }while($more);
 return $sent;
}

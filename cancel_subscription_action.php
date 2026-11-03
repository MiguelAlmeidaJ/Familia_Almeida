<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/subscription_cancellation.php';
require_once __DIR__.'/includes/billing_access.php';
require_once __DIR__.'/includes/mailer.php';
require_once __DIR__.'/includes/email_templates.php';
$user=require_family_access();
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);exit;}
verify_csrf();
$pdo=db();$tenantId=(int)$user['tenant_id'];$claimId=0;
try{
 if(!bemora_cancellation_ready($pdo))throw new RuntimeException('Execute a migration de cancelamentos pelo painel de Manutenção.');
 if(!bemora_asaas_enabled())throw new RuntimeException('Serviço de assinaturas indisponível.');
 if(bemora_asaas_mode()!=='sandbox' && !in_array(config('asaas','cancellation_production_enabled',false),[true,1,'1','true','on'],true))
  throw new RuntimeException('Cancelamentos em produção aguardam homologação.');
 if(($_POST['confirm_cancel']??'')!=='yes')throw new RuntimeException('Confirme que deseja encerrar a renovação da assinatura.');
 $reason=trim((string)($_POST['reason']??''));
 if(mb_strlen($reason)>500)throw new RuntimeException('Motivo muito longo.');
 $pdo->beginTransaction();
 $role=$pdo->prepare('SELECT role FROM tenant_users WHERE tenant_id=? AND user_id=? FOR UPDATE');
 $role->execute([$tenantId,(int)$user['id']]);
 if($role->fetchColumn()!=='family_admin')throw new RuntimeException('Somente o administrador familiar pode cancelar a assinatura.');
 $q=$pdo->prepare("SELECT * FROM subscription_orders WHERE activated_tenant_id=? AND status='completed' AND asaas_subscription_id IS NOT NULL ORDER BY id DESC LIMIT 1 FOR UPDATE");
 $q->execute([$tenantId]);$order=$q->fetch(PDO::FETCH_ASSOC);
 if(!$order)throw new RuntimeException('Assinatura comercial não encontrada.');
 $q=$pdo->prepare('SELECT id,status FROM subscription_cancellations WHERE tenant_id=? FOR UPDATE');
 $q->execute([$tenantId]);$existing=$q->fetch(PDO::FETCH_ASSOC);
 if($existing){
  if($existing['status']==='confirmed')throw new RuntimeException('A renovação desta assinatura já foi cancelada.');
  throw new RuntimeException('Existe um cancelamento em conciliação. Entre em contato com o suporte.');
 }
 $q=$pdo->prepare("SELECT COUNT(*) FROM subscription_change_requests WHERE tenant_id=? AND status IN ('scheduled','processing','reconciliation_required','awaiting_payment','payment_confirmed')");
 $q->execute([$tenantId]);
 if((int)$q->fetchColumn()>0)throw new RuntimeException('Existe uma mudança de plano pendente. Conclua ou regularize antes de cancelar.');
 $sid=(string)$order['asaas_subscription_id'];
 $q=$pdo->prepare("INSERT INTO subscription_cancellations(tenant_id,order_id,requested_by,asaas_subscription_id,status,reason) VALUES(?,?,?,?,'processing',?)");
 $q->execute([$tenantId,(int)$order['id'],(int)$user['id'],$sid,$reason?:null]);
 $claimId=(int)$pdo->lastInsertId();$pdo->commit();
 // The network call is outside the database transaction.
 $remote=bemora_asaas_request('GET','/subscriptions/'.rawurlencode($sid));
 if((string)($remote['id']??'')!==$sid||(string)($remote['customer']??'')!==(string)$order['asaas_customer_id']
    ||(string)($remote['status']??'')!=='ACTIVE')throw new RuntimeException('Assinatura não está ativa ou não corresponde à família.');
 $payments=bemora_cancellation_payment_list($sid,(string)$order['asaas_customer_id']);
 $accessUntil=bemora_cancellation_entitlement($remote,$payments,(string)$order['billing_cycle'],(string)($order['coupon_free_until']??''));
 $pdo->prepare("UPDATE subscription_cancellations SET access_until=? WHERE id=? AND status='processing'")->execute([$accessUntil,$claimId]);
 $changed=bemora_asaas_request('PUT','/subscriptions/'.rawurlencode($sid),['status'=>'INACTIVE']);
 if((string)($changed['id']??'')!==$sid||(string)($changed['status']??'')!=='INACTIVE')
  throw new RuntimeException('O Asaas ainda não confirmou a interrupção das renovações.');
 bemora_cancellation_confirm($pdo,$claimId,$accessUntil);
 try{bemora_reconcile_billing_access($pdo,$tenantId);}catch(Throwable $accessError){error_log('Bemora: cancelamento confirmado, acesso será atualizado pela rotina de conciliação.');}
 try{bemora_cancellation_notice($pdo,$tenantId);}
 catch(Throwable $mailError){error_log('Bemora: cancelamento confirmado, e-mail será reenviado pela conciliação.');}
 flash('success',$accessUntil?'Cancelamento confirmado! Sua assinatura não será renovada. Seu acesso permanece até '.date('d/m/Y',strtotime($accessUntil)).'.':'Cancelamento confirmado! As próximas cobranças recorrentes foram interrompidas.');
}catch(Throwable $error){
 if($pdo->inTransaction())$pdo->rollBack();
 if($claimId){
  $pdo->prepare("UPDATE subscription_cancellations SET status='reconciliation_required',reviewed_at=NOW() WHERE id=? AND status='processing'")->execute([$claimId]);
  error_log('Bemora: cancelamento '.$claimId.' precisa conciliação: '.$error->getMessage());
  flash('error','Não foi possível confirmar o cancelamento completamente. A solicitação foi registrada para conciliação. Verifique com o suporte antes de tentar novamente.');
 }else flash('error',$error->getMessage());
}
redirect_to('/minha-assinatura');

<?php
declare(strict_types=1);
require_once __DIR__.'/asaas.php';
require_once __DIR__.'/subscription_cancellation.php';

function bemora_cleanup_ready(PDO $pdo):bool {
 $q=$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='admin_cleanup_audit'");
 if((int)$q->fetchColumn()!==1)return false;
 $q=$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='subscription_orders' AND column_name='admin_subscription_removed_at'");
 if((int)$q->fetchColumn()!==1)return false;
 $q=$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='subscription_orders' AND column_name='admin_subscription_had_settled_payments'");
 return (int)$q->fetchColumn()===1;
}
function bemora_cleanup_has_table(PDO $pdo,string $table):bool {
 $q=$pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?");
 $q->execute([$table]);return (int)$q->fetchColumn()===1;
}
function bemora_cleanup_audit(PDO $pdo,int $adminId,string $action,int $target,string $type):void {
 $q=$pdo->prepare('INSERT INTO admin_cleanup_audit(admin_user_id,action,target_id,reference_type) VALUES(?,?,?,?)');
 $q->execute([$adminId,$action,$target,$type]);
}
/**
 * Cancels the Asaas subscription permanently; unpaid/open recurring invoices
 * are removed by Asaas. Never removes a paid invoice or the Asaas customer.
 */
function bemora_cleanup_cancel_subscription(PDO $pdo,int $orderId,int $adminId):void {
 if(!bemora_cleanup_ready($pdo))throw new RuntimeException('Execute a migration 048 antes desta operação.');
 $q=$pdo->prepare('SELECT id,asaas_customer_id,asaas_subscription_id,admin_subscription_removed_at FROM subscription_orders WHERE id=?');
 $q->execute([$orderId]);$o=$q->fetch(PDO::FETCH_ASSOC);
 if(!$o)throw new RuntimeException('Pedido não encontrado.');
 if(!empty($o['admin_subscription_removed_at']))throw new RuntimeException('A recorrência já foi removida do Asaas.');
 $sid=(string)$o['asaas_subscription_id'];$cid=(string)$o['asaas_customer_id'];
 if(!preg_match('/^[A-Za-z0-9_-]{4,100}$/D',$sid)||!preg_match('/^[A-Za-z0-9_-]{4,100}$/D',$cid))throw new RuntimeException('Este pedido não possui uma assinatura Asaas válida.');
 $remote=bemora_asaas_request('GET','/subscriptions/'.rawurlencode($sid));
 if((string)($remote['id']??'')!==$sid||(string)($remote['customer']??'')!==$cid)throw new RuntimeException('A assinatura consultada não corresponde ao cliente do pedido.');
 $invoices=bemora_cancellation_payment_list($sid,$cid);
 $hasSettled=false;
 foreach($invoices as $payment){
  // Anything except an unequivocally open or deleted invoice needs retention.
  if(!in_array((string)($payment['status']??''),['PENDING','OVERDUE','DELETED'],true)){$hasSettled=true;break;}
 }
 $answer=bemora_asaas_request('DELETE','/subscriptions/'.rawurlencode($sid));
 if(($answer['deleted']??false)!==true && (string)($answer['id']??'')!==$sid)throw new RuntimeException('O Asaas não confirmou a remoção. Consulte a assinatura antes de tentar novamente.');
 // Remote cancellation has occurred, so do not roll back this fact with a failed audit.
 $pdo->beginTransaction();
 try{
  $q=$pdo->prepare('UPDATE subscription_orders SET admin_subscription_removed_at=NOW(),admin_subscription_had_settled_payments=? WHERE id=? AND admin_subscription_removed_at IS NULL');
  $q->execute([$hasSettled?1:0,$orderId]);
  bemora_cleanup_audit($pdo,$adminId,'cancel_asaas',$orderId,'subscription_order');
  $pdo->commit();
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('Bemora: assinatura removida no Asaas, mas marcação local falhou. Pedido #'.$orderId);throw new RuntimeException('Asaas confirmou a exclusão, mas a atualização local falhou. NÃO repita a exclusão; concilie este pedido com o suporte.');}
}
function bemora_cleanup_order_deletable(array $order):bool {
 if(!empty($order['paid_at'])||(int)($order['admin_subscription_had_settled_payments']??0)===1)return false;
 if(in_array((string)($order['status']??''),['paid','completed','payment_attention'],true))return false;
 if(!empty($order['asaas_subscription_id'])&&empty($order['admin_subscription_removed_at']))return false;
 return true;
}
function bemora_cleanup_delete_order(PDO $pdo,int $orderId,int $adminId):void {
 if(!bemora_cleanup_ready($pdo))throw new RuntimeException('Execute a migration 048.');
 $pdo->beginTransaction();
 try {
  $q=$pdo->prepare('SELECT * FROM subscription_orders WHERE id=? FOR UPDATE');
  $q->execute([$orderId]);$o=$q->fetch(PDO::FETCH_ASSOC);
  if(!$o)throw new RuntimeException('Pedido não encontrado.');
  if(!bemora_cleanup_order_deletable($o))
   throw new RuntimeException('Pedido não elegível: remova primeiro a recorrência no Asaas. Pedidos pagos ou com acesso ativado devem ser preservados, conforme a política de retenção/LGPD.');
  if(!empty($o['asaas_payment_id']) && empty($o['asaas_subscription_id'])){
   $paymentId=(string)$o['asaas_payment_id'];
   if(!preg_match('/^[a-zA-Z0-9_-]{4,100}$/D',$paymentId))throw new RuntimeException('Identificador da cobrança inválido.');
   $remotePayment=bemora_asaas_request('GET','/payments/'.rawurlencode($paymentId));
   if((string)($remotePayment['id']??'')!==$paymentId || (string)($remotePayment['customer']??'')!==(string)$o['asaas_customer_id']
      || (string)($remotePayment['status']??'')!=='DELETED')
    throw new RuntimeException('A cobrança avulsa precisa estar cancelada no Asaas (DELETED) antes de remover o pedido.');
  }
  // Other payment objects may represent paid upgrades or addon charges.
  foreach(['subscription_change_requests','bemora_addon_changes'] as $table){
   if(!bemora_cleanup_has_table($pdo,$table))continue;
   $q=$pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE order_id=? AND (asaas_payment_id IS NOT NULL OR activated_at IS NOT NULL)");
   $q->execute([$orderId]);
   if((int)$q->fetchColumn()>0)throw new RuntimeException('Este pedido possui cobranças de adicionais/evoluções e precisa de conciliação manual.');
  }
  foreach(['subscription_coupon_redemptions','subscription_change_requests','bemora_addon_changes','subscription_cancellations'] as $table){
   if(!bemora_cleanup_has_table($pdo,$table))continue;
   $pdo->prepare("DELETE FROM `$table` WHERE order_id=?")->execute([$orderId]);
  }
  $pdo->prepare('DELETE FROM subscription_orders WHERE id=?')->execute([$orderId]);
  if(!empty($o['invite_id']) && bemora_cleanup_has_table($pdo,'family_invitations')){
   $pdo->prepare('UPDATE family_invitations SET revoked_at=COALESCE(revoked_at,NOW()) WHERE id=? AND used_at IS NULL')->execute([(int)$o['invite_id']]);
  }
  // Never erase Asaas customers or charges already paid. Invitations associated
  // with a registered workspace are kept until that workspace is removed.
  bemora_cleanup_audit($pdo,$adminId,'delete_unpaid_order',$orderId,'subscription_order');
  $pdo->commit();
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
/**
 * Deletes only one tenant's scoped data in FK dependency order.
 * A missing/unknown FK is not bypassed; the entire SQL transaction rolls back.
 */
function bemora_cleanup_tenant_tables(PDO $pdo):array {
 $q=$pdo->query("SELECT DISTINCT table_name FROM information_schema.columns WHERE table_schema=DATABASE() AND column_name='tenant_id'");
 $tables=array_fill_keys($q->fetchAll(PDO::FETCH_COLUMN),true);
 unset($tables['subscription_orders']);
 $q=$pdo->query("SELECT table_name,referenced_table_name FROM information_schema.key_column_usage WHERE table_schema=DATABASE() AND referenced_table_schema=DATABASE() AND referenced_table_name IS NOT NULL");
 $children=[];
 foreach($q->fetchAll(PDO::FETCH_ASSOC) as $fk){
  $parent=$fk['referenced_table_name'];$child=$fk['table_name'];
  if($parent!==$child && isset($tables[$parent],$tables[$child]))$children[$parent][$child]=true;
 }
 $ordered=[];$visiting=[];$visited=[];
 $visit=static function(string $table)use(&$visit,&$ordered,&$visiting,&$visited,$children):void {
  if(isset($visited[$table]))return;
  if(isset($visiting[$table]))throw new RuntimeException('Dependência circular de tabelas: exclusão bloqueada por segurança.');
  $visiting[$table]=true;
  foreach(array_keys($children[$table]??[]) as $child)$visit($child);
  unset($visiting[$table]);$visited[$table]=true;$ordered[]=$table;
 };
 foreach(array_keys($tables) as $table)$visit($table);
 return $ordered;
}
function bemora_cleanup_delete_tenant(PDO $pdo,int $tenantId,int $adminId):void {
 if(!bemora_cleanup_ready($pdo))throw new RuntimeException('Execute a migration 048.');
 $pdo->beginTransaction();
 try{
  $q=$pdo->prepare('SELECT id,slug,name_key FROM tenants WHERE id=? FOR UPDATE');
  $q->execute([$tenantId]);$tenant=$q->fetch(PDO::FETCH_ASSOC);
  if(!$tenant)throw new RuntimeException('Espaço não encontrado.');
  if($tenant['slug']==='familia-almeida')throw new RuntimeException('O espaço legado principal é protegido contra exclusão.');
  $q=$pdo->prepare('SELECT COUNT(*) FROM subscription_orders WHERE activated_tenant_id=?');
  $q->execute([$tenantId]);
  if((int)$q->fetchColumn()>0)throw new RuntimeException('Exclua primeiro os pedidos de teste vinculados ao espaço. Pedidos pagos não podem ser excluídos por esta ferramenta.');
  $q=$pdo->prepare("SELECT u.id FROM tenant_users tu JOIN users u ON u.id=tu.user_id WHERE tu.tenant_id=? AND u.system_role='user' AND NOT EXISTS (SELECT 1 FROM tenant_users other WHERE other.user_id=u.id AND other.tenant_id<>?)");
  $q->execute([$tenantId,$tenantId]);$users=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
  $q=$pdo->prepare("SELECT COUNT(*) FROM tenant_users tu JOIN users u ON u.id=tu.user_id WHERE tu.tenant_id=? AND u.system_role='system_admin'");
  $q->execute([$tenantId]);if((int)$q->fetchColumn())throw new RuntimeException('Este espaço contém um administrador global e não pode ser removido.');
  foreach(bemora_cleanup_tenant_tables($pdo) as $table){
   if(!preg_match('/^[a-zA-Z0-9_]+$/D',$table))throw new RuntimeException('Nome de tabela inválido.');
   $pdo->prepare("DELETE FROM `$table` WHERE tenant_id=?")->execute([$tenantId]);
  }
  $pdo->prepare('DELETE FROM tenants WHERE id=?')->execute([$tenantId]);
  // Remove the consumed invitation/registration created for this workspace,
  // provided no surviving order still refers to that invitation.
  if(!empty($tenant['name_key']) && bemora_cleanup_has_table($pdo,'family_registrations')){
   $q=$pdo->prepare('SELECT id,invitation_id FROM family_registrations WHERE family_name_key=? FOR UPDATE');
   $q->execute([(string)$tenant['name_key']]);
   foreach($q->fetchAll(PDO::FETCH_ASSOC) as $registration){
    $linked=$pdo->prepare('SELECT COUNT(*) FROM subscription_orders WHERE invite_id=?');
    $linked->execute([(int)$registration['invitation_id']]);
    if((int)$linked->fetchColumn()>0)continue;
    $pdo->prepare('DELETE FROM family_registrations WHERE id=?')->execute([(int)$registration['id']]);
    $pdo->prepare('DELETE FROM family_invitations WHERE id=?')->execute([(int)$registration['invitation_id']]);
   }
  }
  // Do not delete a user shared with a different tenant; never delete system admins.
  foreach($users as $uid){
   $pdo->prepare("DELETE FROM users WHERE id=? AND system_role='user' AND NOT EXISTS (SELECT 1 FROM tenant_users WHERE user_id=?)")->execute([$uid,$uid]);
  }
  bemora_cleanup_audit($pdo,$adminId,'delete_test_tenant',$tenantId,'tenant');
  $pdo->commit();
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

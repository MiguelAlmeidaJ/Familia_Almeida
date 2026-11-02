<?php
declare(strict_types=1);
require_once __DIR__.'/asaas.php';

/** Only update an unpaid first charge; do not display its invoice until confirmed adjusted. */
function bemora_adjust_first_coupon_payment(PDO $pdo,int $orderId,array $payment):bool {
 $q=$pdo->prepare("SELECT * FROM subscription_orders WHERE id=?");$q->execute([$orderId]);$order=$q->fetch(PDO::FETCH_ASSOC);
 if(!$order)return false;
 if($order['coupon_kind']!=='first_payment_discount')return true;
 if((int)$order['first_payment_adjusted']===1)return (string)$order['asaas_payment_id']===(string)($payment['id']??'');
 $id=(string)($payment['id']??'');
 if(!preg_match('/^[A-Za-z0-9_-]{3,100}$/D',$id))return false;
 if((string)($payment['subscription']??'')!==(string)$order['asaas_subscription_id'])return false;
 if(!in_array((string)($payment['status']??''),['PENDING','OVERDUE'],true))return false;
 if(abs((float)($payment['value']??0)-(float)$order['recurring_amount'])>.01)return false;
 $updated=bemora_asaas_request('PUT','/payments/'.rawurlencode($id),['value'=>(float)$order['amount']]);
 if(abs((float)($updated['value']??-1)-(float)$order['amount'])>.01)return false;
 $pdo->prepare("UPDATE subscription_orders SET asaas_payment_id=?,first_payment_adjusted=1 WHERE id=? AND first_payment_adjusted=0")->execute([$id,$orderId]);
 return true;
}

/** Complimentary subscriptions can be registered now; billing starts at the end of the granted months. */
function bemora_fulfill_complimentary_order(PDO $pdo,int $orderId):void {
 $secret=(string)config('asaas','invite_secret','');
 $admin=(int)config('asaas','system_admin_user_id',0);
 if(strlen($secret)<32||$admin<1)throw new RuntimeException('Configuração de convite incompleta.');
 $q=$pdo->prepare("SELECT * FROM subscription_orders WHERE id=? FOR UPDATE");
 $pdo->beginTransaction();
 try {
  $q->execute([$orderId]);$order=$q->fetch(PDO::FETCH_ASSOC);
  if(!$order||$order['coupon_kind']!=='free_months'||$order['status']!=='complimentary')throw new RuntimeException('Pedido de cortesia inválido.');
  if($order['invite_id']){$pdo->commit();return;}
  $token=hash_hmac('sha256','bemora-paid-order-'.$orderId,$secret);
  $inv=$pdo->prepare("INSERT INTO family_invitations(token_hash,max_members,expires_at,created_by) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 72 HOUR),?)");
  $inv->execute([hash('sha256',$token),(int)$order['max_members'],$admin]);
  $pdo->prepare("UPDATE subscription_orders SET invite_id=?,status='paid',paid_at=NULL WHERE id=?")->execute([(int)$pdo->lastInsertId(),$orderId]);
  $pdo->commit();
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

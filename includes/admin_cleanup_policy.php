<?php
declare(strict_types=1);
/** Never purge a payment-carrying or still-active Asaas subscription order. */
function bemora_cleanup_order_deletable(array $order):bool {
 if(!empty($order['paid_at'])||(int)($order['admin_subscription_had_settled_payments']??0)===1)return false;
 if(in_array((string)($order['status']??''),['paid','completed','payment_attention'],true))return false;
 if(!empty($order['asaas_subscription_id'])&&empty($order['admin_subscription_removed_at']))return false;
 return true;
}

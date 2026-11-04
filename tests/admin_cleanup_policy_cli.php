<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/admin_test_cleanup.php';
$base=[
 'paid_at'=>null,'status'=>'awaiting_payment','asaas_subscription_id'=>null,
 'admin_subscription_removed_at'=>null,'admin_subscription_had_settled_payments'=>0,
];
$assert=static function(bool $value,string $message):void {
 if(!$value)throw new RuntimeException('Admin cleanup safety test failed: '.$message);
};
$assert(bemora_cleanup_order_deletable($base),'unpaid local order can be removed');
$withRemote=$base;$withRemote['asaas_subscription_id']='sub_123456';
$assert(!bemora_cleanup_order_deletable($withRemote),'remote subscription requires prior Asaas deletion');
$withRemote['admin_subscription_removed_at']='2026-11-04 20:00:00';
$assert(bemora_cleanup_order_deletable($withRemote),'unpaid remote subscription removed');
foreach(['paid','completed','payment_attention'] as $status){
 $item=$base;$item['status']=$status;
 $assert(!bemora_cleanup_order_deletable($item),'protected order status '.$status);
}
$item=$base;$item['paid_at']='2026-11-04 20:00:00';
$assert(!bemora_cleanup_order_deletable($item),'paid_at prohibits removal');
$item=$base;$item['admin_subscription_had_settled_payments']=1;
$assert(!bemora_cleanup_order_deletable($item),'settled remote payment prohibits removal');
echo "OK: admin cleanup protects paid orders and requires verified removal of Asaas subscriptions.\n";

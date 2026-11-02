<?php
declare(strict_types=1);
return ['description'=>'Estado de benefícios temporários de cupons em pedidos Asaas',
'up'=>static function(PDO $pdo):void {
 $columns=$pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='subscription_orders'")->fetchAll(PDO::FETCH_COLUMN);
 foreach(['recurring_amount'=>"DECIMAL(12,2) NULL",'coupon_free_until'=>"DATE NULL",'first_payment_adjusted'=>"TINYINT(1) NOT NULL DEFAULT 0"] as $name=>$type){
  if(!in_array($name,$columns,true))$pdo->exec("ALTER TABLE subscription_orders ADD COLUMN `$name` $type");
 }
}];

<?php
declare(strict_types=1);
function bemora_coupons_ready(PDO $pdo):bool {
 return (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='subscription_coupons'")->fetchColumn()===1;
}
function bemora_coupon_types():array {
 return ['free_months'=>'Meses grátis','first_payment_discount'=>'Desconto na primeira cobrança','annual_discount'=>'Desconto no plano anual'];
}
function bemora_coupon_is_eligible(array $coupon,array $plan,?DateTimeImmutable $now=null):bool {
 $now??=new DateTimeImmutable('now');
 if(!(int)$coupon['enabled'])return false;
 if($coupon['valid_from'] && $now<new DateTimeImmutable($coupon['valid_from']))return false;
 if($coupon['valid_until'] && $now>new DateTimeImmutable($coupon['valid_until']))return false;
 if($coupon['max_redemptions']!==null && (int)$coupon['redeemed_count'] >= (int)$coupon['max_redemptions'])return false;
 if($coupon['plan_id']!==null && (int)$coupon['plan_id']!==(int)$plan['id'])return false;
 if($coupon['cycle_scope']!=='all' && $coupon['cycle_scope']!==$plan['billing_cycle'])return false;
 if($coupon['kind']==='annual_discount' && $plan['billing_cycle']!=='annual')return false;
 return true;
}

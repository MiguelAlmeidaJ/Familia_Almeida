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

function bemora_coupon_find(PDO $pdo,string $code):?array {
 $code=strtoupper(trim($code));
 if(!preg_match('/^[A-Z0-9][A-Z0-9_-]{2,31}$/D',$code))return null;
 if(!bemora_coupons_ready($pdo))return null;
 $q=$pdo->prepare('SELECT * FROM subscription_coupons WHERE code=? LIMIT 1');
 $q->execute([$code]);return $q->fetch(PDO::FETCH_ASSOC)?:null;
}
function bemora_coupon_quote(array $coupon,array $plan,float $base):array {
 if(!bemora_coupon_is_eligible($coupon,$plan))throw new RuntimeException('Cupom indisponível ou não aplicável a este plano.');
 $kind=(string)$coupon['kind'];
 if($kind==='free_months')return ['kind'=>$kind,'amount'=>$base,'discount'=>0.0,'label'=>(int)$coupon['free_months'].' meses gratuitos','supported'=>false];
 $value=(float)$coupon['discount_value'];
 $discount=$coupon['discount_type']==='percent'?round($base*$value/100,2):round($value,2);
 if($discount<0||$discount>=$base)throw new RuntimeException('O desconto ultrapassa o valor permitido da assinatura.');
 return ['kind'=>$kind,'amount'=>round($base-$discount,2),'discount'=>$discount,'label'=>'Economia de '.bemora_coupon_money($discount),'supported'=>$kind==='annual_discount'];
}
function bemora_coupon_money(float $amount):string{return 'R$ '.number_format($amount,2,',','.');}

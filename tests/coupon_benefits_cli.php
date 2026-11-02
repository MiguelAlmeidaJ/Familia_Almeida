<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/coupons.php';
$plan=['id'=>1,'billing_cycle'=>'monthly'];
$base=18.90;
$sample=['enabled'=>1,'valid_from'=>null,'valid_until'=>null,'max_redemptions'=>null,'redeemed_count'=>0,'plan_id'=>null,'cycle_scope'=>'all','kind'=>'first_payment_discount','discount_type'=>'percent','discount_value'=>20,'free_months'=>null];
$q=bemora_coupon_quote($sample,$plan,$base);
if(!$q['supported']||abs($q['amount']-15.12)>.001||abs($q['recurring_amount']-18.9)>.001)throw new RuntimeException('First payment discount calculation failed');
$sample['kind']='free_months';$sample['free_months']=2;
$q=bemora_coupon_quote($sample,$plan,$base);
if(!$q['supported']||$q['amount']!==0.0||$q['recurring_amount']!==$base)throw new RuntimeException('Free months calculation failed');
$sample['kind']='annual_discount';$sample['discount_value']=20;$plan['billing_cycle']='annual';
$q=bemora_coupon_quote($sample,$plan,226.8);
if(!$q['supported']||abs($q['amount']-181.44)>.001)throw new RuntimeException('Annual discount calculation failed');
echo "[PASS] Three coupon modalities validated\n";

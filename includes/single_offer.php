<?php
declare(strict_types=1);
require_once __DIR__.'/plans.php';
function bemora_single_offer_ready(PDO $pdo):bool {
 static $ready=null;
 if($ready!==null)return $ready;
 $q=$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='bemora_single_offer'");
 return $ready=(int)$q->fetchColumn()===1;
}
function bemora_single_offer(PDO $pdo):?array {
 if(!bemora_single_offer_ready($pdo))return null;
 $o=$pdo->query('SELECT * FROM bemora_single_offer WHERE id=1')->fetch(PDO::FETCH_ASSOC);
 return $o?:null;
}
function bemora_single_offer_enabled(PDO $pdo):bool {
 $o=bemora_single_offer($pdo);
 return $o&&(int)$o['enabled']===1;
}
function bemora_single_offer_plan_id(array $o,string $cycle):int {
 $key=['monthly'=>'monthly_plan_id','semiannual'=>'semiannual_plan_id','annual'=>'annual_plan_id'][$cycle]??null;
 return $key?(int)($o[$key]??0):0;
}
function bemora_single_offer_cycle_from_plan(array $o,int $planId):?string {
 foreach(['monthly','semiannual','annual'] as $cycle)if(bemora_single_offer_plan_id($o,$cycle)===$planId)return $cycle;
 return null;
}
/**
 * Calculate all charges from server-owned configuration in integer cents.
 * Seat count INCLUDES the subscriber; add-ons are recurring with the same cycle.
 */
function bemora_single_quote(array $o,string $cycle,int $members,bool $shopping):array {
 if(!in_array($cycle,['monthly','semiannual','annual'],true))throw new RuntimeException('Periodicidade inválida.');
 if($members<1||$members>(int)$o['max_members'])throw new RuntimeException('Número de integrantes inválido.');
 $money=static fn(mixed $n):int=>(int)round((float)$n*100,0,PHP_ROUND_HALF_UP);
 $base=$money($o['base_monthly']);
 $extra=$money($o['extra_member_monthly']);
 $shop=$money($o['shopping_monthly']);
 if($base<100||$extra<0||$shop<0)throw new RuntimeException('Configure os preços antes de liberar assinaturas.');
 $months=['monthly'=>1,'semiannual'=>6,'annual'=>12][$cycle];
 $discount=(float)($cycle==='semiannual'?$o['semi_discount']:($cycle==='annual'?$o['annual_discount']:0));
 if($discount<0||$discount>80)throw new RuntimeException('Desconto inválido.');
 $monthly=$base+$extra*($members-1)+($shopping?$shop:0);
 $total=(int)round($monthly*$months*(100-$discount)/100,0,PHP_ROUND_HALF_UP);
 if($total<100||$total>9999999900)throw new RuntimeException('Preço inválido para o período.');
 return [
  'total'=>$total/100,'monthly'=>$monthly/100,'base_monthly'=>$base/100,
  'extra_monthly'=>$extra/100,'shopping_monthly'=>$shop/100,'members'=>$members,
  'shopping'=>$shopping,'cycle'=>$cycle,'months'=>$months,
  'discount'=>$discount,'plan_id'=>bemora_single_offer_plan_id($o,$cycle)
 ];
}

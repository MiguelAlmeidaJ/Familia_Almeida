<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/market_lists.php';
function check_market(bool $condition,string $label):void{
 if(!$condition)throw new RuntimeException($label);
 echo "[PASS] ".$label."\n";
}
check_market(bemora_market_name("  Café   500 g ")==='Café 500 g','normalize product whitespace');
check_market(bemora_market_quantity('1,500')==='1.500','accept Brazilian decimal quantity');
check_market(bemora_market_price('1.234,56')==='1234.56','accept Brazilian currency');
check_market(bemora_market_price('24.90')==='24.90','accept regular numeric price');
check_market(bemora_market_price('')===null,'allow price pending before purchase');
check_market(bemora_market_normalize('Café 500g')===bemora_market_normalize('CAFÉ 500G'),'normalized product duplicate detection');
check_market(bemora_market_unit('kg')==='kg','supported quantity units');
$invalid=0;
foreach([static fn()=>bemora_market_quantity('0'),static fn()=>bemora_market_price('-9'),static fn()=>bemora_market_unit('other'),static fn()=>bemora_market_name(' ')] as $test){
 try{$test();}catch(InvalidArgumentException $error){$invalid++;}
}
check_market($invalid===4,'reject invalid quantities, prices, units, and names');

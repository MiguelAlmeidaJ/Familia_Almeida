<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/single_offer.php';
$offer=[
 'base_monthly'=>'15.00','extra_member_monthly'=>'5.00',
 'shopping_monthly'=>'10.00','max_members'=>6,
 'semi_discount'=>'10.00','annual_discount'=>'20.00',
 'monthly_plan_id'=>101,'semiannual_plan_id'=>102,'annual_plan_id'=>103
];
$assert=static function(bool $ok,string $name):void{if(!$ok)throw new RuntimeException('Teste falhou: '.$name);};
$first=bemora_single_quote($offer,'monthly',1,false);
$assert($first['total']===15.0,'assinatura individual');
$combo=bemora_single_quote($offer,'monthly',3,true);
$assert($combo['total']===35.0,'integrantes e compras');
$assert(bemora_single_quote($offer,'semiannual',3,true)['total']===189.0,'semestral com desconto');
$assert(bemora_single_quote($offer,'annual',3,true)['total']===336.0,'anual com desconto');
$assert(bemora_single_quote($offer,'annual',1,false)['plan_id']===103,'plano tecnico anual');
try{bemora_single_quote($offer,'monthly',7,false);throw new RuntimeException('Excesso de pessoas aceito');}
catch(RuntimeException $e){$assert($e->getMessage()==='Número de integrantes inválido.','limite de pessoas');}
try{bemora_single_quote($offer,'weekly',1,false);throw new RuntimeException('Ciclo inválido aceito');}
catch(RuntimeException $e){$assert($e->getMessage()==='Periodicidade inválida.','ciclo permitido');}
echo "OK: preço base, adicionais, ciclos, descontos e limites.\n";

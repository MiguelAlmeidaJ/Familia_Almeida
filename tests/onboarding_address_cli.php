<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/onboarding_address.php';
function assertAddress(bool $value,string $label):void {if(!$value)throw new RuntimeException('Falha: '.$label);}
$valid=bemora_validate_onboarding_address(['postal_code'=>'20040-020','street'=>'Rua do Ouvidor','state'=>'RJ','city'=>'Rio de Janeiro']);
assertAddress($valid['postal_code']==='20040020','CEP normalizado');
assertAddress($valid['state_code']==='RJ','UF permitida');
assertAddress($valid['city']==='Rio de Janeiro','cidade selecionada');
$manual=bemora_validate_onboarding_address(['postal_code'=>'01001000','street'=>'Praça da Sé','state'=>'SP','city'=>'','city_manual'=>'São Paulo']);
assertAddress($manual['city']==='São Paulo','cidade manual em indisponibilidade do IBGE');
foreach([
 ['postal_code'=>'1234','street'=>'Avenida Brasil','state'=>'RJ','city'=>'Rio de Janeiro'],
 ['postal_code'=>'20040020','street'=>'Avenida Brasil','state'=>'XX','city'=>'Rio de Janeiro'],
 ['postal_code'=>'20040020','street'=>'A','state'=>'RJ','city'=>'Rio de Janeiro'],
 ['postal_code'=>'20040020','street'=>'Avenida Brasil','state'=>'RJ','city'=>''],
] as $bad){
 try{bemora_validate_onboarding_address($bad);throw new LogicException('Dados inválidos foram aceitos');}
 catch(RuntimeException $expected){}
}
assertAddress(count(bemora_brazil_states())===27,'27 unidades federativas');
echo "OK: validação de CEP, endereço, UF e cidade.\n";

<?php
declare(strict_types=1);

/**
 * Address belongs to the tenant/workspace, not to any individual account.
 * The CEP lookup is a convenience; do not trust client-side autocomplete.
 */
function bemora_brazil_states():array {
 return [
  'AC'=>'Acre','AL'=>'Alagoas','AP'=>'Amapá','AM'=>'Amazonas',
  'BA'=>'Bahia','CE'=>'Ceará','DF'=>'Distrito Federal','ES'=>'Espírito Santo',
  'GO'=>'Goiás','MA'=>'Maranhão','MT'=>'Mato Grosso','MS'=>'Mato Grosso do Sul',
  'MG'=>'Minas Gerais','PA'=>'Pará','PB'=>'Paraíba','PR'=>'Paraná',
  'PE'=>'Pernambuco','PI'=>'Piauí','RJ'=>'Rio de Janeiro','RN'=>'Rio Grande do Norte',
  'RS'=>'Rio Grande do Sul','RO'=>'Rondônia','RR'=>'Roraima',
  'SC'=>'Santa Catarina','SP'=>'São Paulo','SE'=>'Sergipe','TO'=>'Tocantins',
 ];
}
function bemora_onboarding_address_ready(PDO $pdo):bool {
 $q=$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='tenant_addresses'");
 return (int)$q->fetchColumn()===1;
}
function bemora_validate_onboarding_address(array $input):array {
 $cep=preg_replace('/\\D/','',(string)($input['postal_code']??''))??'';
 $street=trim(preg_replace('/\\s+/u',' ',(string)($input['street']??''))??'');
 $state=strtoupper(trim((string)($input['state']??'')));
 $city=trim(preg_replace('/\\s+/u',' ',(string)($input['city']??''))??'');
 if(!preg_match('/^\\d{8}$/D',$cep))throw new RuntimeException('Informe um CEP brasileiro válido com 8 dígitos.');
 if(mb_strlen($street)<3||mb_strlen($street)>180)throw new RuntimeException('Informe um endereço entre 3 e 180 caracteres.');
 if(!isset(bemora_brazil_states()[$state]))throw new RuntimeException('Selecione um estado brasileiro válido.');
 if(mb_strlen($city)<2||mb_strlen($city)>120 || !preg_match('/^[\\p{L}\\p{M}][\\p{L}\\p{M}\\s.\\-\x27]*$/u',$city))throw new RuntimeException('Selecione uma cidade válida.');
 return ['postal_code'=>$cep,'street'=>$street,'state_code'=>$state,'city'=>$city];
}
function bemora_store_onboarding_address(PDO $pdo,int $tenantId,array $address):void {
 $q=$pdo->prepare('INSERT INTO tenant_addresses (tenant_id,postal_code,street,state_code,city) VALUES (?,?,?,?,?)');
 $q->execute([$tenantId,$address['postal_code'],$address['street'],$address['state_code'],$address['city']]);
}

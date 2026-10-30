<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/plans.php';
function bemora_asaas_enabled():bool {return (string)config('asaas','enabled','0')==='1';}
function bemora_asaas_mode():string {return (string)config('asaas','environment','sandbox')==='production'?'production':'sandbox';}
function bemora_asaas_request(string $method,string $path,?array $data=null):array {
 $key=trim((string)config('asaas','api_key',''));
 if(!bemora_asaas_enabled() || strlen($key)<20)throw new RuntimeException('Integração Asaas indisponível. Contate o suporte.');
 if(!function_exists('curl_init'))throw new RuntimeException('Extensão cURL não disponível no PHP.');
 if(!preg_match('~^/[a-zA-Z0-9/_?=&.%-]+$~D',$path))throw new RuntimeException('Endpoint de pagamento inválido.');
 $base=bemora_asaas_mode()==='production'?'https://api.asaas.com/v3':'https://api-sandbox.asaas.com/v3';
 $ch=curl_init($base.$path);
 $headers=['access_token: '.$key,'accept: application/json','User-Agent: Bemora/1.0','Content-Type: application/json'];
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>25,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true]);
 if($data!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
 $output=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$error=curl_error($ch);curl_close($ch);
 if($output===false){error_log('Bemora Asaas: network failure '.$error);throw new RuntimeException('Não foi possível conectar ao meio de pagamento.');}
 $decoded=json_decode((string)$output,true);
 if($code<200||$code>=300){error_log('Bemora Asaas: HTTP '.$code);throw new RuntimeException('O Asaas não aceitou a solicitação. Verifique os dados e tente novamente.');}
 if(!is_array($decoded))throw new RuntimeException('Resposta inválida da plataforma de pagamento.');
 return $decoded;
}
function bemora_asaas_invoice_url(array $payment):?string {
 $url=(string)($payment['invoiceUrl']??'');
 if(!filter_var($url,FILTER_VALIDATE_URL))return null;
 $host=strtolower((string)parse_url($url,PHP_URL_HOST));
 if($host!=='asaas.com'&&!str_ends_with($host,'.asaas.com'))return null;
 return $url;
}
function bemora_asaas_cycle(string $cycle):string {
 return ['monthly'=>'MONTHLY','semiannual'=>'SEMIANNUALLY','annual'=>'YEARLY'][$cycle]??throw new RuntimeException('Periodicidade inválida.');
}

<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/plans.php';
function bemora_asaas_enabled():bool {return in_array(config('asaas','enabled',false),[true,1,'1','true','on'],true);}
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
 if($code<200||$code>=300){
  // A short reference lets support find the exact error in the private PHP log.
  // Do not log request bodies, access tokens, customer IDs, documents or URLs.
  $reference=strtoupper(bin2hex(random_bytes(4)));
  $operation=preg_match('~^/([a-zA-Z]+)~',$path,$route)?strtolower($route[1]):'unknown';
  $details=[];
  if(is_array($decoded)){
   foreach(($decoded['errors']??[]) as $item){
    if(!is_array($item))continue;
    $errorCode=(string)($item['code']??'');
    if(preg_match('/^[A-Za-z0-9_-]{1,50}$/D',$errorCode))$details[]='code='.$errorCode;
    $description=trim((string)($item['description']??''));
    if($description!==''){
     // Server-side diagnostics only. Remove common personal data patterns.
     $description=preg_replace('/[A-Z0-9._%+\\-]+@[A-Z0-9.\\-]+\\.[A-Z]{2,}/i','[email]',$description);
     $description=preg_replace('/\\b\\d{2,3}[.\\- ]?\\d{3}[.\\- ]?\\d{3}[/\\-. ]?\\d{1,4}\\b/','[documento]',$description);
     $description=preg_replace('/\\b\\d{10,14}\\b/','[numero]',$description);
     $description=preg_replace('/[\\r\\n\\t]+/',' ',$description);
     $details[]='description='.mb_substr($description,0,240);
    }
    if(count($details)>=6)break;
   }
  }
  error_log('Bemora Asaas | ref='.$reference.' | operation='.$operation.' | HTTP '.$code.' | '.implode(' | ',$details));
  $status=$code===401?'Falha de autenticação da integração':($code===403?'Acesso à API não autorizado':($code===429?'Limite de requisições atingido':($code>=500?'Serviço de pagamentos temporariamente indisponível':'Solicitação não aprovada pela plataforma')));
  throw new RuntimeException($status.' (HTTP '.$code.', referência '.$reference.').',$code);
 }
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

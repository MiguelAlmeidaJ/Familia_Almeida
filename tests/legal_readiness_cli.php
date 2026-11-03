<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$GLOBALS['test_legal']=[
 'approved'=>'0','approved_terms_version'=>'','approved_privacy_version'=>'',
 'company_name'=>'','company_tax_id'=>'','company_address'=>'','support_email'=>'','privacy_email'=>''
];
function config(string $section,?string $key=null,mixed $default=null):mixed {
 if($section!=='legal')return $default;
 return $key===null?$GLOBALS['test_legal']:($GLOBALS['test_legal'][$key]??$default);
}
require __DIR__.'/../includes/legal_policy.php';
function check_legal(bool $ok,string $message):void {
 if(!$ok)throw new RuntimeException($message);
 echo "[PASS] ".$message."\n";
}
check_legal(!bemora_legal_approved(),'draft must remain unpublished');
$GLOBALS['test_legal']=[
 'approved'=>'1','approved_terms_version'=>BEMORA_TERMS_VERSION,'approved_privacy_version'=>BEMORA_PRIVACY_VERSION,
 'company_name'=>'Fornecedor Exemplo MEI','company_tax_id'=>'11.222.333/0001-81',
 'company_address'=>'Rua Exemplo, 100, Centro, Rio de Janeiro, RJ, Brasil',
 'support_email'=>'suporte@example.test','privacy_email'=>'privacidade@example.test'
];
check_legal(bemora_legal_approved(),'explicit exact-version review and complete supplier identity permit publication');
check_legal(bemora_legal_versions()['terms']===BEMORA_TERMS_VERSION,'terms version is stable');
$GLOBALS['test_legal']['approved_terms_version']='older-contract-version';
check_legal(!bemora_legal_approved(),'any contract text update invalidates prior approval');
$GLOBALS['test_legal']['approved_terms_version']=BEMORA_TERMS_VERSION;
$GLOBALS['test_legal']['company_address']='';
check_legal(!bemora_legal_approved(),'supplier address mandatory before online sale');
$GLOBALS['test_legal']['company_address']='Rua Exemplo, 100, Centro, Rio de Janeiro, RJ';
$GLOBALS['test_legal']['privacy_email']='';
check_legal(!bemora_legal_approved(),'a working privacy channel is mandatory');
$GLOBALS['test_legal']['privacy_email']='privacidade@example.test';
$GLOBALS['test_legal']['company_tax_id']='123';
check_legal(!bemora_legal_approved(),'invalid length CNPJ cannot be published');

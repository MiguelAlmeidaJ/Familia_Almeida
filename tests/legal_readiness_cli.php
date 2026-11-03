<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$GLOBALS['test_legal']=[
 'approved'=>'0','company_name'=>'','company_tax_id'=>'','privacy_email'=>''
];
function config(string $section,?string $key=null,mixed $default=null):mixed {
 if($section!=='legal')return $default;
 return $key===null?$GLOBALS['test_legal']:($GLOBALS['test_legal'][$key]??$default);
}
require __DIR__.'/../includes/legal.php';
function check_legal(bool $ok,string $message):void {
 if(!$ok)throw new RuntimeException($message);
 echo "[PASS] ".$message."\n";
}
check_legal(!bemora_legal_approved(),'unreviewed legal copy cannot be considered published');
$GLOBALS['test_legal']=['approved'=>'1','company_name'=>'Fornecedor de Teste','company_tax_id'=>'00.000.000/0001-00','privacy_email'=>'privacidade@example.test'];
check_legal(bemora_legal_approved(),'complete approved settings enable contractual versions');
check_legal((bemora_legal_versions()['terms']??'')===BEMORA_TERMS_VERSION,'terms acceptance has a stable version identifier');
$GLOBALS['test_legal']['privacy_email']='';
check_legal(!bemora_legal_approved(),'publishing requires valid data protection contact');

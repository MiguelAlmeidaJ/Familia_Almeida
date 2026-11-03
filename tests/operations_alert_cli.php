<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/../includes/ops_alert_templates.php';
function check_alert(bool $valid,string $description):void {
    if(!$valid)throw new RuntimeException($description);
    echo "[PASS] ".$description."\n";
}
$stamp='2026-11-03 15:00:00 UTC';
$failure=bemora_ops_test_email('failure',$stamp);
$recovery=bemora_ops_test_email('recovery',$stamp);
check_alert(str_starts_with($failure['subject'],'[TESTE]'),'failure subject explicitly marked TEST');
check_alert(str_contains($failure['body'],'SIMULAÇÃO CONTROLADA'),'failure email explicitly identifies simulation');
check_alert(str_contains($failure['body'],'Nenhuma rotina ou conta foi alterada.'),'failure does not misrepresent real production state');
check_alert(str_starts_with($recovery['subject'],'[TESTE]'),'recovery subject explicitly marked TEST');
check_alert(str_contains($recovery['body'],'SIMULAÇÃO CONTROLADA'),'recovery email explicitly identifies simulation');
check_alert(str_contains($recovery['body'],$stamp),'simulation email includes UTC audit time');
$rejected=false;
try{bemora_ops_test_email('billing',$stamp);}catch(InvalidArgumentException $e){$rejected=true;}
check_alert($rejected,'unknown scenario is rejected without triggering an email');

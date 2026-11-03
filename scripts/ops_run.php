<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit(1);}
umask(0077);
require_once __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/operations.php';

function bemora_ops_alert(string $task,string $message):void {
    $to=(string)config('operations','alert_email','');
    if(!filter_var($to,FILTER_VALIDATE_EMAIL)){error_log('Bemora operational alert: recipient not configured.');return;}
    require_once __DIR__.'/../includes/mailer.php';
    require_once __DIR__.'/../includes/email_templates.php';
    bemora_send_email($to,'Bemora · Alerta operacional: '.$task,$message,
        bemora_email_layout('ALERTA OPERACIONAL','Atenção à rotina '.$task,$message));
}
function bemora_ops_execute(string $task):int {
    $tasks=['backup'=>'ops_backup.php','billing'=>'reconcile_billing_access.php','plans'=>'process_scheduled_plan_changes.php'];
    if(!isset($tasks[$task]))throw new RuntimeException('Rotina desconhecida.');
    $dir=bemora_ops_dir();
    $lock=fopen($dir.'/lock-'.$task.'.lck','c');
    if(!$lock)throw new RuntimeException('Falha ao iniciar exclusão mútua.');
    chmod($dir.'/lock-'.$task.'.lck',0600);
    if(!flock($lock,LOCK_EX|LOCK_NB)){fclose($lock);throw new RuntimeException('Rotina já em execução; bloqueio de execução concorrente.');}
    try{
        $before=bemora_ops_status($task)??[];
        $lastSuccess=$before['last_success']??null;
        $lastAlert=$before['last_alert_at']??null;
        $started=gmdate('c');
        bemora_ops_write_status($task,['task'=>$task,'last_started'=>$started,'last_success'=>$lastSuccess,'last_alert_at'=>$lastAlert,'state'=>'running']);
        $target=__DIR__.'/'.$tasks[$task];
        $logFile=$dir.'/run-'.$task.'.log';
        $command=[PHP_BINARY,$target];
        $pipes=[];
        $process=proc_open($command,[0=>['pipe','r'],1=>['file',$logFile,'w'],2=>['file',$logFile.'.err','w']],$pipes,dirname(__DIR__));
        if(!is_resource($process))throw new RuntimeException('Não foi possível iniciar a rotina.');
        fclose($pipes[0]);
        $code=proc_close($process);
        $exit=$code===0?'ok':'failed';
        $completed=gmdate('c');
        $status=['task'=>$task,'state'=>$exit,'last_started'=>$started,'last_finished'=>$completed,
          'last_success'=>$code===0?$completed:$lastSuccess,'last_alert_at'=>$lastAlert,'exit_code'=>$code];
        // Do not store raw subprocess output in the dashboard, as it may contain private data.
        if($exit==='failed'){
            $previousAlert=$lastAlert?strtotime((string)$lastAlert):0;
            if(!$previousAlert||time()-$previousAlert>=21600){
                try{
                    bemora_ops_alert($task,'A rotina '.$task.' terminou com falha em '.$completed.'. Consulte os logs protegidos na hospedagem e execute a conciliação após corrigir a causa.');
                    $status['last_alert_at']=$completed;
                }catch(Throwable $mailError){error_log('Bemora: could not deliver operations failure alert.');}
            }
        }elseif(($before['state']??'')==='failed'){
            try{bemora_ops_alert($task,'A rotina '.$task.' voltou a funcionar em '.$completed.'.');}
            catch(Throwable $mailError){error_log('Bemora: could not deliver recovery alert.');}
        }
        bemora_ops_write_status($task,$status);
        return $code===0?0:1;
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}
$task='';
foreach(array_slice($argv,1) as $arg)if(str_starts_with($arg,'--task='))$task=substr($arg,7);
try{exit(bemora_ops_execute($task));}
catch(Throwable $error){error_log('Bemora operations runner: '.$error->getMessage());fwrite(STDERR,"Rotina não concluída: confira o log protegido.\n");exit(1);}

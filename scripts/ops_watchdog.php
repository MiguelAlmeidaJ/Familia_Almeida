<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit(1);}
require_once __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/operations.php';
try{
    $dir=bemora_ops_dir();$failures=0;
    foreach(['backup','billing'] as $task){
        $status=bemora_ops_status($task)??[];
        $last=isset($status['last_success'])?strtotime((string)$status['last_success']):false;
        $stale=!$last||time()-$last>28*3600;
        if(!$stale)continue;
        $failures++;
        $lastAlert=isset($status['last_alert_at'])?strtotime((string)$status['last_alert_at']):0;
        if($lastAlert && time()-$lastAlert<6*3600)continue;
        $recipient=(string)config('operations','alert_email','');
        if(!filter_var($recipient,FILTER_VALIDATE_EMAIL)){
            error_log('Bemora watchdog: missing operations.alert_email for '.$task);
            continue;
        }
        try{
            require_once __DIR__.'/../includes/mailer.php';
            require_once __DIR__.'/../includes/email_templates.php';
            $message='A rotina de '.$task.' não registrou execução bem-sucedida nas últimas 28 horas. Confira o cron, a configuração privada e os logs da hospedagem antes de tentar novamente.';
            bemora_send_email($recipient,'Bemora · Rotina atrasada: '.$task,$message,
                bemora_email_layout('MONITORAMENTO','Rotina atrasada',$message));
            $status['last_alert_at']=gmdate('c');
            bemora_ops_write_status($task,$status);
        }catch(Throwable $e){error_log('Bemora watchdog failed to send alert for '.$task);}
    }
    echo $failures===0?"Rotinas essenciais dentro do prazo.\n":"Rotinas atrasadas: ".$failures."\n";
    exit($failures?1:0);
}catch(Throwable $e){error_log('Bemora watchdog: '.$e->getMessage());fwrite(STDERR,"Falha na verificação das rotinas.\n");exit(1);}

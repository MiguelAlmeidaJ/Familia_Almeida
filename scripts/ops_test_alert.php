<?php
declare(strict_types=1);

/**
 * Controlled operational e-mail simulation.
 *
 * Does not invoke backups, reconciliation, Asaas or the production watchdog.
 * Does not modify status-backup.json/status-billing.json or any business data.
 *
 * Usage:
 *   php scripts/ops_test_alert.php
 *   php scripts/ops_test_alert.php --send --scenario=failure
 *   php scripts/ops_test_alert.php --send --scenario=recovery
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
umask(0077);
require_once __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/operations.php';

function bemora_ops_test_email(string $scenario,string $stamp):array {
    $messages=[
        'failure'=>[
            'subject'=>'[TESTE] Bemora · Falha operacional simulada',
            'headline'=>'Teste de alerta de falha',
            'body'=>'SIMULAÇÃO CONTROLADA: uma rotina operacional fictícia apresentou falha. Este evento não corresponde a uma falha real de backup, conciliação financeira ou cobrança. Nenhuma rotina ou conta foi alterada.',
        ],
        'recovery'=>[
            'subject'=>'[TESTE] Bemora · Recuperação operacional simulada',
            'headline'=>'Teste de alerta de recuperação',
            'body'=>'SIMULAÇÃO CONTROLADA: a rotina fictícia voltou a funcionar. Este evento não corresponde à recuperação de uma falha real. Nenhuma rotina ou conta foi alterada.',
        ],
    ];
    if(!isset($messages[$scenario]))throw new InvalidArgumentException('Cenário inválido; use failure ou recovery.');
    $entry=$messages[$scenario];
    $entry['body'].=' Horário UTC do teste: '.$stamp.'. Não é necessário executar qualquer ação.';
    return $entry;
}

function bemora_ops_test_record(string $privateDir,array $record):void {
    $file=$privateDir.'/alert-test-last.json';
    if(is_link($file))throw new RuntimeException('Arquivo de auditoria inválido.');
    $temporary=tempnam($privateDir,'.alert-test-');
    if($temporary===false)throw new RuntimeException('Não foi possível preparar a auditoria do teste.');
    try {
        chmod($temporary,0600);
        $json=json_encode($record,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
        if(file_put_contents($temporary,$json)===false || !rename($temporary,$file))
            throw new RuntimeException('Falha ao registrar a auditoria do teste.');
    }finally{
        if(is_file($temporary))unlink($temporary);
    }
}

if(realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__){
    try{
        $args=array_slice($argv,1);
        $scenario='failure';
        $send=false;
        foreach($args as $arg){
            if($arg==='--send'){$send=true;continue;}
            if(str_starts_with($arg,'--scenario=')){$scenario=substr($arg,11);continue;}
            throw new InvalidArgumentException('Argumento inválido. Use --send --scenario=failure ou recovery.');
        }
        $stamp=gmdate('Y-m-d H:i:s').' UTC';
        $email=bemora_ops_test_email($scenario,$stamp);
        $privateDir=bemora_ops_dir();
        $recipient=trim((string)config('operations','alert_email',''));
        if(!filter_var($recipient,FILTER_VALIDATE_EMAIL))
            throw new RuntimeException('Configure operations.alert_email com um endereço válido.');
        if(!$send){
            echo "Pré-verificação aprovada: diretório privado, e-mail de alertas e cenário.\n";
            echo "Nenhum e-mail foi enviado. Para testar o SMTP execute com --send.\n";
            exit(0);
        }

        // Independent lock and audit, never the real backup/billing status files.
        $lock=fopen($privateDir.'/alert-test.lock','c');
        if(!$lock)throw new RuntimeException('Falha ao bloquear o teste.');
        chmod($privateDir.'/alert-test.lock',0600);
        if(!flock($lock,LOCK_EX|LOCK_NB)){
            fclose($lock);
            throw new RuntimeException('Outro teste de alerta está em andamento.');
        }
        try{
            $audit=$privateDir.'/alert-test-last.json';
            if(is_link($audit))throw new RuntimeException('Registro de auditoria inválido.');
            if(is_file($audit)){
                $prior=json_decode((string)file_get_contents($audit),true);
                $last=isset($prior['sent_at'])?strtotime((string)$prior['sent_at']):false;
                if($last!==false && $last>time()-60)
                    throw new RuntimeException('Teste enviado recentemente. Aguarde 60 segundos para evitar duplicidades.');
            }
            require_once __DIR__.'/../includes/mailer.php';
            require_once __DIR__.'/../includes/email_templates.php';
            $footer='Teste simulado de monitoramento: a execução real dos crons continua inalterada.';
            $html=bemora_email_layout('TESTE CONTROLADO',$email['headline'],$email['body'],'','',$footer);
            bemora_send_email($recipient,$email['subject'],$email['body'],$html);
            bemora_ops_test_record($privateDir,[
                'scenario'=>$scenario,
                'sent_at'=>gmdate('c'),
                'result'=>'smtp_accepted'
            ]);
            echo "SMTP confirmou o envio do alerta simulado (".$scenario.").\n";
            echo "Confira o recebimento na caixa de entrada e no spam do e-mail administrativo.\n";
            echo "Backups, cron, estados de monitoramento e cobranças não foram modificados.\n";
        }finally{
            flock($lock,LOCK_UN);
            fclose($lock);
        }
        exit(0);
    }catch(Throwable $error){
        error_log('Bemora operations alert test failed: '.$error->getMessage());
        fwrite(STDERR,"Teste de alerta falhou: ".$error->getMessage()."\n");
        exit(1);
    }
}

<?php
declare(strict_types=1);

/**
 * Sends transactional email through authenticated SMTP.
 * Configure credentials in the untracked production config.php.
 */
function bemora_send_email(string $recipient,string $subject,string $body):void {
    $autoload=dirname(__DIR__).'/vendor/autoload.php';
    if(!is_file($autoload))throw new RuntimeException('Dependências SMTP não instaladas (execute composer install).');
    require_once $autoload;
    $host=trim((string)config('mail','host',''));
    $port=(int)config('mail','port',587);
    $username=(string)config('mail','username','');
    $password=(string)config('mail','password','');
    $from=(string)config('mail','from','');
    $fromName=(string)config('mail','from_name','Bemora');
    $encryption=(string)config('mail','encryption','tls');
    if($host===''||$username===''||$password===''||!filter_var($from,FILTER_VALIDATE_EMAIL)
       ||!filter_var($recipient,FILTER_VALIDATE_EMAIL)||!in_array($encryption,['tls','ssl'],true)
       ||$port<1||$port>65535)throw new RuntimeException('Configuração SMTP incompleta ou inválida.');
    $mailer=new \PHPMailer\PHPMailer\PHPMailer(true);
    try{
        $mailer->isSMTP();
        $mailer->Host=$host;
        $mailer->Port=$port;
        $mailer->SMTPAuth=true;
        $mailer->Username=$username;
        $mailer->Password=$password;
        $mailer->SMTPSecure=$encryption==='ssl'
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->SMTPAutoTLS=true;
        $mailer->Timeout=12;
        $mailer->CharSet='UTF-8';
        $mailer->setFrom($from,$fromName);
        $mailer->addAddress($recipient);
        $mailer->Subject=$subject;
        $mailer->Body=$body;
        $mailer->isHTML(false);
        $mailer->send();
    }catch(\PHPMailer\PHPMailer\Exception $e){
        error_log('Bemora SMTP: envio não confirmado.');
        throw new RuntimeException('Falha no envio SMTP. Confira as configurações do servidor e utilize o link para copiar.');
    }
}

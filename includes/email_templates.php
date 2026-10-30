<?php
declare(strict_types=1);

/** Bemora email templates use table layouts and inline styles for Gmail/Outlook/mobile. */
function bemora_email_escape(string $value):string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function bemora_email_layout(string $eyebrow,string $headline,string $message,string $actionLabel='',string $actionUrl='',string $footerNote=''):string {
    $e='bemora_email_escape';
    $base=rtrim((string)config('app','base_url','https://bemora.com.br'),'/');
    if(!preg_match('~^https://[a-z0-9.-]+(?::[0-9]+)?$~i',$base))$base='https://bemora.com.br';
    $logoPath=__DIR__.'/../assets/brand/logo.png';
    $logo=is_file($logoPath)
        ? '<img src="'.$e($base.'/assets/brand/logo.png').'" width="190" alt="Bemora by Anoar" style="display:block;width:190px;max-width:100%;height:auto;border:0;margin:0 auto">'
        : '<span style="color:#064c40;font-size:32px;font-weight:800;letter-spacing:-1.7px">bemora</span><span style="display:block;color:#809289;font-size:11px">by Anoar</span>';
    $messageHtml=nl2br($e($message));
    $actionHtml='';
    if($actionLabel!=='' && filter_var($actionUrl,FILTER_VALIDATE_URL) && str_starts_with($actionUrl,'https://')){
        $safeUrl=$e($actionUrl);
        $actionHtml='<tr><td align="center" style="padding:24px 0 10px"><table role="presentation" cellspacing="0" cellpadding="0"><tr><td bgcolor="#075747" style="border-radius:10px;text-align:center"><a href="'.$safeUrl.'" style="display:inline-block;padding:16px 29px;color:#ffffff;font-size:15px;line-height:20px;font-weight:700;text-decoration:none;border-radius:10px">'.$e($actionLabel).'</a></td></tr></table></td></tr>'
        .'<tr><td style="padding:13px 0 0;color:#819389;font-size:12px;line-height:20px;text-align:center">Se o botão não funcionar, copie o endereço abaixo:</td></tr>'
        .'<tr><td style="padding:5px 0 0;font-size:11px;line-height:18px;text-align:center;word-break:break-all"><a href="'.$safeUrl.'" style="color:#127f67;text-decoration:underline">'.$safeUrl.'</a></td></tr>';
    }
    $extra=$footerNote!==''?'<p style="margin:19px 0 0;padding:13px 16px;background:#eff7f2;border-radius:9px;color:#607d70;font-size:13px;line-height:21px">'.$e($footerNote).'</p>':'';
    return '<!doctype html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$e($headline).'</title></head>'
    .'<body style="margin:0;padding:0;background:#f1f7f3;font-family:Arial,Helvetica,sans-serif;color:#123e35">'
    .'<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#f1f7f3"><tr><td align="center" style="padding:34px 12px">'
    .'<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="width:100%;max-width:590px;background:#ffffff;border:1px solid #e1eae3;border-radius:18px">'
    .'<tr><td align="center" style="padding:35px 28px 28px;border-bottom:1px solid #edf1ed">'.$logo.'</td></tr>'
    .'<tr><td style="padding:35px 35px 38px"><p style="margin:0 0 12px;color:#aa844c;font-size:11px;line-height:16px;letter-spacing:2px;text-transform:uppercase;font-weight:700">'.$e($eyebrow).'</p>'
    .'<h1 style="margin:0 0 19px;font-size:29px;line-height:36px;letter-spacing:-.7px;color:#075246;font-weight:700">'.$e($headline).'</h1>'
    .'<p style="margin:0;font-size:15px;line-height:25px;color:#557469">'.$messageHtml.'</p>'
    .$extra.'<table role="presentation" cellpadding="0" cellspacing="0" width="100%">'.$actionHtml.'</table>'
    .'</td></tr><tr><td style="padding:27px 35px;background:#f8fbf9;border-top:1px solid #e8eee9;border-radius:0 0 18px 18px">'
    .'<p style="margin:0 0 7px;color:#164f41;font-size:13px;font-weight:700">Bemora <span style="font-weight:400;color:#819489">by Anoar</span></p>'
    .'<p style="margin:0 0 10px;font-size:12px;line-height:20px;color:#819489">Organize suas finanças com clareza e tranquilidade.</p>'
    .'<p style="margin:0;font-size:12px;line-height:20px;color:#608477">Suporte comercial: <a href="https://wa.me/5532984075039" style="color:#0a7b60;text-decoration:none;font-weight:700">(32) 98407-5039</a></p>'
    .'</td></tr></table><p style="margin:17px 0 0;color:#98a99d;font-size:11px;line-height:17px;text-align:center">Esta é uma mensagem enviada pela plataforma Bemora.</p>'
    .'</td></tr></table></body></html>';
}

function bemora_invitation_email(string $url,int $maxMembers):string {
    return bemora_email_layout(
        'Convite especial',
        'Seu convite chegou.',
        'Você recebeu um convite para criar sua família no Bemora. Um espaço para acompanhar suas finanças com mais clareza e tranquilidade.',
        'Criar minha família',
        $url,
        'Convite individual e de uso único · válido por 72 horas · até '.$maxMembers.' '.($maxMembers===1?'integrante':'integrantes').'. Se você não esperava este convite, basta ignorar esta mensagem.'
    );
}

function bemora_smtp_test_email(string $timestamp):string {
    return bemora_email_layout('Comunicação Bemora','Seu e-mail está funcionando!','Este é um teste enviado pela área de Manutenção do Bemora. O servidor SMTP aceitou o envio e esta mensagem chegou à sua caixa de entrada.','', '', 'Teste realizado em '.$timestamp.'. Você não precisa responder a este e-mail.');
}

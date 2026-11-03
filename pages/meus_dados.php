<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/layout.php';
$user=require_family_access();
$pdo=db();$csrf=csrf_token();$flash=pull_flash();
$tenant=(int)$user['tenant_id'];$userId=(int)$user['id'];
$ready=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='privacy_requests'")->fetchColumn()===1;
$types=['access'=>'Acessar meus dados','correction'=>'Corrigir informações','deletion'=>'Solicitar exclusão','portability'=>'Solicitar portabilidade','information'=>'Entender uso e compartilhamento','objection'=>'Contestar um tratamento'];
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
 verify_csrf();
 try{
  if(!$ready)throw new RuntimeException('Solicitações indisponíveis. Execute a migration 040.');
  $type=(string)($_POST['request_type']??'');
  $description=trim((string)($_POST['description']??''));
  if(!isset($types[$type])||mb_strlen($description)<15||mb_strlen($description)>3000)
   throw new RuntimeException('Escolha uma categoria e descreva a solicitação entre 15 e 3000 caracteres.');
  $q=$pdo->prepare("SELECT COUNT(*) FROM privacy_requests WHERE user_id=? AND created_at>DATE_SUB(NOW(),INTERVAL 1 HOUR)");
  $q->execute([$userId]);if((int)$q->fetchColumn()>=3)throw new RuntimeException('Aguarde antes de enviar outra solicitação.');
  $q=$pdo->prepare("INSERT INTO privacy_requests(tenant_id,user_id,request_type,description) VALUES(?,?,?,?)");
  $q->execute([$tenant,$userId,$type,$description]);
  $requestId=(int)$pdo->lastInsertId();
  try{
   require_once __DIR__.'/../includes/mailer.php';
   require_once __DIR__.'/../includes/email_templates.php';
   $to=trim((string)config('legal','privacy_email',''));
   if(filter_var($to,FILTER_VALIDATE_EMAIL)){
    $text='Uma nova solicitação de privacidade #'.$requestId.' foi registrada no Bemora. Acesse o painel administrativo para consultar e responder. Não encaminhe dados financeiros por e-mail.';
    bemora_send_email($to,'Bemora · Solicitação de privacidade #'.$requestId,$text,bemora_email_layout('PRIVACIDADE','Nova solicitação recebida',$text));
   }
  }catch(Throwable $mailError){error_log('Bemora: privacy request notification could not be delivered.');}
  flash('success','Solicitação #'.$requestId.' registrada. Nossa equipe fará a verificação e acompanhará o pedido.');
 }catch(Throwable $ex){flash('error',$ex instanceof PDOException?'Não foi possível registrar o pedido.':$ex->getMessage());}
 redirect_to('/meus-dados');
}
$requests=[];
if($ready){
 $q=$pdo->prepare("SELECT id,request_type,status,created_at,response_note,resolved_at FROM privacy_requests WHERE tenant_id=? AND user_id=? ORDER BY id DESC LIMIT 30");
 $q->execute([$tenant,$userId]);$requests=$q->fetchAll(PDO::FETCH_ASSOC);
}
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Privacidade e meus dados · Bemora</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"><link rel="stylesheet" href="/assets/style.css"><link rel="stylesheet" href="/assets/bemora.css?v=2"><link rel="stylesheet" href="/assets/bemora-finance.css?v=2"><link rel="stylesheet" href="/assets/bemora-finish.css?v=3"><link rel="stylesheet" href="/assets/bemora-sidebar.css?v=5"><link rel="stylesheet" href="/assets/bemora-privacy-center.css?v=1"></head><body class="bemora-finance">
<div class="shell"><?php render_sidebar('meu-perfil',$csrf); ?><main><?php render_topbar($user); ?><div class="content privacy-center">
<?php page_header('SUA PRIVACIDADE','Privacidade e meus dados','Acompanhe solicitações relacionadas às suas informações pessoais.'); ?>
<?php if($flash): ?><div class="privacy-notice <?= ($flash['type']??'')==='success'?'ok':'error' ?>" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
<section class="privacy-panel"><div class="privacy-panel-top"><div><span class="privacy-eyebrow">DIREITOS DO TITULAR</span><h2>Como podemos ajudar?</h2><p>Solicite acesso, correção, informações ou análise de exclusão de dados. Alguns registros podem precisar ser conservados por obrigações legais, contratuais ou por envolverem outros integrantes da família.</p></div><i class="bi bi-shield-lock"></i></div>
<?php if(!$ready): ?><div class="privacy-notice error">Execute a migration 20261103_040_privacy_requests_and_contracts para habilitar solicitações.</div><?php else: ?>
<form method="post" action="/meus-dados"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
<label for="privacy-type">Tipo de solicitação<select class="form-select" id="privacy-type" name="request_type" required><option value="">Selecione</option><?php foreach($types as $key=>$name): ?><option value="<?= e($key) ?>"><?= e($name) ?></option><?php endforeach; ?></select></label>
<label for="privacy-description">Descreva seu pedido<textarea class="form-control" id="privacy-description" name="description" rows="4" minlength="15" maxlength="3000" placeholder="Descreva quais informações deseja consultar, corrigir ou excluir..." required></textarea></label>
<div class="privacy-action"><p>Por segurança, a equipe poderá solicitar verificação adicional de identidade antes de fornecer ou alterar dados.</p><button type="submit"><i class="bi bi-send"></i> Enviar solicitação</button></div>
</form><?php endif; ?>
</section>
<section class="privacy-panel"><span class="privacy-eyebrow">ACOMPANHAMENTO</span><h2>Minhas solicitações</h2>
<?php if(!$requests): ?><p class="privacy-empty">Você ainda não enviou solicitações.</p><?php else: ?><div class="privacy-items"><?php foreach($requests as $request): ?>
<div class="privacy-item"><div><strong>#<?= (int)$request['id'] ?> · <?= e($types[$request['request_type']]??$request['request_type']) ?></strong><small><?= e(date('d/m/Y H:i',strtotime($request['created_at']))) ?></small></div><span class="privacy-state"><?= e(['received'=>'Recebida','identity_verification'=>'Verificação de identidade','in_progress'=>'Em análise','completed'=>'Concluída','denied'=>'Encerrada com justificativa'][$request['status']]??$request['status']) ?></span><?php if(trim((string)($request['response_note']??''))!==''): ?><p><?= nl2br(e($request['response_note'])) ?></p><?php endif; ?></div>
<?php endforeach; ?></div><?php endif; ?>
<div class="privacy-links"><a href="/privacidade">Política de privacidade</a><a href="/termos">Termos de uso</a></div>
</section>
</div></main></div></body></html>
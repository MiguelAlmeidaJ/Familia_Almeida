<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/mailer.php';
require_once __DIR__.'/includes/email_templates.php';
$user=require_family_access();
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);exit;}
verify_csrf();
$pdo=db();$tenantId=(int)$user['tenant_id'];$userId=(int)$user['id'];
try{
 $ready=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='support_tickets'")->fetchColumn();
 if(!$ready)throw new RuntimeException('O suporte está em preparação. Tente novamente após a atualização.');
 $description=trim((string)($_POST['description']??''));
 if(mb_strlen($description)<15||mb_strlen($description)>3000)throw new RuntimeException('Descreva o problema com 15 a 3000 caracteres.');
 $method=(string)($_POST['contact_method']??'');
 if(!in_array($method,['whatsapp','email'],true))throw new RuntimeException('Selecione um método de contato.');
 $value=trim((string)($_POST['contact_value']??''));
 if($method==='whatsapp'){
  $digits=preg_replace('/\D+/', '',$value);
  if(!is_string($digits)||!preg_match('/^[1-9][0-9]{9,10}$/D',$digits))throw new RuntimeException('Informe um WhatsApp com DDD válido.');
  $value=$digits;
 }else{
  if(mb_strlen($value)>254||!filter_var($value,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Informe um e-mail válido.');
 }
 $q=$pdo->prepare("SELECT COUNT(*) FROM support_tickets WHERE tenant_id=? AND user_id=? AND created_at>DATE_SUB(NOW(),INTERVAL 1 HOUR)");
 $q->execute([$tenantId,$userId]);
 if((int)$q->fetchColumn()>=3)throw new RuntimeException('Limite de 3 relatos por hora. Aguarde antes de enviar outro.');
 $q=$pdo->prepare("INSERT INTO support_tickets(tenant_id,user_id,description,contact_method,contact_value) VALUES(?,?,?,?,?)");
 $q->execute([$tenantId,$userId,$description,$method,$value]);$ticketId=(int)$pdo->lastInsertId();
 $recipient=trim((string)config('support','email',config('mail','from','')));
 if(!filter_var($recipient,FILTER_VALIDATE_EMAIL)){
  $pdo->prepare("UPDATE support_tickets SET email_status='failed' WHERE id=?")->execute([$ticketId]);
  flash('success','Relato #'.$ticketId.' registrado. Configure o e-mail de suporte para receber avisos; o atendimento já aparece no painel administrativo.');
  redirect_to('/minha-assinatura');
 }
 $family=$pdo->prepare('SELECT name FROM tenants WHERE id=?');$family->execute([$tenantId]);$name=(string)$family->fetchColumn();
 $subject='Bemora · Novo relato de suporte #'.$ticketId;
 $body="Novo relato de suporte #".$ticketId."\nFamília: ".$name."\nUsuário: ".$user['name']."\nContato preferencial: ".$method." — ".$value."\n\nDescrição:\n".$description."\n\nAcesse o painel administrativo para responder.";
 $base=rtrim((string)config('app','base_url','https://bemora.anoar.com.br'),'/');
 $url=$base.'/admin/suporte';
 $html=bemora_email_layout('Novo relato de suporte','Uma família precisa de ajuda',$body,'Abrir painel de suporte',$url);
 try{
  bemora_send_email($recipient,$subject,$body,$html);
  $pdo->prepare("UPDATE support_tickets SET email_status='sent' WHERE id=?")->execute([$ticketId]);
  flash('success','Relato #'.$ticketId.' enviado. Nossa equipe entrará em contato pelo método escolhido.');
 }catch(Throwable $error){
  $pdo->prepare("UPDATE support_tickets SET email_status='failed' WHERE id=?")->execute([$ticketId]);
  error_log('Bemora support email delivery failed for ticket '.$ticketId);
  flash('success','Relato #'.$ticketId.' registrado. A entrega do e-mail está pendente, mas o pedido já aparece no painel de Suporte.');
 }
}catch(Throwable $error){
 flash('error',$error->getMessage());
}
redirect_to('/minha-assinatura');

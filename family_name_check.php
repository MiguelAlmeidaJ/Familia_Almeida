<?php
declare(strict_types=1);
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/family_names.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
$token=(string)($_GET['token']??'');
if(!preg_match('/^[a-f0-9]{64}$/D',$token)){http_response_code(403);echo json_encode(['error'=>'Convite inválido']);exit;}
$q=db()->prepare('SELECT id FROM family_invitations WHERE token_hash=? AND used_at IS NULL AND revoked_at IS NULL AND expires_at>NOW()');
$q->execute([hash('sha256',$token)]);
if(!$q->fetchColumn()){http_response_code(403);echo json_encode(['error'=>'Convite indisponível']);exit;}
try{
 [$suffix,$full,$key]=family_name_parts((string)($_GET['name']??''));
 $taken=family_name_taken(db(),$key);
 echo json_encode(['available'=>!$taken,'display_name'=>$full,'suggestions'=>$taken?family_name_suggestions(db(),$suffix):[]],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(InvalidArgumentException $e){http_response_code(422);echo json_encode(['error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}

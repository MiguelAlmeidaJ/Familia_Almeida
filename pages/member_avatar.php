<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';
$user=require_family_access();
$member=filter_var($_GET['id']??null,FILTER_VALIDATE_INT);
if(!$member || $member<1){http_response_code(404);exit;}
$stmt=db()->prepare('SELECT u.avatar_path FROM tenant_users tu JOIN users u ON u.id=tu.user_id WHERE tu.tenant_id=? AND tu.user_id=? LIMIT 1');
$stmt->execute([(int)$user['tenant_id'],$member]);
$filename=$stmt->fetchColumn();
if(!is_string($filename)||!preg_match('/^[a-f0-9]{48}\.jpg$/D',$filename)){http_response_code(404);exit;}
$path=dirname(__DIR__).'/storage/avatars/'.$filename;
if(!is_file($path)){http_response_code(404);exit;}
header('Content-Type: image/jpeg');
header('Content-Length: '.filesize($path));
header('Cache-Control: private, max-age=60');
header('X-Content-Type-Options: nosniff');
readfile($path);

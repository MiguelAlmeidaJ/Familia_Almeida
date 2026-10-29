<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';
$user=require_family_access();
$q=db()->prepare('SELECT avatar_path FROM users WHERE id=?');
$q->execute([(int)$user['id']]);
$name=(string)$q->fetchColumn();
if(!preg_match('/^[a-f0-9]{48}\.jpg$/',$name)){http_response_code(404);exit;}
$path=dirname(__DIR__).'/storage/avatars/'.$name;
if(!is_file($path)){http_response_code(404);exit;}
header('Content-Type: image/jpeg');
header('Content-Length: '.filesize($path));
header('Cache-Control: private, max-age=60');
header('X-Content-Type-Options: nosniff');
readfile($path);

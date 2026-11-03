<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';

/**
 * Login throttling independent from PHP sessions. Only REMOTE_ADDR is trusted;
 * forwarded IP headers can be spoofed unless explicitly handled at the edge.
 */
function bemora_login_keys(string $email,string $remoteIp):array {
 $email=mb_strtolower(trim($email));
 $ip=filter_var($remoteIp,FILTER_VALIDATE_IP)?$remoteIp:'unknown';
 return [
  ['account',hash('sha256',$email)],
  ['origin',hash('sha256',$ip)],
 ];
}
function bemora_login_check(PDO $pdo,string $email,string $remoteIp):bool {
 $q=$pdo->prepare("SELECT blocked_until FROM login_attempt_limits WHERE scope=? AND identity_hash=? LIMIT 1");
 foreach(bemora_login_keys($email,$remoteIp) as [$scope,$hash]){
  $q->execute([$scope,$hash]);
  $until=$q->fetchColumn();
  if($until!==false && $until!==null && strtotime((string)$until)>time())return false;
 }
 return true;
}
function bemora_login_failed(PDO $pdo,string $email,string $remoteIp):void {
 // Account: five attempts; origin: twenty attempts, in rolling fifteen-minute windows.
 $q=$pdo->prepare("INSERT INTO login_attempt_limits(scope,identity_hash,failures,window_started_at,last_failure_at,blocked_until)
 VALUES(?,?,1,NOW(),NOW(),NULL)
 ON DUPLICATE KEY UPDATE
 failures=IF(window_started_at<DATE_SUB(NOW(),INTERVAL 15 MINUTE),1,failures+1),
 blocked_until=IF(
  IF(window_started_at<DATE_SUB(NOW(),INTERVAL 15 MINUTE),1,failures)>=IF(scope='account',5,20),
  DATE_ADD(NOW(),INTERVAL 15 MINUTE),NULL),
 window_started_at=IF(window_started_at<DATE_SUB(NOW(),INTERVAL 15 MINUTE),NOW(),window_started_at),
 last_failure_at=NOW()");
 foreach(bemora_login_keys($email,$remoteIp) as [$scope,$hash])$q->execute([$scope,$hash]);
}
function bemora_login_succeeded(PDO $pdo,string $email,string $remoteIp):void {
 // Reset only the account key; retain origin-level pressure to resist password spraying.
 $q=$pdo->prepare("DELETE FROM login_attempt_limits WHERE scope='account' AND identity_hash=?");
 $q->execute([hash('sha256',mb_strtolower(trim($email)))]);
}

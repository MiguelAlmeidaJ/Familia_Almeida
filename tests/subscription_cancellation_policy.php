<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../includes/subscription_cancellation.php';
function assert_cancel(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);echo "[PASS] ".$message."\n";}
$today=new DateTimeImmutable('2026-11-03');
$sub=['nextDueDate'=>'2026-12-02'];
$paid=[['status'=>'CONFIRMED','dueDate'=>'2026-11-02']];
assert_cancel(bemora_cancellation_entitlement($sub,$paid,'monthly','',$today)==='2026-12-01','monthly period retained after confirmed payment');
assert_cancel(bemora_cancellation_entitlement($sub,[],'monthly','',$today)===null,'future due date without payment does not grant access');
assert_cancel(bemora_cancellation_entitlement($sub,[],'monthly','2026-11-20',$today)==='2026-11-19','free coupon retains only remaining courtesy interval');
assert_cancel(bemora_cancellation_entitlement(['nextDueDate'=>'2026-11-01'],$paid,'monthly','',$today)===null,'expired billing period grants no future access');
assert_cancel(bemora_cancellation_entitlement(['nextDueDate'=>'2027-02-02'],[['status'=>'RECEIVED','dueDate'=>'2026-02-02']],'annual','',$today)==='2027-02-01','annual paid period remains available');

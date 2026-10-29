<?php
declare(strict_types=1);
// Test harness only. Never expose via webserver in production.
if(PHP_SAPI!=='cli')exit(403);
require_once __DIR__.'/../includes/bootstrap.php';
$payload=json_decode((string)($argv[1]??''),true,512,JSON_THROW_ON_ERROR);
if(!is_array($payload))throw new RuntimeException('Missing test payload.');
$_SESSION['user_id']=(int)($payload['_user']??0);
$_SESSION['tenant_id']=(int)($payload['_tenant']??0);
$_SESSION['csrf_token']='ci-only-token';
unset($payload['_user'],$payload['_tenant']);
$_POST=$payload+['csrf_token'=>'ci-only-token','return_to'=>'/movimentacoes','month'=>'2026-10'];
$_SERVER['REQUEST_METHOD']='POST';
$_SERVER['SCRIPT_NAME']='/action.php';
$_SERVER['REQUEST_URI']='/acao';
require __DIR__.'/../action.php';

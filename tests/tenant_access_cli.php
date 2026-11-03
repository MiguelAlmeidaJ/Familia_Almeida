<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/finance.php';
$userId=(int)($argv[1]??0);$tenantId=(int)($argv[2]??0);
$released=($argv[3]??'0')==='1';
$path=(string)($argv[4]??'/');
putenv('BEMORA_MULTI_TENANT_RELEASE='.($released?'1':'0'));
$_SESSION['user_id']=$userId;
$_SESSION['tenant_id']=$tenantId;
$idleAge=(int)($argv[5]??0);
$sessionAge=(int)($argv[6]??0);
if($idleAge>0)$_SESSION['auth_last_seen']=time()-$idleAge;
if($sessionAge>0)$_SESSION['auth_issued_at']=time()-$sessionAge;
$_SERVER['REQUEST_METHOD']='GET';
$scripts=['/painel'=>'/pages/painel.php','/cartoes'=>'/pages/cartoes.php','/acao'=>'/action.php','/meu-perfil'=>'/pages/meu_perfil.php','/minha-assinatura'=>'/pages/minha_assinatura.php'];
$_SERVER['SCRIPT_NAME']=$scripts[$path]??'/pages/contas.php';
$_SERVER['REQUEST_URI']=$path;
$user=require_family_access();
$scope=finance_scope(db(),$user);
$data=dashboard_data(db(),'2026-10');
echo json_encode(['tenant'=>$scope['tenant_id'],'user'=>$scope['user_id'],'mode'=>$scope['mode'],'income'=>$data['totals']['income']],JSON_THROW_ON_ERROR);

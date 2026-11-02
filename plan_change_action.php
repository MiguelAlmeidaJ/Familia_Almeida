<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/plans.php';
$user=require_family_access();
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);exit;}
verify_csrf();
$pdo=db();$tid=(int)$user['tenant_id'];
try {
 $targetId=filter_var($_POST['plan_id']??null,FILTER_VALIDATE_INT);
 if(!$targetId||$targetId<1)throw new RuntimeException('Escolha um plano válido.');
 $pdo->beginTransaction();
 $role=$pdo->prepare("SELECT role FROM tenant_users WHERE tenant_id=? AND user_id=? FOR UPDATE");
 $role->execute([$tid,(int)$user['id']]);
 if($role->fetchColumn()!=='family_admin')throw new RuntimeException('Somente o administrador familiar pode solicitar a mudança.');
 $orderQuery=$pdo->prepare("SELECT * FROM subscription_orders WHERE activated_tenant_id=? AND status='completed' ORDER BY id DESC LIMIT 1 FOR UPDATE");
 $orderQuery->execute([$tid]);$order=$orderQuery->fetch(PDO::FETCH_ASSOC);
 if(!$order||!$order['asaas_subscription_id'])throw new RuntimeException('Assinatura atual indisponível.');
 $targetQuery=$pdo->prepare("SELECT * FROM subscription_plans WHERE id=? AND active=1");
 $targetQuery->execute([$targetId]);$target=$targetQuery->fetch(PDO::FETCH_ASSOC);
 if(!$target || (int)$target['id']===(int)$order['plan_id'])throw new RuntimeException('Selecione um plano diferente do atual.');
 $countQuery=$pdo->prepare('SELECT COUNT(*) FROM tenant_users WHERE tenant_id=?');$countQuery->execute([$tid]);
 if((int)$target['max_members']<(int)$countQuery->fetchColumn())throw new RuntimeException('Este plano não comporta os membros atuais da família.');
 $pending=$pdo->prepare("SELECT id FROM subscription_change_requests WHERE tenant_id=? AND status='pending' LIMIT 1 FOR UPDATE");
 $pending->execute([$tid]);if($pending->fetchColumn())throw new RuntimeException('Já existe uma solicitação de troca em análise.');
 $amount=bemora_plan_promo_active($target)?(float)$target['promo_price']:(float)$target['price'];
 $insert=$pdo->prepare("INSERT INTO subscription_change_requests(tenant_id,order_id,requested_by,from_plan_id,to_plan_id,quoted_amount) VALUES(?,?,?,?,?,?)");
 $insert->execute([$tid,(int)$order['id'],(int)$user['id'],(int)$order['plan_id'],(int)$target['id'],$amount]);
 $pdo->commit();
 flash('success','Solicitação registrada. Nenhuma cobrança ou alteração de plano foi realizada.');
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();flash('error',$e instanceof PDOException?'Não foi possível registrar a solicitação. Verifique se a migration 028 foi executada.':$e->getMessage());}
redirect_to('/evoluir-plano');

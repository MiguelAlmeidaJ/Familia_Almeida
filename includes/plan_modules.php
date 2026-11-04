<?php
declare(strict_types=1);

/**
 * Bemora module entitlements, independent of billing cycle or seat count.
 * Existing plans are backfilled as enabled in migration 043.
 * If migration is not installed or the family has no catalog plan,
 * preserve existing access until a plan is explicitly assigned.
 */
function bemora_module_catalog():array {
 return [
  'reports'=>['label'=>'Relatórios completos','description'=>'Análises, evolução e comparativos financeiros.'],
  'shopping'=>['label'=>'Listas de compras','description'=>'Planejamento de compras e carrinho de mercado.'],
  'scanner'=>['label'=>'Leitor de etiquetas','description'=>'Câmera e reconhecimento de etiquetas (exige Compras).'],
 ];
}
function bemora_modules_ready(PDO $pdo):bool {
 static $ready=null;
 if($ready!==null)return $ready;
 $q=$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='subscription_plan_modules'");
 return $ready=(int)$q->fetchColumn()===1;
}
function bemora_plan_modules(PDO $pdo,int $planId):array {
 $result=array_fill_keys(array_keys(bemora_module_catalog()),true);
 if($planId<1||!bemora_modules_ready($pdo))return $result;
 $q=$pdo->prepare('SELECT module_key,enabled FROM subscription_plan_modules WHERE plan_id=?');
 $q->execute([$planId]);
 foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){
  if(array_key_exists($row['module_key'],$result))$result[$row['module_key']]=(bool)$row['enabled'];
 }
 if(!$result['shopping'])$result['scanner']=false;
 return $result;
}
function bemora_tenant_plan_id(PDO $pdo,int $tenantId):int {
 if($tenantId<=0)return 0;
 // A commercial subscription has priority over an internal courtesy plan.
 $q=$pdo->prepare("SELECT plan_id FROM subscription_orders WHERE activated_tenant_id=? AND asaas_subscription_id IS NOT NULL AND status IN ('completed','paid','payment_attention') ORDER BY id DESC LIMIT 1");
 $q->execute([$tenantId]);
 $paid=(int)$q->fetchColumn();
 if($paid>0)return $paid;
 $q=$pdo->prepare('SELECT plan_id FROM tenant_internal_plans WHERE tenant_id=?');
 $q->execute([$tenantId]);
 return (int)$q->fetchColumn();
}
function bemora_tenant_modules(PDO $pdo,int $tenantId):array {
 static $cache=[];
 if(isset($cache[$tenantId]))return $cache[$tenantId];
 if(!bemora_modules_ready($pdo))return $cache[$tenantId]=array_fill_keys(array_keys(bemora_module_catalog()),true);
 return $cache[$tenantId]=bemora_plan_modules($pdo,bemora_tenant_plan_id($pdo,$tenantId));
}
function bemora_has_module(PDO $pdo,int $tenantId,string $key):bool {
 if(!array_key_exists($key,bemora_module_catalog()))return false;
 return bemora_tenant_modules($pdo,$tenantId)[$key]??false;
}
function bemora_require_module(PDO $pdo,int $tenantId,string $key):void {
 if(bemora_has_module($pdo,$tenantId,$key))return;
 http_response_code(403);
 header('Content-Type: text/plain; charset=utf-8');
 exit('Este recurso não está incluído no plano da família. Consulte Minha assinatura para conhecer as opções.');
}

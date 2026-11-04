<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/admin_layout.php';
require_once __DIR__.'/../includes/single_offer.php';
require_once __DIR__.'/../includes/plan_modules.php';
require_once __DIR__.'/../includes/plan_benefits.php';
require_admin_access();
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);exit('Método não permitido.');}
verify_csrf();
$pdo=db();
try{
 if(!bemora_single_offer_ready($pdo)||!bemora_modules_ready($pdo))throw new RuntimeException('Execute as migrations pendentes antes de configurar a assinatura.');
 $selectedBenefits=bemora_benefits_ready($pdo)?bemora_validate_selected_benefits($pdo,$_POST['benefits']??[]):[];
 $current=bemora_single_offer($pdo);
 if(!$current)throw new RuntimeException('A configuração comercial não foi inicializada.');
 $name=trim((string)($_POST['name']??''));
 if(mb_strlen($name)<3||mb_strlen($name)>100)throw new RuntimeException('Nome inválido.');
 $cents=static function(string $value):int {
  $value=trim(preg_replace('/^R\$\s*/u','',$value));
  $value=str_replace(' ','',$value);
  if(str_contains($value,','))$value=str_replace(',','.',str_replace('.','',$value));
  if(!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D',$value))throw new RuntimeException('Preço inválido.');
  [$whole,$dec]=array_pad(explode('.',$value,2),2,'');
  return (int)$whole*100+(int)str_pad($dec,2,'0');
 };
 $base=$cents((string)($_POST['base_monthly']??''));
 $seat=$cents((string)($_POST['extra_member_monthly']??''));
 $shop=$cents((string)($_POST['shopping_monthly']??''));
 $max=filter_var($_POST['max_members']??null,FILTER_VALIDATE_INT);
 $semi=(float)($_POST['semi_discount']??-1);
 $annual=(float)($_POST['annual_discount']??-1);
 $requestedEnabled=($_POST['enabled']??'')==='1';
 if($base<100||!$max||$max<1||$max>30||!is_finite($semi)||!is_finite($annual)||$semi<0||$semi>80||$annual<0||$annual>80)throw new RuntimeException('Revise os preços, os limites e os descontos.');
 if(($max>1 && $seat<1)||$shop<1)throw new RuntimeException('Configure um valor positivo para cada adicional disponível.');
 // A zero-priced optional service is allowed, but must not accidentally bypass the selection.
 $new=$current;
 foreach(['name'=>$name,'base_monthly'=>$base/100,'extra_member_monthly'=>$seat/100,'shopping_monthly'=>$shop/100,'max_members'=>$max,'semi_discount'=>$semi,'annual_discount'=>$annual] as $k=>$v)$new[$k]=$v;
 foreach(['monthly','semiannual','annual'] as $cycle)bemora_single_quote($new,$cycle,$max,true);
 $activating=$requestedEnabled&&(int)$current['enabled']!==1;
 if($activating&&(string)($_POST['confirmation']??'')!=='SUBSTITUIR PLANOS')throw new RuntimeException('Para substituir as ofertas antigas, digite SUBSTITUIR PLANOS.');
 $q=$pdo->query("SELECT COALESCE(MAX(max_members),0) FROM subscription_orders WHERE single_offer=1 AND activated_tenant_id IS NOT NULL AND status IN ('completed','paid','payment_attention')");
 if($max<(int)$q->fetchColumn())throw new RuntimeException('Este limite é menor que a quantidade de pessoas de assinaturas já contratadas.');
 $pdo->beginTransaction();
 $ids=[];
 foreach(['monthly','semiannual','annual'] as $cycle){
  $key=['monthly'=>'monthly_plan_id','semiannual'=>'semiannual_plan_id','annual'=>'annual_plan_id'][$cycle];
  $id=(int)($current[$key]??0);
  $quote=bemora_single_quote($new,$cycle,1,false);
  $details='Assinatura financeira individual com adicionais opcionais de integrantes e Compras.';
  if($id>0){
   // Existing contract snapshots and remote Asaas charges are never updated here.
   $q=$pdo->prepare("UPDATE subscription_plans SET name=?,details=?,max_members=1,price=?,public_sale=1,active=? WHERE id=?");
   $q->execute([$name,$details,$quote['total'],$requestedEnabled?1:0,$id]);
  }else{
   $q=$pdo->prepare("INSERT INTO subscription_plans(name,billing_cycle,details,max_members,price,public_sale,active) VALUES(?,?,?,1,?,1,?)");
   $q->execute([$name,$cycle,$details,$quote['total'],$requestedEnabled?1:0]);
   $id=(int)$pdo->lastInsertId();
  }
  $ids[$cycle]=$id;
  $permissions=['reports'=>1,'shopping'=>0,'scanner'=>0];
  $stmt=$pdo->prepare('INSERT INTO subscription_plan_modules(plan_id,module_key,enabled) VALUES(?,?,?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled)');
  foreach($permissions as $module=>$enabled)$stmt->execute([$id,$module,$enabled]);
  if(bemora_benefits_ready($pdo))bemora_sync_plan_benefits($pdo,$id,$selectedBenefits);
 }
 // Old offers are archived, never deleted; existing subscription_orders retain their FKs.
 if($requestedEnabled){
  $pdo->prepare("UPDATE subscription_plans SET active=0 WHERE public_sale=1 AND id NOT IN (?,?,?)")->execute(array_values($ids));
 }
 $save=$pdo->prepare("UPDATE bemora_single_offer SET name=?,base_monthly=?,extra_member_monthly=?,shopping_monthly=?,max_members=?,semi_discount=?,annual_discount=?,monthly_plan_id=?,semiannual_plan_id=?,annual_plan_id=?,enabled=? WHERE id=1");
 $save->execute([$name,$base/100,$seat/100,$shop/100,$max,$semi,$annual,$ids['monthly'],$ids['semiannual'],$ids['annual'],$requestedEnabled?1:0]);
 $pdo->commit();
 flash('success',$requestedEnabled?'Assinatura única atualizada. Ofertas antigas arquivadas sem cancelar contratos.':'Configuração salva sem liberar novas vendas.');
}catch(Throwable $e){
 if($pdo->inTransaction())$pdo->rollBack();
 flash('error',$e instanceof PDOException?'Erro ao salvar a assinatura no banco.':$e->getMessage());
}
redirect_to('/admin/assinatura');

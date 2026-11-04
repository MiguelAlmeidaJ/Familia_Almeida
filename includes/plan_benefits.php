<?php
declare(strict_types=1);
function bemora_benefits_ready(PDO $pdo):bool {
 static $ready=null;
 if($ready!==null)return $ready;
 $q=$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('plan_benefits','plan_benefit_links')");
 return $ready=((int)$q->fetchColumn()===2);
}
function bemora_benefits_catalog(PDO $pdo,bool $activeOnly=false):array {
 if(!bemora_benefits_ready($pdo))return [];
 $sql='SELECT id,title,sort_order,active FROM plan_benefits'.($activeOnly?' WHERE active=1':'').' ORDER BY sort_order,id';
 return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}
function bemora_plan_benefits(PDO $pdo,array $planIds):array {
 if(!bemora_benefits_ready($pdo)||!$planIds)return [];
 $ids=array_values(array_unique(array_filter(array_map('intval',$planIds),static fn(int $id)=>$id>0)));
 if(!$ids)return [];
 $placeholders=implode(',',array_fill(0,count($ids),'?'));
 $q=$pdo->prepare("SELECT l.plan_id,b.id,b.title FROM plan_benefit_links l JOIN plan_benefits b ON b.id=l.benefit_id WHERE b.active=1 AND l.plan_id IN ($placeholders) ORDER BY b.sort_order,b.id");
 $q->execute($ids);
 $out=[];
 foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row)$out[(int)$row['plan_id']][]=['id'=>(int)$row['id'],'title'=>$row['title']];
 return $out;
}
function bemora_plan_benefit_ids(PDO $pdo,array $planIds):array {
 if(!bemora_benefits_ready($pdo)||!$planIds)return [];
 $ids=array_values(array_unique(array_filter(array_map('intval',$planIds),static fn(int $id)=>$id>0)));
 if(!$ids)return [];
 $q=$pdo->prepare('SELECT plan_id,benefit_id FROM plan_benefit_links WHERE plan_id IN ('.implode(',',array_fill(0,count($ids),'?')).')');
 $q->execute($ids);$out=[];
 foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row)$out[(int)$row['plan_id']][]=(int)$row['benefit_id'];
 return $out;
}
function bemora_validate_selected_benefits(PDO $pdo,mixed $input):array {
 if(!is_array($input))throw new RuntimeException('Seleção de benefícios inválida.');
 if(!bemora_benefits_ready($pdo))throw new RuntimeException('Execute a migration 044 de benefícios em Manutenção.');
 $ids=[];
 foreach($input as $value){
  if(!is_string($value)||!ctype_digit($value)||(int)$value<1)throw new RuntimeException('Benefício inválido.');
  $ids[]=(int)$value;
 }
 $ids=array_values(array_unique($ids));
 if(!$ids)return [];
 $q=$pdo->prepare('SELECT COUNT(*) FROM plan_benefits WHERE active=1 AND id IN ('.implode(',',array_fill(0,count($ids),'?')).')');
 $q->execute($ids);
 if((int)$q->fetchColumn()!==count($ids))throw new RuntimeException('Há benefícios inválidos ou inativos na seleção.');
 return $ids;
}
function bemora_sync_plan_benefits(PDO $pdo,int $planId,array $ids):void {
 $pdo->prepare('DELETE FROM plan_benefit_links WHERE plan_id=?')->execute([$planId]);
 $stmt=$pdo->prepare('INSERT INTO plan_benefit_links(plan_id,benefit_id) VALUES(?,?)');
 foreach($ids as $id)$stmt->execute([$planId,(int)$id]);
}

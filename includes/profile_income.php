<?php
declare(strict_types=1);
require_once __DIR__.'/tenant.php';

/**
 * Virtual monthly earnings from user profile income sources.
 * These are forecasts, never persisted transactions or bank receipts.
 */
function profile_monthly_income(PDO $pdo,string $month):array {
 if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D',$month))throw new InvalidArgumentException('Mês inválido.');
 $tenantId=require_tenant_id();
 $userId=(int)($_SESSION['user_id']??0);
 if($userId<1)return [];
 $ready=$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='user_income_sources'");
 if(!(int)$ready->fetchColumn())return [];
 $state=$pdo->prepare('SELECT finance_mode FROM tenants WHERE id=? AND active=1');
 $state->execute([$tenantId]);$mode=$state->fetchColumn();
 if(!in_array($mode,['shared','separate'],true))return [];
 $versioned=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='user_income_source_versions'")->fetchColumn()===1;
 $sql=$versioned
 ? "SELECT s.id,s.user_id,v.job_title,v.salary,v.payday,u.name AS member_name FROM user_income_sources s JOIN tenant_users tu ON tu.user_id=s.user_id AND tu.tenant_id=? JOIN users u ON u.id=s.user_id JOIN user_income_source_versions v ON v.source_id=s.id AND v.valid_from=(SELECT MAX(v2.valid_from) FROM user_income_source_versions v2 WHERE v2.source_id=s.id AND v2.valid_from<=?) WHERE (s.ended_month IS NULL OR s.ended_month>=?)"
 : "SELECT s.id,s.user_id,s.job_title,s.salary,s.payday,u.name AS member_name FROM user_income_sources s JOIN tenant_users tu ON tu.user_id=s.user_id AND tu.tenant_id=? JOIN users u ON u.id=s.user_id WHERE DATE_FORMAT(s.created_at,'%Y-%m')<=?";
 $params=$versioned?[$tenantId,$month,$month]:[$tenantId,$month];
 if($mode==='separate'){$sql.=' AND s.user_id=?';$params[]=$userId;}
 $sql.=' ORDER BY s.user_id,s.id';
 $stmt=$pdo->prepare($sql);$stmt->execute($params);
 $maxDay=(int)(new DateTimeImmutable($month.'-01'))->format('t');
 $items=[];
 foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
   $day=min($maxDay,(int)$row['payday']);
   $items[]=[
     'source_id'=>(int)$row['id'],'user_id'=>(int)$row['user_id'],
     'description'=>'Renda: '.$row['job_title'],
     'category'=>'Fontes de renda',
     'amount'=>(float)$row['salary'],
     'date'=>sprintf('%s-%02d',$month,$day),
     'occurred_on'=>sprintf('%s-%02d',$month,$day),
     'member_name'=>(string)$row['member_name'],
   ];
 }
 return $items;
}

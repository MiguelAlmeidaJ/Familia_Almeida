<?php
declare(strict_types=1);
require_once __DIR__.'/monthly_budget.php';

/**
 * A financial report is a projection, not an account balance.
 * In separate mode, 'shared' deliberately contains ONLY shared records.
 * It never sums another member's private finances.
 */
function bemora_report_month(PDO $pdo,string $month,array $scope,string $view):array {
 if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D',$month))throw new InvalidArgumentException('Mês inválido.');
 $tenant=(int)$scope['tenant_id'];$user=(int)$scope['user_id'];
 $individual=$scope['mode']==='separate';
 if(!$individual || $view==='mine'){
  $data=monthly_budget_data($pdo,$month);
  return ['month'=>$month,'income'=>(float)$data['incomeTotal'],'bills'=>(float)$data['billTotal'],
   'expenses'=>(float)$data['variableSpent'],'balance'=>(float)$data['expected_end'],
   'missing'=>(int)$data['missingBills']];
 }
 if($view!=='shared')throw new InvalidArgumentException('Visão inválida.');
 $start=$month.'-01';$end=(new DateTimeImmutable($start))->modify('+1 month')->format('Y-m-d');
 // Shared-only finance is distinct from household totals in a separated family.
 $stmt=$pdo->prepare("SELECT type,COALESCE(SUM(amount),0) total FROM transactions
   WHERE tenant_id=? AND owner_user_id IS NULL AND occurred_on>=? AND occurred_on<?
   AND type IN ('income','expense')
   AND (type='income' OR (bill_payment_id IS NULL AND LOWER(category) NOT IN ('contas recorrentes','contas fixas')))
   GROUP BY type");
 $stmt->execute([$tenant,$start,$end]);
 $income=0.0;$expenses=0.0;
 foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r){if($r['type']==='income')$income=(float)$r['total'];else $expenses=(float)$r['total'];}
 $stmt=$pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM recurring_incomes WHERE tenant_id=? AND owner_user_id IS NULL AND DATE_FORMAT(created_at,\'%Y-%m\')<=? AND (ended_month IS NULL OR ended_month>=?)');
 $stmt->execute([$tenant,$month,$month]);$income+=(float)$stmt->fetchColumn();
 // Select bills with an explicit tenant/owner predicate. Use the common recurring-bill engine
 // for applicability, installments and variable monthly amounts, then filter shared owner IDs.
 $sharedBills=$pdo->prepare('SELECT id FROM fixed_bills WHERE tenant_id=? AND owner_user_id IS NULL');
 $sharedBills->execute([$tenant]);$ids=array_fill_keys(array_map('intval',$sharedBills->fetchAll(PDO::FETCH_COLUMN)),true);
 $billTotal=0.0;$missing=0;
 foreach(fixed_bills_data($pdo,$month) as $bill){
   if(!isset($ids[(int)$bill['id']]))continue;
   $billTotal+=(float)$bill['amount'];
   if($bill['needs_amount'])$missing++;
 }
 return ['month'=>$month,'income'=>$income,'bills'=>$billTotal,'expenses'=>$expenses,
  'balance'=>$income-$billTotal-$expenses,'missing'=>$missing];
}
function bemora_report_series(PDO $pdo,string $endMonth,array $scope,string $view,int $months=12):array {
 $last=new DateTimeImmutable($endMonth.'-01');$rows=[];
 for($i=$months-1;$i>=0;$i--){
  $month=$last->modify('-'.$i.' months')->format('Y-m');
  $rows[]=bemora_report_month($pdo,$month,$scope,$view);
 }
 return $rows;
}

<?php
declare(strict_types=1);

/** Resolve a recurring due date, clamping 29–31 to the month's last day. */
function bill_due_date(string $month,int $dueDay,bool $nextBusinessDay=false):string {
 if(!preg_match('/^\\d{4}-(0[1-9]|1[0-2])$/D',$month)||$dueDay<1||$dueDay>31)
   throw new InvalidArgumentException('Mês ou dia de vencimento inválido.');
 $first=new DateTimeImmutable($month.'-01');
 $last=(int)$first->format('t');
 $date=$first->setDate((int)$first->format('Y'),(int)$first->format('m'),min($dueDay,$last));
 if(!$nextBusinessDay)return $date->format('Y-m-d');
 // Weekends and nationwide holidays. Regional/municipal holidays require future configuration.
 for($i=0;$i<15;$i++){
   $year=(int)$date->format('Y');
   $easter=(new DateTimeImmutable($year.'-03-21'))->modify('+'.easter_days($year).' days');
   $goodFriday=$easter->modify('-2 days')->format('m-d');
   $national=['01-01','04-21','05-01','09-07','10-12','11-02','11-15','11-20','12-25',$goodFriday];
   if((int)$date->format('N')<=5 && !in_array($date->format('m-d'),$national,true))
      return $date->format('Y-m-d');
   $date=$date->modify('+1 day');
 }
 throw new RuntimeException('Não foi possível calcular o próximo dia útil.');
}

/** Count business days within the selected month, excluding nationwide holidays. */
function bill_business_date(string $month,string $rule):string {
 if(!in_array($rule,['first','fifth','penultimate','last'],true))throw new InvalidArgumentException('Dia útil inválido.');
 $first=new DateTimeImmutable($month.'-01');$days=(int)$first->format('t');$valid=[];
 for($d=1;$d<=$days;$d++){
  $date=$first->setDate((int)$first->format('Y'),(int)$first->format('m'),$d);
  $year=(int)$date->format('Y');
  $easter=(new DateTimeImmutable($year.'-03-21'))->modify('+'.easter_days($year).' days');
  $holidays=['01-01','04-21','05-01','09-07','10-12','11-02','11-15','11-20','12-25',$easter->modify('-2 days')->format('m-d')];
  if((int)$date->format('N')<=5&&!in_array($date->format('m-d'),$holidays,true))$valid[]=$date->format('Y-m-d');
 }
 if(count($valid)<5)throw new RuntimeException('Mês sem dias úteis suficientes.');
 return match($rule){'first'=>$valid[0],'fifth'=>$valid[4],'penultimate'=>$valid[count($valid)-2],'last'=>$valid[count($valid)-1]};
}
function bill_due_date_by_mode(string $month,int $day,string $mode,string $rule=''):string {
 return $mode==='business'?bill_business_date($month,$rule):bill_due_date($month,$day,false);
}

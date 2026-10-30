<?php
declare(strict_types=1);
return [
 'description'=>'Preservar meses antigos ao encerrar receitas recorrentes',
 'up'=>static function(PDO $pdo):void {
  $q=$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='recurring_incomes' AND column_name='ended_month'");
  if(!(int)$q->fetchColumn())$pdo->exec("ALTER TABLE recurring_incomes ADD COLUMN ended_month CHAR(7) NULL");
 }
];
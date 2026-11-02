<?php
declare(strict_types=1);
return [
 'description'=>'Permitir preco zero em planos internos; planos publicos continuam pagos',
 'up'=>static function(PDO $pdo):void {
  $q=$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='subscription_plans' AND column_name='public_sale'");
  if(!(int)$q->fetchColumn())throw new RuntimeException('Execute a migration 031 antes desta migration.');
  // MariaDB 10.11 uses DROP CONSTRAINT for CHECK constraints (not DROP CHECK).
  // Each operation is idempotent because DDL may already have committed on a previous attempt.
  $q=$pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='subscription_plans' AND CONSTRAINT_TYPE='CHECK'");
  $existing=$q->fetchAll(PDO::FETCH_COLUMN);
  if(in_array('chk_plan_price',$existing,true)) {
   $pdo->exec("ALTER TABLE subscription_plans DROP CONSTRAINT chk_plan_price");
  }
  if(!in_array('chk_plan_price',$existing,true)) {
   // No legacy positive-price constraint remains.
  }
  $q=$pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='subscription_plans' AND CONSTRAINT_TYPE='CHECK'");
  $existing=$q->fetchAll(PDO::FETCH_COLUMN);
  if(!in_array('chk_plan_price',$existing,true)) {
   $pdo->exec("ALTER TABLE subscription_plans ADD CONSTRAINT chk_plan_price CHECK (price >= 0)");
  }
  if(!in_array('chk_public_plan_price',$existing,true)) {
   $pdo->exec("ALTER TABLE subscription_plans ADD CONSTRAINT chk_public_plan_price CHECK (public_sale = 0 OR price > 0)");
  }
 }
];

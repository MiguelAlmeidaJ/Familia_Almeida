<?php
declare(strict_types=1);
return [
 'description'=>'Permitir preco zero em planos internos; planos publicos continuam pagos',
 'up'=>static function(PDO $pdo):void {
  $q=$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='subscription_plans' AND column_name='public_sale'");
  if(!(int)$q->fetchColumn())throw new RuntimeException('Execute a migration 031 antes desta migration.');
  $pdo->exec("ALTER TABLE subscription_plans DROP CHECK chk_plan_price");
  $pdo->exec("ALTER TABLE subscription_plans ADD CONSTRAINT chk_plan_price CHECK (price >= 0)");
  $pdo->exec("ALTER TABLE subscription_plans ADD CONSTRAINT chk_public_plan_price CHECK (public_sale = 0 OR price > 0)");
 }
];

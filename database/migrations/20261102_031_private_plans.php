<?php
declare(strict_types=1);
return ['description'=>'Permite planos internos nao comercializados no site publico',
'up'=>static function(PDO $pdo):void {
 $q=$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='subscription_plans' AND column_name='public_sale'");
 if(!(int)$q->fetchColumn())$pdo->exec("ALTER TABLE subscription_plans ADD COLUMN public_sale TINYINT(1) NOT NULL DEFAULT 1 AFTER active");
}];

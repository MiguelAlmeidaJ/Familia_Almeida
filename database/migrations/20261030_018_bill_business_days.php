<?php
return ['description'=>'Regra de vencimento em dia util','up'=>static function(PDO $pdo):void {
 $pdo->exec("ALTER TABLE fixed_bills ADD COLUMN due_business_day TINYINT(1) NOT NULL DEFAULT 0");
}];

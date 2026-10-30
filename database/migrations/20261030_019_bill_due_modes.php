<?php
return ['description'=>'Vencimento por dia fixo ou dia util','up'=>static function(PDO $pdo):void {
 $pdo->exec("ALTER TABLE fixed_bills ADD COLUMN due_mode VARCHAR(20) NOT NULL DEFAULT 'fixed', ADD COLUMN business_day_rule VARCHAR(20) NULL");
}];

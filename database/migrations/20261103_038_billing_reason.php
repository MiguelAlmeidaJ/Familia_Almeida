<?php
return ['description'=>'Auditoria de suspensão','up'=>static function(PDO $pdo):void {$pdo->exec('ALTER TABLE tenant_billing_access ADD COLUMN reason VARCHAR(48) NULL');}];

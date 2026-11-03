<?php
declare(strict_types=1);
return [
 'description'=>'Registrar o motivo da suspensão financeira separadamente da suspensão manual',
 'up'=>static function(PDO $pdo):void {
  $q=$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='tenant_billing_access' AND column_name='reason'");
  if(!(int)$q->fetchColumn())$pdo->exec("ALTER TABLE tenant_billing_access ADD COLUMN reason VARCHAR(48) NULL");
 }
];

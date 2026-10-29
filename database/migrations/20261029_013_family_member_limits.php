<?php
declare(strict_types=1);
return [
 'description'=>'Limite de membros por família',
 'up'=>static function(PDO $pdo):void {
  $q=$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='tenants' AND column_name='max_members'");
  if(!(int)$q->fetchColumn())$pdo->exec("ALTER TABLE tenants ADD COLUMN max_members SMALLINT UNSIGNED NOT NULL DEFAULT 30");
  // No existing membership is displaced by the new quota.
  $pdo->exec("UPDATE tenants t SET max_members=GREATEST(max_members,(SELECT COUNT(*) FROM tenant_users tu WHERE tu.tenant_id=t.id))");
 }
];
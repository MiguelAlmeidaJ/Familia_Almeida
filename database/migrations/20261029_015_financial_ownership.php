<?php
declare(strict_types=1);
/**
 * Prepare explicit personal ownership. Existing records remain shared/unassigned.
 * Do not enable individual finance mode until all read/write endpoints are scoped.
 */
return [
 'description'=>'Preparar titularidade individual sem redistribuir dados legados',
 'up'=>static function(PDO $pdo):void {
  foreach(['transactions','recurring_incomes','fixed_bills','debts','spending_goals','monthly_allocations','credit_cards'] as $table){
   $exists=$pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?");
   $exists->execute([$table]);if(!(int)$exists->fetchColumn())continue;
   $column=$pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name='owner_user_id'");
   $column->execute([$table]);
   if(!(int)$column->fetchColumn())$pdo->exec("ALTER TABLE `{$table}` ADD COLUMN owner_user_id BIGINT UNSIGNED NULL");
   $index=$pdo->prepare("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? AND index_name='tenant_owner_idx'");
   $index->execute([$table]);
   if(!(int)$index->fetchColumn())$pdo->exec("CREATE INDEX tenant_owner_idx ON `{$table}` (tenant_id,owner_user_id)");
  }
  // All existing finances remain shared. A separate owner must be assigned
  // explicitly and verified before enabling personal mode.
 }
];
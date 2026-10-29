<?php
declare(strict_types=1);
/**
 * Scope business keys used by the active budget/category modules.
 * Allocation owner 0 denotes a deliberately shared family allocation.
 */
return [
 'description'=>'Isolar categorias e alocacoes mensais por familia e titular',
 'up'=>static function(PDO $pdo):void {
   $cols=$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='monthly_allocations' AND column_name='owner_user_id'")->fetchColumn();
   if(!(int)$cols)throw new RuntimeException('Execute a migration 015 antes da 016.');
   $pdo->exec("UPDATE monthly_allocations SET owner_user_id=0 WHERE owner_user_id IS NULL");
   $pdo->exec("ALTER TABLE monthly_allocations MODIFY owner_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0");
   $pk=$pdo->query("SELECT GROUP_CONCAT(column_name ORDER BY seq_in_index SEPARATOR ',') FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='monthly_allocations' AND index_name='PRIMARY'")->fetchColumn();
   if($pk!=='tenant_id,month,category,owner_user_id'){
     $pdo->exec("ALTER TABLE monthly_allocations DROP PRIMARY KEY, ADD PRIMARY KEY(tenant_id,month,category,owner_user_id)");
   }
   $old=$pdo->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='categories' AND index_name='categories_type_name_unique'")->fetchColumn();
   if((int)$old)$pdo->exec("ALTER TABLE categories DROP INDEX categories_type_name_unique");
   $new=$pdo->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='categories' AND index_name='categories_tenant_type_name_unique'")->fetchColumn();
   if(!(int)$new)$pdo->exec("ALTER TABLE categories ADD UNIQUE KEY categories_tenant_type_name_unique(tenant_id,type,name)");
 }
];
<?php
declare(strict_types=1);
return ['description'=>'Pagamento confirmado antes da ativacao de evolucoes de assinatura',
'up'=>static function(PDO $pdo):void {
 $columns=$pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='subscription_change_requests'")->fetchAll(PDO::FETCH_COLUMN);
 foreach([
 'asaas_payment_id'=>"VARCHAR(100) NULL",
 'invoice_url'=>"VARCHAR(1500) NULL",
 'payment_amount'=>"DECIMAL(12,2) NULL",
 'old_recurring_amount'=>"DECIMAL(12,2) NULL",
 'payment_confirmed_at'=>"DATETIME NULL",
 'activated_at'=>"DATETIME NULL",
 'failure_reason'=>"VARCHAR(250) NULL"
 ] as $column=>$definition){
  if(!in_array($column,$columns,true))$pdo->exec("ALTER TABLE subscription_change_requests ADD COLUMN `$column` $definition");
 }
 $pdo->exec("CREATE UNIQUE INDEX change_payment_unique ON subscription_change_requests(asaas_payment_id)");
}];

<?php
declare(strict_types=1);
return ['description'=>'Agendamento e rastreamento da troca de plano no proximo ciclo',
'up'=>static function(PDO $pdo):void {
 $columns=$pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='subscription_change_requests'")->fetchAll(PDO::FETCH_COLUMN);
 if(!in_array('effective_at',$columns,true))$pdo->exec("ALTER TABLE subscription_change_requests ADD COLUMN effective_at DATE NULL");
 if(!in_array('change_mode',$columns,true))$pdo->exec("ALTER TABLE subscription_change_requests ADD COLUMN change_mode VARCHAR(20) NOT NULL DEFAULT 'immediate'");
}];

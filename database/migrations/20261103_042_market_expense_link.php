<?php
declare(strict_types=1);
return [
 'description'=>'Vincula compra concluída ao único gasto financeiro criado na finalização',
 'up'=>static function(PDO $pdo):void {
  $q=$pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bemora_market_lists'");
  $columns=$q->fetchAll(PDO::FETCH_COLUMN);
  if(!in_array('transaction_id',$columns,true)){
   $pdo->exec('ALTER TABLE bemora_market_lists ADD COLUMN transaction_id BIGINT UNSIGNED NULL AFTER completed_at');
  }
  $q=$pdo->query("SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bemora_market_lists'");
  $indexes=$q->fetchAll(PDO::FETCH_COLUMN);
  if(!in_array('bemora_market_unique_transaction',$indexes,true)){
   $pdo->exec('ALTER TABLE bemora_market_lists ADD UNIQUE KEY bemora_market_unique_transaction(transaction_id)');
  }
 }
];
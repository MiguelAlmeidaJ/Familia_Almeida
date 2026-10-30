<?php
declare(strict_types=1);
return [
 'description'=>'Alinhar vigência inicial de fontes existentes ao mês de criação do usuário',
 'up'=>static function(PDO $pdo):void {
  $rows=$pdo->query("SELECT s.id source_id,DATE_FORMAT(u.created_at,'%Y-%m') user_month,
   (SELECT v.id FROM user_income_source_versions v WHERE v.source_id=s.id ORDER BY v.valid_from ASC LIMIT 1) first_version_id,
   (SELECT v.valid_from FROM user_income_source_versions v WHERE v.source_id=s.id ORDER BY v.valid_from ASC LIMIT 1) first_month
   FROM user_income_sources s JOIN users u ON u.id=s.user_id")->fetchAll(PDO::FETCH_ASSOC);
  $update=$pdo->prepare('UPDATE user_income_source_versions SET valid_from=? WHERE id=? AND source_id=?');
  foreach($rows as $row){
   if(!$row['first_version_id']||!$row['first_month']||!$row['user_month'])continue;
   if($row['user_month']<$row['first_month'])$update->execute([$row['user_month'],$row['first_version_id'],$row['source_id']]);
  }
 }
];
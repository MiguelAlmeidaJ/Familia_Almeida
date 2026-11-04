<?php
declare(strict_types=1);
return [
 'description'=>'Catálogo independente de benefícios comerciais vinculados às ofertas, preservando benefícios atuais',
 'up'=>static function(PDO $pdo):void {
  $pdo->exec("CREATE TABLE IF NOT EXISTS plan_benefits (
   id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
   title VARCHAR(160) NOT NULL,
   sort_order INT NOT NULL DEFAULT 0,
   active TINYINT(1) NOT NULL DEFAULT 1,
   created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
   updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   UNIQUE KEY uq_plan_benefits_title(title),
   INDEX idx_plan_benefits_order(active,sort_order,id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $pdo->exec("CREATE TABLE IF NOT EXISTS plan_benefit_links (
   plan_id BIGINT UNSIGNED NOT NULL,
   benefit_id BIGINT UNSIGNED NOT NULL,
   PRIMARY KEY(plan_id,benefit_id),
   CONSTRAINT fk_plan_benefit_plan FOREIGN KEY(plan_id) REFERENCES subscription_plans(id) ON DELETE CASCADE,
   CONSTRAINT fk_plan_benefit_benefit FOREIGN KEY(benefit_id) REFERENCES plan_benefits(id) ON DELETE RESTRICT
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  // Legacy display labels are moved to the editable catalog; keep equivalent
  // selections on pre-existing plans, reflecting actual module permissions.
  $seed=['Organizar receitas e despesas','Acompanhar contas previstas',
         'Visualizar relatórios financeiros','Organizar listas de compras','Escanear etiquetas de preços'];
  $insert=$pdo->prepare('INSERT IGNORE INTO plan_benefits(title,sort_order) VALUES(?,?)');
  foreach($seed as $i=>$title)$insert->execute([$title,($i+1)*10]);
  $pdo->exec("INSERT IGNORE INTO plan_benefit_links(plan_id,benefit_id)
   SELECT p.id,b.id FROM subscription_plans p JOIN plan_benefits b
   ON b.title IN ('Organizar receitas e despesas','Acompanhar contas previstas')");
  $hasModules=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='subscription_plan_modules'")->fetchColumn();
  if($hasModules){
   $modules=['reports'=>'Visualizar relatórios financeiros','shopping'=>'Organizar listas de compras','scanner'=>'Escanear etiquetas de preços'];
   $bind=$pdo->prepare("INSERT IGNORE INTO plan_benefit_links(plan_id,benefit_id)
    SELECT m.plan_id,b.id FROM subscription_plan_modules m JOIN plan_benefits b ON b.title=?
    WHERE m.module_key=? AND m.enabled=1");
   foreach($modules as $key=>$label)$bind->execute([$label,$key]);
  }
 }
];
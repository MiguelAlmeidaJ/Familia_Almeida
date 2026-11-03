<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/includes/bootstrap.php';
require_once dirname(__DIR__).'/includes/migrations.php';
try {
 $pending=array_values(array_filter(migration_status(db()),static fn(array $m):bool=>!$m['applied']));
 if(!$pending){echo "Nenhuma migration pendente.\n";exit(0);}
 echo "Migrations pendentes: ".implode(', ',array_column($pending,'id'))."\n";
 if(!in_array('--apply',$argv,true)){
  fwrite(STDERR,"Nenhuma alteração aplicada. Faça backup antes de executar com --apply.\n");
  exit(2);
 }
 $done=run_pending_migrations(db());
 echo "Aplicadas: ".implode(', ',$done)."\n";
}catch(Throwable $error){
 fwrite(STDERR,"Falha ao aplicar migrations; verifique o log do servidor e a estrutura do banco.\n");
 error_log('Bemora CLI migrations: '.$error->getMessage());
 exit(1);
}

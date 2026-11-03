<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit(1);}
umask(0077);
require_once __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/operations.php';
try{
    $root=bemora_ops_dir();
    $name=null;$restoreTest=false;
    foreach(array_slice($argv,1) as $arg){
        if(str_starts_with($arg,'--backup='))$name=substr($arg,9);
        if($arg==='--restore-test')$restoreTest=true;
    }
    if($name!==null&&!preg_match('/^\d{8}_\d{6}_[a-f0-9]{8}$/D',$name))throw new RuntimeException('Identificador de backup inválido.');
    if($name===null){
        $found=glob($root.'/backups/[0-9]*',GLOB_ONLYDIR)?:[];
        rsort($found,SORT_STRING);
        if(!$found)throw new RuntimeException('Nenhum backup encontrado.');
        $name=basename($found[0]);
    }
    $folder=$root.'/backups/'.$name;
    $details=bemora_ops_verify_backup($folder);
    echo "Integridade SHA-256, SQL comprimido e arquivos privados: OK\n";
    echo "Backup: ".$name."\nEntradas de arquivos: ".(int)$details['archive_entries']."\n";
    if(!$restoreTest)exit(0);
    $database=(string)config('operations','restore_test_database','');
    $production=(string)config('db','name','');
    if(!preg_match('/^[a-zA-Z0-9_]+$/D',$database)||$database===$production)
        throw new RuntimeException('Configure uma base de homologação separada em operations.restore_test_database.');
    $pdo=db();
    $check=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=?');
    $check->execute([$database]);
    if((int)$check->fetchColumn()>0)throw new RuntimeException('A base de restauração deve existir e estar vazia; nenhuma tabela foi modificada.');
    $available=$pdo->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name=?');
    $available->execute([$database]);
    if(!(int)$available->fetchColumn())throw new RuntimeException('Crie previamente uma base de homologação isolada.');
    $secret=tempnam($root,'.restore-');
    if($secret===false)throw new RuntimeException('Falha ao preparar teste de restauração.');
    $errorLog=$root.'/restore-test-error.log';
    $escape=static fn(string $v):string=>'"'.str_replace(['\\','"'],['\\\\','\\"'],$v).'"';
    try{
        $connection=['host'=>(string)config('db','host','localhost'),'user'=>(string)config('db','user',''),'password'=>(string)config('db','pass','')];
        foreach($connection as $item)if(preg_match('/[\r\n]/',$item))throw new RuntimeException('Credenciais inválidas.');
        chmod($secret,0600);
        file_put_contents($secret,"[client]\nhost=".$escape($connection['host'])."\nport=".(int)config('db','port',3306)."\nuser=".$escape($connection['user'])."\npassword=".$escape($connection['password'])."\n");
        $binary=(string)config('operations','mysql_binary','mysql');
        if(!preg_match('~^(?:/[A-Za-z0-9_./-]+|[A-Za-z0-9_-]+)$~D',$binary))throw new RuntimeException('Cliente mysql inválido.');
        $pipes=[];
        $proc=proc_open([$binary,'--defaults-extra-file='.$secret,'--default-character-set=utf8mb4',$database],
            [0=>['pipe','r'],1=>['file',$root.'/restore-test-output.log','w'],2=>['file',$errorLog,'w']],$pipes,dirname(__DIR__));
        if(!is_resource($proc))throw new RuntimeException('Cliente MySQL indisponível.');
        $input=gzopen($folder.'/database.sql.gz','rb');
        if(!$input){fclose($pipes[0]);proc_close($proc);throw new RuntimeException('Falha ao abrir dump.');}
        $writeFailed=false;
        while(!gzeof($input)){
            $chunk=gzread($input,32768);
            if($chunk===false){$writeFailed=true;break;}
            $size=strlen($chunk);$written=0;
            while($written<$size){
                $n=fwrite($pipes[0],substr($chunk,$written));
                if($n===false||$n===0){$writeFailed=true;break 2;}
                $written+=$n;
            }
        }
        gzclose($input);fclose($pipes[0]);$exit=proc_close($proc);
        if($writeFailed||$exit!==0)throw new RuntimeException('Teste de restauração SQL falhou; consulte o log privado de restauração.');
        $check->execute([$database]);
        $tables=(int)$check->fetchColumn();
        if($tables<1)throw new RuntimeException('Nenhuma tabela restaurada.');
        echo "Restauração em base isolada: OK (".$tables." tabelas).\n";
        echo "A base de teste não foi removida. Inspecione-a antes de descartá-la.\n";
    }finally{if(is_file($secret))unlink($secret);}
}catch(Throwable $e){
    error_log('Bemora restore verification: '.$e->getMessage());
    fwrite(STDERR,"Verificação/restauração falhou: ".$e->getMessage()."\n");
    exit(1);
}

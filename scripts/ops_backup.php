<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit(1);}
require_once __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/operations.php';

/** Run daily with the operations runner; no HTTP execution or secrets on argv. */
function bemora_ops_backup():array {
    $root=bemora_ops_dir();
    if(!function_exists('gzopen')||!class_exists(ZipArchive::class)||!function_exists('proc_open'))
        throw new RuntimeException('O servidor precisa de zlib, ZipArchive e proc_open.');
    $base=$root.'/backups';
    if(!is_dir($base) && !mkdir($base,0700,true))throw new RuntimeException('Não foi possível criar a pasta de backups.');
    chmod($base,0700);
    $id=gmdate('Ymd_His').'_'.bin2hex(random_bytes(4));
    $staging=$base.'/.partial_'.$id;
    if(!mkdir($staging,0700))throw new RuntimeException('Não foi possível preparar o backup.');
    $secret=$staging.'/mysql.cnf';$stderr=$staging.'/dump-error.log';$sqlFile=$staging.'/database.sql.gz';
    $archive=$staging.'/files.zip';
    $success=false;
    try{
        $connection=[
          'host'=>(string)config('db','host','localhost'),
          'port'=>(int)config('db','port',3306),
          'user'=>(string)config('db','user',''),
          'password'=>(string)config('db','pass',''),
          'database'=>(string)config('db','name','')
        ];
        if(!preg_match('/^[a-zA-Z0-9_]+$/D',$connection['database'])||$connection['user']==='')
            throw new RuntimeException('Configuração do banco inválida.');
        foreach(['host','user','password'] as $part){
            if(preg_match('/[\r\n]/',$connection[$part]))throw new RuntimeException('Credenciais de banco em formato inválido.');
        }
        // A short-lived 0600 defaults file prevents password disclosure in ps output.
        $escape=static fn(string $val):string=>'"'.str_replace(['\\','"'],['\\\\','\\"'],$val).'"';
        $ini="[client]\n"."host=".$escape($connection['host'])."\nport=".$connection['port']."\nuser=".$escape($connection['user'])."\npassword=".$escape($connection['password'])."\n";
        if(file_put_contents($secret,$ini)===false||!chmod($secret,0600))throw new RuntimeException('Não foi possível proteger as credenciais temporárias.');
        $binary=(string)config('operations','mysqldump_binary','mysqldump');
        if(!preg_match('~^(?:/[A-Za-z0-9_./-]+|[A-Za-z0-9_-]+)$~D',$binary))throw new RuntimeException('Executável de dump inválido.');
        $command=[$binary,'--defaults-extra-file='.$secret,'--single-transaction','--quick','--skip-lock-tables','--triggers','--hex-blob','--default-character-set=utf8mb4',$connection['database']];
        $descriptors=[0=>['pipe','r'],1=>['pipe','w'],2=>['file',$stderr,'w']];
        $pipes=[];$process=proc_open($command,$descriptors,$pipes,dirname(__DIR__));
        if(!is_resource($process))throw new RuntimeException('mysqldump indisponível.');
        fclose($pipes[0]);$gz=gzopen($sqlFile,'wb6');
        if(!$gz){fclose($pipes[1]);proc_close($process);throw new RuntimeException('Não foi possível gerar o SQL comprimido.');}
        $bytes=0;$readFailed=false;
        try{
            while(!feof($pipes[1])){
                $data=fread($pipes[1],65536);
                if($data===false){$readFailed=true;break;}
                if($data==='')continue;
                $bytes+=strlen($data);
                if(gzwrite($gz,$data)!==strlen($data)){$readFailed=true;break;}
            }
        }finally{gzclose($gz);fclose($pipes[1]);}
        $exit=proc_close($process);
        if($readFailed||$exit!==0||$bytes<200)throw new RuntimeException('mysqldump falhou; backup descartado. Confira as permissões do usuário MySQL e o log protegido no servidor.');
        @unlink($secret);@unlink($stderr);
        chmod($sqlFile,0600);
        $zip=new ZipArchive();
        if($zip->open($archive,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Não foi possível criar o arquivo ZIP.');
        $entryCount=0;
        try{
            foreach(['storage/receipts','storage/avatars','assets/brand'] as $relative){
                $directory=dirname(__DIR__).'/'.$relative;
                if(!is_dir($directory))continue;
                $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory,FilesystemIterator::SKIP_DOTS));
                foreach($iterator as $entry){
                    if($entry->isLink())throw new RuntimeException('Links simbólicos não são aceitos no backup.');
                    if(!$entry->isFile())continue;
                    $name=$relative.'/'.substr($entry->getPathname(),strlen($directory)+1);
                    if(!$zip->addFile($entry->getPathname(),$name))throw new RuntimeException('Falha ao incluir arquivo no backup.');
                    $entryCount++;
                }
            }
        }finally{$zip->close();}
        chmod($archive,0600);
        $manifest=['format'=>'bemora-ops-v1','created_at'=>gmdate('c'),'database'=>$connection['database'],'archive_entries'=>$entryCount,
          'files'=>[
            'database.sql.gz'=>['bytes'=>filesize($sqlFile),'sha256'=>hash_file('sha256',$sqlFile)],
            'files.zip'=>['bytes'=>filesize($archive),'sha256'=>hash_file('sha256',$archive)]
          ]];
        $manifestFile=$staging.'/manifest.json';
        file_put_contents($manifestFile,json_encode($manifest,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));chmod($manifestFile,0600);
        bemora_ops_verify_backup($staging);
        $final=$base.'/'.$id;
        if(!rename($staging,$final))throw new RuntimeException('Não foi possível concluir a publicação do backup.');
        $success=true;
        // Keep recent successful backups; off-site encrypted copies remain an operator responsibility.
        $days=max(7,min(90,(int)config('operations','retention_days',14)));
        foreach(glob($base.'/*',GLOB_ONLYDIR)?:[] as $old){
            if(!preg_match('/^\d{8}_\d{6}_[a-f0-9]{8}$/D',basename($old))||is_link($old))continue;
            if(filemtime($old)>time()-$days*86400)continue;
            foreach(['database.sql.gz','files.zip','manifest.json'] as $name)if(is_file($old.'/'.$name))unlink($old.'/'.$name);
            @rmdir($old);
        }
        return ['name'=>$id,'created_at'=>$manifest['created_at'],'bytes'=>(int)$manifest['files']['database.sql.gz']['bytes']+(int)$manifest['files']['files.zip']['bytes'],'archive_entries'=>$entryCount];
    }finally{
        if(!$success){
            foreach(['mysql.cnf','dump-error.log','database.sql.gz','files.zip','manifest.json'] as $file)if(is_file($staging.'/'.$file))@unlink($staging.'/'.$file);
            @rmdir($staging);
        }
    }
}
if(realpath((string)($_SERVER['SCRIPT_FILENAME']??''))===__FILE__){
    try{$backup=bemora_ops_backup();echo 'Backup verificado: '.$backup['name'].PHP_EOL;exit(0);}
    catch(Throwable $e){error_log('Bemora backup failure: '.$e->getMessage());fwrite(STDERR,"Backup falhou. Verifique o log PHP privado.\n");exit(1);}
}

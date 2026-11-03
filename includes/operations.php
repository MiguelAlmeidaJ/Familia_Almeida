<?php
declare(strict_types=1);

/**
 * Operations storage must be explicitly configured OUTSIDE the web project.
 * All operational files contain sensitive metadata and must remain private.
 */
function bemora_ops_dir():string {
    $configured=(string)config('operations','private_dir','');
    if($configured===''||$configured[0]!=='/')throw new RuntimeException('Configure operations.private_dir com um caminho absoluto fora do site.');
    $path=realpath($configured);
    $project=realpath(dirname(__DIR__));
    if($path===false||$project===false||!is_dir($path)||is_link($configured)
       ||$path===$project||str_starts_with($path,$project.'/')
       ||str_starts_with($project,$path.'/'))
        throw new RuntimeException('O diretório de operações deve existir fora da pasta pública e não pode ser ancestral do projeto.');
    $permissions=fileperms($path);
    if($permissions===false||($permissions & 0077)!==0)throw new RuntimeException('Proteja o diretório de operações com chmod 700.');
    if(!is_writable($path))throw new RuntimeException('Diretório de operações sem permissão de escrita.');
    return $path;
}
function bemora_ops_status(string $task):?array {
    if(!in_array($task,['backup','billing','plans'],true))throw new InvalidArgumentException('Rotina desconhecida.');
    $path=bemora_ops_dir().'/status-'.$task.'.json';
    if(!is_file($path)||is_link($path))return null;
    $data=json_decode((string)file_get_contents($path),true);
    return is_array($data)?$data:null;
}
function bemora_ops_write_status(string $task,array $status):void {
    if(!in_array($task,['backup','billing','plans'],true))throw new InvalidArgumentException('Rotina desconhecida.');
    $directory=bemora_ops_dir();
    $file=$directory.'/status-'.$task.'.json';
    $temp=tempnam($directory,'.status-');
    if(!$temp)throw new RuntimeException('Falha ao preparar estado de monitoramento.');
    try{
        chmod($temp,0600);
        if(file_put_contents($temp,json_encode($status,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE))===false)throw new RuntimeException('Falha ao salvar estado do monitoramento.');
        if(!rename($temp,$file))throw new RuntimeException('Falha ao publicar estado do monitoramento.');
    }finally{if(is_file($temp))unlink($temp);}
}
function bemora_ops_verify_backup(string $backupDir):array {
    $base=realpath($backupDir);
    $root=bemora_ops_dir();
    if($base===false||!str_starts_with($base,$root.'/backups/')||is_link($backupDir))
        throw new RuntimeException('Caminho do backup não permitido.');
    $manifestFile=$base.'/manifest.json';
    if(!is_file($manifestFile)||is_link($manifestFile))throw new RuntimeException('Manifesto de backup ausente.');
    $manifest=json_decode((string)file_get_contents($manifestFile),true);
    if(!is_array($manifest)||($manifest['format']??'')!=='bemora-ops-v1')throw new RuntimeException('Formato de backup não reconhecido.');
    foreach(['database.sql.gz','files.zip'] as $name){
        $expected=$manifest['files'][$name]['sha256']??null;
        $size=$manifest['files'][$name]['bytes']??null;
        $path=$base.'/'.$name;
        if(!is_string($expected)||!preg_match('/^[a-f0-9]{64}$/D',$expected)||!is_file($path)||is_link($path)||
          filesize($path)!==(int)$size||!hash_equals($expected,(string)hash_file('sha256',$path)))
            throw new RuntimeException('Integridade do backup comprometida: '.$name);
    }
    if(!function_exists('gzopen')||!class_exists(ZipArchive::class))throw new RuntimeException('Extensões zlib e zip são necessárias.');
    $gz=gzopen($base.'/database.sql.gz','rb');
    if(!$gz)throw new RuntimeException('Falha ao abrir SQL comprimido.');
    $bytes=0;try{
        while(!gzeof($gz)){
            $chunk=gzread($gz,65536);
            if($chunk===false)throw new RuntimeException('Falha na leitura do SQL comprimido.');
            $bytes+=strlen($chunk);
        }
    }finally{gzclose($gz);}
    if($bytes===0)throw new RuntimeException('SQL vazio.');
    $zip=new ZipArchive();
    if($zip->open($base.'/files.zip')!==true)throw new RuntimeException('Arquivos ZIP inválidos.');
    try{
        for($i=0;$i<$zip->numFiles;$i++){
            $stat=$zip->statIndex($i);
            if(!is_array($stat)||!is_string($stat['name'])||
              (!str_starts_with($stat['name'],'storage/receipts/')&&!str_starts_with($stat['name'],'storage/avatars/')&&!str_starts_with($stat['name'],'assets/brand/'))||
              str_contains($stat['name'],'../')||str_starts_with($stat['name'],'/'))
                throw new RuntimeException('Caminho inesperado no backup de arquivos.');
            $stream=$zip->getStream($stat['name']);
            if(!$stream)throw new RuntimeException('Arquivo ZIP ilegível.');
            while(!feof($stream)){if(fread($stream,65536)===false)throw new RuntimeException('Arquivo ZIP corrompido.');}
            fclose($stream);
        }
    }finally{$zip->close();}
    return ['created_at'=>$manifest['created_at']??null,'sql_uncompressed_bytes'=>$bytes,'archive_entries'=>$manifest['archive_entries']??0,'database'=>$manifest['database']??''];
}

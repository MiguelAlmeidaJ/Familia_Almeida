<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$opsTestDir=sys_get_temp_dir().'/bemora-ops-test-'.bin2hex(random_bytes(6));
mkdir($opsTestDir,0700);chmod($opsTestDir,0700);
$GLOBALS['opsTestDirectory']=$opsTestDir;
function config(string $section,?string $key=null,mixed $default=null):mixed {
    if($section==='operations'&&$key==='private_dir')return $GLOBALS['opsTestDirectory'];
    return $default;
}
require_once __DIR__.'/../includes/operations.php';
function ops_check(bool $ok,string $description):void {
    if(!$ok)throw new RuntimeException($description);
    echo "[PASS] ".$description."\n";
}
try{
 ops_check(bemora_ops_dir()===realpath($opsTestDir),'private operations directory accepted');
 $base=$opsTestDir.'/backups/20261103_010000_1234abcd';
 mkdir($opsTestDir.'/backups',0700);mkdir($base,0700);
 $sql='-- Backup fixture only'."\n".str_repeat('INSERT INTO t VALUES (1);'."\n",12);
 file_put_contents($base.'/database.sql.gz',gzencode($sql));
 $zip=new ZipArchive();
 if($zip->open($base.'/files.zip',ZipArchive::CREATE)!==true)throw new RuntimeException('zip test unavailable');
 $zip->addFromString('storage/receipts/example.txt','Example fixture');
 $zip->close();
 $files=[];
 foreach(['database.sql.gz','files.zip'] as $file)
  $files[$file]=['bytes'=>filesize($base.'/'.$file),'sha256'=>hash_file('sha256',$base.'/'.$file)];
 file_put_contents($base.'/manifest.json',json_encode(['format'=>'bemora-ops-v1','created_at'=>gmdate('c'),'database'=>'test_only','archive_entries'=>1,'files'=>$files],JSON_THROW_ON_ERROR));
 $result=bemora_ops_verify_backup($base);
 ops_check($result['sql_uncompressed_bytes']===strlen($sql),'archive integrity and gzip decompression verified');
 ops_check($result['archive_entries']===1,'archived upload counted');
 file_put_contents($base.'/files.zip','tampered');
 $rejected=false;
 try{bemora_ops_verify_backup($base);}catch(RuntimeException $e){$rejected=true;}
 ops_check($rejected,'tampered archives rejected');
 $GLOBALS['opsTestDirectory']=dirname(__DIR__);
 $rejected=false;
 try{bemora_ops_dir();}catch(RuntimeException $e){$rejected=true;}
 ops_check($rejected,'public repository cannot be used as private storage');
}finally{
 $base=$opsTestDir.'/backups/20261103_010000_1234abcd';
 foreach(['database.sql.gz','files.zip','manifest.json'] as $name)if(is_file($base.'/'.$name))unlink($base.'/'.$name);
 if(is_dir($base))rmdir($base);
 if(is_dir($opsTestDir.'/backups'))rmdir($opsTestDir.'/backups');
 if(is_dir($opsTestDir))rmdir($opsTestDir);
}

// Simulated alert templates are also validated by the existing operations CI job.
require __DIR__.'/operations_alert_cli.php';

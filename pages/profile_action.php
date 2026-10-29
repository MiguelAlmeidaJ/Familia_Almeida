<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';
$user=require_family_access();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Método não permitido.');}
verify_csrf();
$pdo=db();$uid=(int)$user['id'];
$action=(string)($_POST['action']??'');
try{
 if($action==='save_profile'){
  $name=trim(preg_replace('/\s+/u',' ',(string)($_POST['name']??''))??'');
  $email=strtolower(trim((string)($_POST['email']??'')));
  $phone=trim((string)($_POST['phone']??''));
  if($name===''||mb_strlen($name)>80||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>160)
   throw new RuntimeException('Informe nome e e-mail válidos.');
  if($phone!==''&&(!preg_match('/^[+0-9()\s-]{8,25}$/',$phone)))throw new RuntimeException('Telefone inválido.');
  $titles=$_POST['job_title']??[];$salaries=$_POST['salary']??[];$days=$_POST['payday']??[];
  if(!is_array($titles)||!is_array($salaries)||!is_array($days)||count($titles)>12||count($titles)!==count($salaries)||count($titles)!==count($days))
   throw new RuntimeException('Fontes de renda inválidas (máximo 12).');
  $sources=[];
  foreach($titles as $i=>$raw){
   $title=trim((string)$raw);$value=trim((string)$salaries[$i]);$day=(int)$days[$i];
   if($title===''&&$value===''&&$day===0)continue;
   $normalized=str_replace(',','.',$value);
   if($title===''||mb_strlen($title)>120||!preg_match('/^\d+(?:\.\d{1,2})?$/',$normalized)||!is_numeric($normalized)|| (float)$normalized>9999999999||$day<1||$day>31)
    throw new RuntimeException('Revise emprego, salário e dia de recebimento.');
   $sources[]=[$title,$normalized,$day];
  }
  $pdo->beginTransaction();
  $q=$pdo->prepare('SELECT id FROM users WHERE email=? AND id<>? LIMIT 1');$q->execute([$email,$uid]);
  if($q->fetchColumn())throw new RuntimeException('Este e-mail já está em uso.');
  $pdo->prepare('UPDATE users SET name=?,email=?,phone=? WHERE id=?')->execute([$name,$email,$phone?:null,$uid]);
  $pdo->prepare('DELETE FROM user_income_sources WHERE user_id=?')->execute([$uid]);
  $ins=$pdo->prepare('INSERT INTO user_income_sources(user_id,job_title,salary,payday) VALUES(?,?,?,?)');
  foreach($sources as $source)$ins->execute([$uid,...$source]);
  $pdo->commit();
  flash('success','Seu perfil foi atualizado.');
 }elseif($action==='save_avatar'){
  $image=(string)($_POST['avatar_image']??'');
  if(strlen($image)>3000000||!preg_match('~^data:image/jpeg;base64,([A-Za-z0-9+/=]+)$~',$image,$matches))
   throw new RuntimeException('Imagem inválida. Recorte a foto novamente.');
  $bytes=base64_decode($matches[1],true);
  if($bytes===false||strlen($bytes)>1800000||strlen($bytes)<1000)throw new RuntimeException('Imagem muito grande ou inválida.');
  $info=@getimagesizefromstring($bytes);
  if(!$info||$info[0]!==1080||$info[1]!==1080||$info['mime']!=='image/jpeg')
   throw new RuntimeException('A foto deve ter exatamente 1080 × 1080 pixels em JPEG.');
  $dir=dirname(__DIR__).'/storage/avatars';
  if(!is_dir($dir)&&!mkdir($dir,0750,true)&&!is_dir($dir))throw new RuntimeException('Pasta de fotos indisponível.');
  $filename=bin2hex(random_bytes(24)).'.jpg';
  $destination=$dir.'/'.$filename;
  if(file_put_contents($destination,$bytes,LOCK_EX)!==strlen($bytes))throw new RuntimeException('Não foi possível salvar a foto.');
  @chmod($destination,0640);
  try{
    $pdo->beginTransaction();
    $q=$pdo->prepare('SELECT avatar_path FROM users WHERE id=? FOR UPDATE');$q->execute([$uid]);$previous=$q->fetchColumn();
    $pdo->prepare('UPDATE users SET avatar_path=? WHERE id=?')->execute([$filename,$uid]);
    $pdo->commit();
    if(is_string($previous)&&preg_match('/^[a-f0-9]{48}\.jpg$/',$previous))@unlink($dir.'/'.$previous);
  }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();@unlink($destination);throw $e;}
  flash('success','Foto do perfil atualizada.');
 }elseif($action==='remove_avatar'){
  $q=$pdo->prepare('SELECT avatar_path FROM users WHERE id=?');$q->execute([$uid]);$previous=$q->fetchColumn();
  $pdo->prepare('UPDATE users SET avatar_path=NULL WHERE id=?')->execute([$uid]);
  if(is_string($previous)&&preg_match('/^[a-f0-9]{48}\.jpg$/',$previous))@unlink(dirname(__DIR__).'/storage/avatars/'.$previous);
  flash('success','Foto removida.');
 }else throw new RuntimeException('Ação inválida.');
}catch(Throwable $e){
 if($pdo->inTransaction())$pdo->rollBack();
 flash('error',$e instanceof PDOException?'Não foi possível salvar. Confira os dados e tente novamente.':$e->getMessage());
}
redirect_to('/meu-perfil');

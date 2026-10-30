<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';
$user=require_family_access();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Método não permitido.');}
verify_csrf();
$pdo=db();$uid=(int)$user['id'];
$action=(string)($_POST['action']??'');
if($action==='check_password'){
 header('Content-Type: application/json; charset=utf-8');
 header('Cache-Control: no-store');
 $limit=$_SESSION['password_check_limit']??['since'=>time(),'count'=>0];
 if(time()-(int)$limit['since']>900)$limit=['since'=>time(),'count'=>0];
 $limit['count']++;
 $_SESSION['password_check_limit']=$limit;
 if($limit['count']>20){http_response_code(429);echo json_encode(['valid'=>false,'message'=>'Muitas tentativas. Tente novamente mais tarde.']);exit;}
 $stmt=$pdo->prepare('SELECT password_hash FROM users WHERE id=?');
 $stmt->execute([$uid]);
 $hash=$stmt->fetchColumn();
 $correct=is_string($hash)&&password_verify((string)($_POST['current_password']??''),$hash);
 echo json_encode(['valid'=>$correct,'message'=>$correct?'Senha atual confirmada.':'Senha atual incorreta.'],JSON_UNESCAPED_UNICODE);
 exit;
}
try{
 if($action==='save_profile'){
  $name=trim(preg_replace('/\s+/u',' ',(string)($_POST['name']??''))??'');
  $email=strtolower(trim((string)($_POST['email']??'')));
  $phone=trim((string)($_POST['phone']??''));
  $phoneDigits=preg_replace('/\D/','',$phone)??'';
  if($phone!=='' && !in_array(strlen($phoneDigits),[10,11],true))throw new RuntimeException('Informe um telefone com DDD válido.');
  $phone=$phoneDigits===''?'':(strlen($phoneDigits)===11?sprintf('(%s) %s-%s',substr($phoneDigits,0,2),substr($phoneDigits,2,5),substr($phoneDigits,7)):sprintf('(%s) %s-%s',substr($phoneDigits,0,2),substr($phoneDigits,2,4),substr($phoneDigits,6)));
  if($name===''||mb_strlen($name)>80||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>160)
   throw new RuntimeException('Informe nome e e-mail válidos.');
  if($phone!==''&&(!preg_match('/^[+0-9()\s-]{8,25}$/',$phone)))throw new RuntimeException('Telefone inválido.');
  $titles=$_POST['job_title']??[];$salaries=$_POST['salary']??[];$days=$_POST['payday']??[];$ids=$_POST['source_id']??[];
  $effective=(string)($_POST['effective_month']??date('Y-m'));
  if(!preg_match('/^\\d{4}-(0[1-9]|1[0-2])$/D',$effective)||$effective!==selected_month())throw new RuntimeException('O mês da alteração deve ser o mês selecionado no cabeçalho.');
  if(!is_array($titles)||!is_array($salaries)||!is_array($days)||!is_array($ids)||count($titles)>12||count($titles)!==count($salaries)||count($titles)!==count($days)||count($titles)!==count($ids))
   throw new RuntimeException('Fontes de renda inválidas (máximo 12).');
  $sources=[];
  foreach($titles as $i=>$raw){
   $title=trim((string)$raw);$value=trim((string)$salaries[$i]);$day=(int)$days[$i];$sourceId=(int)$ids[$i];
   $normalized=trim(preg_replace('/\\s+/u','',str_replace('R$','',$value))??'');
   if(str_contains($normalized,','))$normalized=str_replace(',','.',str_replace('.','',$normalized));
   if($title===''||mb_strlen($title)>120||!preg_match('/^\\d+(?:\\.\\d{1,2})?$/D',$normalized)||(float)$normalized>9999999999||$day<1||$day>31||$sourceId<0)
    throw new RuntimeException('Revise emprego, salário e dia de recebimento.');
   $sources[]=[$sourceId,$title,$normalized,$day];
  }
  $pdo->beginTransaction();
  $q=$pdo->prepare('SELECT id FROM users WHERE email=? AND id<>? LIMIT 1');$q->execute([$email,$uid]);
  if($q->fetchColumn())throw new RuntimeException('Este e-mail já está em uso.');
  $pdo->prepare('UPDATE users SET name=?,email=?,phone=? WHERE id=?')->execute([$name,$email,$phone?:null,$uid]);
  $memberStartStmt=$pdo->prepare('SELECT created_at FROM users WHERE id=?');
  $memberStartStmt->execute([$uid]);$createdMonth=substr((string)$memberStartStmt->fetchColumn(),0,7);
  if($effective<$createdMonth)throw new RuntimeException('Não é possível alterar rendas antes da criação do usuário.');
  $known=$pdo->prepare('SELECT id,job_title,salary,payday,ended_month FROM user_income_sources WHERE user_id=? FOR UPDATE');
  $known->execute([$uid]);$existing=[];foreach($known->fetchAll(PDO::FETCH_ASSOC) as $row)$existing[(int)$row['id']]=$row;
  $updated=[];
  $ins=$pdo->prepare('INSERT INTO user_income_sources(user_id,job_title,salary,payday) VALUES(?,?,?,?)');
  $up=$pdo->prepare('UPDATE user_income_sources SET job_title=?,salary=?,payday=?,ended_month=NULL WHERE user_id=? AND id=?');
  $ver=$pdo->prepare('INSERT INTO user_income_source_versions(source_id,user_id,valid_from,job_title,salary,payday) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE job_title=VALUES(job_title),salary=VALUES(salary),payday=VALUES(payday)');
  $versionAt=$pdo->prepare('SELECT job_title,salary,payday FROM user_income_source_versions WHERE source_id=? AND valid_from<=? ORDER BY valid_from DESC LIMIT 1');
  $latestAt=$pdo->prepare('SELECT MAX(valid_from) FROM user_income_source_versions WHERE source_id=?');
  foreach($sources as [$id,$title,$salary,$payday]){
    if($id){
      if(!isset($existing[$id])||isset($updated[$id]))throw new RuntimeException('Fonte de renda inválida.');
      $row=$existing[$id];
      $versionAt->execute([$id,$effective]);$currentVersion=$versionAt->fetch(PDO::FETCH_ASSOC);
      if(!$currentVersion)throw new RuntimeException('A fonte ainda não existia neste mês.');
      $changed=$currentVersion['job_title']!==$title||abs((float)$currentVersion['salary']-(float)$salary)>0.001||(int)$currentVersion['payday']!==$payday;
      if($changed){
        $ver->execute([$id,$uid,$effective,$title,$salary,$payday]);
        $latestAt->execute([$id]);$latest=(string)$latestAt->fetchColumn();
        if($latest===$effective)$up->execute([$title,$salary,$payday,$uid,$id]);
      }
      if($row['ended_month']!==null && $row['ended_month']<$effective){
        throw new RuntimeException('Não é possível reativar uma fonte encerrada em um mês anterior por este formulário.');
      }
    }else{
      $ins->execute([$uid,$title,$salary,$payday]);$id=(int)$pdo->lastInsertId();
      $ver->execute([$id,$uid,$effective,$title,$salary,$payday]);
    }
    $updated[$id]=true;
  }
  foreach($existing as $id=>$row){
    if(isset($updated[$id]))continue;
    $versionAt->execute([$id,$effective]);
    if(!$versionAt->fetch())continue; // Future sources must not be ended retroactively.
    $end=(new DateTimeImmutable($effective.'-01'))->modify('-1 month')->format('Y-m');
    if($row['ended_month']===null || $row['ended_month']>$end)
      $pdo->prepare('UPDATE user_income_sources SET ended_month=? WHERE id=? AND user_id=?')->execute([$end,$id,$uid]);
  }
  $pdo->commit();
  flash('success','Seu perfil foi atualizado.');
 }elseif($action==='change_password'){
  $current=(string)($_POST['current_password']??'');
  $next=(string)($_POST['new_password']??'');
  $confirm=(string)($_POST['confirm_password']??'');
  $q=$pdo->prepare('SELECT password_hash FROM users WHERE id=?');$q->execute([$uid]);
  $hash=$q->fetchColumn();
  if(!$hash||!password_verify($current,(string)$hash))throw new RuntimeException('Senha atual incorreta.');
  if(strlen($next)<8||strlen($next)>16||!preg_match('/[A-Z]/',$next)||!preg_match('/[a-z]/',$next)||!preg_match('/[0-9]/',$next)||!preg_match('/[^A-Za-z0-9\\s]/',$next))
   throw new RuntimeException('A nova senha deve ter 8 a 16 caracteres, com maiúscula, minúscula, número e caractere especial.');
  if(!hash_equals($next,$confirm))throw new RuntimeException('As senhas não coincidem.');
  $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($next,PASSWORD_DEFAULT),$uid]);
  session_regenerate_id(true);
  flash('success','Sua senha foi atualizada com sucesso.');
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

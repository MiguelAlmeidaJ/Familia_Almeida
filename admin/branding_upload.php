<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/branding.php';
require_admin_access();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Método não permitido.'); }
verify_csrf();
$kind=(string)($_POST['asset_kind']??'');
if (!in_array($kind,['logo','favicon'],true)) { http_response_code(400); exit('Tipo inválido.'); }
$file=$_FILES['brand_file']??null;
if (!is_array($file) || ($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_uploaded_file((string)($file['tmp_name']??''))) {
 flash('error','Não foi possível receber o arquivo.'); redirect_to('/admin/configuracoes');
}
if (($file['size']??0)<1 || $file['size']>2097152) {
 flash('error','Tamanho máximo: 2 MB.'); redirect_to('/admin/configuracoes');
}
$tmp=(string)$file['tmp_name'];
$info=@getimagesize($tmp);
$mime=$info['mime']??'';
$extensions=['image/png'=>'png','image/webp'=>'webp'];
if (!isset($extensions[$mime]) || ($info[0]??0)<16 || ($info[1]??0)<16 || ($info[0]??0)>4096 || ($info[1]??0)>4096) {
 flash('error','Envie imagem PNG ou WebP válida entre 16 e 4096 pixels.'); redirect_to('/admin/configuracoes');
}
if ($kind==='favicon' && (($info[0]??0)>1024 || ($info[1]??0)>1024)) {
 flash('error','Favicon deve ter até 1024 × 1024 pixels.'); redirect_to('/admin/configuracoes');
}
$dir=dirname(__DIR__) . '/assets/brand';
if (!is_dir($dir) && !mkdir($dir,0755,true) && !is_dir($dir)) {
 flash('error','Pasta de identidade visual indisponível.'); redirect_to('/admin/configuracoes');
}
$ext=$extensions[$mime];
$target=$dir.'/'.$kind.'.'.$ext;
$staging=$dir.'/.'.bin2hex(random_bytes(12)).'.tmp';
if (!move_uploaded_file($tmp,$staging)) {
 flash('error','Falha ao salvar a imagem. Confira as permissões da pasta.'); redirect_to('/admin/configuracoes');
}
chmod($staging,0644);
if (!rename($staging,$target)) {
 @unlink($staging); flash('error','Falha ao publicar a imagem.'); redirect_to('/admin/configuracoes');
}
foreach (['png','webp'] as $other) {
 if ($other!==$ext) @unlink($dir.'/'.$kind.'.'.$other);
}
flash('success',($kind==='logo'?'Logo':'Favicon').' atualizado com sucesso.');
redirect_to('/admin/configuracoes');

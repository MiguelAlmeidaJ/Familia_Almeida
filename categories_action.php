<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/categories.php';
$user=require_family_access();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Método não permitido.'); }
verify_csrf();
$pdo = db();
$tenantId=(int)$user['tenant_id'];
try {
 if(!categories_ready($pdo)) throw new RuntimeException('Execute a migration de categorias em Configurações > Manutenção.');
 $action = (string)($_POST['action'] ?? '');
 if($action === 'create') {
  $type=(string)($_POST['type'] ?? '');
  $name=category_normalize((string)($_POST['name'] ?? ''));
  if(!in_array($type,['income','expense'],true)) throw new RuntimeException('Tipo de categoria inválido.');
  category_ensure($pdo,$name,$type);
  flash('success','Categoria salva.');
 } elseif($action === 'remove') {
  $id=filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
  if(!$id || $id<1) throw new RuntimeException('Categoria inválida.');
  $pdo->prepare('UPDATE categories SET active=0 WHERE id=? AND tenant_id=?')->execute([$id,$tenantId]);
  flash('success','Categoria removida do cadastro. Histórico preservado.');
 } else throw new RuntimeException('Ação inválida.');
} catch(Throwable $e) { flash('error',$e->getMessage()); }
redirect_to('/categorias');

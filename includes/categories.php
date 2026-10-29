<?php
declare(strict_types=1);
require_once __DIR__.'/tenant.php';
function categories_ready(PDO $pdo):bool {
 $stmt=$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='categories'");
 return (int)$stmt->fetchColumn()>0;
}
function category_list(PDO $pdo):array {
 if(!categories_ready($pdo)) return ['income'=>[],'expense'=>[]];
 $stmt=$pdo->prepare("SELECT id,name,type FROM categories WHERE tenant_id=? AND active=1 ORDER BY name");
 $stmt->execute([require_tenant_id()]);$rows=$stmt->fetchAll();
 $result=['income'=>[],'expense'=>[]];
 foreach($rows as $row) $result[$row['type']][]=$row;
 return $result;
}
function category_normalize(string $value):string {
 return trim(preg_replace('/\s+/u',' ',$value) ?? $value);
}
function category_ensure(PDO $pdo,string $value,string $type):string {
 $name=category_normalize($value);
 if(!in_array($type,['income','expense'],true) || $name==='' || strlen($name)>100) throw new RuntimeException('Categoria inválida.');
 if(!categories_ready($pdo)) return $name;
 $stmt=$pdo->prepare('SELECT id,name FROM categories WHERE tenant_id=? AND type=? AND LOWER(name)=LOWER(?) LIMIT 1');
 $tenantId=require_tenant_id();
 $stmt->execute([$tenantId,$type,$name]);
 $existing=$stmt->fetch();
 if($existing) {
  $pdo->prepare('UPDATE categories SET active=1 WHERE id=? AND tenant_id=?')->execute([$existing['id'],$tenantId]);
  return (string)$existing['name'];
 }
 $pdo->prepare('INSERT INTO categories(tenant_id,name,type) VALUES (?,?,?)')->execute([$tenantId,$name,$type]);
 return $name;
}

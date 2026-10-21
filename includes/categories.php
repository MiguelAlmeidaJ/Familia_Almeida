<?php
declare(strict_types=1);
function categories_ready(PDO $pdo):bool {
 $stmt=$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='categories'");
 return (int)$stmt->fetchColumn()>0;
}
function category_list(PDO $pdo):array {
 if(!categories_ready($pdo)) return ['income'=>[],'expense'=>[]];
 $rows=$pdo->query("SELECT id,name,type FROM categories WHERE active=1 ORDER BY name")->fetchAll();
 $result=['income'=>[],'expense'=>[]];
 foreach($rows as $row) $result[$row['type']][]=$row;
 return $result;
}
function category_normalize(string $value):string {
 return trim(preg_replace('/\s+/u',' ',$value) ?? $value);
}
function category_ensure(PDO $pdo,string $value,string $type):string {
 $name=category_normalize($value);
 if(!in_array($type,['income','expense'],true) || $name==='' || mb_strlen($name,'UTF-8')>100) throw new RuntimeException('Categoria inválida.');
 if(!categories_ready($pdo)) return $name;
 $stmt=$pdo->prepare('SELECT id,name FROM categories WHERE type=? AND LOWER(name)=LOWER(?) LIMIT 1');
 $stmt->execute([$type,$name]);
 $existing=$stmt->fetch();
 if($existing) {
  $pdo->prepare('UPDATE categories SET active=1 WHERE id=?')->execute([$existing['id']]);
  return (string)$existing['name'];
 }
 $pdo->prepare('INSERT INTO categories(name,type) VALUES (?,?)')->execute([$name,$type]);
 return $name;
}

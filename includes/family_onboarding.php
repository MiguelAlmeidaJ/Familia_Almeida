<?php
declare(strict_types=1);
function bemora_activate_registration(PDO $pdo,int $registrationId,array $members,int $adminIndex,string $name,string $key,int $limit):array {
 $slug='familia-'.$registrationId.'-'.bin2hex(random_bytes(4));
 $stmt=$pdo->prepare("INSERT INTO tenants(name,slug,name_key,active,status,max_members,finance_mode) VALUES(?,?,?,1,'active',?,'shared')");
 $stmt->execute([$name,$slug,$key,$limit]);
 $tenant=(int)$pdo->lastInsertId();
 $insert=$pdo->prepare('INSERT INTO users(name,email,password_hash,sex,birth_date) VALUES(?,?,?,?,?)');
 $link=$pdo->prepare('INSERT INTO tenant_users(tenant_id,user_id,role) VALUES(?,?,?)');
 $adminId=0;
 foreach($members as $index=>$m){
  $insert->execute([$m['name'],$m['email'],$m['password_hash'],$m['sex'],$m['birth_date']]);
  $uid=(int)$pdo->lastInsertId();
  $link->execute([$tenant,$uid,$index===$adminIndex?'family_admin':'member']);
  if($index===$adminIndex)$adminId=$uid;
 }
 if($adminId<1)throw new RuntimeException('Administrador familiar não definido.');
 $pdo->prepare("UPDATE family_registrations SET state='approved',members_json='[]' WHERE id=?")->execute([$registrationId]);
 return ['tenant_id'=>$tenant,'user_id'=>$adminId];
}

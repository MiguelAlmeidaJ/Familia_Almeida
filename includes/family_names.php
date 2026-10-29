<?php
declare(strict_types=1);
function family_name_parts(string $input): array {
    $suffix=trim(preg_replace('/\s+/u',' ',$input) ?? '');
    $suffix=preg_replace('/^família\s+/iu','',$suffix) ?? $suffix;
    $suffix=trim($suffix);
    if($suffix==='' || mb_strlen($suffix)>150 || !preg_match('/^[\p{L}\p{M}\p{N}][\p{L}\p{M}\p{N} .-]*$/u',$suffix)) throw new InvalidArgumentException('Informe um nome válido para a família.');
    return [$suffix,'Família '.$suffix,mb_strtolower('Família '.$suffix,'UTF-8')];
}
function family_name_taken(PDO $pdo,string $key):bool {
    // Legacy families may have been saved simply as "Almeida", whereas new
    // registrations use "Família Almeida". Treat both representations alike.
    $suffix=preg_replace('/^família\s+/iu','',$key) ?? $key;
    $suffix=mb_strtolower(trim($suffix),'UTF-8');
    $full='família '.$suffix;
    $q=$pdo->prepare("SELECT 1 FROM tenants
        WHERE LOWER(TRIM(name)) IN (?,?)
           OR LOWER(TRIM(COALESCE(name_key,''))) IN (?,?)
        LIMIT 1");
    $q->execute([$suffix,$full,$suffix,$full]);
    if($q->fetchColumn())return true;
    $q=$pdo->prepare("SELECT 1 FROM family_registrations
        WHERE state='pending' AND
        (LOWER(TRIM(family_name)) IN (?,?) OR LOWER(TRIM(family_name_key)) IN (?,?))
        LIMIT 1");
    $q->execute([$suffix,$full,$suffix,$full]);
    return (bool)$q->fetchColumn();
}
function family_name_suggestions(PDO $pdo,string $suffix):array {
    $result=[];
    for($i=1;$i<=99 && count($result)<2;$i++){
        $candidate=$suffix.'-'.str_pad((string)$i,2,'0',STR_PAD_LEFT);
        try {[,,$key]=family_name_parts($candidate);}catch(InvalidArgumentException){break;}
        if(!family_name_taken($pdo,$key))$result[]=$candidate;
    }
    return $result;
}

<?php
declare(strict_types=1);

// Change these identifiers whenever the corresponding public text changes.
// New versions require a fresh, explicit review in config.php.
const BEMORA_TERMS_VERSION='2026-11-03-v2';
const BEMORA_PRIVACY_VERSION='2026-11-03-v2';

function bemora_legal_identity_ready():bool {
    $required=['company_name','company_tax_id','company_address','support_email','privacy_email'];
    foreach($required as $key)if(trim((string)config('legal',$key,''))==='')return false;
    foreach(['support_email','privacy_email'] as $key)
        if(filter_var((string)config('legal',$key,''),FILTER_VALIDATE_EMAIL)===false)return false;
    $tax=preg_replace('/\D/','',(string)config('legal','company_tax_id',''));
    if(strlen($tax)!==14)return false; // Brazilian company, including MEI, uses CNPJ.
    $address=trim((string)config('legal','company_address',''));
    if(mb_strlen($address)<12)return false;
    return true;
}

/**
 * This flag only reflects explicit operator approval of the exact document
 * versions and complete supplier identity. It is NOT a legal certification.
 */
function bemora_legal_approved():bool {
    return (string)config('legal','approved','0')==='1'
      && bemora_legal_identity_ready()
      && (string)config('legal','approved_terms_version','')===BEMORA_TERMS_VERSION
      && (string)config('legal','approved_privacy_version','')===BEMORA_PRIVACY_VERSION;
}
function bemora_legal_require_approval():void {
    if(!bemora_legal_approved())throw new RuntimeException('Contratação ainda não liberada: revise e aprove as versões atuais dos documentos legais.');
}
function bemora_legal_versions():array {
    return ['terms'=>BEMORA_TERMS_VERSION,'privacy'=>BEMORA_PRIVACY_VERSION];
}
function bemora_legal_label(string $key,string $fallback='Não informado'):string {
    return htmlspecialchars(trim((string)config('legal',$key,''))?:$fallback,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
}

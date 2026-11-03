<?php
declare(strict_types=1);
const BEMORA_TERMS_VERSION='2026-11-03-draft-1';
const BEMORA_PRIVACY_VERSION='2026-11-03-draft-1';

/**
 * Commercial acceptance can only be enforced when the contractual copy was
 * reviewed and the legal owner explicitly enables publication in config.php.
 */
function bemora_legal_approved():bool {
 return in_array((string)config('legal','approved','0'),['1','true','on'],true)
  && trim((string)config('legal','company_name',''))!==''
  && trim((string)config('legal','company_tax_id',''))!==''
  && filter_var((string)config('legal','privacy_email',''),FILTER_VALIDATE_EMAIL)!==false;
}
function bemora_legal_require_approval():void {
 if(!bemora_legal_approved())throw new RuntimeException('Contratação ainda não liberada: documentos legais aguardam publicação e revisão.');
}
function bemora_legal_versions():array {
 return ['terms'=>BEMORA_TERMS_VERSION,'privacy'=>BEMORA_PRIVACY_VERSION];
}
function bemora_legal_label(string $key,string $fallback='Não informado'):string {
 return htmlspecialchars(trim((string)config('legal',$key,''))?:$fallback,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
}

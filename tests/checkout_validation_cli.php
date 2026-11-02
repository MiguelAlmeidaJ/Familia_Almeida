<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/checkout_validation.php';
function assert_case(bool $condition,string $message):void {if(!$condition)throw new RuntimeException($message);echo "[PASS] $message\n";}
assert_case(bemora_checkout_valid_document('529.982.247-25'),'valid CPF');
assert_case(!bemora_checkout_valid_document('529.982.247-26'),'invalid CPF checksum');
assert_case(!bemora_checkout_valid_document('111.111.111-11'),'repeated CPF rejected');
assert_case(bemora_checkout_valid_document('11.222.333/0001-81'),'valid CNPJ');
assert_case(!bemora_checkout_valid_document('11.222.333/0001-82'),'invalid CNPJ checksum');
assert_case(bemora_checkout_valid_phone('(31) 99999-9999'),'mobile phone with DDD');
assert_case(bemora_checkout_valid_phone('+55 (31) 99999-9999'),'mobile with country prefix');
assert_case(!bemora_checkout_valid_phone('(31) 3333-4444'),'landline rejected');

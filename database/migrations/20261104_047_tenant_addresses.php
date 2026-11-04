<?php
declare(strict_types=1);
return [
 'description'=>'Endereço do espaço Bemora informado na etapa final do primeiro acesso',
 'up'=>static function(PDO $pdo):void {
  $pdo->exec("CREATE TABLE IF NOT EXISTS tenant_addresses (
    tenant_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    postal_code CHAR(8) NOT NULL,
    street VARCHAR(180) NOT NULL,
    state_code CHAR(2) NOT NULL,
    city VARCHAR(120) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_tenant_addresses_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 }
];

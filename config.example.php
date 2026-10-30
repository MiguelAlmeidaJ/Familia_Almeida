<?php

return [
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'familia_almeida',
        'user' => 'SEU_USUARIO_MYSQL',
        'pass' => 'SUA_SENHA_MYSQL',
        'charset' => 'utf8mb4',
    ],
    'mail' => [
        'host' => 'smtp.seudominio.com.br',
        'port' => 587,
        'encryption' => 'tls', // tls (587) ou ssl (465)
        'username' => 'seu-email@seudominio.com.br',
        'password' => 'SENHA_SMTP_NAO_COMMITAR',
        'from' => 'seu-email@seudominio.com.br',
        'from_name' => 'Bemora',
    ],
    'app' => [
        'name' => 'Bemora',
        'base_url' => 'https://familia.anoar.com.br',
        'install_key' => 'TROQUE-POR-UMA-CHAVE-LONGA-E-ALEATORIA',
    ],
];

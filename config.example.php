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
    'operations' => [
        // CRIE esta pasta fora de public_html e execute chmod 700 nela.
        // Exemplo: /home/USUARIO/bemora_private_operations
        'private_dir' => '',
        'alert_email' => '', // E-mail do administrador para falhas de cron e recuperação
        'retention_days' => 14, // Arquivos locais; mantenha cópias externas criptografadas
        'mysqldump_binary' => 'mysqldump', // ou /usr/bin/mariadb-dump
        'mysql_binary' => 'mysql', // ou /usr/bin/mariadb
        'restore_test_database' => '', // Banco de homologação vazio e DIFERENTE do banco principal
    ],
    'support' => [
        'email' => 'suporte@seudominio.com.br', // Destinatário dos relatos de suporte
    ],
    'asaas' => [
        'enabled' => '0', // Liberar depois de validar sandbox e webhooks
        'environment' => 'sandbox', // sandbox ou production
        'production_enabled' => '0', // Segunda trava antes de cobranças reais
        'cancellation_production_enabled' => '0', // Habilitar cancelamento em produção somente após homologação no Asaas
        'api_key' => 'CHAVE_PRIVADA_ASAAS',
        'webhook_token' => 'TOKEN_WEBHOOK_EXCLUSIVO_MINIMO_32_CARACTERES',
        'invite_secret' => 'SEGREDO_INDEPENDENTE_MINIMO_32_CARACTERES',
        'system_admin_user_id' => 0, // ID válido do admin global
    ],
    'app' => [
        'name' => 'Bemora',
        'base_url' => 'https://familia.anoar.com.br',
        'install_key' => 'TROQUE-POR-UMA-CHAVE-LONGA-E-ALEATORIA',
    ],
];

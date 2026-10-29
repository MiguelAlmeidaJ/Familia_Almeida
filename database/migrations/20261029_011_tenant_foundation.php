<?php
declare(strict_types=1);

/**
 * Phase 1: identity and legacy data ownership.
 * Does NOT enable onboarding additional tenants. Existing queries still need
 * tenant authorization and filtering before any second family can be created.
 */
return [
    'description' => 'Criar familias, vinculos e vincular todos os dados legados a Familia Almeida',
    'up' => static function (PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS tenants (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(160) NOT NULL,
            slug VARCHAR(100) NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY tenants_slug_unique (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("INSERT IGNORE INTO tenants (name,slug) VALUES ('Família Almeida','familia-almeida')");
        $stmt = $pdo->query("SELECT id FROM tenants WHERE slug = 'familia-almeida' LIMIT 1");
        $legacyId = (int) $stmt->fetchColumn();
        if ($legacyId < 1) {
            throw new RuntimeException('Não foi possível criar a família legada.');
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS tenant_users (
            tenant_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            role ENUM('family_admin','member') NOT NULL DEFAULT 'member',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (tenant_id,user_id),
            KEY tenant_users_user_idx (user_id),
            CONSTRAINT tenant_users_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            CONSTRAINT tenant_users_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $col = $pdo->query("SELECT COUNT(*) FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'system_role'");
        if ((int) $col->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE users ADD COLUMN system_role ENUM('user','system_admin') NOT NULL DEFAULT 'user'");
        }

        // Existing users have shared access to this household. Keep that behavior
        // for the legacy tenant only, and permit later role refinement.
        $stmt = $pdo->prepare("INSERT IGNORE INTO tenant_users (tenant_id,user_id,role)
            SELECT ?, id, 'family_admin' FROM users");
        $stmt->execute([$legacyId]);

        $tables = [
            'debts','transactions','fixed_bills','bill_payments',
            'transaction_receipts','shopping_lists','shopping_purchases',
            'shopping_items','inventory_items','inventory_movements',
            'spending_goals','settings','recurring_incomes','monthly_allocations',
            'categories','credit_cards','credit_card_purchases','credit_card_invoices'
        ];

        foreach ($tables as $table) {
            // Some optional modules may not exist on older installations.
            $exists = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables
                WHERE table_schema = DATABASE() AND table_name = ?");
            $exists->execute([$table]);
            if ((int) $exists->fetchColumn() === 0) continue;

            $column = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = ? AND column_name = 'tenant_id'");
            $column->execute([$table]);
            if ((int) $column->fetchColumn() === 0) {
                $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN tenant_id BIGINT UNSIGNED NULL");
            }
            $stmt = $pdo->prepare("UPDATE `{$table}` SET tenant_id = ? WHERE tenant_id IS NULL");
            $stmt->execute([$legacyId]);
            $remaining = (int) $pdo->query("SELECT COUNT(*) FROM `{$table}` WHERE tenant_id IS NULL")->fetchColumn();
            if ($remaining > 0) throw new RuntimeException("Falha ao atribuir família em {$table}");
            $pdo->exec("ALTER TABLE `{$table}` MODIFY tenant_id BIGINT UNSIGNED NOT NULL");

            $index = $pdo->prepare("SELECT COUNT(*) FROM information_schema.statistics
                WHERE table_schema = DATABASE() AND table_name = ? AND index_name = 'tenant_id_idx'");
            $index->execute([$table]);
            if ((int) $index->fetchColumn() === 0) {
                $pdo->exec("CREATE INDEX tenant_id_idx ON `{$table}` (tenant_id)");
            }
        }
    }
];

<?php

declare(strict_types=1);

function ensure_migrations_table(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
            migration VARCHAR(190) NOT NULL,
            description VARCHAR(255) NOT NULL,
            executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (migration)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function migration_definitions(): array
{
    $directory = dirname(__DIR__) . '/database/migrations';
    if (!is_dir($directory)) return [];

    $files = glob($directory . '/*.php') ?: [];
    sort($files, SORT_STRING);

    $definitions = [];
    foreach ($files as $file) {
        $definition = require $file;
        if (!is_array($definition) || !isset($definition['description'], $definition['up']) || !is_callable($definition['up'])) {
            throw new RuntimeException('Migration inválida: ' . basename($file));
        }
        $id = pathinfo($file, PATHINFO_FILENAME);
        $definitions[$id] = ['id' => $id, 'description' => (string) $definition['description'], 'up' => $definition['up']];
    }
    return $definitions;
}

function migration_status(PDO $pdo): array
{
    ensure_migrations_table($pdo);
    $rows = $pdo->query('SELECT migration, description, executed_at FROM schema_migrations ORDER BY migration')->fetchAll();
    $applied = [];
    foreach ($rows as $row) $applied[$row['migration']] = $row;

    $status = [];
    foreach (migration_definitions() as $id => $definition) {
        $status[] = [
            'id' => $id,
            'description' => $definition['description'],
            'applied' => isset($applied[$id]),
            'executed_at' => $applied[$id]['executed_at'] ?? null,
        ];
    }
    return $status;
}

function run_pending_migrations(PDO $pdo): array
{
    ensure_migrations_table($pdo);
    $appliedRows = $pdo->query('SELECT migration FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
    $applied = array_fill_keys($appliedRows, true);
    $insert = $pdo->prepare('INSERT INTO schema_migrations (migration, description) VALUES (?, ?)');
    $executed = [];

    foreach (migration_definitions() as $id => $definition) {
        if (isset($applied[$id])) continue;
        ($definition['up'])($pdo);
        $insert->execute([$id, $definition['description']]);
        $executed[] = $id;
    }
    return $executed;
}

function database_diagnostics(PDO $pdo): array
{
    $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
    $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();

    $sizeStmt = $pdo->prepare('SELECT COALESCE(SUM(data_length + index_length), 0) FROM information_schema.tables WHERE table_schema = ?');
    $sizeStmt->execute([$database]);
    $sizeBytes = (int) $sizeStmt->fetchColumn();

    $tablesStmt = $pdo->prepare('SELECT table_name, table_rows, data_length, index_length FROM information_schema.tables WHERE table_schema = ? ORDER BY table_name');
    $tablesStmt->execute([$database]);

    return [
        'mysql_version' => $version,
        'database' => $database,
        'size_bytes' => $sizeBytes,
        'tables' => $tablesStmt->fetchAll(),
    ];
}

function optimize_database_tables(PDO $pdo): array
{
    $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $stmt = $pdo->prepare('SELECT table_name FROM information_schema.tables WHERE table_schema = ? AND engine = "InnoDB" ORDER BY table_name');
    $stmt->execute([$database]);
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $optimized = [];
    foreach ($tables as $table) {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) continue;
        $pdo->exec('OPTIMIZE TABLE ' . $table);
        $optimized[] = $table;
    }
    return $optimized;
}

function bytes_human(int $bytes): string
{
    if ($bytes < 1024) return $bytes . ' B';
    $units = ['KB', 'MB', 'GB', 'TB'];
    $value = $bytes / 1024;
    foreach ($units as $unit) {
        if ($value < 1024 || $unit === 'TB') return number_format($value, 2, ',', '.') . ' ' . $unit;
        $value /= 1024;
    }
    return number_format($value, 2, ',', '.') . ' TB';
}

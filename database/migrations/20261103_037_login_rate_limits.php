<?php
declare(strict_types=1);
return [
 'description'=>'Controle persistente de tentativas de login por conta e origem',
 'up'=>static function(PDO $pdo):void {
  $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempt_limits (
    scope VARCHAR(12) NOT NULL,
    identity_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    failures INT UNSIGNED NOT NULL DEFAULT 0,
    window_started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    blocked_until DATETIME NULL,
    last_failure_at DATETIME NULL,
    PRIMARY KEY (scope,identity_hash),
    KEY login_limits_cleanup (last_failure_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 }
];
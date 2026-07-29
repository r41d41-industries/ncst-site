<?php

declare(strict_types=1);

/**
 * One-shot migrate for Facebook sync logs table.
 * Run: php sql/apply_facebook_sync_logs.php
 */

require dirname(__DIR__) . '/includes/bootstrap.php';

$pdo = db();

function facebook_sync_logs_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
    );
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}

$table = cs_table('facebook_sync_logs');

if (!facebook_sync_logs_table_exists($pdo, $table)) {
    $pdo->exec(
        "CREATE TABLE `{$table}` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `ran_at` DATETIME NOT NULL,
          `source` VARCHAR(16) NOT NULL,
          `posts_created` INT UNSIGNED NOT NULL DEFAULT 0,
          `posts_updated` INT UNSIGNED NOT NULL DEFAULT 0,
          `comments_new` INT UNSIGNED NOT NULL DEFAULT 0,
          `triggers_processed` INT UNSIGNED NOT NULL DEFAULT 0,
          `failures` INT UNSIGNED NOT NULL DEFAULT 0,
          `ok` TINYINT(1) NOT NULL DEFAULT 1,
          `error_message` TEXT DEFAULT NULL,
          `details_json` MEDIUMTEXT DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `idx_cs_facebook_sync_logs_ran_at` (`ran_at`),
          KEY `idx_cs_facebook_sync_logs_source` (`source`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "Created {$table} table.\n";
} else {
    echo "{$table} table already present.\n";
}

echo "Done.\n";

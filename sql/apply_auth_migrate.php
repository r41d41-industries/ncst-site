<?php

declare(strict_types=1);

/**
 * One-shot local migrate for auth email/reset columns.
 * Run: php sql/apply_auth_migrate.php
 */

require dirname(__DIR__) . '/includes/bootstrap.php';

$pdo = db();

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
    );
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
}

function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
    );
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}

$users = cs_table('users');
$resets = cs_table('password_resets');

if (!column_exists($pdo, $users, 'email')) {
    $pdo->exec(
        "ALTER TABLE `{$users}`
           ADD COLUMN `email` VARCHAR(255) DEFAULT NULL AFTER `username`,
           ADD COLUMN `display_name` VARCHAR(128) DEFAULT NULL AFTER `email`"
    );
    echo "Added email + display_name columns.\n";
} else {
    echo "email column already present.\n";
}

// Unique email index (ignore if exists)
$idx = $pdo->query("SHOW INDEX FROM `{$users}` WHERE Key_name = 'uq_cs_users_email'")->fetchAll();
if (!$idx) {
    $pdo->exec("ALTER TABLE `{$users}` ADD UNIQUE KEY `uq_cs_users_email` (`email`)");
    echo "Added unique email index.\n";
}

if (!table_exists($pdo, $resets)) {
    $pdo->exec(
        "CREATE TABLE `{$resets}` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `user_id` INT UNSIGNED NOT NULL,
          `token_hash` CHAR(64) NOT NULL,
          `expires_at` DATETIME NOT NULL,
          `used_at` DATETIME DEFAULT NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_cs_password_resets_token_hash` (`token_hash`),
          KEY `idx_cs_password_resets_user` (`user_id`),
          KEY `idx_cs_password_resets_expires` (`expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "Created password_resets table.\n";
} else {
    echo "password_resets table already present.\n";
}

$adminEmail = trim((string) (getenv('ADMIN_EMAIL') ?: 'contact@r41d41.com'));
if ($adminEmail !== '' && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
    $stmt = $pdo->prepare(
        "UPDATE `{$users}`
         SET email = COALESCE(NULLIF(email, ''), ?),
             display_name = COALESCE(NULLIF(display_name, ''), 'Admin')
         WHERE username = 'admin'"
    );
    $stmt->execute([$adminEmail]);
    echo "Assigned admin email: {$adminEmail}\n";
}

echo "Done.\n";

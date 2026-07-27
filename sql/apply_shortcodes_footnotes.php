<?php

declare(strict_types=1);

/**
 * Migrate: CS_shortcodes + CS_posts.footnotes
 * Run: php sql/apply_shortcodes_footnotes.php
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

$shortcodes = cs_table('shortcodes');
$posts = cs_table('posts');

if (!table_exists($pdo, $shortcodes)) {
    $pdo->exec(
        "CREATE TABLE `{$shortcodes}` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `code` VARCHAR(64) NOT NULL,
          `replacement` TEXT NOT NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_cs_shortcodes_code` (`code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "Created shortcodes table.\n";
} else {
    echo "shortcodes table already present.\n";
}

$stmt = $pdo->prepare(
    "INSERT INTO `{$shortcodes}` (`code`, `replacement`) VALUES (?, ?)
     ON DUPLICATE KEY UPDATE `code` = `code`"
);
$stmt->execute(['current time', '__NOW__']);
echo "Seeded current time shortcode.\n";

if (!column_exists($pdo, $posts, 'footnotes')) {
    $pdo->exec("ALTER TABLE `{$posts}` ADD COLUMN `footnotes` JSON DEFAULT NULL AFTER `article_body`");
    echo "Added posts.footnotes column.\n";
} else {
    echo "posts.footnotes already present.\n";
}

echo "Done.\n";

<?php

declare(strict_types=1);

/**
 * One-shot migrate for Facebook Page posts sync table.
 * Run: php sql/apply_facebook_posts.php
 */

require dirname(__DIR__) . '/includes/bootstrap.php';

$pdo = db();

function facebook_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
    );
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}

$table = cs_table('facebook_posts');

if (!facebook_table_exists($pdo, $table)) {
    $pdo->exec(
        "CREATE TABLE `{$table}` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `fb_post_id` VARCHAR(64) NOT NULL,
          `message` TEXT DEFAULT NULL,
          `permalink_url` VARCHAR(1024) DEFAULT NULL,
          `status_type` VARCHAR(64) DEFAULT NULL,
          `full_picture` VARCHAR(1024) DEFAULT NULL,
          `fb_created_time` DATETIME DEFAULT NULL,
          `fb_updated_time` DATETIME DEFAULT NULL,
          `is_new` TINYINT(1) NOT NULL DEFAULT 1,
          `first_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `last_synced_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `raw_json` MEDIUMTEXT DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_cs_facebook_posts_fb_post_id` (`fb_post_id`),
          KEY `idx_cs_facebook_posts_created` (`fb_created_time`),
          KEY `idx_cs_facebook_posts_is_new` (`is_new`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "Created {$table} table.\n";
} else {
    echo "{$table} table already present.\n";
}

echo "Done.\n";

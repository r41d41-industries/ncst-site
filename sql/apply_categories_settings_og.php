<?php

declare(strict_types=1);

/**
 * One-shot migrate for categories, settings, and post OG columns.
 * Run: php sql/apply_categories_settings_og.php
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

$categories = cs_table('categories');
$settings = cs_table('settings');
$posts = cs_table('posts');

if (!table_exists($pdo, $categories)) {
    $pdo->exec(
        "CREATE TABLE `{$categories}` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `slug` VARCHAR(32) NOT NULL,
          `name` VARCHAR(64) NOT NULL,
          `template` VARCHAR(16) NOT NULL,
          `color` CHAR(7) NOT NULL DEFAULT '#f7931e',
          `sort_order` INT NOT NULL DEFAULT 0,
          `is_filter` TINYINT(1) NOT NULL DEFAULT 1,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_cs_categories_slug` (`slug`),
          KEY `idx_cs_categories_sort` (`sort_order`, `id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "Created categories table.\n";
} else {
    echo "categories table already present.\n";
}

if (!table_exists($pdo, $settings)) {
    $pdo->exec(
        "CREATE TABLE `{$settings}` (
          `setting_key` VARCHAR(64) NOT NULL,
          `setting_value` TEXT DEFAULT NULL,
          `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "Created settings table.\n";
} else {
    echo "settings table already present.\n";
}

foreach (['og_title', 'og_description', 'og_image_path'] as $col) {
    if (!column_exists($pdo, $posts, $col)) {
        $after = match ($col) {
            'og_title' => 'read_more_url',
            'og_description' => 'og_title',
            'og_image_path' => 'og_description',
        };
        $type = $col === 'og_description' ? 'TEXT' : ($col === 'og_title' ? 'VARCHAR(255)' : 'VARCHAR(512)');
        $pdo->exec("ALTER TABLE `{$posts}` ADD COLUMN `{$col}` {$type} DEFAULT NULL AFTER `{$after}`");
        echo "Added posts.{$col} column.\n";
    } else {
        echo "posts.{$col} already present.\n";
    }
}

$seedCats = [
    ['NEWS', 'NEWS', 'news', '#2563eb', 10],
    ['UPDATES', 'UPDATES', 'news', '#7c3aed', 20],
    ['TRAFFIC', 'TRAFFIC', 'incident', '#f7931e', 30],
    ['CRIME', 'CRIME', 'incident', '#8d4f00', 40],
    ['FIRE', 'FIRE', 'incident', '#ba1a1a', 50],
    ['WEATHER', 'WEATHER', 'weather', '#0284c7', 60],
];

$insertCat = $pdo->prepare(
    "INSERT INTO `{$categories}` (`slug`, `name`, `template`, `color`, `sort_order`, `is_filter`)
     VALUES (?, ?, ?, ?, ?, 1)
     ON DUPLICATE KEY UPDATE
       `name` = VALUES(`name`),
       `template` = VALUES(`template`),
       `color` = VALUES(`color`),
       `sort_order` = VALUES(`sort_order`),
       `is_filter` = VALUES(`is_filter`)"
);
foreach ($seedCats as $row) {
    $insertCat->execute($row);
}
echo "Seeded categories.\n";

$seedSettings = [
    'og_title' => 'NCST Main Feed',
    'og_description' => 'Newnan Coweta Scanner Traffic — live scanner feed for Newnan and Coweta County.',
    'og_site_name' => 'Newnan Coweta Scanner Traffic',
    'og_type' => 'website',
    'og_image_path' => null,
];

$insertSetting = $pdo->prepare(
    "INSERT INTO `{$settings}` (`setting_key`, `setting_value`)
     VALUES (?, ?)
     ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`"
);
foreach ($seedSettings as $key => $value) {
    $insertSetting->execute([$key, $value]);
}
echo "Seeded settings defaults.\n";
echo "Done.\n";

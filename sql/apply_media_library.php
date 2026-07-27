<?php

declare(strict_types=1);

/**
 * One-shot migrate for media library tables and post media FK columns.
 * Run: php sql/apply_media_library.php
 */

require dirname(__DIR__) . '/includes/bootstrap.php';

$pdo = db();

function media_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
    );
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
}

function media_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
    );
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}

$media = cs_table('media');
$galleries = cs_table('galleries');
$galleryItems = cs_table('gallery_items');
$playlists = cs_table('playlists');
$playlistItems = cs_table('playlist_items');
$posts = cs_table('posts');

if (!media_table_exists($pdo, $media)) {
    $pdo->exec(
        "CREATE TABLE `{$media}` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `kind` ENUM('image','audio','document') NOT NULL,
          `path` VARCHAR(512) NOT NULL,
          `original_name` VARCHAR(255) NOT NULL,
          `mime` VARCHAR(128) NOT NULL,
          `size_bytes` INT UNSIGNED NOT NULL DEFAULT 0,
          `title` VARCHAR(255) DEFAULT NULL,
          `alt_text` VARCHAR(255) DEFAULT NULL,
          `caption` VARCHAR(512) DEFAULT NULL,
          `description` TEXT DEFAULT NULL,
          `width` INT UNSIGNED DEFAULT NULL,
          `height` INT UNSIGNED DEFAULT NULL,
          `duration_seconds` INT UNSIGNED DEFAULT NULL,
          `uploaded_by` INT UNSIGNED DEFAULT NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_cs_media_kind` (`kind`),
          KEY `idx_cs_media_created` (`created_at`),
          KEY `idx_cs_media_uploaded_by` (`uploaded_by`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "Created media table.\n";
} else {
    echo "media table already present.\n";
}

if (!media_table_exists($pdo, $galleries)) {
    $pdo->exec(
        "CREATE TABLE `{$galleries}` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `title` VARCHAR(255) NOT NULL,
          `description` TEXT DEFAULT NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_cs_galleries_updated` (`updated_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "Created galleries table.\n";
} else {
    echo "galleries table already present.\n";
}

if (!media_table_exists($pdo, $galleryItems)) {
    $pdo->exec(
        "CREATE TABLE `{$galleryItems}` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `gallery_id` INT UNSIGNED NOT NULL,
          `media_id` INT UNSIGNED NOT NULL,
          `sort_order` INT NOT NULL DEFAULT 0,
          `caption` VARCHAR(512) DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `idx_cs_gallery_items_gallery` (`gallery_id`, `sort_order`, `id`),
          KEY `idx_cs_gallery_items_media` (`media_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "Created gallery_items table.\n";
} else {
    echo "gallery_items table already present.\n";
}

if (!media_table_exists($pdo, $playlists)) {
    $pdo->exec(
        "CREATE TABLE `{$playlists}` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `title` VARCHAR(255) NOT NULL,
          `description` TEXT DEFAULT NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_cs_playlists_updated` (`updated_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "Created playlists table.\n";
} else {
    echo "playlists table already present.\n";
}

if (!media_table_exists($pdo, $playlistItems)) {
    $pdo->exec(
        "CREATE TABLE `{$playlistItems}` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `playlist_id` INT UNSIGNED NOT NULL,
          `media_id` INT UNSIGNED NOT NULL,
          `sort_order` INT NOT NULL DEFAULT 0,
          `title` VARCHAR(255) DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `idx_cs_playlist_items_playlist` (`playlist_id`, `sort_order`, `id`),
          KEY `idx_cs_playlist_items_media` (`media_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    echo "Created playlist_items table.\n";
} else {
    echo "playlist_items table already present.\n";
}

if (!media_column_exists($pdo, $posts, 'image_media_id')) {
    $pdo->exec("ALTER TABLE `{$posts}` ADD COLUMN `image_media_id` INT UNSIGNED DEFAULT NULL AFTER `image_path`");
    echo "Added posts.image_media_id column.\n";
} else {
    echo "posts.image_media_id already present.\n";
}

if (!media_column_exists($pdo, $posts, 'og_image_media_id')) {
    $pdo->exec("ALTER TABLE `{$posts}` ADD COLUMN `og_image_media_id` INT UNSIGNED DEFAULT NULL AFTER `og_image_path`");
    echo "Added posts.og_image_media_id column.\n";
} else {
    echo "posts.og_image_media_id already present.\n";
}

$dirs = [
    dirname(__DIR__) . '/assets/uploads/media/images',
    dirname(__DIR__) . '/assets/uploads/media/audio',
    dirname(__DIR__) . '/assets/uploads/media/documents',
];
foreach ($dirs as $dir) {
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        echo "WARNING: could not create {$dir}\n";
    } else {
        echo "Ensured dir: {$dir}\n";
    }
}

echo "Done.\n";

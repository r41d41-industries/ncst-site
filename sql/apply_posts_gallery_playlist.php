<?php

declare(strict_types=1);

/**
 * Migrate: CS_posts.gallery_id + CS_posts.playlist_id
 * Run: php sql/apply_posts_gallery_playlist.php
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

$posts = cs_table('posts');

if (!column_exists($pdo, $posts, 'gallery_id')) {
    $pdo->exec(
        "ALTER TABLE `{$posts}`
         ADD COLUMN `gallery_id` INT UNSIGNED DEFAULT NULL AFTER `og_image_media_id`,
         ADD KEY `idx_cs_posts_gallery` (`gallery_id`)"
    );
    echo "Added posts.gallery_id column.\n";
} else {
    echo "posts.gallery_id already present.\n";
}

if (!column_exists($pdo, $posts, 'playlist_id')) {
    $pdo->exec(
        "ALTER TABLE `{$posts}`
         ADD COLUMN `playlist_id` INT UNSIGNED DEFAULT NULL AFTER `gallery_id`,
         ADD KEY `idx_cs_posts_playlist` (`playlist_id`)"
    );
    echo "Added posts.playlist_id column.\n";
} else {
    echo "posts.playlist_id already present.\n";
}

echo "Done.\n";

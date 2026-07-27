<?php

declare(strict_types=1);

/**
 * One-shot migrate: add CS_posts.trashed_at for Trash status.
 * Run: php sql/apply_posts_trash.php
 */

require dirname(__DIR__) . '/includes/bootstrap.php';

$pdo = db();
$posts = cs_table('posts');

$stmt = $pdo->prepare(
    'SELECT 1 FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
);
$stmt->execute([$posts, 'trashed_at']);
if ($stmt->fetchColumn()) {
    echo "trashed_at already present.\n";
    exit(0);
}

$pdo->exec(
    "ALTER TABLE `{$posts}`
       ADD COLUMN `trashed_at` DATETIME DEFAULT NULL AFTER `published`,
       ADD KEY `idx_cs_posts_trashed_at` (`trashed_at`)"
);
echo "Added trashed_at column.\nDone.\n";

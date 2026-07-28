<?php

declare(strict_types=1);

/**
 * One-shot migrate: add CS_facebook_posts.cs_post_id for convert-to-feed.
 * Run: php sql/apply_facebook_convert.php
 */

require dirname(__DIR__) . '/includes/bootstrap.php';

$pdo = db();
$table = cs_table('facebook_posts');

$stmt = $pdo->prepare(
    'SELECT 1 FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
);
$stmt->execute([$table, 'cs_post_id']);
if ($stmt->fetchColumn()) {
    echo "cs_post_id already present.\n";
    exit(0);
}

$pdo->exec(
    "ALTER TABLE `{$table}`
       ADD COLUMN `cs_post_id` INT UNSIGNED DEFAULT NULL AFTER `is_new`,
       ADD UNIQUE KEY `uq_cs_facebook_posts_cs_post_id` (`cs_post_id`)"
);
echo "Added cs_post_id column.\nDone.\n";

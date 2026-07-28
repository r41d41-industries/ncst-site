<?php

declare(strict_types=1);

/**
 * One-shot: set CS_posts.created_at / updated_at from linked Facebook publish time.
 * Run: php sql/apply_facebook_created_at_backfill.php
 */

require dirname(__DIR__) . '/includes/bootstrap.php';

$posts = cs_table('posts');
$fbPosts = cs_table('facebook_posts');
$pdo = db();

$stmt = $pdo->query(
    "SELECT p.id AS post_id, f.fb_created_time
     FROM `{$fbPosts}` f
     INNER JOIN `{$posts}` p ON p.id = f.cs_post_id
     WHERE f.cs_post_id IS NOT NULL
       AND f.fb_created_time IS NOT NULL
       AND TRIM(f.fb_created_time) <> ''"
);
$rows = $stmt->fetchAll();

if ($rows === []) {
    echo "No converted Facebook posts to backfill.\nDone.\n";
    exit(0);
}

$update = $pdo->prepare(
    "UPDATE `{$posts}`
     SET created_at = ?, updated_at = ?
     WHERE id = ?"
);

$updated = 0;
foreach ($rows as $row) {
    $fbTime = trim((string) ($row['fb_created_time'] ?? ''));
    $postId = (int) ($row['post_id'] ?? 0);
    if ($postId <= 0 || $fbTime === '') {
        continue;
    }
    $update->execute([$fbTime, $fbTime, $postId]);
    if ($update->rowCount() > 0) {
        $updated++;
    }
    echo "Post #{$postId} -> {$fbTime}\n";
}

echo "Updated {$updated} of " . count($rows) . " converted post(s).\nDone.\n";

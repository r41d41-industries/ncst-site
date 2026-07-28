<?php

declare(strict_types=1);

/**
 * One-shot: convert previously UTC-stored Facebook timestamps to America/New_York.
 * Safe to run once; skips if setting fb_times_eastern_backfilled=1.
 * Run: php sql/apply_facebook_eastern_times.php
 */

require dirname(__DIR__) . '/includes/bootstrap.php';

if (settings_get('fb_times_eastern_backfilled') === '1') {
    echo "Already backfilled (fb_times_eastern_backfilled=1). Nothing to do.\n";
    exit(0);
}

$pdo = db();
$fbPosts = cs_table('facebook_posts');
$fbComments = cs_table('facebook_comments');
$posts = cs_table('posts');

$fbRows = $pdo->query("SELECT id, fb_created_time, fb_updated_time, cs_post_id FROM `{$fbPosts}`")->fetchAll();
$updateFb = $pdo->prepare(
    "UPDATE `{$fbPosts}` SET fb_created_time = ?, fb_updated_time = ? WHERE id = ?"
);
$updateCms = $pdo->prepare(
    "UPDATE `{$posts}` SET created_at = ?, updated_at = ?, dispatched_at = ? WHERE id = ?"
);

$fbUpdated = 0;
$cmsUpdated = 0;

foreach ($fbRows as $row) {
    $id = (int) $row['id'];
    $created = facebook_utc_naive_to_eastern(isset($row['fb_created_time']) ? (string) $row['fb_created_time'] : null);
    $updated = facebook_utc_naive_to_eastern(isset($row['fb_updated_time']) ? (string) $row['fb_updated_time'] : null);
    $updateFb->execute([$created, $updated, $id]);
    $fbUpdated++;

    $csPostId = isset($row['cs_post_id']) && $row['cs_post_id'] !== null && $row['cs_post_id'] !== ''
        ? (int) $row['cs_post_id']
        : 0;
    if ($csPostId > 0 && $created !== null) {
        $updateCms->execute([$created, $created, $created, $csPostId]);
        $cmsUpdated++;
    }
}

$commentRows = $pdo->query("SELECT id, fb_created_time FROM `{$fbComments}`")->fetchAll();
$updateComment = $pdo->prepare(
    "UPDATE `{$fbComments}` SET fb_created_time = ? WHERE id = ?"
);
$commentsUpdated = 0;
foreach ($commentRows as $row) {
    $created = facebook_utc_naive_to_eastern(isset($row['fb_created_time']) ? (string) $row['fb_created_time'] : null);
    $updateComment->execute([$created, (int) $row['id']]);
    $commentsUpdated++;
}

settings_set('fb_times_eastern_backfilled', '1');

echo "Facebook posts updated: {$fbUpdated}\n";
echo "Converted CMS posts updated: {$cmsUpdated}\n";
echo "Facebook comments updated: {$commentsUpdated}\n";
echo "Done.\n";

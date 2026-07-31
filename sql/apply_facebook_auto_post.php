<?php

declare(strict_types=1);

/**
 * One-shot migrate for Facebook auto-post mode (comment applied_at tracking).
 * Run: php sql/apply_facebook_auto_post.php
 */

require dirname(__DIR__) . '/includes/bootstrap.php';

$pdo = db();

function facebook_auto_post_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
    );
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
}

function facebook_auto_post_index_exists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1'
    );
    $stmt->execute([$table, $index]);
    return (bool) $stmt->fetchColumn();
}

$table = cs_table('facebook_comments');

if (!facebook_auto_post_column_exists($pdo, $table, 'applied_at')) {
    $pdo->exec(
        "ALTER TABLE `{$table}`
          ADD COLUMN `applied_at` DATETIME DEFAULT NULL AFTER `last_synced_at`"
    );
    echo "Added {$table}.applied_at column.\n";
} else {
    echo "{$table}.applied_at already present.\n";
}

if (!facebook_auto_post_index_exists($pdo, $table, 'idx_cs_facebook_comments_applied')) {
    $pdo->exec(
        "ALTER TABLE `{$table}`
          ADD KEY `idx_cs_facebook_comments_applied` (`facebook_post_id`, `applied_at`)"
    );
    echo "Added idx_cs_facebook_comments_applied index.\n";
} else {
    echo "idx_cs_facebook_comments_applied already present.\n";
}

// Treat existing Page UPDATE/CLEARED comments as already applied to avoid duplicate timeline rows.
$select = $pdo->query(
    "SELECT id, message FROM `{$table}`
     WHERE is_page = 1 AND applied_at IS NULL"
);
$backfill = 0;
$mark = $pdo->prepare(
    "UPDATE `{$table}`
     SET applied_at = COALESCE(fb_created_time, last_synced_at, NOW())
     WHERE id = ? AND applied_at IS NULL"
);
if ($select !== false) {
    while ($row = $select->fetch(PDO::FETCH_ASSOC)) {
        $type = facebook_comment_action_type(isset($row['message']) ? (string) $row['message'] : null);
        if ($type === 'update' || $type === 'cleared') {
            $mark->execute([(int) $row['id']]);
            $backfill += $mark->rowCount() > 0 ? 1 : 0;
        }
    }
}
echo "Backfilled applied_at on {$backfill} existing Page action comment(s).\n";
echo "Done.\n";

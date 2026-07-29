<?php

declare(strict_types=1);

/**
 * One-shot migrate for sticky posts (is_sticky on CS_posts).
 * Run: php sql/apply_posts_sticky.php
 */

require dirname(__DIR__) . '/includes/bootstrap.php';

$pdo = db();

function posts_sticky_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
    );
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
}

function posts_sticky_index_exists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1'
    );
    $stmt->execute([$table, $index]);
    return (bool) $stmt->fetchColumn();
}

$table = posts_table();

if (!posts_sticky_column_exists($pdo, $table, 'is_sticky')) {
    $pdo->exec(
        "ALTER TABLE `{$table}`
          ADD COLUMN `is_sticky` TINYINT(1) NOT NULL DEFAULT 0 AFTER `published`"
    );
    echo "Added {$table}.is_sticky column.\n";
} else {
    echo "{$table}.is_sticky already present.\n";
}

if (!posts_sticky_index_exists($pdo, $table, 'idx_cs_posts_published_sticky_created')) {
    $pdo->exec(
        "ALTER TABLE `{$table}`
          ADD KEY `idx_cs_posts_published_sticky_created` (`published`, `is_sticky`, `created_at`)"
    );
    echo "Added idx_cs_posts_published_sticky_created index.\n";
} else {
    echo "idx_cs_posts_published_sticky_created already present.\n";
}

echo "Done.\n";

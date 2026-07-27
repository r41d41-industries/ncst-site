<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/feed_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$pageSize = 4;
$offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;
$filterCategories = cs_filter_category_slugs();
$category = isset($_GET['category']) ? strtoupper(trim((string) $_GET['category'])) : 'ALL';
if (!in_array($category, $filterCategories, true)) {
    $category = 'ALL';
}

$filter = $category === 'ALL' ? null : $category;
$posts = posts_list_published($filter, $pageSize, $offset);
$total = posts_count_published($filter);
$nextOffset = $offset + count($posts);
$hasMore = $nextOffset < $total;

$newerBoundaryCreatedAt = null;
if ($offset > 0) {
    $neighbors = posts_list_published($filter, 1, $offset - 1);
    if ($neighbors !== []) {
        $newerBoundaryCreatedAt = (string) ($neighbors[0]['created_at'] ?? '');
        if ($newerBoundaryCreatedAt === '') {
            $newerBoundaryCreatedAt = null;
        }
    }
}

$html = cs_render_feed_sequence($posts, $newerBoundaryCreatedAt);

echo json_encode([
    'ok' => true,
    'html' => $html,
    'count' => count($posts),
    'offset' => $offset,
    'nextOffset' => $nextOffset,
    'hasMore' => $hasMore,
    'total' => $total,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

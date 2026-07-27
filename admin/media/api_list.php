<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
auth_require();

header('Content-Type: application/json; charset=utf-8');

$kind = isset($_GET['kind']) ? strtolower(trim((string) $_GET['kind'])) : '';
$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

$filters = ['limit' => 100];
if (in_array($kind, ['image', 'audio', 'document'], true)) {
    $filters['kind'] = $kind;
}
if ($q !== '') {
    $filters['q'] = $q;
}

$items = [];
foreach (media_list($filters) as $row) {
    $items[] = [
        'id' => (int) $row['id'],
        'kind' => (string) $row['kind'],
        'path' => (string) $row['path'],
        'title' => $row['title'],
        'alt_text' => $row['alt_text'],
        'original_name' => (string) $row['original_name'],
        'mime' => (string) $row['mime'],
    ];
}

echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_SLASHES);

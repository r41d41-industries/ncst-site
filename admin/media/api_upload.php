<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
auth_require();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

if (!csrf_verify($_POST['_csrf'] ?? null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token.']);
    exit;
}

try {
    $meta = [
        'title' => isset($_POST['title']) ? trim((string) $_POST['title']) : null,
        'alt_text' => isset($_POST['alt_text']) ? trim((string) $_POST['alt_text']) : null,
        'description' => isset($_POST['description']) ? trim((string) $_POST['description']) : null,
    ];
    $kind = isset($_POST['kind']) ? trim((string) $_POST['kind']) : '';
    if (in_array($kind, ['image', 'audio', 'document'], true)) {
        $meta['kind'] = $kind;
    }

    if (!isset($_FILES['file'])) {
        throw new RuntimeException('No file uploaded.');
    }

    $row = media_store_upload($_FILES['file'], $meta);
    echo json_encode([
        'ok' => true,
        'media' => [
            'id' => (int) $row['id'],
            'kind' => (string) $row['kind'],
            'path' => (string) $row['path'],
            'title' => $row['title'],
            'alt_text' => $row['alt_text'],
            'original_name' => (string) $row['original_name'],
            'mime' => (string) $row['mime'],
            'size_bytes' => (int) $row['size_bytes'],
            'width' => $row['width'] !== null ? (int) $row['width'] : null,
            'height' => $row['height'] !== null ? (int) $row['height'] : null,
        ],
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_SLASHES);
}

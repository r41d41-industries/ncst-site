<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

auth_require();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = [];
$contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');

if (str_contains($contentType, 'application/json') && is_string($raw) && $raw !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $data = $decoded;
    }
} else {
    $data = $_POST;
}

if (!csrf_verify(isset($data['_csrf']) ? (string) $data['_csrf'] : null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid session token.']);
    exit;
}

$userId = auth_user_id();
if ($userId === null) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated.']);
    exit;
}

$newPassword = (string) ($data['new_password'] ?? '');
$confirm = (string) ($data['new_password_confirm'] ?? '');
if ($newPassword !== '' && $newPassword !== $confirm) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'New passwords do not match.']);
    exit;
}

$error = auth_update_account($userId, [
    'display_name' => (string) ($data['display_name'] ?? ''),
    'email' => (string) ($data['email'] ?? ''),
    'current_password' => (string) ($data['current_password'] ?? ''),
    'new_password' => $newPassword,
]);

if ($error !== null) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $error]);
    exit;
}

$user = auth_current_user();
echo json_encode([
    'ok' => true,
    'message' => 'Account updated.',
    'user' => [
        'username' => $user['username'] ?? auth_username(),
        'email' => $user['email'] ?? null,
        'display_name' => $user['display_name'] ?? null,
    ],
]);

<?php

declare(strict_types=1);

/** Filter categories shown in the public UI (Figma). */
const CS_FILTER_CATEGORIES = ['ALL', 'NEWS', 'UPDATES', 'TRAFFIC', 'CRIME', 'FIRE', 'WEATHER'];

/** Allowed category values for posts. */
const CS_POST_CATEGORIES = [
    'NEWS', 'UPDATES', 'TRAFFIC', 'CRIME', 'FIRE', 'WEATHER',
];

/** Categories that use the full incident card layout (image + updates + agencies). */
const CS_INCIDENT_CATEGORIES = ['CRIME', 'FIRE', 'TRAFFIC'];

/** Categories that use the weather/bulletin card (image + body + read-more + VALID range). */
const CS_WEATHER_CATEGORIES = ['WEATHER'];

/** Categories that use the news card (image + body + optional read-more). */
const CS_NEWS_CATEGORIES = ['NEWS', 'UPDATES'];

function cs_is_incident_category(string $category): bool
{
    return in_array(strtoupper($category), CS_INCIDENT_CATEGORIES, true);
}

function cs_is_weather_category(string $category): bool
{
    return in_array(strtoupper($category), CS_WEATHER_CATEGORIES, true);
}

function cs_is_news_category(string $category): bool
{
    return in_array(strtoupper($category), CS_NEWS_CATEGORIES, true);
}

function cs_post_layout_label(string $category): string
{
    if (cs_is_incident_category($category)) {
        return 'incident layout';
    }
    if (cs_is_weather_category($category)) {
        return 'weather layout';
    }
    if (cs_is_news_category($category)) {
        return 'news layout';
    }
    return 'news layout';
}

function posts_table(): string
{
    return cs_table('posts');
}

function posts_updates_table(): string
{
    return cs_table('post_updates');
}

/**
 * @param list<int> $postIds
 * @return array<int, list<array<string, mixed>>>
 */
function posts_updates_by_post_ids(array $postIds): array
{
    $postIds = array_values(array_unique(array_map('intval', $postIds)));
    $map = [];
    foreach ($postIds as $id) {
        $map[$id] = [];
    }
    if ($postIds === []) {
        return $map;
    }

    $table = posts_updates_table();
    $placeholders = implode(',', array_fill(0, count($postIds), '?'));
    $sql = "SELECT * FROM `{$table}` WHERE post_id IN ({$placeholders}) ORDER BY created_at DESC, id DESC";
    $stmt = db()->prepare($sql);
    $stmt->execute($postIds);
    foreach ($stmt->fetchAll() as $row) {
        $pid = (int) $row['post_id'];
        $map[$pid][] = $row;
    }

    // Legacy fallback: single update_label/update_text on the post when no timeline rows
    $postsTable = posts_table();
    $sqlLegacy = "SELECT id, update_label, update_text, updated_at, created_at FROM `{$postsTable}` WHERE id IN ({$placeholders})";
    $stmtLegacy = db()->prepare($sqlLegacy);
    $stmtLegacy->execute($postIds);
    foreach ($stmtLegacy->fetchAll() as $post) {
        $pid = (int) $post['id'];
        if ($map[$pid] !== []) {
            continue;
        }
        $label = trim((string) ($post['update_label'] ?? ''));
        $body = trim((string) ($post['update_text'] ?? ''));
        if ($label === '' && $body === '') {
            continue;
        }
        $map[$pid][] = [
            'id' => 0,
            'post_id' => $pid,
            'label' => $label !== '' ? $label : null,
            'body' => $body,
            'created_at' => $post['updated_at'] ?? $post['created_at'],
        ];
    }

    return $map;
}

/**
 * @param list<array<string, mixed>> $posts
 * @return list<array<string, mixed>>
 */
function posts_attach_updates(array $posts): array
{
    if ($posts === []) {
        return $posts;
    }
    $ids = array_map(static fn(array $p): int => (int) $p['id'], $posts);
    $byId = posts_updates_by_post_ids($ids);
    foreach ($posts as &$post) {
        $post['updates'] = $byId[(int) $post['id']] ?? [];
    }
    unset($post);
    return $posts;
}

/**
 * @return list<array<string, mixed>>
 */
function posts_updates_for(int $postId): array
{
    return posts_updates_by_post_ids([$postId])[$postId] ?? [];
}

/**
 * @param array{label?: ?string, body: string, created_at?: ?string} $data
 */
function posts_add_update(int $postId, array $data): int
{
    $table = posts_updates_table();
    $body = trim((string) ($data['body'] ?? ''));
    if ($body === '') {
        throw new InvalidArgumentException('Update text is required.');
    }
    $label = trim((string) ($data['label'] ?? ''));
    $createdAt = trim((string) ($data['created_at'] ?? ''));
    if ($createdAt === '') {
        $sql = "INSERT INTO `{$table}` (post_id, label, body) VALUES (?, ?, ?)";
        $stmt = db()->prepare($sql);
        $stmt->execute([$postId, $label !== '' ? $label : null, $body]);
    } else {
        $sql = "INSERT INTO `{$table}` (post_id, label, body, created_at) VALUES (?, ?, ?, ?)";
        $stmt = db()->prepare($sql);
        $stmt->execute([$postId, $label !== '' ? $label : null, $body, $createdAt]);
    }
    return (int) db()->lastInsertId();
}

function posts_delete_update(int $updateId, int $postId): void
{
    $table = posts_updates_table();
    $stmt = db()->prepare("DELETE FROM `{$table}` WHERE id = ? AND post_id = ?");
    $stmt->execute([$updateId, $postId]);
}

/**
 * @return list<array<string, mixed>>
 */
function posts_list_published(?string $category = null, ?int $limit = null, int $offset = 0): array
{
    $table = posts_table();
    $sql = "SELECT * FROM `{$table}` WHERE published = 1";
    $params = [];

    if ($category !== null && $category !== '' && strtoupper($category) !== 'ALL') {
        $sql .= ' AND category = ?';
        $params[] = strtoupper($category);
    }

    $sql .= ' ORDER BY created_at DESC, id DESC';
    if ($limit !== null) {
        $sql .= ' LIMIT ' . max(0, $limit) . ' OFFSET ' . max(0, $offset);
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return posts_attach_updates($stmt->fetchAll());
}

function posts_count_published(?string $category = null): int
{
    $table = posts_table();
    $sql = "SELECT COUNT(*) FROM `{$table}` WHERE published = 1";
    $params = [];

    if ($category !== null && $category !== '' && strtoupper($category) !== 'ALL') {
        $sql .= ' AND category = ?';
        $params[] = strtoupper($category);
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

/**
 * @return list<array<string, mixed>>
 */
function posts_list_all(): array
{
    $table = posts_table();
    $stmt = db()->query("SELECT * FROM `{$table}` ORDER BY created_at DESC, id DESC");
    return $stmt->fetchAll();
}

function posts_find(int $id): ?array
{
    $table = posts_table();
    $stmt = db()->prepare("SELECT * FROM `{$table}` WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $row['updates'] = posts_updates_for($id);
    return $row;
}

/**
 * @param array<string, mixed> $data
 */
function posts_create(array $data): int
{
    $table = posts_table();
    $sql = "INSERT INTO `{$table}` (
        category, title, body, article_body, update_label, update_text,
        agency, dispatched_at, cleared_at, recorded_at, expires_at, dispatched_text, status_text,
        image_path, facebook_url, x_url, read_more_url, published
    ) VALUES (
        :category, :title, :body, :article_body, :update_label, :update_text,
        :agency, :dispatched_at, :cleared_at, :recorded_at, :expires_at, :dispatched_text, :status_text,
        :image_path, :facebook_url, :x_url, :read_more_url, :published
    )";
    $stmt = db()->prepare($sql);
    $stmt->execute([
        ':category' => $data['category'],
        ':title' => $data['title'],
        ':body' => $data['body'],
        ':article_body' => $data['article_body'] ?? null,
        ':update_label' => $data['update_label'] ?? null,
        ':update_text' => $data['update_text'] ?? null,
        ':agency' => $data['agency'] ?? null,
        ':dispatched_at' => $data['dispatched_at'] ?? null,
        ':cleared_at' => $data['cleared_at'] ?? null,
        ':recorded_at' => $data['recorded_at'] ?? null,
        ':expires_at' => $data['expires_at'] ?? null,
        ':dispatched_text' => $data['dispatched_text'] ?? null,
        ':status_text' => $data['status_text'] ?? null,
        ':image_path' => $data['image_path'] ?? null,
        ':facebook_url' => $data['facebook_url'] ?? null,
        ':x_url' => $data['x_url'] ?? null,
        ':read_more_url' => $data['read_more_url'] ?? null,
        ':published' => !empty($data['published']) ? 1 : 0,
    ]);
    return (int) db()->lastInsertId();
}

/**
 * @param array<string, mixed> $data
 */
function posts_update(int $id, array $data): void
{
    $table = posts_table();
    $sql = "UPDATE `{$table}` SET
        category = :category,
        title = :title,
        body = :body,
        article_body = :article_body,
        update_label = :update_label,
        update_text = :update_text,
        agency = :agency,
        dispatched_at = :dispatched_at,
        cleared_at = :cleared_at,
        recorded_at = :recorded_at,
        expires_at = :expires_at,
        dispatched_text = :dispatched_text,
        status_text = :status_text,
        image_path = :image_path,
        facebook_url = :facebook_url,
        x_url = :x_url,
        read_more_url = :read_more_url,
        published = :published,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = :id";
    $stmt = db()->prepare($sql);
    $stmt->execute([
        ':id' => $id,
        ':category' => $data['category'],
        ':title' => $data['title'],
        ':body' => $data['body'],
        ':article_body' => $data['article_body'] ?? null,
        ':update_label' => $data['update_label'] ?? null,
        ':update_text' => $data['update_text'] ?? null,
        ':agency' => $data['agency'] ?? null,
        ':dispatched_at' => $data['dispatched_at'] ?? null,
        ':cleared_at' => $data['cleared_at'] ?? null,
        ':recorded_at' => $data['recorded_at'] ?? null,
        ':expires_at' => $data['expires_at'] ?? null,
        ':dispatched_text' => $data['dispatched_text'] ?? null,
        ':status_text' => $data['status_text'] ?? null,
        ':image_path' => $data['image_path'] ?? null,
        ':facebook_url' => $data['facebook_url'] ?? null,
        ':x_url' => $data['x_url'] ?? null,
        ':read_more_url' => $data['read_more_url'] ?? null,
        ':published' => !empty($data['published']) ? 1 : 0,
    ]);
}

function posts_delete(int $id): void
{
    $table = posts_table();
    $stmt = db()->prepare("DELETE FROM `{$table}` WHERE id = ?");
    $stmt->execute([$id]);
}

/**
 * Detect an uploaded image MIME type without requiring fileinfo.
 * Prefers finfo, then mime_content_type(), then getimagesize() / extension allowlist.
 */
function posts_detect_upload_mime(string $tmpPath, string $originalName = ''): ?string
{
    $allowed = [
        'image/jpeg' => true,
        'image/png' => true,
        'image/webp' => true,
        'image/gif' => true,
    ];

    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpPath);
        if (is_string($mime) && isset($allowed[$mime])) {
            return $mime;
        }
    }

    if (function_exists('mime_content_type')) {
        $mime = @mime_content_type($tmpPath);
        if (is_string($mime) && isset($allowed[$mime])) {
            return $mime;
        }
    }

    // Fallback when fileinfo is unavailable: extension allowlist + getimagesize().
    $extMap = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
    ];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($ext === '' || !isset($extMap[$ext])) {
        return null;
    }

    $info = @getimagesize($tmpPath);
    if (!is_array($info) || !isset($info['mime']) || $info['mime'] !== $extMap[$ext]) {
        return null;
    }

    return $info['mime'];
}

/**
 * Validate and store an uploaded image under assets/uploads/.
 * Returns relative path (assets/uploads/...) or null if no file.
 *
 * @param array<string, mixed> $file $_FILES entry
 */
function posts_handle_upload(array $file, ?string $existingPath = null): ?string
{
    if (!isset($file['error']) || (int) $file['error'] === UPLOAD_ERR_NO_FILE) {
        return $existingPath;
    }

    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed.');
    }

    $maxBytes = 5 * 1024 * 1024;
    if ((int) $file['size'] > $maxBytes) {
        throw new RuntimeException('Image must be 5MB or smaller.');
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    $mime = posts_detect_upload_mime($file['tmp_name'], (string) ($file['name'] ?? ''));
    if ($mime === null || !isset($allowed[$mime])) {
        throw new RuntimeException('Image must be JPEG, PNG, WebP, or GIF.');
    }

    $dir = dirname(__DIR__) . '/assets/uploads';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create uploads directory.');
    }

    $name = bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save uploaded image.');
    }

    return 'assets/uploads/' . $name;
}

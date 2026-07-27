<?php

declare(strict_types=1);

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
    $sql = "SELECT * FROM `{$table}` WHERE published = 1 AND trashed_at IS NULL";
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
    $sql = "SELECT COUNT(*) FROM `{$table}` WHERE published = 1 AND trashed_at IS NULL";
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
 * Admin list: optional category + status (draft|published|trash).
 * Default (no status) excludes trash.
 *
 * @return list<array<string, mixed>>
 */
function posts_list_all(?string $category = null, ?string $status = null): array
{
    $table = posts_table();
    $sql = "SELECT * FROM `{$table}` WHERE 1=1";
    $params = [];

    $status = $status !== null ? strtolower(trim($status)) : null;
    if ($status === 'draft') {
        $sql .= ' AND published = 0 AND trashed_at IS NULL';
    } elseif ($status === 'published') {
        $sql .= ' AND published = 1 AND trashed_at IS NULL';
    } elseif ($status === 'trash') {
        $sql .= ' AND trashed_at IS NOT NULL';
    } else {
        $sql .= ' AND trashed_at IS NULL';
    }

    if ($category !== null && $category !== '' && strtoupper($category) !== 'ALL') {
        $sql .= ' AND category = ?';
        $params[] = strtoupper($category);
    }

    $sql .= ' ORDER BY created_at DESC, id DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Counts for Library status links.
 *
 * @return array{all: int, draft: int, published: int, trash: int}
 */
function posts_status_counts(): array
{
    $table = posts_table();
    $row = db()->query(
        "SELECT
            SUM(CASE WHEN trashed_at IS NULL THEN 1 ELSE 0 END) AS all_count,
            SUM(CASE WHEN published = 0 AND trashed_at IS NULL THEN 1 ELSE 0 END) AS draft_count,
            SUM(CASE WHEN published = 1 AND trashed_at IS NULL THEN 1 ELSE 0 END) AS published_count,
            SUM(CASE WHEN trashed_at IS NOT NULL THEN 1 ELSE 0 END) AS trash_count
         FROM `{$table}`"
    )->fetch();

    return [
        'all' => (int) ($row['all_count'] ?? 0),
        'draft' => (int) ($row['draft_count'] ?? 0),
        'published' => (int) ($row['published_count'] ?? 0),
        'trash' => (int) ($row['trash_count'] ?? 0),
    ];
}

function posts_is_trashed(array $post): bool
{
    $trashed = $post['trashed_at'] ?? null;
    return $trashed !== null && trim((string) $trashed) !== '';
}

function posts_trash(int $id): void
{
    $table = posts_table();
    $stmt = db()->prepare(
        "UPDATE `{$table}` SET trashed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?"
    );
    $stmt->execute([$id]);
}

function posts_restore(int $id): void
{
    $table = posts_table();
    $stmt = db()->prepare(
        "UPDATE `{$table}` SET trashed_at = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?"
    );
    $stmt->execute([$id]);
}

function posts_delete(int $id): void
{
    $updates = posts_updates_table();
    $stmtUpdates = db()->prepare("DELETE FROM `{$updates}` WHERE post_id = ?");
    $stmtUpdates->execute([$id]);

    $table = posts_table();
    $stmt = db()->prepare("DELETE FROM `{$table}` WHERE id = ?");
    $stmt->execute([$id]);
}

/**
 * Permanently delete every trashed post (and their updates).
 *
 * @return int Number of posts deleted
 */
function posts_empty_trash(): int
{
    $table = posts_table();
    $updates = posts_updates_table();

    $ids = db()->query("SELECT id FROM `{$table}` WHERE trashed_at IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
    if ($ids === []) {
        return 0;
    }

    $ids = array_map('intval', $ids);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmtUpdates = db()->prepare("DELETE FROM `{$updates}` WHERE post_id IN ({$placeholders})");
    $stmtUpdates->execute($ids);

    $stmt = db()->prepare("DELETE FROM `{$table}` WHERE id IN ({$placeholders})");
    $stmt->execute($ids);

    return count($ids);
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
        image_path, image_media_id, facebook_url, x_url, read_more_url,
        og_title, og_description, og_image_path, og_image_media_id, published
    ) VALUES (
        :category, :title, :body, :article_body, :update_label, :update_text,
        :agency, :dispatched_at, :cleared_at, :recorded_at, :expires_at, :dispatched_text, :status_text,
        :image_path, :image_media_id, :facebook_url, :x_url, :read_more_url,
        :og_title, :og_description, :og_image_path, :og_image_media_id, :published
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
        ':image_media_id' => $data['image_media_id'] ?? null,
        ':facebook_url' => $data['facebook_url'] ?? null,
        ':x_url' => $data['x_url'] ?? null,
        ':read_more_url' => $data['read_more_url'] ?? null,
        ':og_title' => $data['og_title'] ?? null,
        ':og_description' => $data['og_description'] ?? null,
        ':og_image_path' => $data['og_image_path'] ?? null,
        ':og_image_media_id' => $data['og_image_media_id'] ?? null,
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
        image_media_id = :image_media_id,
        facebook_url = :facebook_url,
        x_url = :x_url,
        read_more_url = :read_more_url,
        og_title = :og_title,
        og_description = :og_description,
        og_image_path = :og_image_path,
        og_image_media_id = :og_image_media_id,
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
        ':image_media_id' => $data['image_media_id'] ?? null,
        ':facebook_url' => $data['facebook_url'] ?? null,
        ':x_url' => $data['x_url'] ?? null,
        ':read_more_url' => $data['read_more_url'] ?? null,
        ':og_title' => $data['og_title'] ?? null,
        ':og_description' => $data['og_description'] ?? null,
        ':og_image_path' => $data['og_image_path'] ?? null,
        ':og_image_media_id' => $data['og_image_media_id'] ?? null,
        ':published' => !empty($data['published']) ? 1 : 0,
    ]);
}

/**
 * Detect an uploaded image MIME type (delegates to media library).
 */
function posts_detect_upload_mime(string $tmpPath, string $originalName = ''): ?string
{
    $mime = media_detect_mime($tmpPath, $originalName);
    if ($mime === null || !str_starts_with($mime, 'image/')) {
        return null;
    }
    return $mime;
}

/**
 * Validate and store an uploaded image via the media library.
 * Returns relative path (assets/uploads/media/images/...) or existing path if no file.
 * When a new file is stored, also returns media_id via $mediaIdOut.
 *
 * @param array<string, mixed> $file $_FILES entry
 */
function posts_handle_upload(array $file, ?string $existingPath = null, ?int &$mediaIdOut = null): ?string
{
    if (!isset($file['error']) || (int) $file['error'] === UPLOAD_ERR_NO_FILE) {
        return $existingPath;
    }

    $row = media_store_upload($file, ['kind' => 'image']);
    $mediaIdOut = (int) $row['id'];
    return (string) $row['path'];
}

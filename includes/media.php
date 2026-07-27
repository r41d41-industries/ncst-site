<?php

declare(strict_types=1);

/**
 * Media library helpers: upload, MIME validation, CRUD, crop, galleries, playlists.
 */

function media_table(): string
{
    return cs_table('media');
}

/**
 * @return array{ext:string,max:int,kind:string}
 */
function media_allowed_map(): array
{
    return [
        'image/jpeg' => ['ext' => 'jpg', 'max' => 5 * 1024 * 1024, 'kind' => 'image'],
        'image/png' => ['ext' => 'png', 'max' => 5 * 1024 * 1024, 'kind' => 'image'],
        'image/webp' => ['ext' => 'webp', 'max' => 5 * 1024 * 1024, 'kind' => 'image'],
        'image/gif' => ['ext' => 'gif', 'max' => 5 * 1024 * 1024, 'kind' => 'image'],
        'audio/mpeg' => ['ext' => 'mp3', 'max' => 25 * 1024 * 1024, 'kind' => 'audio'],
        'audio/mp3' => ['ext' => 'mp3', 'max' => 25 * 1024 * 1024, 'kind' => 'audio'],
        'audio/wav' => ['ext' => 'wav', 'max' => 25 * 1024 * 1024, 'kind' => 'audio'],
        'audio/x-wav' => ['ext' => 'wav', 'max' => 25 * 1024 * 1024, 'kind' => 'audio'],
        'audio/ogg' => ['ext' => 'ogg', 'max' => 25 * 1024 * 1024, 'kind' => 'audio'],
        'audio/mp4' => ['ext' => 'm4a', 'max' => 25 * 1024 * 1024, 'kind' => 'audio'],
        'audio/x-m4a' => ['ext' => 'm4a', 'max' => 25 * 1024 * 1024, 'kind' => 'audio'],
        'application/pdf' => ['ext' => 'pdf', 'max' => 10 * 1024 * 1024, 'kind' => 'document'],
        'application/msword' => ['ext' => 'doc', 'max' => 10 * 1024 * 1024, 'kind' => 'document'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => [
            'ext' => 'docx',
            'max' => 10 * 1024 * 1024,
            'kind' => 'document',
        ],
        'text/plain' => ['ext' => 'txt', 'max' => 10 * 1024 * 1024, 'kind' => 'document'],
    ];
}

/**
 * @return array<string, string> extension => mime
 */
function media_extension_map(): array
{
    return [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'ogg' => 'audio/ogg',
        'm4a' => 'audio/mp4',
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'txt' => 'text/plain',
    ];
}

function media_subdir_for_kind(string $kind): string
{
    return match ($kind) {
        'image' => 'images',
        'audio' => 'audio',
        'document' => 'documents',
        default => throw new InvalidArgumentException('Invalid media kind.'),
    };
}

function media_absolute_path(string $relativePath): string
{
    return dirname(__DIR__) . '/' . ltrim(str_replace('\\', '/', $relativePath), '/');
}

function media_detect_mime(string $tmpPath, string $originalName = ''): ?string
{
    $allowed = media_allowed_map();
    $extMap = media_extension_map();

    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpPath);
        if (is_string($mime) && isset($allowed[$mime])) {
            return $mime === 'audio/mp3' ? 'audio/mpeg' : $mime;
        }
    }

    if (function_exists('mime_content_type')) {
        $mime = @mime_content_type($tmpPath);
        if (is_string($mime) && isset($allowed[$mime])) {
            return $mime === 'audio/mp3' ? 'audio/mpeg' : $mime;
        }
    }

    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($ext === '' || !isset($extMap[$ext])) {
        return null;
    }

    $expected = $extMap[$ext];
    if (str_starts_with($expected, 'image/')) {
        $info = @getimagesize($tmpPath);
        if (!is_array($info) || !isset($info['mime']) || $info['mime'] !== $expected) {
            return null;
        }
        return $info['mime'];
    }

    // Non-image fallback: trust extension when magic bytes unavailable.
    return $expected;
}

/**
 * @param array<string, mixed> $file $_FILES entry
 * @param array{title?:?string,alt_text?:?string,caption?:?string,description?:?string,kind?:?string} $meta
 * @return array<string, mixed>
 */
function media_store_upload(array $file, array $meta = []): array
{
    if (!isset($file['error']) || (int) $file['error'] === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('No file uploaded.');
    }
    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed.');
    }

    $originalName = (string) ($file['name'] ?? 'upload');
    $mime = media_detect_mime((string) $file['tmp_name'], $originalName);
    $allowed = media_allowed_map();
    if ($mime === null || !isset($allowed[$mime])) {
        throw new RuntimeException('Unsupported file type.');
    }

    $spec = $allowed[$mime];
    $kind = $spec['kind'];
    $forcedKind = isset($meta['kind']) ? (string) $meta['kind'] : '';
    if ($forcedKind !== '' && $forcedKind !== $kind) {
        throw new RuntimeException('File type does not match the requested media kind.');
    }

    $size = (int) $file['size'];
    if ($size > $spec['max']) {
        $mb = (int) round($spec['max'] / (1024 * 1024));
        throw new RuntimeException(ucfirst($kind) . " must be {$mb}MB or smaller.");
    }

    $subdir = media_subdir_for_kind($kind);
    $dir = dirname(__DIR__) . '/assets/uploads/media/' . $subdir;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create uploads directory.');
    }

    $name = bin2hex(random_bytes(8)) . '.' . $spec['ext'];
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file((string) $file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save uploaded file.');
    }

    $relative = 'assets/uploads/media/' . $subdir . '/' . $name;
    $width = null;
    $height = null;
    if ($kind === 'image') {
        $info = @getimagesize($dest);
        if (is_array($info)) {
            $width = isset($info[0]) ? (int) $info[0] : null;
            $height = isset($info[1]) ? (int) $info[1] : null;
        }
    }

    $title = isset($meta['title']) ? trim((string) $meta['title']) : '';
    if ($title === '') {
        $title = pathinfo($originalName, PATHINFO_FILENAME) ?: null;
    }

    $table = media_table();
    $stmt = db()->prepare(
        "INSERT INTO `{$table}` (
            kind, path, original_name, mime, size_bytes, title, alt_text, caption, description,
            width, height, uploaded_by
        ) VALUES (
            :kind, :path, :original_name, :mime, :size_bytes, :title, :alt_text, :caption, :description,
            :width, :height, :uploaded_by
        )"
    );
    $stmt->execute([
        ':kind' => $kind,
        ':path' => $relative,
        ':original_name' => $originalName,
        ':mime' => $mime === 'audio/mp3' ? 'audio/mpeg' : $mime,
        ':size_bytes' => $size,
        ':title' => $title !== '' ? $title : null,
        ':alt_text' => isset($meta['alt_text']) && trim((string) $meta['alt_text']) !== ''
            ? trim((string) $meta['alt_text'])
            : null,
        ':caption' => isset($meta['caption']) && trim((string) $meta['caption']) !== ''
            ? trim((string) $meta['caption'])
            : null,
        ':description' => isset($meta['description']) && trim((string) $meta['description']) !== ''
            ? trim((string) $meta['description'])
            : null,
        ':width' => $width,
        ':height' => $height,
        ':uploaded_by' => auth_user_id(),
    ]);

    $id = (int) db()->lastInsertId();
    $row = media_find($id);
    if ($row === null) {
        throw new RuntimeException('Media row could not be loaded after upload.');
    }
    return $row;
}

function media_find(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $table = media_table();
    $stmt = db()->prepare("SELECT * FROM `{$table}` WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * @param array{kind?:?string,q?:?string,limit?:int,offset?:int} $filters
 * @return list<array<string, mixed>>
 */
function media_list(array $filters = []): array
{
    $table = media_table();
    $where = [];
    $params = [];

    $kind = isset($filters['kind']) ? strtolower(trim((string) $filters['kind'])) : '';
    if (in_array($kind, ['image', 'audio', 'document'], true)) {
        $where[] = 'kind = ?';
        $params[] = $kind;
    }

    $q = isset($filters['q']) ? trim((string) $filters['q']) : '';
    if ($q !== '') {
        $where[] = '(title LIKE ? OR original_name LIKE ? OR alt_text LIKE ? OR description LIKE ?)';
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like, $like);
    }

    $sql = "SELECT * FROM `{$table}`";
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY created_at DESC, id DESC';

    $limit = isset($filters['limit']) ? max(1, min(500, (int) $filters['limit'])) : 200;
    $offset = isset($filters['offset']) ? max(0, (int) $filters['offset']) : 0;
    $sql .= " LIMIT {$limit} OFFSET {$offset}";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll() ?: [];
}

function media_count(array $filters = []): int
{
    $table = media_table();
    $where = [];
    $params = [];

    $kind = isset($filters['kind']) ? strtolower(trim((string) $filters['kind'])) : '';
    if (in_array($kind, ['image', 'audio', 'document'], true)) {
        $where[] = 'kind = ?';
        $params[] = $kind;
    }

    $q = isset($filters['q']) ? trim((string) $filters['q']) : '';
    if ($q !== '') {
        $where[] = '(title LIKE ? OR original_name LIKE ? OR alt_text LIKE ? OR description LIKE ?)';
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like, $like);
    }

    $sql = "SELECT COUNT(*) FROM `{$table}`";
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

/**
 * @param array{title?:?string,alt_text?:?string,caption?:?string,description?:?string,duration_seconds?:?int} $meta
 */
function media_update_meta(int $id, array $meta): void
{
    $row = media_find($id);
    if ($row === null) {
        throw new RuntimeException('Media not found.');
    }

    $table = media_table();
    $stmt = db()->prepare(
        "UPDATE `{$table}` SET
            title = :title,
            alt_text = :alt_text,
            caption = :caption,
            description = :description,
            duration_seconds = :duration_seconds,
            updated_at = CURRENT_TIMESTAMP
         WHERE id = :id"
    );
    $stmt->execute([
        ':id' => $id,
        ':title' => isset($meta['title']) && trim((string) $meta['title']) !== ''
            ? trim((string) $meta['title'])
            : null,
        ':alt_text' => isset($meta['alt_text']) && trim((string) $meta['alt_text']) !== ''
            ? trim((string) $meta['alt_text'])
            : null,
        ':caption' => isset($meta['caption']) && trim((string) $meta['caption']) !== ''
            ? trim((string) $meta['caption'])
            : null,
        ':description' => isset($meta['description']) && trim((string) $meta['description']) !== ''
            ? trim((string) $meta['description'])
            : null,
        ':duration_seconds' => array_key_exists('duration_seconds', $meta) && $meta['duration_seconds'] !== null && $meta['duration_seconds'] !== ''
            ? (int) $meta['duration_seconds']
            : ($row['duration_seconds'] ?? null),
    ]);
}

/**
 * @return array{posts:int,galleries:int,playlists:int}
 */
function media_reference_counts(int $id): array
{
    $posts = cs_table('posts');
    $galleryItems = cs_table('gallery_items');
    $playlistItems = cs_table('playlist_items');

    $postStmt = db()->prepare(
        "SELECT COUNT(*) FROM `{$posts}` WHERE image_media_id = ? OR og_image_media_id = ?"
    );
    $postStmt->execute([$id, $id]);

    $gStmt = db()->prepare("SELECT COUNT(*) FROM `{$galleryItems}` WHERE media_id = ?");
    $gStmt->execute([$id]);

    $pStmt = db()->prepare("SELECT COUNT(*) FROM `{$playlistItems}` WHERE media_id = ?");
    $pStmt->execute([$id]);

    return [
        'posts' => (int) $postStmt->fetchColumn(),
        'galleries' => (int) $gStmt->fetchColumn(),
        'playlists' => (int) $pStmt->fetchColumn(),
    ];
}

/**
 * Delete media. Soft-clears post FKs/paths; blocks if used in galleries/playlists unless $force.
 */
function media_delete(int $id, bool $force = false): void
{
    $row = media_find($id);
    if ($row === null) {
        throw new RuntimeException('Media not found.');
    }

    $refs = media_reference_counts($id);
    if (!$force && ($refs['galleries'] > 0 || $refs['playlists'] > 0)) {
        throw new RuntimeException('Media is used in a gallery or playlist and cannot be deleted.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $posts = cs_table('posts');
        $pdo->prepare(
            "UPDATE `{$posts}` SET image_path = NULL, image_media_id = NULL
             WHERE image_media_id = ?"
        )->execute([$id]);
        $pdo->prepare(
            "UPDATE `{$posts}` SET og_image_path = NULL, og_image_media_id = NULL
             WHERE og_image_media_id = ?"
        )->execute([$id]);

        if ($force) {
            $pdo->prepare('DELETE FROM `' . cs_table('gallery_items') . '` WHERE media_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM `' . cs_table('playlist_items') . '` WHERE media_id = ?')->execute([$id]);
        }

        $pdo->prepare('DELETE FROM `' . media_table() . '` WHERE id = ?')->execute([$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    $abs = media_absolute_path((string) $row['path']);
    if (is_file($abs)) {
        @unlink($abs);
    }

    // Clear site OG setting if it pointed at this media.
    $ogMediaId = settings_get('og_image_media_id');
    if ($ogMediaId !== null && (int) $ogMediaId === $id) {
        settings_set('og_image_media_id', null);
        settings_set('og_image_path', null);
    }
}

/**
 * Replace media file contents with a cropped image blob (JPEG/PNG/WebP).
 *
 * @param array{data:string,mime?:string}|array<string,mixed> $upload Either raw binary in data, or $_FILES-like entry
 */
function media_save_cropped_image(int $id, array $upload, bool $asNew = false): array
{
    $row = media_find($id);
    if ($row === null) {
        throw new RuntimeException('Media not found.');
    }
    if ((string) $row['kind'] !== 'image') {
        throw new RuntimeException('Only images can be cropped.');
    }
    if (str_contains((string) $row['mime'], 'gif')) {
        throw new RuntimeException('GIF images cannot be cropped.');
    }

    $binary = null;
    $mime = null;

    if (isset($upload['data']) && is_string($upload['data'])) {
        $binary = $upload['data'];
        $mime = isset($upload['mime']) ? (string) $upload['mime'] : 'image/jpeg';
    } elseif (isset($upload['tmp_name'], $upload['error']) && (int) $upload['error'] === UPLOAD_ERR_OK) {
        $binary = (string) file_get_contents((string) $upload['tmp_name']);
        $mime = media_detect_mime((string) $upload['tmp_name'], (string) ($upload['name'] ?? 'crop.jpg'))
            ?? 'image/jpeg';
    } else {
        throw new RuntimeException('Cropped image data missing.');
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Cropped image must be JPEG, PNG, or WebP.');
    }

    $subdir = 'images';
    $dir = dirname(__DIR__) . '/assets/uploads/media/' . $subdir;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create uploads directory.');
    }

    $ext = $allowed[$mime];
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = $dir . '/' . $name;
    if (file_put_contents($dest, $binary) === false) {
        throw new RuntimeException('Could not save cropped image.');
    }

    $relative = 'assets/uploads/media/' . $subdir . '/' . $name;
    $width = null;
    $height = null;
    $info = @getimagesize($dest);
    if (is_array($info)) {
        $width = isset($info[0]) ? (int) $info[0] : null;
        $height = isset($info[1]) ? (int) $info[1] : null;
    }
    $size = (int) filesize($dest);

    if ($asNew) {
        $table = media_table();
        $stmt = db()->prepare(
            "INSERT INTO `{$table}` (
                kind, path, original_name, mime, size_bytes, title, alt_text, caption, description,
                width, height, uploaded_by
            ) VALUES (
                'image', :path, :original_name, :mime, :size_bytes, :title, :alt_text, :caption, :description,
                :width, :height, :uploaded_by
            )"
        );
        $stmt->execute([
            ':path' => $relative,
            ':original_name' => pathinfo((string) $row['original_name'], PATHINFO_FILENAME) . '-crop.' . $ext,
            ':mime' => $mime,
            ':size_bytes' => $size,
            ':title' => $row['title'] ?? null,
            ':alt_text' => $row['alt_text'] ?? null,
            ':caption' => $row['caption'] ?? null,
            ':description' => $row['description'] ?? null,
            ':width' => $width,
            ':height' => $height,
            ':uploaded_by' => auth_user_id(),
        ]);
        $newId = (int) db()->lastInsertId();
        $newRow = media_find($newId);
        if ($newRow === null) {
            throw new RuntimeException('Cropped media could not be loaded.');
        }
        return $newRow;
    }

    $oldAbs = media_absolute_path((string) $row['path']);
    $table = media_table();
    $stmt = db()->prepare(
        "UPDATE `{$table}` SET
            path = :path,
            mime = :mime,
            size_bytes = :size_bytes,
            width = :width,
            height = :height,
            updated_at = CURRENT_TIMESTAMP
         WHERE id = :id"
    );
    $stmt->execute([
        ':id' => $id,
        ':path' => $relative,
        ':mime' => $mime,
        ':size_bytes' => $size,
        ':width' => $width,
        ':height' => $height,
    ]);

    if (is_file($oldAbs) && realpath($oldAbs) !== realpath($dest)) {
        @unlink($oldAbs);
    }

    // Dual-write updated path onto posts / settings that reference this media id.
    $posts = cs_table('posts');
    db()->prepare(
        "UPDATE `{$posts}` SET image_path = ? WHERE image_media_id = ?"
    )->execute([$relative, $id]);
    db()->prepare(
        "UPDATE `{$posts}` SET og_image_path = ? WHERE og_image_media_id = ?"
    )->execute([$relative, $id]);
    $ogMediaId = settings_get('og_image_media_id');
    if ($ogMediaId !== null && (int) $ogMediaId === $id) {
        settings_set('og_image_path', $relative);
    }

    $updated = media_find($id);
    if ($updated === null) {
        throw new RuntimeException('Media could not be reloaded after crop.');
    }
    return $updated;
}

function media_public_url(array $row): string
{
    return '/' . ltrim((string) ($row['path'] ?? ''), '/');
}

function media_format_bytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1024 * 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return round($bytes / (1024 * 1024), 1) . ' MB';
}

function media_label(array $row): string
{
    $title = trim((string) ($row['title'] ?? ''));
    if ($title !== '') {
        return $title;
    }
    return (string) ($row['original_name'] ?? 'Media #' . ($row['id'] ?? ''));
}

/**
 * Resolve media id → dual-write path (+ id). Returns [media_id, path].
 *
 * @return array{0:?int,1:?string}
 */
function media_resolve_selection(?int $mediaId, ?string $fallbackPath = null): array
{
    if ($mediaId !== null && $mediaId > 0) {
        $row = media_find($mediaId);
        if ($row !== null && (string) $row['kind'] === 'image') {
            return [$mediaId, (string) $row['path']];
        }
    }
    $path = $fallbackPath !== null && trim($fallbackPath) !== '' ? trim($fallbackPath) : null;
    return [null, $path];
}

/* —— Galleries —— */

function galleries_table(): string
{
    return cs_table('galleries');
}

function gallery_items_table(): string
{
    return cs_table('gallery_items');
}

/**
 * @return list<array<string, mixed>>
 */
function galleries_list(): array
{
    $g = galleries_table();
    $gi = gallery_items_table();
    $sql = "SELECT g.*, COUNT(gi.id) AS item_count
            FROM `{$g}` g
            LEFT JOIN `{$gi}` gi ON gi.gallery_id = g.id
            GROUP BY g.id
            ORDER BY g.updated_at DESC, g.id DESC";
    return db()->query($sql)->fetchAll() ?: [];
}

function gallery_find(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $g = galleries_table();
    $stmt = db()->prepare("SELECT * FROM `{$g}` WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $row['items'] = gallery_items($id);
    return $row;
}

/**
 * @return list<array<string, mixed>>
 */
function gallery_items(int $galleryId): array
{
    $gi = gallery_items_table();
    $m = media_table();
    $stmt = db()->prepare(
        "SELECT gi.*, m.path, m.title AS media_title, m.alt_text, m.mime, m.kind, m.original_name
         FROM `{$gi}` gi
         INNER JOIN `{$m}` m ON m.id = gi.media_id
         WHERE gi.gallery_id = ?
         ORDER BY gi.sort_order ASC, gi.id ASC"
    );
    $stmt->execute([$galleryId]);
    return $stmt->fetchAll() ?: [];
}

function gallery_create(string $title, ?string $description = null): int
{
    $title = trim($title);
    if ($title === '') {
        throw new RuntimeException('Gallery title is required.');
    }
    $g = galleries_table();
    $stmt = db()->prepare(
        "INSERT INTO `{$g}` (title, description) VALUES (?, ?)"
    );
    $stmt->execute([$title, $description !== null && trim($description) !== '' ? trim($description) : null]);
    return (int) db()->lastInsertId();
}

function gallery_update(int $id, string $title, ?string $description = null): void
{
    $title = trim($title);
    if ($title === '') {
        throw new RuntimeException('Gallery title is required.');
    }
    $g = galleries_table();
    $stmt = db()->prepare(
        "UPDATE `{$g}` SET title = ?, description = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?"
    );
    $stmt->execute([
        $title,
        $description !== null && trim($description) !== '' ? trim($description) : null,
        $id,
    ]);
}

/**
 * @param list<array{media_id:int,caption?:?string}> $items
 */
function gallery_replace_items(int $galleryId, array $items): void
{
    $pdo = db();
    $gi = gallery_items_table();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM `{$gi}` WHERE gallery_id = ?")->execute([$galleryId]);
        $insert = $pdo->prepare(
            "INSERT INTO `{$gi}` (gallery_id, media_id, sort_order, caption) VALUES (?, ?, ?, ?)"
        );
        $order = 0;
        foreach ($items as $item) {
            $mediaId = (int) ($item['media_id'] ?? 0);
            if ($mediaId <= 0) {
                continue;
            }
            $media = media_find($mediaId);
            if ($media === null || (string) $media['kind'] !== 'image') {
                throw new RuntimeException('Gallery items must be images.');
            }
            $caption = isset($item['caption']) && trim((string) $item['caption']) !== ''
                ? trim((string) $item['caption'])
                : null;
            $insert->execute([$galleryId, $mediaId, $order, $caption]);
            $order++;
        }
        $pdo->prepare(
            'UPDATE `' . galleries_table() . '` SET updated_at = CURRENT_TIMESTAMP WHERE id = ?'
        )->execute([$galleryId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function gallery_delete(int $id): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM `' . gallery_items_table() . '` WHERE gallery_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM `' . galleries_table() . '` WHERE id = ?')->execute([$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/* —— Playlists —— */

function playlists_table(): string
{
    return cs_table('playlists');
}

function playlist_items_table(): string
{
    return cs_table('playlist_items');
}

/**
 * @return list<array<string, mixed>>
 */
function playlists_list(): array
{
    $p = playlists_table();
    $pi = playlist_items_table();
    $sql = "SELECT p.*, COUNT(pi.id) AS item_count
            FROM `{$p}` p
            LEFT JOIN `{$pi}` pi ON pi.playlist_id = p.id
            GROUP BY p.id
            ORDER BY p.updated_at DESC, p.id DESC";
    return db()->query($sql)->fetchAll() ?: [];
}

function playlist_find(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $p = playlists_table();
    $stmt = db()->prepare("SELECT * FROM `{$p}` WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $row['items'] = playlist_items($id);
    return $row;
}

/**
 * @return list<array<string, mixed>>
 */
function playlist_items(int $playlistId): array
{
    $pi = playlist_items_table();
    $m = media_table();
    $stmt = db()->prepare(
        "SELECT pi.*, m.path, m.title AS media_title, m.mime, m.kind, m.original_name, m.duration_seconds
         FROM `{$pi}` pi
         INNER JOIN `{$m}` m ON m.id = pi.media_id
         WHERE pi.playlist_id = ?
         ORDER BY pi.sort_order ASC, pi.id ASC"
    );
    $stmt->execute([$playlistId]);
    return $stmt->fetchAll() ?: [];
}

function playlist_create(string $title, ?string $description = null): int
{
    $title = trim($title);
    if ($title === '') {
        throw new RuntimeException('Playlist title is required.');
    }
    $p = playlists_table();
    $stmt = db()->prepare(
        "INSERT INTO `{$p}` (title, description) VALUES (?, ?)"
    );
    $stmt->execute([$title, $description !== null && trim($description) !== '' ? trim($description) : null]);
    return (int) db()->lastInsertId();
}

function playlist_update(int $id, string $title, ?string $description = null): void
{
    $title = trim($title);
    if ($title === '') {
        throw new RuntimeException('Playlist title is required.');
    }
    $p = playlists_table();
    $stmt = db()->prepare(
        "UPDATE `{$p}` SET title = ?, description = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?"
    );
    $stmt->execute([
        $title,
        $description !== null && trim($description) !== '' ? trim($description) : null,
        $id,
    ]);
}

/**
 * @param list<array{media_id:int,title?:?string}> $items
 */
function playlist_replace_items(int $playlistId, array $items): void
{
    $pdo = db();
    $pi = playlist_items_table();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM `{$pi}` WHERE playlist_id = ?")->execute([$playlistId]);
        $insert = $pdo->prepare(
            "INSERT INTO `{$pi}` (playlist_id, media_id, sort_order, title) VALUES (?, ?, ?, ?)"
        );
        $order = 0;
        foreach ($items as $item) {
            $mediaId = (int) ($item['media_id'] ?? 0);
            if ($mediaId <= 0) {
                continue;
            }
            $media = media_find($mediaId);
            if ($media === null || (string) $media['kind'] !== 'audio') {
                throw new RuntimeException('Playlist items must be audio.');
            }
            $title = isset($item['title']) && trim((string) $item['title']) !== ''
                ? trim((string) $item['title'])
                : null;
            $insert->execute([$playlistId, $mediaId, $order, $title]);
            $order++;
        }
        $pdo->prepare(
            'UPDATE `' . playlists_table() . '` SET updated_at = CURRENT_TIMESTAMP WHERE id = ?'
        )->execute([$playlistId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function playlist_delete(int $id): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM `' . playlist_items_table() . '` WHERE playlist_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM `' . playlists_table() . '` WHERE id = ?')->execute([$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

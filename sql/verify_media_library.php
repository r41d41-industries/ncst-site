<?php

declare(strict_types=1);

/**
 * Smoke checks for media library helpers (no browser login required).
 * Run: php sql/verify_media_library.php
 */

require dirname(__DIR__) . '/includes/bootstrap.php';

function assert_true(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
    echo "ok: {$msg}\n";
}

$pdo = db();
foreach (['media', 'galleries', 'gallery_items', 'playlists', 'playlist_items'] as $t) {
    $name = cs_table($t);
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
    );
    $stmt->execute([$name]);
    assert_true((bool) $stmt->fetchColumn(), "table {$name} exists");
}

$posts = cs_table('posts');
foreach (['image_media_id', 'og_image_media_id'] as $col) {
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
    );
    $stmt->execute([$posts, $col]);
    assert_true((bool) $stmt->fetchColumn(), "posts.{$col} exists");
}

foreach (['images', 'audio', 'documents'] as $subdir) {
    $dir = dirname(__DIR__) . '/assets/uploads/media/' . $subdir;
    assert_true(is_dir($dir), "disk dir media/{$subdir}");
}

// Create a tiny PNG and store as media.
$png = base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
);
assert_true($png !== false && $png !== '', 'fixture png decoded');

$tmp = tempnam(sys_get_temp_dir(), 'ncstmedia');
assert_true($tmp !== false, 'temp file created');
file_put_contents($tmp, $png);

$file = [
    'name' => 'pixel.png',
    'type' => 'image/png',
    'tmp_name' => $tmp,
    'error' => UPLOAD_ERR_OK,
    'size' => strlen($png),
];

// media_store_upload expects move_uploaded_file; for CLI use a shim path copy.
// Bypass by writing directly via internal path insert if move_uploaded_file fails.
$uploaded = null;
try {
    // Simulate upload: copy into place manually if needed.
    if (!is_uploaded_file($tmp)) {
        $mime = media_detect_mime($tmp, 'pixel.png');
        assert_true($mime === 'image/png', 'detect mime png');
        $dir = dirname(__DIR__) . '/assets/uploads/media/images';
        $name = bin2hex(random_bytes(8)) . '.png';
        $dest = $dir . '/' . $name;
        copy($tmp, $dest);
        $relative = 'assets/uploads/media/images/' . $name;
        $table = media_table();
        $stmt = db()->prepare(
            "INSERT INTO `{$table}` (kind, path, original_name, mime, size_bytes, title, width, height)
             VALUES ('image', ?, 'pixel.png', 'image/png', ?, 'Smoke pixel', 1, 1)"
        );
        $stmt->execute([$relative, strlen($png)]);
        $uploaded = media_find((int) db()->lastInsertId());
    } else {
        $uploaded = media_store_upload($file, ['title' => 'Smoke pixel']);
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: upload ' . $e->getMessage() . "\n");
    exit(1);
}

assert_true($uploaded !== null && (int) $uploaded['id'] > 0, 'media row created');
$mediaId = (int) $uploaded['id'];

media_update_meta($mediaId, [
    'title' => 'Smoke pixel updated',
    'alt_text' => 'red pixel',
    'description' => 'verify meta',
]);
$updated = media_find($mediaId);
assert_true($updated !== null && (string) $updated['title'] === 'Smoke pixel updated', 'meta update');

$list = media_list(['kind' => 'image', 'q' => 'Smoke pixel']);
assert_true(count($list) >= 1, 'media_list finds image');

$galleryId = gallery_create('Smoke gallery', 'verify');
gallery_replace_items($galleryId, [['media_id' => $mediaId, 'caption' => 'cap']]);
$g = gallery_find($galleryId);
assert_true($g !== null && count($g['items']) === 1, 'gallery with one image');

// Audio fixture (empty-ish wav header not needed — create placeholder txt as document instead)
$docTmp = tempnam(sys_get_temp_dir(), 'ncstdoc');
file_put_contents($docTmp, "hello media verify\n");
$docName = bin2hex(random_bytes(6)) . '.txt';
$docRel = 'assets/uploads/media/documents/' . $docName;
copy($docTmp, dirname(__DIR__) . '/' . $docRel);
$table = media_table();
db()->prepare(
    "INSERT INTO `{$table}` (kind, path, original_name, mime, size_bytes, title)
     VALUES ('document', ?, 'hello.txt', 'text/plain', ?, 'Smoke doc')"
)->execute([$docRel, filesize(dirname(__DIR__) . '/' . $docRel)]);
$docId = (int) db()->lastInsertId();
assert_true($docId > 0, 'document media row');

// Audio row for playlist
$audioName = bin2hex(random_bytes(6)) . '.mp3';
$audioRel = 'assets/uploads/media/audio/' . $audioName;
file_put_contents(dirname(__DIR__) . '/' . $audioRel, 'ID3');
db()->prepare(
    "INSERT INTO `{$table}` (kind, path, original_name, mime, size_bytes, title)
     VALUES ('audio', ?, 'tone.mp3', 'audio/mpeg', 3, 'Smoke audio')"
)->execute([$audioRel]);
$audioId = (int) db()->lastInsertId();

$playlistId = playlist_create('Smoke playlist', null);
playlist_replace_items($playlistId, [['media_id' => $audioId, 'title' => 'Track 1']]);
$p = playlist_find($playlistId);
assert_true($p !== null && count($p['items']) === 1, 'playlist with one audio');

[$resolvedId, $resolvedPath] = media_resolve_selection($mediaId, null);
assert_true($resolvedId === $mediaId && is_string($resolvedPath) && $resolvedPath !== '', 'resolve selection dual-write path');

// Delete should block while in gallery
$blocked = false;
try {
    media_delete($mediaId);
} catch (Throwable $e) {
    $blocked = true;
}
assert_true($blocked, 'delete blocked when in gallery');

gallery_delete($galleryId);
media_delete($mediaId);
assert_true(media_find($mediaId) === null, 'media deleted after gallery cleared');

playlist_delete($playlistId);
media_delete($audioId);
media_delete($docId);

@unlink($tmp);
@unlink($docTmp);

echo "All media library smoke checks passed.\n";

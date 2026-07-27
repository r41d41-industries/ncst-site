<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
auth_require();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$item = media_find($id);
if ($item === null) {
    flash_set('error', 'Media not found.');
    redirect('/admin/media/');
}

$error = null;
$flash = flash_get('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'save');
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $error = 'Invalid CSRF token.';
    } elseif ($action === 'delete') {
        try {
            media_delete($id);
            flash_set('success', 'Media deleted.');
            redirect('/admin/media/');
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    } elseif ($action === 'crop') {
        try {
            $b64 = (string) ($_POST['crop_data'] ?? '');
            if ($b64 === '') {
                throw new RuntimeException('No crop data received.');
            }
            $binary = base64_decode($b64, true);
            if ($binary === false || $binary === '') {
                throw new RuntimeException('Invalid crop data.');
            }
            $mime = (string) ($_POST['crop_mime'] ?? 'image/jpeg');
            $asNew = isset($_POST['crop_as_new']);
            $saved = media_save_cropped_image($id, ['data' => $binary, 'mime' => $mime], $asNew);
            flash_set('success', $asNew ? 'Cropped copy saved.' : 'Cropped image saved.');
            redirect('/admin/media/edit.php?id=' . (int) $saved['id']);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    } elseif ($action === 'replace') {
        try {
            if (!isset($_FILES['replace_file']) || (int) ($_FILES['replace_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                throw new RuntimeException('Choose a file to replace.');
            }
            $new = media_store_upload($_FILES['replace_file'], [
                'kind' => (string) $item['kind'],
                'title' => (string) ($item['title'] ?? ''),
                'alt_text' => (string) ($item['alt_text'] ?? ''),
                'description' => (string) ($item['description'] ?? ''),
            ]);
            // Point posts that used old id… keep old row but delete after soft-clearing refs to old.
            // Simpler: delete old file/row after clearing FKs that we re-point.
            $posts = cs_table('posts');
            db()->prepare(
                "UPDATE `{$posts}` SET image_path = ?, image_media_id = ? WHERE image_media_id = ?"
            )->execute([(string) $new['path'], (int) $new['id'], $id]);
            db()->prepare(
                "UPDATE `{$posts}` SET og_image_path = ?, og_image_media_id = ? WHERE og_image_media_id = ?"
            )->execute([(string) $new['path'], (int) $new['id'], $id]);
            $gi = cs_table('gallery_items');
            db()->prepare("UPDATE `{$gi}` SET media_id = ? WHERE media_id = ?")->execute([(int) $new['id'], $id]);
            $pi = cs_table('playlist_items');
            db()->prepare("UPDATE `{$pi}` SET media_id = ? WHERE media_id = ?")->execute([(int) $new['id'], $id]);
            $ogMediaId = settings_get('og_image_media_id');
            if ($ogMediaId !== null && (int) $ogMediaId === $id) {
                settings_set_og_image_from_media((int) $new['id']);
            }
            media_delete($id, true);
            flash_set('success', 'File replaced.');
            redirect('/admin/media/edit.php?id=' . (int) $new['id']);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    } else {
        try {
            media_update_meta($id, [
                'title' => $_POST['title'] ?? null,
                'alt_text' => $_POST['alt_text'] ?? null,
                'caption' => $_POST['caption'] ?? null,
                'description' => $_POST['description'] ?? null,
                'duration_seconds' => ($_POST['duration_seconds'] ?? '') !== ''
                    ? (int) $_POST['duration_seconds']
                    : null,
            ]);
            flash_set('success', 'Media updated.');
            redirect('/admin/media/edit.php?id=' . $id);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
    $item = media_find($id) ?? $item;
}

$refs = media_reference_counts($id);
$canCrop = (string) $item['kind'] === 'image' && !str_contains((string) $item['mime'], 'gif');
$cropMime = match ((string) $item['mime']) {
    'image/png' => 'image/png',
    'image/webp' => 'image/webp',
    default => 'image/jpeg',
};

$adminPageTitle = 'Edit media';
$adminSection = 'media';
$adminPanelTitle = 'Media';
$adminShowAdd = false;
$adminMediaPage = 'all';
$adminExtraHead = $canCrop
    ? '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">'
    : '';
$adminExtraScripts = $canCrop
    ? '<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>'
    : '';

require dirname(__DIR__, 2) . '/includes/partials/admin_shell_start.php';
?>
        <div class="admin-content__header">
          <div>
            <h1>Edit media</h1>
            <p class="admin-content__lead"><?= e(media_label($item)) ?> · <?= e((string) $item['kind']) ?></p>
          </div>
          <a class="tu-btn tu-btn--secondary" href="/admin/media/">Back to library</a>
        </div>

        <?php if ($flash): ?>
          <div class="tu-alert tu-alert--success" role="status"><?= e($flash) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="tu-alert tu-alert--danger" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="media-edit-layout">
          <div>
            <div class="media-edit-preview">
              <?php if ((string) $item['kind'] === 'image'): ?>
                <?php if ($canCrop): ?>
                  <div class="media-crop-wrap">
                    <img id="media-crop-image" src="<?= e(media_public_url($item)) ?>?v=<?= e((string) strtotime((string) $item['updated_at'])) ?>" alt="<?= e((string) ($item['alt_text'] ?? '')) ?>">
                  </div>
                <?php else: ?>
                  <img src="<?= e(media_public_url($item)) ?>" alt="<?= e((string) ($item['alt_text'] ?? '')) ?>">
                <?php endif; ?>
              <?php elseif ((string) $item['kind'] === 'audio'): ?>
                <audio controls src="<?= e(media_public_url($item)) ?>"></audio>
              <?php else: ?>
                <a class="tu-btn tu-btn--secondary" href="<?= e(media_public_url($item)) ?>" target="_blank" rel="noopener">Open document</a>
              <?php endif; ?>
            </div>

            <?php if ($canCrop): ?>
              <form id="media-crop-form" method="post" action="" class="tu-card" style="margin-top:16px;">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="crop">
                <input type="hidden" name="crop_data" id="media-crop-data" value="">
                <input type="hidden" name="crop_mime" id="media-crop-mime" value="<?= e($cropMime) ?>">
                <h2 style="margin-top:0;font-size:16px;">Crop image</h2>
                <p class="tu-help">Adjust the crop area, then save. Optionally keep the original by saving as a new library item.</p>
                <label class="tu-check" style="margin-bottom:12px;">
                  <input type="checkbox" name="crop_as_new" value="1">
                  Save as new media item
                </label>
                <div class="tu-actions">
                  <button type="button" class="tu-btn tu-btn--brand" id="media-crop-save">Save crop</button>
                </div>
              </form>
            <?php endif; ?>
          </div>

          <div>
            <div class="tu-card">
              <form method="post" action="">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save">
                <div class="tu-form-row">
                  <label for="title">Title</label>
                  <input id="title" name="title" type="text" maxlength="255" value="<?= e((string) ($item['title'] ?? '')) ?>">
                </div>
                <?php if ((string) $item['kind'] === 'image'): ?>
                  <div class="tu-form-row">
                    <label for="alt_text">Alt text</label>
                    <input id="alt_text" name="alt_text" type="text" maxlength="255" value="<?= e((string) ($item['alt_text'] ?? '')) ?>">
                  </div>
                <?php endif; ?>
                <div class="tu-form-row">
                  <label for="caption">Caption</label>
                  <input id="caption" name="caption" type="text" maxlength="512" value="<?= e((string) ($item['caption'] ?? '')) ?>">
                </div>
                <div class="tu-form-row">
                  <label for="description">Description</label>
                  <textarea id="description" name="description" rows="4"><?= e((string) ($item['description'] ?? '')) ?></textarea>
                </div>
                <?php if ((string) $item['kind'] === 'audio'): ?>
                  <div class="tu-form-row">
                    <label for="duration_seconds">Duration (seconds)</label>
                    <input id="duration_seconds" name="duration_seconds" type="number" min="0" step="1" value="<?= e((string) ($item['duration_seconds'] ?? '')) ?>">
                  </div>
                <?php endif; ?>
                <p class="tu-help">
                  File: <?= e((string) $item['original_name']) ?> · <?= e((string) $item['mime']) ?> · <?= e(media_format_bytes((int) $item['size_bytes'])) ?>
                  <?php if (!empty($item['width'])): ?>
                    · <?= e((string) $item['width']) ?>×<?= e((string) $item['height']) ?>
                  <?php endif; ?>
                </p>
                <p class="tu-help">
                  References — posts: <?= e((string) $refs['posts']) ?>,
                  galleries: <?= e((string) $refs['galleries']) ?>,
                  playlists: <?= e((string) $refs['playlists']) ?>
                </p>
                <div class="tu-actions">
                  <button class="tu-btn tu-btn--brand" type="submit">Save metadata</button>
                </div>
              </form>
            </div>

            <div class="tu-card" style="margin-top:16px;">
              <h2 style="margin-top:0;font-size:16px;">Replace file</h2>
              <form method="post" enctype="multipart/form-data" action="">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="replace">
                <div class="tu-form-row">
                  <label for="replace_file">New file (same kind)</label>
                  <input id="replace_file" name="replace_file" type="file" required>
                </div>
                <button class="tu-btn tu-btn--secondary" type="submit">Replace</button>
              </form>
            </div>

            <div class="tu-card" style="margin-top:16px;">
              <h2 style="margin-top:0;font-size:16px;">Delete</h2>
              <p class="tu-help">Deletes the file from disk. Blocked while used in galleries or playlists. Post references are cleared.</p>
              <form method="post" action="" onsubmit="return confirm('Delete this media item permanently?');">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete">
                <button class="tu-btn tu-btn--danger" type="submit">Delete media</button>
              </form>
            </div>
          </div>
        </div>
<?php
require dirname(__DIR__, 2) . '/includes/partials/admin_shell_end.php';

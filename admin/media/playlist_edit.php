<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
auth_require();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$playlist = $id > 0 ? playlist_find($id) : null;
$isEdit = $playlist !== null;
$error = null;

$form = [
    'title' => $playlist['title'] ?? '',
    'description' => $playlist['description'] ?? '',
];
$items = $isEdit ? ($playlist['items'] ?? []) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $error = 'Invalid CSRF token.';
    } else {
        $action = (string) ($_POST['action'] ?? 'save');
        if ($isEdit && $action === 'delete') {
            try {
                playlist_delete($id);
                flash_set('success', 'Playlist deleted.');
                redirect('/admin/media/playlists.php');
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        } else {
            $form['title'] = trim((string) ($_POST['title'] ?? ''));
            $form['description'] = trim((string) ($_POST['description'] ?? ''));
            $mediaIds = $_POST['item_media_ids'] ?? [];
            $titles = $_POST['item_captions'] ?? [];
            if (!is_array($mediaIds)) {
                $mediaIds = [];
            }
            if (!is_array($titles)) {
                $titles = [];
            }
            $ordered = [];
            foreach ($mediaIds as $i => $mid) {
                $ordered[] = [
                    'media_id' => (int) $mid,
                    'title' => isset($titles[$i]) ? (string) $titles[$i] : null,
                ];
            }
            try {
                if ($isEdit) {
                    playlist_update($id, $form['title'], $form['description']);
                    playlist_replace_items($id, $ordered);
                    flash_set('success', 'Playlist saved.');
                    redirect('/admin/media/playlist_edit.php?id=' . $id);
                } else {
                    $newId = playlist_create($form['title'], $form['description']);
                    playlist_replace_items($newId, $ordered);
                    flash_set('success', 'Playlist created.');
                    redirect('/admin/media/playlist_edit.php?id=' . $newId);
                }
            } catch (Throwable $e) {
                $error = $e->getMessage();
                $items = [];
                foreach ($ordered as $row) {
                    $m = media_find((int) $row['media_id']);
                    if ($m) {
                        $items[] = array_merge($m, [
                            'media_id' => (int) $m['id'],
                            'title' => $row['title'],
                            'media_title' => $m['title'],
                        ]);
                    }
                }
            }
        }
    }
}

$flash = flash_get('success');

$adminPageTitle = ($isEdit ? 'Edit' : 'New') . ' playlist';
$adminSection = 'media';
$adminPanelTitle = 'Media';
$adminShowAdd = false;
$adminMediaPage = 'playlists';

require dirname(__DIR__, 2) . '/includes/partials/admin_shell_start.php';
?>
        <div class="admin-content__header">
          <div>
            <h1><?= $isEdit ? 'Edit playlist' : 'New playlist' ?></h1>
            <p class="admin-content__lead">Add audio from the library and set track order.</p>
          </div>
          <a class="tu-btn tu-btn--secondary" href="/admin/media/playlists.php">Back to playlists</a>
        </div>

        <?php if ($flash): ?>
          <div class="tu-alert tu-alert--success" role="status"><?= e($flash) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="tu-alert tu-alert--danger" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="tu-card">
          <form method="post" action="">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save">
            <div class="tu-form-row">
              <label for="title">Title</label>
              <input id="title" name="title" type="text" required maxlength="255" value="<?= e((string) $form['title']) ?>">
            </div>
            <div class="tu-form-row">
              <label for="description">Description</label>
              <textarea id="description" name="description" rows="3"><?= e((string) $form['description']) ?></textarea>
            </div>

            <div class="tu-form-row">
              <span class="tu-label">Tracks</span>
              <ul id="playlist-items" class="media-collection-items">
                <?php foreach ($items as $row): ?>
                  <?php
                    $mid = (int) ($row['media_id'] ?? $row['id'] ?? 0);
                    $label = (string) ($row['media_title'] ?? $row['original_name'] ?? ('#' . $mid));
                    $override = (string) ($row['title'] ?? '');
                  ?>
                  <li class="media-collection-items__row" data-media-id="<?= e((string) $mid) ?>">
                    <span class="media-collection-items__ord"></span>
                    <span class="media-grid__thumb-icon">Audio</span>
                    <div>
                      <strong><?= e($label) ?></strong>
                      <input type="hidden" name="item_media_ids[]" value="<?= e((string) $mid) ?>">
                      <input class="tu-input" type="text" name="item_captions[]" value="<?= e($override) ?>" placeholder="Optional title override" style="margin-top:6px;">
                    </div>
                    <button type="button" class="tu-btn tu-btn--secondary" data-collection-remove>Remove</button>
                  </li>
                <?php endforeach; ?>
              </ul>
              <div class="media-field__actions" style="margin-top:10px;">
                <button type="button" class="tu-btn tu-btn--secondary" data-collection-add data-collection-list="playlist-items" data-media-kind="audio">Add audio</button>
                <button type="button" class="tu-btn tu-btn--tertiary" data-media-upload-open data-media-kind="audio">Upload new</button>
              </div>
            </div>

            <div class="tu-actions">
              <button class="tu-btn tu-btn--brand" type="submit"><?= $isEdit ? 'Save playlist' : 'Create playlist' ?></button>
            </div>
          </form>
        </div>

        <?php if ($isEdit): ?>
          <div class="tu-card" style="margin-top:16px;">
            <form method="post" action="" onsubmit="return confirm('Delete this playlist?');">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="delete">
              <button class="tu-btn tu-btn--danger" type="submit">Delete playlist</button>
            </form>
          </div>
        <?php endif; ?>
<?php
require dirname(__DIR__, 2) . '/includes/partials/admin_shell_end.php';

<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
auth_require();

$defaults = settings_og_defaults();
$error = null;
$flash = flash_get('success');

$form = [
    'og_title' => $defaults['title'],
    'og_description' => $defaults['description'],
    'og_site_name' => $defaults['site_name'],
    'og_type' => $defaults['type'],
    'og_image_path' => $defaults['image_path'] ?? '',
    'og_image_media_id' => settings_get('og_image_media_id') ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['og_title'] = trim((string) ($_POST['og_title'] ?? ''));
    $form['og_description'] = trim((string) ($_POST['og_description'] ?? ''));
    $form['og_site_name'] = trim((string) ($_POST['og_site_name'] ?? ''));
    $form['og_type'] = trim((string) ($_POST['og_type'] ?? 'website'));
    $form['og_image_media_id'] = trim((string) ($_POST['og_image_media_id'] ?? ''));
    $form['og_image_path'] = trim((string) ($_POST['og_image_path'] ?? ''));
    $removeImage = isset($_POST['remove_image']);

    if ($form['og_title'] === '') {
        $error = 'Title is required.';
    } else {
        try {
            $mediaId = $form['og_image_media_id'] !== '' ? (int) $form['og_image_media_id'] : null;
            $imagePath = $form['og_image_path'] !== '' ? $form['og_image_path'] : null;

            if ($removeImage) {
                $mediaId = null;
                $imagePath = null;
            } else {
                [$mediaId, $imagePath] = media_resolve_selection($mediaId, $imagePath);
                $uploadedId = null;
                $uploadedPath = settings_handle_og_upload(
                    $_FILES['og_image'] ?? ['error' => UPLOAD_ERR_NO_FILE],
                    $imagePath,
                    $uploadedId
                );
                if ($uploadedId !== null) {
                    $mediaId = $uploadedId;
                    $imagePath = $uploadedPath;
                } else {
                    $imagePath = $uploadedPath;
                }
            }

            settings_set_many([
                'og_title' => $form['og_title'],
                'og_description' => $form['og_description'],
                'og_site_name' => $form['og_site_name'] !== '' ? $form['og_site_name'] : 'Newnan Coweta Scanner Traffic',
                'og_type' => $form['og_type'] !== '' ? $form['og_type'] : 'website',
                'og_image_path' => $imagePath,
                'og_image_media_id' => $mediaId !== null ? (string) $mediaId : null,
            ]);

            flash_set('success', 'Open Graph settings saved.');
            redirect('/admin/settings/open-graph.php');
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$adminPageTitle = 'Settings — Open Graph';
$adminSection = 'settings';
$adminPanelTitle = 'Settings';
$adminShowAdd = false;
$adminSettingsPage = 'open-graph';

require dirname(__DIR__, 2) . '/includes/partials/admin_shell_start.php';
?>
        <div class="admin-content__header">
          <div>
            <h1>Open Graph</h1>
            <p class="admin-content__lead">Site-wide defaults for social sharing. News, updates, and weather posts can override these per article.</p>
          </div>
        </div>

        <?php if ($flash): ?>
          <div class="tu-alert tu-alert--success" role="status"><?= e($flash) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="tu-alert tu-alert--danger" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="tu-card">
          <form method="post" enctype="multipart/form-data" action="">
            <div class="tu-form-row">
              <label for="og_title">Default title</label>
              <input id="og_title" name="og_title" type="text" required maxlength="255" value="<?= e((string) $form['og_title']) ?>">
            </div>
            <div class="tu-form-row">
              <label for="og_description">Default description</label>
              <textarea id="og_description" name="og_description" rows="4"><?= e((string) $form['og_description']) ?></textarea>
            </div>
            <div class="tu-form-row">
              <label for="og_site_name">Site name</label>
              <input id="og_site_name" name="og_site_name" type="text" maxlength="255" value="<?= e((string) $form['og_site_name']) ?>">
            </div>
            <div class="tu-form-row">
              <label for="og_type">Default type</label>
              <input id="og_type" name="og_type" type="text" maxlength="64" value="<?= e((string) $form['og_type']) ?>">
              <p class="tu-help">Usually <code>website</code>. Article pages use <code>article</code> automatically.</p>
            </div>
            <div class="tu-form-row">
              <span class="tu-label">Default image</span>
              <div class="media-field" data-media-field data-media-kind="image">
                <input type="hidden" name="og_image_media_id" data-media-id value="<?= e((string) $form['og_image_media_id']) ?>">
                <input type="hidden" name="og_image_path" data-media-path value="<?= e((string) $form['og_image_path']) ?>">
                <div class="media-field__preview">
                  <img
                    class="media-field__thumb"
                    data-media-thumb
                    src="<?= !empty($form['og_image_path']) ? e('/' . ltrim((string) $form['og_image_path'], '/')) : '' ?>"
                    alt=""
                    <?= empty($form['og_image_path']) ? 'hidden' : '' ?>
                  >
                  <span data-media-label><?= !empty($form['og_image_path']) || !empty($form['og_image_media_id']) ? e((string) ($form['og_image_path'] ?: ('Media #' . $form['og_image_media_id']))) : 'No media selected' ?></span>
                </div>
                <div class="media-field__actions">
                  <button type="button" class="tu-btn tu-btn--secondary" data-media-choose>Choose from library</button>
                  <button type="button" class="tu-btn tu-btn--tertiary" data-media-clear>Clear</button>
                </div>
                <label class="tu-check" style="margin-top:8px;">
                  <input type="checkbox" name="remove_image" value="1">
                  Remove image on save
                </label>
                <p class="tu-help" style="margin-top:8px;">Or upload a new file (adds to the library):</p>
                <input id="og_image" name="og_image" type="file" accept="image/jpeg,image/png,image/webp,image/gif">
              </div>
            </div>
            <div class="tu-actions">
              <button class="tu-btn tu-btn--brand" type="submit">Save Open Graph settings</button>
            </div>
          </form>
        </div>
<?php
require dirname(__DIR__, 2) . '/includes/partials/admin_shell_end.php';

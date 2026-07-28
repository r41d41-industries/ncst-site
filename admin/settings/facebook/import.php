<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
auth_require();

$error = null;
$flash = flash_get('success');
$form = [
    'fb_import_status' => facebook_import_status(),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $error = 'Invalid request. Please try again.';
    } else {
        $status = strtolower(trim((string) ($_POST['fb_import_status'] ?? 'draft')));
        if (!in_array($status, ['draft', 'published', 'published_with_comments'], true)) {
            $status = 'draft';
        }
        $form['fb_import_status'] = $status;
        try {
            settings_set('fb_import_status', $status);
            flash_set('success', 'Import settings saved.');
            redirect('/admin/settings/facebook/import.php');
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$adminPageTitle = 'Settings — Facebook Import';
$adminSection = 'settings';
$adminPanelTitle = 'Settings';
$adminShowAdd = false;
$adminSettingsPage = 'facebook-import';

require dirname(__DIR__, 3) . '/includes/partials/admin_shell_start.php';
?>
        <div class="admin-content__header">
          <div>
            <h1>Facebook Import</h1>
            <p class="admin-content__lead">Choose whether converting a Facebook post creates a draft or publishes it to the feed immediately.</p>
          </div>
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
            <fieldset class="tu-form-row">
              <legend class="tu-label">When creating a feed post</legend>
              <label class="tu-check">
                <input
                  type="radio"
                  name="fb_import_status"
                  value="draft"
                  <?= $form['fb_import_status'] === 'draft' ? ' checked' : '' ?>
                >
                Save as draft
              </label>
              <label class="tu-check">
                <input
                  type="radio"
                  name="fb_import_status"
                  value="published"
                  <?= $form['fb_import_status'] === 'published' ? ' checked' : '' ?>
                >
                Publish immediately
              </label>
              <label class="tu-check">
                <input
                  type="radio"
                  name="fb_import_status"
                  value="published_with_comments"
                  <?= $form['fb_import_status'] === 'published_with_comments' ? ' checked' : '' ?>
                >
                Publish immediately + apply Page comments
              </label>
              <p class="tu-help">Applies to the Create post action on Posts → Facebook. The third option also turns Page <code>UPDATE |</code> comments into feed updates and sets Cleared from <code>CLEARED |</code> comments.</p>
            </fieldset>
            <div class="tu-btn-row">
              <button type="submit" class="tu-btn tu-btn--brand">Save import settings</button>
              <a class="tu-btn tu-btn--secondary" href="/admin/facebook/posts.php">Open Facebook posts</a>
            </div>
          </form>
        </div>
<?php
require dirname(__DIR__, 3) . '/includes/partials/admin_shell_end.php';

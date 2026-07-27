<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
auth_require();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$row = $id > 0 ? shortcodes_find($id) : null;
$isEdit = $row !== null;

$error = null;
$form = [
    'code' => $row['code'] ?? '',
    'replacement' => $row['replacement'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'save');
    if ($action === 'delete' && $isEdit) {
        shortcodes_delete($id);
        flash_set('success', 'Shortcode deleted.');
        redirect('/admin/settings/shortcodes.php');
    }

    $form['code'] = trim((string) ($_POST['code'] ?? ''));
    $form['replacement'] = (string) ($_POST['replacement'] ?? '');
    try {
        if ($isEdit) {
            shortcodes_update($id, $form['code'], $form['replacement']);
            flash_set('success', 'Shortcode updated.');
        } else {
            shortcodes_create($form['code'], $form['replacement']);
            flash_set('success', 'Shortcode created.');
        }
        redirect('/admin/settings/shortcodes.php');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$adminPageTitle = ($isEdit ? 'Edit' : 'Add') . ' Shortcode';
$adminSection = 'settings';
$adminPanelTitle = 'Settings';
$adminShowAdd = false;
$adminSettingsPage = 'shortcodes';

require dirname(__DIR__, 2) . '/includes/partials/admin_shell_start.php';
?>
        <div class="admin-content__header">
          <div>
            <h1><?= $isEdit ? 'Edit shortcode' : 'Add shortcode' ?></h1>
            <p class="admin-content__lead">Keys are used as <code>[key]</code> in article HTML. Use <code>__NOW__</code> in the value for the current date/time at render.</p>
          </div>
          <a class="tu-btn tu-btn--secondary" href="/admin/settings/shortcodes.php">Back to shortcodes</a>
        </div>

        <?php if ($error): ?>
          <div class="tu-alert tu-alert--danger" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="tu-card">
          <form method="post" action="">
            <input type="hidden" name="action" value="save">
            <div class="tu-form-row">
              <label for="code">Key <span class="tu-required">*</span></label>
              <input id="code" name="code" type="text" required maxlength="64" value="<?= e((string) $form['code']) ?>" placeholder="current time">
              <p class="tu-help">Without brackets. Example: <code>current time</code> → <code>[current time]</code></p>
            </div>
            <div class="tu-form-row">
              <label for="replacement">Replacement</label>
              <textarea id="replacement" name="replacement" rows="4"><?= e((string) $form['replacement']) ?></textarea>
            </div>
            <div class="tu-actions">
              <button class="tu-btn tu-btn--brand" type="submit"><?= $isEdit ? 'Save changes' : 'Create shortcode' ?></button>
              <a class="tu-btn tu-btn--tertiary" href="/admin/settings/shortcodes.php">Cancel</a>
            </div>
          </form>
          <?php if ($isEdit): ?>
            <form method="post" action="" style="margin-top:24px;" onsubmit="return confirm('Delete this shortcode?');">
              <input type="hidden" name="action" value="delete">
              <button class="tu-btn tu-btn--danger" type="submit">Delete shortcode</button>
            </form>
          <?php endif; ?>
        </div>
<?php
require dirname(__DIR__, 2) . '/includes/partials/admin_shell_end.php';

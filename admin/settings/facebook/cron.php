<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
auth_require();

$error = null;
$flash = flash_get('success');
$cron = facebook_cron_settings();
$frequencies = facebook_cron_frequencies();

$form = [
    'fb_cron_enabled' => $cron['enabled'] ? '1' : '0',
    'fb_cron_frequency' => $cron['frequency'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $error = 'Invalid request. Please try again.';
    } else {
        $enabled = isset($_POST['fb_cron_enabled']) && (string) $_POST['fb_cron_enabled'] === '1' ? '1' : '0';
        $frequency = trim((string) ($_POST['fb_cron_frequency'] ?? 'hourly'));
        if (!isset($frequencies[$frequency])) {
            $frequency = 'hourly';
        }

        $form = [
            'fb_cron_enabled' => $enabled,
            'fb_cron_frequency' => $frequency,
        ];

        try {
            settings_set_many([
                'fb_cron_enabled' => $enabled,
                'fb_cron_frequency' => $frequency,
            ]);
            flash_set('success', 'Facebook cron settings saved.');
            redirect('/admin/settings/facebook/cron.php');
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$adminPageTitle = 'Settings — Facebook Cron';
$adminSection = 'settings';
$adminPanelTitle = 'Settings';
$adminShowAdd = false;
$adminSettingsPage = 'facebook-cron';

require dirname(__DIR__, 3) . '/includes/partials/admin_shell_start.php';
?>
        <div class="admin-content__header">
          <div>
            <h1>Facebook Cron</h1>
            <p class="admin-content__lead">Schedule settings for automatic Facebook post sync. Automatic runs are not wired yet — use Sync Now on the Posts page for now.</p>
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
            <div class="tu-form-row">
              <span class="tu-label">Automatic sync</span>
              <label class="tu-check">
                <input type="checkbox" name="fb_cron_enabled" value="1"<?= $form['fb_cron_enabled'] === '1' ? ' checked' : '' ?>>
                Enable scheduled sync
              </label>
            </div>
            <div class="tu-form-row">
              <label for="fb_cron_frequency">Frequency</label>
              <select id="fb_cron_frequency" name="fb_cron_frequency">
                <?php foreach ($frequencies as $value => $label): ?>
                  <option value="<?= e($value) ?>"<?= $form['fb_cron_frequency'] === $value ? ' selected' : '' ?>>
                    <?= e($label) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="tu-form-row">
              <span class="tu-label">Last automatic run</span>
              <p class="tu-help" style="margin:0;">
                <?= $cron['last_run'] !== null ? e($cron['last_run']) : 'Never (scheduler not connected yet).' ?>
              </p>
            </div>
            <div class="tu-btn-row">
              <button type="submit" class="tu-btn tu-btn--brand">Save cron settings</button>
              <a class="tu-btn tu-btn--secondary" href="/admin/facebook/posts.php">Back to posts</a>
            </div>
          </form>
        </div>
<?php
require dirname(__DIR__, 3) . '/includes/partials/admin_shell_end.php';

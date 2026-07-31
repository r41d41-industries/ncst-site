<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
auth_require();

$error = null;
$flash = flash_get('success');
$cron = facebook_cron_settings();
$frequencies = facebook_cron_frequencies();
$autoPost = facebook_auto_post_enabled();

$form = [
    'fb_cron_enabled' => $cron['enabled'] ? '1' : '0',
    'fb_cron_frequency' => $cron['frequency'],
    'fb_auto_post_mode' => $autoPost ? '1' : '0',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $error = 'Invalid request. Please try again.';
    } else {
        $wantAuto = isset($_POST['fb_auto_post_mode']) && (string) $_POST['fb_auto_post_mode'] === '1';
        $enabled = isset($_POST['fb_cron_enabled']) && (string) $_POST['fb_cron_enabled'] === '1' ? '1' : '0';
        $frequency = trim((string) ($_POST['fb_cron_frequency'] ?? 'hourly'));
        if (!isset($frequencies[$frequency])) {
            $frequency = 'hourly';
        }

        $form = [
            'fb_cron_enabled' => $enabled,
            'fb_cron_frequency' => $frequency,
            'fb_auto_post_mode' => $wantAuto ? '1' : '0',
        ];

        try {
            $newlyEnabling = $wantAuto && !$autoPost;
            $turningOffAuto = !$wantAuto && $autoPost;

            if ($newlyEnabling) {
                // Shared enable path: forces cron on + 15m, then immediate 10-minute catch-up sync.
                $saved = facebook_auto_post_set(true);
                $freqLabel = facebook_cron_frequencies()[$saved['frequency']] ?? $saved['frequency'];
                $msg = 'Auto-post enabled. Cron is on and set to ' . $freqLabel
                    . ' (editable; includes Every 5 minutes).';
                if ($saved['bootstrap_error'] !== null) {
                    $msg .= ' Catch-up sync failed: ' . $saved['bootstrap_error'];
                } elseif (is_array($saved['bootstrap'])) {
                    $converted = (int) ($saved['bootstrap']['auto_convert']['converted'] ?? 0);
                    $msg .= ' Catch-up sync ran for the prior 10 minutes'
                        . ($converted === 1 ? ' (1 post converted).' : ' (' . $converted . ' posts converted).');
                }
                flash_set('success', $msg);
            } elseif ($turningOffAuto) {
                facebook_auto_post_set(false);
                // Preserve cron enabled/frequency from the form after disabling auto-post.
                settings_set_many([
                    'fb_cron_enabled' => $enabled,
                    'fb_cron_frequency' => $frequency,
                ]);
                flash_set('success', 'Auto-post disabled. Cron settings saved.');
            } else {
                // Auto-post unchanged: save cron settings only (and keep auto-post flag in sync).
                settings_set_many([
                    'fb_auto_post_mode' => $wantAuto ? '1' : '0',
                    'fb_cron_enabled' => $enabled,
                    'fb_cron_frequency' => $frequency,
                ]);
                flash_set('success', 'Facebook cron settings saved.');
            }
            redirect('/admin/settings/facebook/cron.php');
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$cronSecretConfigured = facebook_cron_secret() !== '';
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
            <p class="admin-content__lead">Schedule automatic Facebook post sync. Use the CLI or HTTP endpoint below; frequency is enforced in PHP so you can invoke the runner more often than the interval.</p>
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
              <span class="tu-label">Auto-post mode</span>
              <label class="tu-check">
                <input type="checkbox" name="fb_auto_post_mode" value="1"<?= $form['fb_auto_post_mode'] === '1' ? ' checked' : '' ?>>
                Enable auto-post (convert hashtag posts, apply comments, refresh bodies)
              </label>
              <p class="tu-help">Turning auto-post on enables scheduled sync, sets frequency to every 15 minutes (editable afterward, including every 5 minutes), and immediately runs a catch-up sync for posts from the prior 10 minutes. See <a href="/admin/settings/facebook/auto-post.php">Auto-post</a> for full behavior.</p>
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
                <?= $cron['last_run'] !== null ? e($cron['last_run']) : 'Never' ?>
              </p>
            </div>
            <div class="tu-form-row">
              <span class="tu-label">Runner</span>
              <p class="tu-help" style="margin:0;">
                CLI: <code>php cron/facebook_sync.php</code> (add <code>--force</code> to ignore the interval)
                <?php if ($cronSecretConfigured): ?>
                  · HTTP: <code>/cron/facebook_sync.php?secret=…</code>
                <?php else: ?>
                  · Set <code>CRON_SECRET</code> in <code>.env</code> for HTTP triggers.
                <?php endif; ?>
              </p>
            </div>
            <div class="tu-btn-row">
              <button type="submit" class="tu-btn tu-btn--brand">Save cron settings</button>
              <a class="tu-btn tu-btn--secondary" href="/admin/settings/facebook/auto-post.php">Auto-post settings</a>
              <a class="tu-btn tu-btn--secondary" href="/admin/facebook/posts.php">Back to posts</a>
            </div>
          </form>
        </div>
<?php
require dirname(__DIR__, 3) . '/includes/partials/admin_shell_end.php';

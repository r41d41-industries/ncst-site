<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
auth_require();

$error = null;
$flash = flash_get('success');
try {
    facebook_ensure_auto_post_schema();
} catch (Throwable) {
    // Schema ensure is best-effort; page still loads.
}
$cron = facebook_cron_settings();
$autoPost = facebook_auto_post_enabled();
$form = [
    'fb_auto_post_mode' => $autoPost ? '1' : '0',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $error = 'Invalid request. Please try again.';
    } else {
        $enabled = isset($_POST['fb_auto_post_mode']) && (string) $_POST['fb_auto_post_mode'] === '1';
        $form['fb_auto_post_mode'] = $enabled ? '1' : '0';
        try {
            $wasEnabled = $autoPost;
            $saved = facebook_auto_post_set($enabled);
            $cron = facebook_cron_settings();
            $autoPost = $saved['auto_post'];
            if ($enabled && !$wasEnabled) {
                $freqLabel = facebook_cron_frequencies()[$saved['frequency']] ?? $saved['frequency'];
                $msg = 'Auto-post mode enabled. Cron is on and set to ' . $freqLabel
                    . ' (editable under Cron, including Every 5 minutes).';
                if ($saved['bootstrap_error'] !== null) {
                    $msg .= ' Catch-up sync failed: ' . $saved['bootstrap_error'];
                } elseif (is_array($saved['bootstrap'])) {
                    $converted = (int) ($saved['bootstrap']['auto_convert']['converted'] ?? 0);
                    $msg .= ' Catch-up sync ran for the prior 10 minutes'
                        . ($converted === 1 ? ' (1 post converted).' : ' (' . $converted . ' posts converted).');
                }
                flash_set('success', $msg);
            } elseif ($enabled) {
                flash_set('success', 'Auto-post settings saved.');
            } else {
                flash_set('success', 'Auto-post mode disabled. Scheduled sync settings were left unchanged.');
            }
            redirect('/admin/settings/facebook/auto-post.php');
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$cronSecretConfigured = facebook_cron_secret() !== '';
$adminPageTitle = 'Settings — Facebook Auto-post';
$adminSection = 'settings';
$adminPanelTitle = 'Settings';
$adminShowAdd = false;
$adminSettingsPage = 'facebook-auto-post';

require dirname(__DIR__, 3) . '/includes/partials/admin_shell_start.php';
?>
        <div class="admin-content__header">
          <div>
            <h1>Facebook Auto-post</h1>
            <p class="admin-content__lead">When enabled, the scheduled sync converts new Facebook posts with a mapped hashtag, applies Page <code>UPDATE |</code> / <code>CLEARED |</code> comments, and refreshes body text for posts within 6 hours of their Facebook created time.</p>
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
              <span class="tu-label">Auto-post mode</span>
              <label class="tu-check">
                <input type="checkbox" name="fb_auto_post_mode" value="1"<?= $form['fb_auto_post_mode'] === '1' ? ' checked' : '' ?>>
                Enable Facebook Sync auto-post mode
              </label>
              <p class="tu-help">Turning this on enables scheduled sync, sets frequency to every 15 minutes (change later on the Cron page — including every 5 minutes), and immediately runs a catch-up sync for qualifying posts from the prior 10 minutes. Manual Sync Now does not run auto-post actions.</p>
            </div>
            <div class="tu-form-row">
              <span class="tu-label">Current schedule</span>
              <p class="tu-help" style="margin:0;">
                Cron: <?= $cron['enabled'] ? 'enabled' : 'disabled' ?>
                · Frequency: <?= e(facebook_cron_frequencies()[$cron['frequency']] ?? $cron['frequency']) ?>
                · Last run: <?= $cron['last_run'] !== null ? e($cron['last_run']) : 'Never' ?>
              </p>
            </div>
            <div class="tu-form-row">
              <span class="tu-label">While active, each scheduled sync</span>
              <ul class="tu-help" style="margin:0.35rem 0 0; padding-left:1.25rem;">
                <li>Syncs Facebook posts</li>
                <li>Auto-converts newly synced posts that have a mapped hashtag topic (published; skips if no hashtag)</li>
                <li>Syncs comments for converted posts and applies new Page UPDATE / CLEARED comments</li>
                <li>Updates body text when the Facebook message changed and <code>fb_created_time</code> is within 6 hours</li>
              </ul>
            </div>
            <div class="tu-form-row">
              <span class="tu-label">On enable</span>
              <p class="tu-help" style="margin:0;">
                Immediately syncs Facebook and auto-converts unconverted posts with a mapped hashtag whose <code>fb_created_time</code> is within the prior 10 minutes, then applies comments and refreshes recent bodies.
              </p>
            </div>
            <div class="tu-form-row">
              <span class="tu-label">Cron endpoint</span>
              <p class="tu-help" style="margin:0;">
                CLI: <code>php cron/facebook_sync.php</code>
                <?php if ($cronSecretConfigured): ?>
                  · HTTP: <code>/cron/facebook_sync.php?secret=…</code>
                <?php else: ?>
                  · Set <code>CRON_SECRET</code> in <code>.env</code> to allow HTTP triggers.
                <?php endif; ?>
              </p>
            </div>
            <div class="tu-btn-row">
              <button type="submit" class="tu-btn tu-btn--brand">Save auto-post settings</button>
              <a class="tu-btn tu-btn--secondary" href="/admin/settings/facebook/cron.php">Cron settings</a>
            </div>
          </form>
        </div>
<?php
require dirname(__DIR__, 3) . '/includes/partials/admin_shell_end.php';

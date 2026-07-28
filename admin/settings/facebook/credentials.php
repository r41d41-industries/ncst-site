<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
auth_require();

$error = null;
$flash = flash_get('success');
$form = facebook_credentials_form_values();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $error = 'Invalid request. Please try again.';
    } else {
        $pageId = trim((string) ($_POST['fb_page_id'] ?? ''));
        $token = trim((string) ($_POST['fb_page_access_token'] ?? ''));
        $appId = trim((string) ($_POST['fb_app_id'] ?? ''));
        $appSecret = trim((string) ($_POST['fb_app_secret'] ?? ''));
        $version = trim((string) ($_POST['fb_graph_version'] ?? ''));

        $existing = facebook_credentials();
        // Empty password-style fields keep the current resolved value when saving.
        if ($token === '') {
            $token = $existing['page_access_token'];
        }
        if ($appSecret === '') {
            $appSecret = $existing['app_secret'];
        }
        if ($version === '') {
            $version = 'v25.0';
        }
        if (!str_starts_with($version, 'v')) {
            $version = 'v' . $version;
        }

        $form = [
            'fb_page_id' => $pageId,
            'fb_page_access_token' => $token,
            'fb_app_id' => $appId,
            'fb_app_secret' => $appSecret,
            'fb_graph_version' => $version,
        ];

        if ($pageId === '') {
            $error = 'Page ID is required.';
        } elseif ($token === '') {
            $error = 'Page access token is required.';
        } else {
            try {
                settings_set_many([
                    'fb_page_id' => $pageId,
                    'fb_page_access_token' => $token,
                    'fb_app_id' => $appId !== '' ? $appId : null,
                    'fb_app_secret' => $appSecret !== '' ? $appSecret : null,
                    'fb_graph_version' => $version,
                ]);
                flash_set('success', 'Facebook credentials saved.');
                redirect('/admin/settings/facebook/credentials.php');
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
}

$adminPageTitle = 'Settings — Facebook Credentials';
$adminSection = 'settings';
$adminPanelTitle = 'Settings';
$adminShowAdd = false;
$adminSettingsPage = 'facebook-credentials';

require dirname(__DIR__, 3) . '/includes/partials/admin_shell_start.php';
?>
        <div class="admin-content__header">
          <div>
            <h1>Facebook Credentials</h1>
            <p class="admin-content__lead">Page access credentials for Graph API sync. Values fall back to <code>.env</code> (<code>FB_*</code>) until you save them here.</p>
          </div>
        </div>

        <?php if ($flash): ?>
          <div class="tu-alert tu-alert--success" role="status"><?= e($flash) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="tu-alert tu-alert--danger" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="tu-card">
          <form method="post" action="" autocomplete="off">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <div class="tu-form-row">
              <label for="fb_page_id">Page ID</label>
              <input id="fb_page_id" name="fb_page_id" type="text" required maxlength="64" value="<?= e($form['fb_page_id']) ?>">
            </div>
            <div class="tu-form-row">
              <label for="fb_page_access_token">Page access token</label>
              <input
                id="fb_page_access_token"
                name="fb_page_access_token"
                type="password"
                maxlength="512"
                value=""
                placeholder="<?= $form['fb_page_access_token'] !== '' ? e(facebook_mask_secret($form['fb_page_access_token'])) : 'Enter page access token' ?>"
                autocomplete="new-password"
              >
              <p class="tu-help">Leave blank to keep the current token<?= $form['fb_page_access_token'] !== '' ? ' (' . e(facebook_mask_secret($form['fb_page_access_token'])) . ')' : '' ?>.</p>
            </div>
            <div class="tu-form-row">
              <label for="fb_app_id">App ID</label>
              <input id="fb_app_id" name="fb_app_id" type="text" maxlength="64" value="<?= e($form['fb_app_id']) ?>">
            </div>
            <div class="tu-form-row">
              <label for="fb_app_secret">App secret</label>
              <input
                id="fb_app_secret"
                name="fb_app_secret"
                type="password"
                maxlength="128"
                value=""
                placeholder="<?= $form['fb_app_secret'] !== '' ? e(facebook_mask_secret($form['fb_app_secret'])) : 'Enter app secret' ?>"
                autocomplete="new-password"
              >
              <p class="tu-help">Leave blank to keep the current secret<?= $form['fb_app_secret'] !== '' ? ' (' . e(facebook_mask_secret($form['fb_app_secret'])) . ')' : '' ?>.</p>
            </div>
            <div class="tu-form-row">
              <label for="fb_graph_version">Graph API version</label>
              <input id="fb_graph_version" name="fb_graph_version" type="text" maxlength="16" value="<?= e($form['fb_graph_version']) ?>">
              <p class="tu-help">Example: <code>v25.0</code>. Requires <code>pages_read_engagement</code> on the Page token.</p>
            </div>
            <div class="tu-btn-row">
              <button type="submit" class="tu-btn tu-btn--brand">Save credentials</button>
              <a class="tu-btn tu-btn--secondary" href="/admin/facebook/posts.php">Back to posts</a>
            </div>
          </form>
        </div>
<?php
require dirname(__DIR__, 3) . '/includes/partials/admin_shell_end.php';

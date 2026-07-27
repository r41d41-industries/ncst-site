<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (auth_check()) {
    redirect('/admin/');
}

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$reset = $token !== '' ? auth_find_valid_reset($token) : null;
$error = null;

if ($token === '' || !$reset) {
    $authPageTitle = 'Reset password';
    require dirname(__DIR__) . '/includes/partials/admin_auth_start.php';
    ?>
    <div class="admin-auth__card tu-card">
      <h1>Reset link invalid</h1>
      <p class="admin-auth__lead">This password reset link is invalid or has expired. Request a new link to continue.</p>
      <div class="tu-alert tu-alert--danger" role="alert">Invalid or expired reset token.</div>
      <p class="admin-auth__helper"><a href="/admin/forgot-password.php">Request a new reset link</a> · <a href="/admin/login.php">Sign in</a></p>
    </div>
    <?php
    require dirname(__DIR__) . '/includes/partials/admin_auth_end.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $error = 'Invalid session. Please try again.';
    } elseif (empty($_POST['accept_terms'])) {
        $error = 'Please accept the Terms of Service to continue.';
    } else {
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');

        if (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } elseif (!auth_consume_password_reset($reset['id'], $reset['user_id'], $password)) {
            $error = 'This reset link is no longer valid. Request a new one.';
        } else {
            flash_set('auth_success', 'Password updated. You can sign in now.');
            redirect('/admin/login.php');
        }
    }
}

$authPageTitle = 'Reset password';
require dirname(__DIR__) . '/includes/partials/admin_auth_start.php';
?>
    <div class="admin-auth__card tu-card">
      <h1>Change password</h1>
      <p class="admin-auth__lead">Choose a new password for your admin account.</p>

      <?php if ($error): ?>
        <div class="tu-alert tu-alert--danger" role="alert"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" action="/admin/reset-password.php" autocomplete="new-password">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <div class="tu-form-row">
          <label for="password">New password <span class="tu-required" aria-hidden="true">*</span></label>
          <div class="tu-input-icon">
            <svg class="tu-input-icon__glyph" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <rect x="4" y="11" width="16" height="10" rx="2"/>
              <path d="M8 11V8a4 4 0 0 1 8 0v3" stroke-linecap="round"/>
            </svg>
            <input id="password" name="password" type="password" required autofocus autocomplete="new-password" minlength="8" placeholder="••••••••">
          </div>
        </div>

        <div class="tu-form-row">
          <label for="password_confirm">Confirm password <span class="tu-required" aria-hidden="true">*</span></label>
          <div class="tu-input-icon">
            <svg class="tu-input-icon__glyph" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <rect x="4" y="11" width="16" height="10" rx="2"/>
              <path d="M8 11V8a4 4 0 0 1 8 0v3" stroke-linecap="round"/>
            </svg>
            <input id="password_confirm" name="password_confirm" type="password" required autocomplete="new-password" minlength="8" placeholder="••••••••">
          </div>
        </div>

        <div class="tu-form-row">
          <label class="tu-check">
            <input type="checkbox" name="accept_terms" value="1" required<?= !empty($_POST['accept_terms']) ? ' checked' : '' ?>>
            <span>I agree to the <a href="/#terms" target="_blank" rel="noopener">Terms of Service</a></span>
          </label>
        </div>

        <button class="tu-btn tu-btn--brand tu-btn--block" type="submit">Reset password</button>
      </form>

      <p class="admin-auth__helper"><a href="/admin/login.php">Back to sign in</a></p>
    </div>
<?php
require dirname(__DIR__) . '/includes/partials/admin_auth_end.php';

<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (auth_check()) {
    redirect('/admin/');
}

$error = null;
$flash = flash_get('auth_success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $error = 'Invalid session. Please try again.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $remember = !empty($_POST['remember']);

        if ($username === '' || $password === '') {
            $error = 'Enter username and password.';
        } elseif (auth_login($username, $password, $remember)) {
            redirect('/admin/');
        } else {
            $error = 'Invalid username or password.';
        }
    }
}

$authPageTitle = 'Admin Login';
require dirname(__DIR__) . '/includes/partials/admin_auth_start.php';
?>
    <div class="admin-auth__card tu-card">
      <h1>Sign in</h1>
      <p class="admin-auth__lead">Sign in to manage the scanner feed.</p>

      <?php if ($flash): ?>
        <div class="tu-alert tu-alert--success" role="status"><?= e($flash) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="tu-alert tu-alert--danger" role="alert"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" action="/admin/login.php" autocomplete="username">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

        <div class="tu-form-row">
          <label for="username">Username <span class="tu-required" aria-hidden="true">*</span></label>
          <div class="tu-input-icon">
            <svg class="tu-input-icon__glyph" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <path d="M20 21a8 8 0 0 0-16 0" stroke-linecap="round"/>
              <circle cx="12" cy="8" r="4"/>
            </svg>
            <input id="username" name="username" type="text" required autofocus autocomplete="username" placeholder="admin" value="<?= e((string) ($_POST['username'] ?? '')) ?>">
          </div>
        </div>

        <div class="tu-form-row">
          <label for="password">Password <span class="tu-required" aria-hidden="true">*</span></label>
          <div class="tu-input-icon">
            <svg class="tu-input-icon__glyph" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <rect x="4" y="11" width="16" height="10" rx="2"/>
              <path d="M8 11V8a4 4 0 0 1 8 0v3" stroke-linecap="round"/>
            </svg>
            <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="••••••••">
          </div>
        </div>

        <div class="admin-auth__meta">
          <label class="tu-check">
            <input type="checkbox" name="remember" value="1"<?= !empty($_POST['remember']) ? ' checked' : '' ?>>
            <span>Remember me</span>
          </label>
          <a class="admin-auth__link" href="/admin/forgot-password.php">Forgot password?</a>
        </div>

        <button class="tu-btn tu-btn--brand tu-btn--block" type="submit">Sign in</button>
      </form>

      <p class="admin-auth__helper">Looking for the public site? <a href="/">View feed</a></p>
    </div>
<?php
require dirname(__DIR__) . '/includes/partials/admin_auth_end.php';

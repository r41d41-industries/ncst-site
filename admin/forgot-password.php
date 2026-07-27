<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (auth_check()) {
    redirect('/admin/');
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $error = 'Invalid session. Please try again.';
    } elseif (empty($_POST['accept_terms'])) {
        $error = 'Please accept the Terms of Service to continue.';
    } elseif (auth_forgot_rate_limited()) {
        // Same generic copy — avoid confirming throttle state to attackers
        $success = 'If an account exists for that email, we sent a password reset link. Check your inbox.';
    } else {
        auth_forgot_rate_hit();
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address.';
        } else {
            $user = auth_find_user_by_email($email);
            if ($user && $user['email']) {
                try {
                    $token = auth_create_password_reset($user['id']);
                    $mail = cs_mail_send_password_reset($user['email'], $token, $user['display_name']);
                    if (!$mail['ok']) {
                        error_log('NCST password reset mail failed: ' . ($mail['error'] ?? 'unknown'));
                    }
                } catch (Throwable $e) {
                    error_log('NCST password reset error: ' . $e->getMessage());
                }
            }
            $success = 'If an account exists for that email, we sent a password reset link. Check your inbox.';
        }
    }
}

$authPageTitle = 'Forgot password';
require dirname(__DIR__) . '/includes/partials/admin_auth_start.php';
?>
    <div class="admin-auth__card tu-card">
      <h1>Forgot your password?</h1>
      <p class="admin-auth__lead">Enter the email on your admin account and we will send a reset link if it matches a user.</p>

      <?php if ($success): ?>
        <div class="tu-alert tu-alert--success" role="status"><?= e($success) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="tu-alert tu-alert--danger" role="alert"><?= e($error) ?></div>
      <?php endif; ?>

      <?php if (!$success): ?>
      <form method="post" action="/admin/forgot-password.php" autocomplete="email">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

        <div class="tu-form-row">
          <label for="email">Email <span class="tu-required" aria-hidden="true">*</span></label>
          <div class="tu-input-icon">
            <svg class="tu-input-icon__glyph" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <rect x="3" y="5" width="18" height="14" rx="2"/>
              <path d="M3 7l9 7 9-7" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <input id="email" name="email" type="email" required autofocus autocomplete="email" placeholder="name@example.com" value="<?= e((string) ($_POST['email'] ?? '')) ?>">
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
      <?php endif; ?>

      <p class="admin-auth__helper"><a href="/admin/login.php">Back to sign in</a></p>
    </div>
<?php
require dirname(__DIR__) . '/includes/partials/admin_auth_end.php';

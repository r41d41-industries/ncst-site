<?php

declare(strict_types=1);

$user = auth_current_user();
$accountDisplay = (string) ($user['display_name'] ?? '');
$accountEmail = (string) ($user['email'] ?? '');
$accountUsername = (string) ($user['username'] ?? auth_username() ?? '');
?>
<div id="admin-account-overlay" class="admin-modal-overlay" hidden>
  <div
    id="admin-account-modal"
    class="admin-modal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="admin-account-title"
    tabindex="-1"
  >
    <div class="admin-modal__header">
      <h2 id="admin-account-title" class="admin-modal__title">Manage account</h2>
      <button type="button" class="admin-modal__close" data-account-close aria-label="Close">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <path d="M6 6l12 12M18 6L6 18" stroke-linecap="round"/>
        </svg>
      </button>
    </div>
    <form id="admin-account-form" class="admin-modal__form" action="/admin/account.php" method="post" novalidate>
      <div class="admin-modal__body">
        <div id="admin-account-status" class="admin-modal__status" role="status" aria-live="polite"></div>
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <div class="admin-modal__grid">
          <div class="tu-form-row">
            <label for="account-display-name">Display name</label>
            <input id="account-display-name" name="display_name" type="text" maxlength="128" value="<?= e($accountDisplay) ?>" autocomplete="name">
          </div>
          <div class="tu-form-row">
            <label for="account-email">Email <span class="tu-required" aria-hidden="true">*</span></label>
            <input id="account-email" name="email" type="email" required value="<?= e($accountEmail) ?>" autocomplete="email">
          </div>
          <div class="tu-form-row admin-modal__span-2">
            <label for="account-username">Username</label>
            <input id="account-username" type="text" value="<?= e($accountUsername) ?>" disabled readonly>
            <p class="tu-help">Username is used to sign in and cannot be changed here.</p>
          </div>
          <div class="tu-form-row admin-modal__span-2">
            <label for="account-current-password">Current password</label>
            <input id="account-current-password" name="current_password" type="password" autocomplete="current-password" placeholder="Required to change password">
          </div>
          <div class="tu-form-row">
            <label for="account-new-password">New password</label>
            <input id="account-new-password" name="new_password" type="password" autocomplete="new-password" minlength="8" placeholder="Leave blank to keep">
          </div>
          <div class="tu-form-row">
            <label for="account-new-password-confirm">Confirm new password</label>
            <input id="account-new-password-confirm" name="new_password_confirm" type="password" autocomplete="new-password" minlength="8">
          </div>
        </div>
      </div>
      <div class="admin-modal__footer">
        <button type="submit" class="tu-btn tu-btn--brand tu-btn--modal">Save changes</button>
        <button type="button" class="tu-btn tu-btn--secondary tu-btn--modal" data-account-close>Close</button>
      </div>
    </form>
  </div>
</div>

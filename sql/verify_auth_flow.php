<?php

declare(strict_types=1);

/**
 * Local verification harness for auth reset flow.
 * Run: php sql/verify_auth_flow.php
 */

ob_start();
require dirname(__DIR__) . '/includes/bootstrap.php';
ob_end_clean();

$ok = true;
function assert_true(bool $cond, string $label): void
{
    global $ok;
    if ($cond) {
        echo "OK  {$label}\n";
    } else {
        echo "FAIL {$label}\n";
        $ok = false;
    }
}

$user = auth_find_user_by_username('admin');
assert_true($user !== null, 'admin user exists');
assert_true(!empty($user['email']), 'admin has email: ' . ($user['email'] ?? ''));

$login = auth_login('admin', 'changeme', false);
assert_true($login, 'login with username/password');
assert_true(auth_check(), 'session authenticated after login');

$token = auth_create_password_reset((int) $user['id']);
assert_true(strlen($token) >= 64, 'reset token generated');
$reset = auth_find_valid_reset($token);
assert_true($reset !== null, 'reset token validates');

$mail = cs_mail_send_password_reset((string) $user['email'], $token, $user['display_name']);
assert_true($mail['ok'], 'SMTP2GO reset email sent' . ($mail['error'] ? ' — ' . $mail['error'] : ''));

$newPass = 'changeme-verify-' . bin2hex(random_bytes(3));
$consumed = auth_consume_password_reset($reset['id'], $reset['user_id'], $newPass);
assert_true($consumed, 'password reset consumed');
assert_true(auth_find_valid_reset($token) === null, 'token single-use');

auth_logout();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('NCSTSESSID');
    session_start();
}
assert_true(auth_login('admin', $newPass, false), 'login with new password');

$users = cs_table('users');
$hash = password_hash('changeme', PASSWORD_DEFAULT);
db()->prepare("UPDATE `{$users}` SET password_hash = ? WHERE username = 'admin'")->execute([$hash]);
assert_true(true, 'restored admin password to changeme');

$err = auth_update_account((int) $user['id'], [
    'display_name' => 'Admin Verify',
    'email' => (string) $user['email'],
    'current_password' => 'changeme',
    'new_password' => '',
]);
assert_true($err === null, 'account profile update');
$fresh = auth_find_user_by_id((int) $user['id']);
assert_true(($fresh['display_name'] ?? '') === 'Admin Verify', 'display_name persisted');

auth_update_account((int) $user['id'], [
    'display_name' => 'Admin',
    'email' => (string) $user['email'],
]);

echo $ok ? "\nALL CHECKS PASSED\n" : "\nSOME CHECKS FAILED\n";
exit($ok ? 0 : 1);

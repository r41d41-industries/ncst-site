<?php

declare(strict_types=1);

function auth_user_id(): ?int
{
    $id = $_SESSION['cs_user_id'] ?? null;
    return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
}

function auth_username(): ?string
{
    $name = $_SESSION['cs_username'] ?? null;
    return is_string($name) && $name !== '' ? $name : null;
}

function auth_email(): ?string
{
    $email = $_SESSION['cs_email'] ?? null;
    return is_string($email) && $email !== '' ? $email : null;
}

function auth_display_name(): ?string
{
    $name = $_SESSION['cs_display_name'] ?? null;
    return is_string($name) && $name !== '' ? $name : null;
}

function auth_check(): bool
{
    return auth_user_id() !== null;
}

function auth_require(): void
{
    if (!auth_check()) {
        redirect('/admin/login.php');
    }
}

function cs_cookie_secure(): bool
{
    $site = (string) (getenv('SITE_URL') ?: '');
    return str_starts_with(strtolower($site), 'https://');
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_verify(?string $token): bool
{
    $session = $_SESSION['_csrf'] ?? '';
    return is_string($session)
        && $session !== ''
        && is_string($token)
        && hash_equals($session, $token);
}

/**
 * @return array{id:int,username:string,email:?string,display_name:?string,password_hash:string}|null
 */
function auth_find_user_by_id(int $id): ?array
{
    $table = cs_table('users');
    $stmt = db()->prepare(
        "SELECT id, username, email, display_name, password_hash FROM `{$table}` WHERE id = ? LIMIT 1"
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ? auth_normalize_user_row($row) : null;
}

/**
 * @return array{id:int,username:string,email:?string,display_name:?string,password_hash:string}|null
 */
function auth_find_user_by_username(string $username): ?array
{
    $table = cs_table('users');
    $stmt = db()->prepare(
        "SELECT id, username, email, display_name, password_hash FROM `{$table}` WHERE username = ? LIMIT 1"
    );
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    return $row ? auth_normalize_user_row($row) : null;
}

/**
 * @return array{id:int,username:string,email:?string,display_name:?string,password_hash:string}|null
 */
function auth_find_user_by_email(string $email): ?array
{
    $email = strtolower(trim($email));
    if ($email === '') {
        return null;
    }
    $table = cs_table('users');
    $stmt = db()->prepare(
        "SELECT id, username, email, display_name, password_hash FROM `{$table}` WHERE LOWER(email) = ? LIMIT 1"
    );
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    return $row ? auth_normalize_user_row($row) : null;
}

/**
 * @param array<string,mixed> $row
 * @return array{id:int,username:string,email:?string,display_name:?string,password_hash:string}
 */
function auth_normalize_user_row(array $row): array
{
    $email = isset($row['email']) && is_string($row['email']) && $row['email'] !== ''
        ? (string) $row['email']
        : null;
    $display = isset($row['display_name']) && is_string($row['display_name']) && $row['display_name'] !== ''
        ? (string) $row['display_name']
        : null;

    return [
        'id' => (int) $row['id'],
        'username' => (string) $row['username'],
        'email' => $email,
        'display_name' => $display,
        'password_hash' => (string) $row['password_hash'],
    ];
}

/**
 * @return array{id:int,username:string,email:?string,display_name:?string,password_hash:string}|null
 */
function auth_current_user(): ?array
{
    $id = auth_user_id();
    if ($id === null) {
        return null;
    }
    return auth_find_user_by_id($id);
}

/**
 * @param array{id:int,username:string,email:?string,display_name:?string} $user
 */
function auth_set_session_user(array $user, bool $remember = false): void
{
    session_regenerate_id(true);
    $_SESSION['cs_user_id'] = (int) $user['id'];
    $_SESSION['cs_username'] = (string) $user['username'];
    $_SESSION['cs_email'] = $user['email'] ?? null;
    $_SESSION['cs_display_name'] = $user['display_name'] ?? null;

    $params = session_get_cookie_params();
    $expires = $remember ? time() + (60 * 60 * 24 * 30) : 0;
    setcookie(session_name(), session_id(), [
        'expires' => $expires,
        'path' => $params['path'] ?: '/',
        'domain' => $params['domain'] ?? '',
        'secure' => cs_cookie_secure() || !empty($params['secure']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function auth_login(string $username, string $password, bool $remember = false): bool
{
    $row = auth_find_user_by_username($username);
    if (!$row || !password_verify($password, $row['password_hash'])) {
        return false;
    }

    auth_set_session_user($row, $remember);
    return true;
}

function auth_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'] ?: '/',
            'domain' => $params['domain'] ?? '',
            'secure' => (bool) $params['secure'],
            'httponly' => (bool) $params['httponly'],
            'samesite' => 'Lax',
        ]);
    }
    session_destroy();
}

function auth_forgot_rate_limited(): bool
{
    $now = time();
    $window = 900; // 15 minutes
    $max = 5;
    $bucket = $_SESSION['_forgot_rl'] ?? ['start' => $now, 'count' => 0];
    if (!is_array($bucket)) {
        $bucket = ['start' => $now, 'count' => 0];
    }
    $start = (int) ($bucket['start'] ?? $now);
    $count = (int) ($bucket['count'] ?? 0);
    if ($now - $start > $window) {
        $start = $now;
        $count = 0;
    }
    $_SESSION['_forgot_rl'] = ['start' => $start, 'count' => $count];
    return $count >= $max;
}

function auth_forgot_rate_hit(): void
{
    $now = time();
    $bucket = $_SESSION['_forgot_rl'] ?? ['start' => $now, 'count' => 0];
    if (!is_array($bucket)) {
        $bucket = ['start' => $now, 'count' => 0];
    }
    $start = (int) ($bucket['start'] ?? $now);
    $count = (int) ($bucket['count'] ?? 0);
    if ($now - $start > 900) {
        $start = $now;
        $count = 0;
    }
    $_SESSION['_forgot_rl'] = ['start' => $start, 'count' => $count + 1];
}

/**
 * Create a single-use reset token (raw returned once; only hash stored).
 */
function auth_create_password_reset(int $userId): string
{
    $raw = bin2hex(random_bytes(32));
    $hash = hash('sha256', $raw);
    $table = cs_table('password_resets');

    // Invalidate prior unused tokens for this user
    $invalidate = db()->prepare(
        "UPDATE `{$table}` SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL"
    );
    $invalidate->execute([$userId]);

    $stmt = db()->prepare(
        "INSERT INTO `{$table}` (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))"
    );
    $stmt->execute([$userId, $hash]);

    return $raw;
}

/**
 * @return array{id:int,user_id:int}|null
 */
function auth_find_valid_reset(string $rawToken): ?array
{
    $rawToken = trim($rawToken);
    if ($rawToken === '' || strlen($rawToken) < 32) {
        return null;
    }
    $hash = hash('sha256', $rawToken);
    $table = cs_table('password_resets');
    $stmt = db()->prepare(
        "SELECT id, user_id FROM `{$table}`
         WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
         LIMIT 1"
    );
    $stmt->execute([$hash]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    return [
        'id' => (int) $row['id'],
        'user_id' => (int) $row['user_id'],
    ];
}

function auth_consume_password_reset(int $resetId, int $userId, string $newPassword): bool
{
    $users = cs_table('users');
    $resets = cs_table('password_resets');
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $upd = $pdo->prepare("UPDATE `{$users}` SET password_hash = ? WHERE id = ?");
        $upd->execute([$hash, $userId]);

        $mark = $pdo->prepare(
            "UPDATE `{$resets}` SET used_at = NOW() WHERE id = ? AND user_id = ? AND used_at IS NULL"
        );
        $mark->execute([$resetId, $userId]);
        if ($mark->rowCount() < 1) {
            $pdo->rollBack();
            return false;
        }

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Update profile fields for the current user.
 * Returns error message or null on success.
 *
 * @param array{display_name?:string,email?:string,current_password?:string,new_password?:string} $data
 */
function auth_update_account(int $userId, array $data): ?string
{
    $user = auth_find_user_by_id($userId);
    if (!$user) {
        return 'Account not found.';
    }

    $displayName = trim((string) ($data['display_name'] ?? $user['display_name'] ?? ''));
    $email = strtolower(trim((string) ($data['email'] ?? $user['email'] ?? '')));
    $currentPassword = (string) ($data['current_password'] ?? '');
    $newPassword = (string) ($data['new_password'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Enter a valid email address.';
    }

    if (strlen($displayName) > 128) {
        return 'Display name is too long.';
    }

    $changingPassword = $newPassword !== '';
    if ($changingPassword) {
        if ($currentPassword === '' || !password_verify($currentPassword, $user['password_hash'])) {
            return 'Current password is incorrect.';
        }
        if (strlen($newPassword) < 8) {
            return 'New password must be at least 8 characters.';
        }
    }

    // Unique email check (allow keeping own)
    $dup = auth_find_user_by_email($email);
    if ($dup && $dup['id'] !== $userId) {
        return 'That email is already in use.';
    }

    $table = cs_table('users');
    if ($changingPassword) {
        $stmt = db()->prepare(
            "UPDATE `{$table}` SET display_name = ?, email = ?, password_hash = ? WHERE id = ?"
        );
        $stmt->execute([
            $displayName !== '' ? $displayName : null,
            $email,
            password_hash($newPassword, PASSWORD_DEFAULT),
            $userId,
        ]);
    } else {
        $stmt = db()->prepare(
            "UPDATE `{$table}` SET display_name = ?, email = ? WHERE id = ?"
        );
        $stmt->execute([
            $displayName !== '' ? $displayName : null,
            $email,
            $userId,
        ]);
    }

    $fresh = auth_find_user_by_id($userId);
    if ($fresh) {
        $_SESSION['cs_username'] = $fresh['username'];
        $_SESSION['cs_email'] = $fresh['email'];
        $_SESSION['cs_display_name'] = $fresh['display_name'];
    }

    return null;
}

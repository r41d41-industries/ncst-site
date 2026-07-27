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

function auth_login(string $username, string $password): bool
{
    $table = cs_table('users');
    $stmt = db()->prepare("SELECT id, username, password_hash FROM `{$table}` WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($password, $row['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['cs_user_id'] = (int) $row['id'];
    $_SESSION['cs_username'] = (string) $row['username'];
    return true;
}

function auth_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
}

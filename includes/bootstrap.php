<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/categories.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/media.php';
require_once __DIR__ . '/posts.php';

load_env(dirname(__DIR__) . '/.env');

$secret = getenv('SESSION_SECRET') ?: 'dev-insecure-session-secret';
if (session_status() !== PHP_SESSION_ACTIVE) {
    $siteUrl = (string) (getenv('SITE_URL') ?: '');
    $secure = str_starts_with(strtolower($siteUrl), 'https://');
    session_name('NCSTSESSID');
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => $secure,
        'use_strict_mode' => true,
    ]);
}

// Soft binding so session cookies are tied to a local secret (regenerate if changed).
if (!isset($_SESSION['_secret_ok']) || $_SESSION['_secret_ok'] !== hash('sha256', $secret)) {
    if (isset($_SESSION['cs_user_id'])) {
        // Invalidate auth when secret changes
        unset(
            $_SESSION['cs_user_id'],
            $_SESSION['cs_username'],
            $_SESSION['cs_email'],
            $_SESSION['cs_display_name']
        );
    }
    $_SESSION['_secret_ok'] = hash('sha256', $secret);
}

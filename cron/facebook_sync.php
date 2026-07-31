<?php

declare(strict_types=1);

/**
 * Facebook Sync scheduled runner.
 *
 * CLI:
 *   php cron/facebook_sync.php
 *   php cron/facebook_sync.php --force
 *
 * HTTP (requires CRON_SECRET in .env):
 *   /cron/facebook_sync.php?secret=YOUR_SECRET
 *   /cron/facebook_sync.php?secret=YOUR_SECRET&force=1
 *
 * Suggested crontab (every 5 minutes; PHP enforces fb_cron_frequency):
 *   Every 5 minutes: php /path/to/cron/facebook_sync.php >/dev/null 2>&1
 */

require dirname(__DIR__) . '/includes/bootstrap.php';

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    $secret = facebook_cron_secret();
    if ($secret === '') {
        http_response_code(503);
        echo json_encode([
            'ok' => false,
            'error' => 'CRON_SECRET is not configured.',
        ], JSON_UNESCAPED_SLASHES);
        exit(1);
    }

    $provided = (string) ($_GET['secret'] ?? $_SERVER['HTTP_X_CRON_SECRET'] ?? '');
    if (!hash_equals($secret, $provided)) {
        http_response_code(403);
        echo json_encode([
            'ok' => false,
            'error' => 'Forbidden.',
        ], JSON_UNESCAPED_SLASHES);
        exit(1);
    }
}

$force = false;
if ($isCli) {
    global $argv;
    $args = is_array($argv ?? null) ? $argv : [];
    $force = in_array('--force', $args, true) || in_array('-f', $args, true);
} else {
    $force = isset($_GET['force']) && (string) $_GET['force'] !== '0' && (string) $_GET['force'] !== '';
}

try {
    $result = facebook_cron_run(['force' => $force]);
    $payload = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($payload === false) {
        throw new RuntimeException('Unable to encode cron result.');
    }
    if ($isCli) {
        echo $payload . PHP_EOL;
    } else {
        echo $payload;
    }
    exit(0);
} catch (Throwable $e) {
    $error = [
        'ok' => false,
        'error' => $e->getMessage(),
    ];
    $payload = json_encode($error, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($isCli) {
        fwrite(STDERR, ($payload !== false ? $payload : $e->getMessage()) . PHP_EOL);
    } else {
        http_response_code(500);
        echo $payload !== false ? $payload : '{"ok":false,"error":"Cron failed."}';
    }
    exit(1);
}

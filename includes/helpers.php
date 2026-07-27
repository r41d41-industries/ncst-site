<?php

declare(strict_types=1);

/**
 * Escape for HTML body/attribute text.
 */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Absolute site URL for a path (leading slash optional).
 */
function site_url(string $path = ''): string
{
    $base = rtrim((string) (getenv('SITE_URL') ?: ''), '/');
    $path = ltrim($path, '/');
    if ($path === '') {
        return $base !== '' ? $base : '/';
    }
    return ($base !== '' ? $base : '') . '/' . $path;
}

/**
 * Prefixed table name: cs_table('posts') → CS_posts
 */
function cs_table(string $name): string
{
    $prefix = getenv('DB_TABLE_PREFIX') ?: 'CS_';
    $name = ltrim($name, '_');
    if (str_starts_with($name, $prefix)) {
        return $name;
    }
    return $prefix . $name;
}

/**
 * Redirect and exit.
 */
function redirect(string $location): never
{
    header('Location: ' . $location);
    exit;
}

/**
 * Simple flash message via session.
 */
function flash_set(string $key, string $message): void
{
    $_SESSION['_flash'][$key] = $message;
}

function flash_get(string $key): ?string
{
    if (!isset($_SESSION['_flash'][$key])) {
        return null;
    }
    $msg = $_SESSION['_flash'][$key];
    unset($_SESSION['_flash'][$key]);
    return $msg;
}

/**
 * Normalize an optional http(s) URL from admin input. Returns null if empty/invalid.
 */
function cs_normalize_url(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    if (!preg_match('#^https?://#i', $value)) {
        $value = 'https://' . $value;
    }
    if (filter_var($value, FILTER_VALIDATE_URL) === false) {
        return null;
    }
    $parts = parse_url($value);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    if ($scheme !== 'http' && $scheme !== 'https') {
        return null;
    }
    return $value;
}

/**
 * Strip a hardcoded label prefix from a stored admin value (case-insensitive).
 * e.g. "DISPATCHED: TODAY AT 3:55 PM" → "TODAY AT 3:55 PM"
 */
function cs_strip_label_prefix(?string $value, string ...$prefixes): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    foreach ($prefixes as $prefix) {
        $prefix = trim($prefix);
        if ($prefix === '') {
            continue;
        }
        if (preg_match('/^' . preg_quote($prefix, '/') . '\s*/i', $value)) {
            return trim(substr($value, strlen($prefix)));
        }
    }
    return $value;
}

/**
 * Format an admin-entered event datetime for feed display.
 * Same calendar day (midnight–midnight): "TODAY AT 3:55 PM"
 * Otherwise: "July 26, 3:55 PM" (year included if not current year).
 * Empty + $unknownIfEmpty → "UNKNOWN"
 */
function cs_format_event_time(?string $datetime, bool $unknownIfEmpty = false): string
{
    $datetime = trim((string) $datetime);
    if ($datetime === '') {
        return $unknownIfEmpty ? 'UNKNOWN' : '';
    }

    $ts = strtotime($datetime);
    if ($ts === false) {
        return $unknownIfEmpty ? 'UNKNOWN' : '';
    }

    $eventDay = date('Y-m-d', $ts);
    $today = date('Y-m-d');
    if ($eventDay === $today) {
        return 'TODAY AT ' . date('g:i A', $ts);
    }

    if (date('Y', $ts) === date('Y')) {
        return date('F j, g:i A', $ts);
    }

    return date('F j, Y, g:i A', $ts);
}

/**
 * Parse datetime-local / common admin input into MySQL DATETIME or null.
 */
function cs_parse_datetime_input(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $value = str_replace('T', ' ', $value);
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
        $value .= ':00';
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return null;
    }
    return date('Y-m-d H:i:s', $ts);
}

/**
 * Value for datetime-local inputs.
 */
function cs_datetime_local_value(?string $datetime): string
{
    $datetime = trim((string) $datetime);
    if ($datetime === '') {
        return '';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '';
    }
    return date('Y-m-d\TH:i', $ts);
}

/**
 * Legacy free-text update label → display fragment (time only).
 */
function cs_update_time_value(?string $label): string
{
    $label = trim((string) $label);
    if ($label === '') {
        return '';
    }
    $label = cs_strip_label_prefix($label, 'UPDATE |', 'UPDATE:', 'UPDATE');
    return ltrim($label, "| \t");
}

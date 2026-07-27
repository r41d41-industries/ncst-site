<?php

declare(strict_types=1);

function shortcodes_table(): string
{
    return cs_table('shortcodes');
}

/**
 * @return list<array<string, mixed>>
 */
function shortcodes_all(): array
{
    $table = shortcodes_table();
    $stmt = db()->query("SELECT * FROM `{$table}` ORDER BY code ASC, id ASC");
    return $stmt->fetchAll();
}

function shortcodes_find(int $id): ?array
{
    $table = shortcodes_table();
    $stmt = db()->prepare("SELECT * FROM `{$table}` WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function shortcodes_normalize_code(string $code): string
{
    $code = strtolower(trim($code));
    $code = preg_replace('/\s+/', ' ', $code) ?? $code;
    return $code;
}

/**
 * @throws InvalidArgumentException
 */
function shortcodes_create(string $code, string $replacement): int
{
    $code = shortcodes_normalize_code($code);
    if ($code === '' || strlen($code) > 64) {
        throw new InvalidArgumentException('Shortcode key is required (max 64 chars).');
    }
    if (preg_match('/[\[\]]/', $code)) {
        throw new InvalidArgumentException('Do not include brackets in the shortcode key.');
    }

    $table = shortcodes_table();
    $stmt = db()->prepare("INSERT INTO `{$table}` (code, replacement) VALUES (?, ?)");
    try {
        $stmt->execute([$code, $replacement]);
    } catch (PDOException $e) {
        throw new InvalidArgumentException('That shortcode key already exists.', 0, $e);
    }
    return (int) db()->lastInsertId();
}

/**
 * @throws InvalidArgumentException
 */
function shortcodes_update(int $id, string $code, string $replacement): void
{
    $code = shortcodes_normalize_code($code);
    if ($code === '' || strlen($code) > 64) {
        throw new InvalidArgumentException('Shortcode key is required (max 64 chars).');
    }
    if (preg_match('/[\[\]]/', $code)) {
        throw new InvalidArgumentException('Do not include brackets in the shortcode key.');
    }

    $table = shortcodes_table();
    $stmt = db()->prepare(
        "UPDATE `{$table}` SET code = ?, replacement = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?"
    );
    try {
        $stmt->execute([$code, $replacement, $id]);
    } catch (PDOException $e) {
        throw new InvalidArgumentException('That shortcode key already exists.', 0, $e);
    }
}

function shortcodes_delete(int $id): void
{
    $table = shortcodes_table();
    $stmt = db()->prepare("DELETE FROM `{$table}` WHERE id = ?");
    $stmt->execute([$id]);
}

/**
 * Expand [shortcode] tokens. Values containing __NOW__ get the current datetime.
 */
function shortcodes_expand(string $html): string
{
    $map = [];
    foreach (shortcodes_all() as $row) {
        $key = shortcodes_normalize_code((string) $row['code']);
        $map[$key] = (string) ($row['replacement'] ?? '');
    }
    if ($map === []) {
        return $html;
    }

    return (string) preg_replace_callback(
        '/\[([^\]]+)\]/',
        static function (array $m) use ($map): string {
            $key = shortcodes_normalize_code($m[1]);
            if (!array_key_exists($key, $map)) {
                return $m[0];
            }
            $value = $map[$key];
            if (str_contains($value, '__NOW__')) {
                $now = date('F j, Y, g:i A');
                $value = str_replace('__NOW__', $now, $value);
            }
            return $value;
        },
        $html
    );
}

<?php

declare(strict_types=1);

/**
 * @return array<string, ?string>
 */
function settings_all(bool $refresh = false): array
{
    static $cache = null;
    if ($refresh) {
        $cache = null;
    }
    if (is_array($cache)) {
        return $cache;
    }

    $table = cs_table('settings');
    $stmt = db()->query("SELECT setting_key, setting_value FROM `{$table}`");
    $cache = [];
    foreach ($stmt->fetchAll() as $row) {
        $cache[(string) $row['setting_key']] = $row['setting_value'] !== null
            ? (string) $row['setting_value']
            : null;
    }
    return $cache;
}

function settings_get(string $key, ?string $default = null): ?string
{
    $all = settings_all();
    if (!array_key_exists($key, $all)) {
        return $default;
    }
    $value = $all[$key];
    return $value !== null && $value !== '' ? $value : $default;
}

function settings_set(string $key, ?string $value): void
{
    $table = cs_table('settings');
    $stmt = db()->prepare(
        "INSERT INTO `{$table}` (setting_key, setting_value)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP"
    );
    $stmt->execute([$key, $value]);
    settings_all(true);
}

/**
 * @param array<string, ?string> $pairs
 */
function settings_set_many(array $pairs): void
{
    foreach ($pairs as $key => $value) {
        settings_set((string) $key, $value);
    }
}

/**
 * Agency quick-fill values for the incident form (ORG or BADGE|ORG).
 *
 * @return list<string>
 */
function incident_agencies_list(): array
{
    $raw = settings_get('incident_agencies');
    if ($raw === null || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }
    $out = [];
    foreach ($decoded as $item) {
        $value = trim((string) $item);
        if ($value === '') {
            continue;
        }
        $out[] = $value;
    }
    return array_values(array_unique($out));
}

/**
 * @param list<string> $values
 */
function incident_agencies_save(array $values): void
{
    $clean = [];
    foreach ($values as $item) {
        $value = trim((string) $item);
        if ($value === '') {
            continue;
        }
        if (strlen($value) > 128) {
            $value = substr($value, 0, 128);
        }
        $clean[] = $value;
    }
    $clean = array_values(array_unique($clean));
    $json = json_encode($clean, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('Unable to save agencies.');
    }
    settings_set('incident_agencies', $json === '[]' ? null : $json);
}

/**
 * Site-wide Open Graph defaults.
 *
 * @return array{title: string, description: string, site_name: string, type: string, image_path: ?string}
 */
function settings_og_defaults(): array
{
    return [
        'title' => (string) (settings_get('og_title', 'NCST Main Feed') ?? 'NCST Main Feed'),
        'description' => (string) (settings_get(
            'og_description',
            'Newnan Coweta Scanner Traffic — live scanner feed for Newnan and Coweta County.'
        ) ?? ''),
        'site_name' => (string) (settings_get('og_site_name', 'Newnan Coweta Scanner Traffic') ?? 'Newnan Coweta Scanner Traffic'),
        'type' => (string) (settings_get('og_type', 'website') ?? 'website'),
        'image_path' => settings_get('og_image_path'),
    ];
}

/**
 * Resolve OG tags for a page. Post overrides win over site defaults; image falls back
 * to post image_path then site default.
 *
 * @param array<string, mixed>|null $post
 * @return array{title: string, description: string, site_name: string, type: string, image_url: ?string, url: string}
 */
function settings_resolve_og(?array $post = null, ?string $pageUrl = null): array
{
    $defaults = settings_og_defaults();
    $title = $defaults['title'];
    $description = $defaults['description'];
    $type = $defaults['type'];
    $imagePath = $defaults['image_path'];

    if ($post !== null) {
        $overrideTitle = trim((string) ($post['og_title'] ?? ''));
        $overrideDesc = trim((string) ($post['og_description'] ?? ''));
        $overrideImage = trim((string) ($post['og_image_path'] ?? ''));
        $postImage = trim((string) ($post['image_path'] ?? ''));

        if ($overrideTitle !== '') {
            $title = $overrideTitle;
        } else {
            $postTitle = trim((string) ($post['title'] ?? ''));
            if ($postTitle !== '') {
                $title = $postTitle;
            }
        }

        if ($overrideDesc !== '') {
            $description = $overrideDesc;
        } else {
            $body = trim((string) ($post['body'] ?? ''));
            if ($body !== '') {
                $description = strlen($body) > 200 ? substr($body, 0, 197) . '…' : $body;
            }
        }

        if ($overrideImage !== '') {
            $imagePath = $overrideImage;
        } elseif ($postImage !== '') {
            $imagePath = $postImage;
        }

        $type = 'article';
    }

    $imageUrl = null;
    if ($imagePath !== null && $imagePath !== '') {
        $imageUrl = site_url(ltrim($imagePath, '/'));
    }

    $url = $pageUrl !== null && $pageUrl !== ''
        ? $pageUrl
        : site_url(ltrim((string) ($_SERVER['REQUEST_URI'] ?? '/'), '/'));

    // Prefer absolute URL when SITE_URL is set
    if ($pageUrl === null || $pageUrl === '') {
        $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
        $query = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY) ?: '');
        $url = site_url(ltrim($path, '/'));
        if ($query !== '') {
            $url .= '?' . $query;
        }
    }

    return [
        'title' => $title,
        'description' => $description,
        'site_name' => $defaults['site_name'],
        'type' => $type,
        'image_url' => $imageUrl,
        'url' => $url,
    ];
}

/**
 * Store an OG image via the media library (kind=image).
 * Returns relative path or existing path if no file.
 * When a new file is stored, also returns media_id via $mediaIdOut.
 *
 * @param array<string, mixed> $file $_FILES entry
 */
function settings_handle_og_upload(array $file, ?string $existingPath = null, ?int &$mediaIdOut = null): ?string
{
    if (!isset($file['error']) || (int) $file['error'] === UPLOAD_ERR_NO_FILE) {
        return $existingPath;
    }

    $row = media_store_upload($file, ['kind' => 'image']);
    $mediaIdOut = (int) $row['id'];
    return (string) $row['path'];
}

/**
 * Dual-write site OG image from a library media id.
 */
function settings_set_og_image_from_media(?int $mediaId): void
{
    if ($mediaId === null || $mediaId <= 0) {
        settings_set('og_image_media_id', null);
        settings_set('og_image_path', null);
        return;
    }
    $row = media_find($mediaId);
    if ($row === null || (string) $row['kind'] !== 'image') {
        throw new RuntimeException('OG image must be an image from the media library.');
    }
    settings_set('og_image_media_id', (string) $mediaId);
    settings_set('og_image_path', (string) $row['path']);
}

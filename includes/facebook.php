<?php

declare(strict_types=1);

/**
 * Facebook Page post sync (Graph API).
 *
 * Credentials: CS_settings first, then getenv() fallback (FB_*).
 */

/**
 * @return array{
 *   page_id: string,
 *   page_access_token: string,
 *   app_id: string,
 *   app_secret: string,
 *   graph_version: string
 * }
 */
function facebook_credentials(): array
{
    $pageId = (string) (settings_get('fb_page_id') ?: (getenv('FB_PAGE_ID') ?: ''));
    $token = (string) (settings_get('fb_page_access_token') ?: (getenv('FB_PAGE_ACCESS_TOKEN') ?: ''));
    $appId = (string) (settings_get('fb_app_id') ?: (getenv('FB_APP_ID') ?: ''));
    $appSecret = (string) (settings_get('fb_app_secret') ?: (getenv('FB_APP_SECRET') ?: ''));
    $version = (string) (settings_get('fb_graph_version') ?: (getenv('FB_GRAPH_VERSION') ?: 'v25.0'));
    if ($version === '') {
        $version = 'v25.0';
    }
    if (!str_starts_with($version, 'v')) {
        $version = 'v' . $version;
    }

    return [
        'page_id' => $pageId,
        'page_access_token' => $token,
        'app_id' => $appId,
        'app_secret' => $appSecret,
        'graph_version' => $version,
    ];
}

/**
 * Form defaults: settings value if set, else env (for display).
 *
 * @return array{fb_page_id: string, fb_page_access_token: string, fb_app_id: string, fb_app_secret: string, fb_graph_version: string}
 */
function facebook_credentials_form_values(): array
{
    $creds = facebook_credentials();
    return [
        'fb_page_id' => $creds['page_id'],
        'fb_page_access_token' => $creds['page_access_token'],
        'fb_app_id' => $creds['app_id'],
        'fb_app_secret' => $creds['app_secret'],
        'fb_graph_version' => $creds['graph_version'],
    ];
}

/**
 * @return array{enabled: bool, frequency: string, last_run: ?string}
 */
function facebook_cron_settings(): array
{
    $enabled = settings_get('fb_cron_enabled', '0') === '1';
    $frequency = (string) (settings_get('fb_cron_frequency', 'hourly') ?? 'hourly');
    $allowed = facebook_cron_frequencies();
    if (!isset($allowed[$frequency])) {
        $frequency = 'hourly';
    }
    $lastRun = settings_get('fb_cron_last_run');

    return [
        'enabled' => $enabled,
        'frequency' => $frequency,
        'last_run' => $lastRun !== null && $lastRun !== '' ? $lastRun : null,
    ];
}

/** Whether Facebook Sync auto-post mode is on. */
function facebook_auto_post_enabled(): bool
{
    return settings_get('fb_auto_post_mode', '0') === '1';
}

/**
 * Persist auto-post mode. Turning it on also enables cron and sets frequency to 15m.
 * Frequency remains editable afterward via cron settings.
 *
 * @return array{auto_post: bool, cron_enabled: bool, frequency: string}
 */
function facebook_auto_post_set(bool $enabled): array
{
    $wasEnabled = facebook_auto_post_enabled();
    $cron = facebook_cron_settings();
    $frequency = $cron['frequency'];
    $cronEnabled = $cron['enabled'];

    if ($enabled && !$wasEnabled) {
        $cronEnabled = true;
        $frequency = '15m';
    }

    settings_set_many([
        'fb_auto_post_mode' => $enabled ? '1' : '0',
        'fb_cron_enabled' => $cronEnabled ? '1' : '0',
        'fb_cron_frequency' => $frequency,
    ]);

    return [
        'auto_post' => $enabled,
        'cron_enabled' => $cronEnabled,
        'frequency' => $frequency,
    ];
}

/**
 * Interval seconds for a cron frequency key.
 */
function facebook_cron_interval_seconds(string $frequency): int
{
    return match ($frequency) {
        '15m' => 15 * 60,
        '6h' => 6 * 3600,
        'daily' => 24 * 3600,
        default => 3600,
    };
}

/**
 * Whether a scheduled run should execute now (cron enabled and interval elapsed).
 */
function facebook_cron_is_due(): bool
{
    $cron = facebook_cron_settings();
    if (!$cron['enabled']) {
        return false;
    }
    if ($cron['last_run'] === null) {
        return true;
    }
    try {
        $last = new DateTimeImmutable($cron['last_run'], facebook_timezone());
    } catch (Throwable) {
        return true;
    }
    $now = new DateTimeImmutable('now', facebook_timezone());
    $elapsed = $now->getTimestamp() - $last->getTimestamp();
    return $elapsed >= facebook_cron_interval_seconds($cron['frequency']);
}

function facebook_cron_mark_ran(?DateTimeImmutable $when = null): void
{
    $when ??= new DateTimeImmutable('now', facebook_timezone());
    settings_set('fb_cron_last_run', $when->setTimezone(facebook_timezone())->format('Y-m-d H:i:s'));
}

/**
 * Shared secret for the Facebook cron CLI/HTTP endpoint (env CRON_SECRET).
 */
function facebook_cron_secret(): string
{
    return trim((string) (getenv('CRON_SECRET') ?: ''));
}

/**
 * How converted Facebook posts are saved to the feed.
 *
 * @return 'draft'|'published'|'published_with_comments'
 */
function facebook_import_status(): string
{
    $status = strtolower(trim((string) (settings_get('fb_import_status', 'draft') ?? 'draft')));
    if ($status === 'published_with_comments') {
        return 'published_with_comments';
    }
    return $status === 'published' ? 'published' : 'draft';
}

/** Whether the import status publishes the feed post. */
function facebook_import_publishes(): bool
{
    $status = facebook_import_status();
    return $status === 'published' || $status === 'published_with_comments';
}

/**
 * Default hashtag → category map from incident-template category slugs.
 *
 * @return array<string, string> uppercase hashtag tag => uppercase category slug
 */
function facebook_hashtag_map_defaults(): array
{
    $map = [];
    foreach (category_slugs_by_template('incident') as $slug) {
        $tag = strtoupper(trim((string) $slug));
        if ($tag !== '') {
            $map[$tag] = $tag;
        }
    }
    return $map;
}

/**
 * @return array<string, string> uppercase hashtag tag => uppercase category slug
 */
function facebook_hashtag_map(): array
{
    $raw = settings_get('fb_hashtag_map');
    if ($raw === null || $raw === '') {
        return facebook_hashtag_map_defaults();
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return facebook_hashtag_map_defaults();
    }

    $map = [];
    foreach ($decoded as $tag => $slug) {
        $tagKey = strtoupper(preg_replace('/[^A-Z0-9_]+/', '', strtoupper(ltrim((string) $tag, '#'))) ?? '');
        $slugKey = strtoupper(trim((string) $slug));
        if ($tagKey === '' || $slugKey === '') {
            continue;
        }
        if (category_by_slug($slugKey) === null) {
            continue;
        }
        $map[$tagKey] = $slugKey;
    }

    return $map !== [] ? $map : facebook_hashtag_map_defaults();
}

/**
 * Normalize and persist hashtag map rows from the settings form.
 *
 * @param list<array{tag?: string, category?: string}>|array<string, string> $rows
 */
function facebook_hashtag_map_save(array $rows): void
{
    $map = [];
    foreach ($rows as $key => $value) {
        if (is_array($value)) {
            $tag = (string) ($value['tag'] ?? '');
            $slug = (string) ($value['category'] ?? '');
        } else {
            $tag = (string) $key;
            $slug = (string) $value;
        }
        $tagKey = strtoupper(preg_replace('/[^A-Z0-9_]+/', '', strtoupper(ltrim($tag, '#'))) ?? '');
        $slugKey = strtoupper(trim($slug));
        if ($tagKey === '' || $slugKey === '') {
            continue;
        }
        if (category_by_slug($slugKey) === null) {
            throw new InvalidArgumentException('Unknown category for hashtag #' . $tagKey . ': ' . $slugKey);
        }
        $map[$tagKey] = $slugKey;
    }

    $json = json_encode($map, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('Unable to encode hashtag map.');
    }
    settings_set('fb_hashtag_map', $json);
}

/**
 * First hashtag in the message that maps to a known category.
 */
function facebook_suggest_category(string $message): ?string
{
    $map = facebook_hashtag_map();
    if ($map === []) {
        return null;
    }
    if (!preg_match_all('/#([A-Za-z0-9_]+)/', $message, $matches)) {
        return null;
    }
    foreach ($matches[1] as $tag) {
        $key = strtoupper((string) $tag);
        if (isset($map[$key])) {
            $slug = $map[$key];
            if (category_by_slug($slug) !== null) {
                return $slug;
            }
        }
    }
    return null;
}

/**
 * Split a Facebook message into feed title + body.
 *
 * Title: first non-empty line starting with `\`; otherwise first content line
 * (skipping hashtag-only lines); default "Facebook post" + date if empty.
 * Body: message without the title line; trailing "Posted…" line removed.
 *
 * @return array{title: string, body: string}
 */
function facebook_message_to_title_body(string $message, ?string $createdAt = null): array
{
    $rawLines = preg_split('/\R/u', $message) ?: [];
    $title = '';
    $titleLineIndex = null;

    // Prefer first non-empty line that starts with one or more backslashes.
    foreach ($rawLines as $i => $rawLine) {
        $line = trim((string) $rawLine);
        if ($line === '') {
            continue;
        }
        if (str_starts_with($line, '\\')) {
            $title = $line;
            $titleLineIndex = $i;
            break;
        }
    }

    // Fallback: first content line (skip hashtag-only).
    if ($title === '') {
        foreach ($rawLines as $i => $rawLine) {
            $line = trim((string) $rawLine);
            if ($line === '') {
                continue;
            }
            $withoutTags = trim(preg_replace('/#([A-Za-z0-9_]+)/', '', $line) ?? '');
            if ($withoutTags === '' && preg_match('/#/', $line)) {
                continue;
            }
            $title = $line;
            $titleLineIndex = $i;
            break;
        }
    }

    if ($title === '') {
        $title = 'Facebook post';
        if ($createdAt !== null && $createdAt !== '') {
            $title .= ' ' . $createdAt;
        }
    }

    if (strlen($title) > 120) {
        $title = rtrim(substr($title, 0, 119)) . '…';
    }

    $bodyLines = $rawLines;
    if ($titleLineIndex !== null) {
        unset($bodyLines[$titleLineIndex]);
        $bodyLines = array_values($bodyLines);
    }

    // Strip trailing empty lines, then a last non-empty line starting with "Posted".
    while ($bodyLines !== [] && trim((string) $bodyLines[array_key_last($bodyLines)]) === '') {
        array_pop($bodyLines);
    }
    if ($bodyLines !== []) {
        $last = trim((string) $bodyLines[array_key_last($bodyLines)]);
        if ($last !== '' && preg_match('/^Posted\b/i', $last) === 1) {
            array_pop($bodyLines);
        }
    }

    $body = trim(implode("\n", $bodyLines));

    return [
        'title' => $title,
        'body' => $body,
    ];
}

/**
 * Build a feed post title from a Facebook message.
 */
function facebook_title_from_message(string $message, ?string $createdAt = null): string
{
    return facebook_message_to_title_body($message, $createdAt)['title'];
}

/**
 * Categories for the convert dropdown: incidents first, then others.
 *
 * @return list<array{slug: string, name: string, template: string}>
 */
function facebook_convert_category_options(): array
{
    $incident = [];
    $other = [];
    foreach (categories_all() as $row) {
        $item = [
            'slug' => strtoupper((string) $row['slug']),
            'name' => (string) $row['name'],
            'template' => strtolower((string) ($row['template'] ?? 'news')),
        ];
        if ($item['template'] === 'incident') {
            $incident[] = $item;
        } else {
            $other[] = $item;
        }
    }
    return array_merge($incident, $other);
}

/**
 * Convert a synced Facebook row into a CMS feed post.
 *
 * @param array{force_published?: bool, apply_comments?: bool} $options
 * @throws RuntimeException|InvalidArgumentException
 */
function facebook_convert_to_post(int $fbRowId, string $category, array $options = []): int
{
    $row = facebook_post_find($fbRowId);
    if ($row === null) {
        throw new RuntimeException('Facebook post not found.');
    }
    if (!empty($row['cs_post_id'])) {
        throw new RuntimeException('This Facebook post was already converted to feed post #' . (int) $row['cs_post_id'] . '.');
    }

    $message = trim((string) ($row['message'] ?? ''));
    if ($message === '') {
        throw new RuntimeException('Facebook post has no message content.');
    }

    $category = strtoupper(trim($category));
    if ($category === '' || category_by_slug($category) === null) {
        throw new InvalidArgumentException('Choose a valid category.');
    }

    $forcePublished = !empty($options['force_published']);
    $permalink = trim((string) ($row['permalink_url'] ?? ''));
    $facebookUrl = $permalink !== '' ? cs_normalize_url($permalink) : null;
    $fbTime = !empty($row['fb_created_time']) ? (string) $row['fb_created_time'] : null;
    $importStatus = facebook_import_status();
    $published = ($forcePublished || facebook_import_publishes()) ? 1 : 0;
    $applyComments = array_key_exists('apply_comments', $options)
        ? (bool) $options['apply_comments']
        : ($importStatus === 'published_with_comments');
    $parsed = facebook_message_to_title_body($message, $fbTime);
    $title = $parsed['title'];
    $body = $parsed['body'];

    $postId = posts_create([
        'category' => $category,
        'title' => $title,
        'body' => $body,
        'article_body' => null,
        'footnotes' => null,
        'update_label' => null,
        'update_text' => null,
        'agency' => null,
        'dispatched_at' => $fbTime,
        'cleared_at' => null,
        'recorded_at' => null,
        'expires_at' => null,
        'dispatched_text' => null,
        'status_text' => null,
        'image_path' => null,
        'image_media_id' => null,
        'facebook_url' => $facebookUrl,
        'x_url' => null,
        'read_more_url' => null,
        'og_title' => null,
        'og_description' => null,
        'og_image_path' => null,
        'og_image_media_id' => null,
        'gallery_id' => null,
        'playlist_id' => null,
        'published' => $published,
        'created_at' => $fbTime,
        'updated_at' => $fbTime,
    ]);

    $table = cs_table('facebook_posts');
    $stmt = db()->prepare(
        "UPDATE `{$table}` SET cs_post_id = ?, is_new = 0 WHERE id = ? AND cs_post_id IS NULL"
    );
    $stmt->execute([$postId, $fbRowId]);
    if ($stmt->rowCount() === 0) {
        // Race: another convert won; leave the orphan CMS post and surface the conflict.
        throw new RuntimeException('This Facebook post was already converted by another request.');
    }

    try {
        facebook_sync_comments_for_post($fbRowId);
    } catch (Throwable) {
        // Comments can sync on the next Sync Now; conversion still succeeded.
    }

    if ($applyComments) {
        try {
            facebook_apply_page_comments_to_post($postId);
        } catch (Throwable) {
            // Post is published; comments can still be applied manually in the editor.
        }
    }

    return $postId;
}

/**
 * Auto-convert newly synced Facebook rows that have a mapped hashtag topic.
 * Skips posts without a matching hashtag. Always publishes.
 *
 * @param list<int> $fbRowIds
 * @return array{converted: int, skipped: int, errors: int, post_ids: list<int>}
 */
function facebook_auto_convert_new_posts(array $fbRowIds): array
{
    $converted = 0;
    $skipped = 0;
    $errors = 0;
    $postIds = [];

    foreach ($fbRowIds as $fbRowId) {
        $fbRowId = (int) $fbRowId;
        if ($fbRowId <= 0) {
            $skipped++;
            continue;
        }
        $row = facebook_post_find($fbRowId);
        if ($row === null || !empty($row['cs_post_id'])) {
            $skipped++;
            continue;
        }
        $message = trim((string) ($row['message'] ?? ''));
        $category = facebook_suggest_category($message);
        if ($category === null) {
            $skipped++;
            continue;
        }
        try {
            $postIds[] = facebook_convert_to_post($fbRowId, $category, [
                'force_published' => true,
                'apply_comments' => false,
            ]);
            $converted++;
        } catch (Throwable) {
            $errors++;
        }
    }

    return [
        'converted' => $converted,
        'skipped' => $skipped,
        'errors' => $errors,
        'post_ids' => $postIds,
    ];
}

/**
 * @return array<string, mixed>|null
 */
function facebook_post_find(int $id): ?array
{
    $table = cs_table('facebook_posts');
    $stmt = db()->prepare("SELECT * FROM `{$table}` WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/**
 * Local Facebook row linked to a CMS feed post, if any.
 *
 * @return array<string, mixed>|null
 */
function facebook_post_by_cs_post_id(int $csPostId): ?array
{
    if ($csPostId <= 0) {
        return null;
    }
    $table = cs_table('facebook_posts');
    $stmt = db()->prepare("SELECT * FROM `{$table}` WHERE cs_post_id = ? LIMIT 1");
    $stmt->execute([$csPostId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/**
 * Fetch comments for a local Facebook post row and upsert into CS_facebook_comments.
 * Stores all comments; Page-authored ones are flagged via is_page.
 *
 * @return array{fetched: int, inserted: int, updated: int}
 */
function facebook_sync_comments_for_post(int $facebookPostRowId): array
{
    $row = facebook_post_find($facebookPostRowId);
    if ($row === null) {
        throw new RuntimeException('Facebook post not found.');
    }

    $fbPostId = trim((string) ($row['fb_post_id'] ?? ''));
    if ($fbPostId === '') {
        throw new RuntimeException('Facebook post is missing a Graph post ID.');
    }

    $creds = facebook_credentials();
    $pageId = (string) $creds['page_id'];

    $response = facebook_graph_get($fbPostId . '/comments', [
        'fields' => 'id,message,created_time,from{id,name}',
        'filter' => 'stream',
        'order' => 'chronological',
        'limit' => 100,
    ]);

    if (!$response['ok'] || !is_array($response['json'])) {
        $msg = 'Facebook comments API error';
        if (is_array($response['json']) && isset($response['json']['error']['message'])) {
            $msg .= ': ' . (string) $response['json']['error']['message'];
        } else {
            $msg .= ' (HTTP ' . $response['status'] . ').';
        }
        throw new RuntimeException($msg);
    }

    $data = $response['json']['data'] ?? [];
    if (!is_array($data)) {
        $data = [];
    }

    $table = cs_table('facebook_comments');
    $pdo = db();
    $select = $pdo->prepare("SELECT id FROM `{$table}` WHERE fb_comment_id = ? LIMIT 1");
    $insert = $pdo->prepare(
        "INSERT INTO `{$table}`
          (facebook_post_id, fb_comment_id, message, from_id, from_name, is_page, fb_created_time, last_synced_at, raw_json)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)"
    );
    $update = $pdo->prepare(
        "UPDATE `{$table}` SET
          facebook_post_id = ?,
          message = ?,
          from_id = ?,
          from_name = ?,
          is_page = ?,
          fb_created_time = ?,
          last_synced_at = NOW(),
          raw_json = ?
         WHERE fb_comment_id = ?"
    );

    $inserted = 0;
    $updated = 0;

    foreach ($data as $comment) {
        if (!is_array($comment) || empty($comment['id'])) {
            continue;
        }
        $fbCommentId = (string) $comment['id'];
        $message = isset($comment['message']) ? (string) $comment['message'] : null;
        $from = is_array($comment['from'] ?? null) ? $comment['from'] : [];
        $fromId = isset($from['id']) ? (string) $from['id'] : null;
        $fromName = isset($from['name']) ? (string) $from['name'] : null;
        $isPage = ($pageId !== '' && $fromId !== null && $fromId === $pageId) ? 1 : 0;
        $created = facebook_parse_graph_time(isset($comment['created_time']) ? (string) $comment['created_time'] : null);
        $raw = json_encode($comment, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($raw === false) {
            $raw = null;
        } elseif (strlen($raw) > 60000) {
            $raw = substr($raw, 0, 60000);
        }

        $select->execute([$fbCommentId]);
        $exists = $select->fetchColumn();

        if ($exists) {
            $update->execute([
                $facebookPostRowId,
                $message,
                $fromId,
                $fromName,
                $isPage,
                $created,
                $raw,
                $fbCommentId,
            ]);
            $updated++;
        } else {
            $insert->execute([
                $facebookPostRowId,
                $fbCommentId,
                $message,
                $fromId,
                $fromName,
                $isPage,
                $created,
                $raw,
            ]);
            $inserted++;
        }
    }

    return [
        'fetched' => count($data),
        'inserted' => $inserted,
        'updated' => $updated,
    ];
}

/**
 * Page-authored comments for a CMS post, chronological (oldest first).
 *
 * @param bool $onlyUnapplied When true, only comments with applied_at IS NULL.
 * @return list<array<string, mixed>>
 */
function facebook_page_comments_for_cs_post(int $csPostId, bool $onlyUnapplied = false): array
{
    $fb = facebook_post_by_cs_post_id($csPostId);
    if ($fb === null) {
        return [];
    }
    $table = cs_table('facebook_comments');
    $sql = "SELECT * FROM `{$table}`
         WHERE facebook_post_id = ? AND is_page = 1";
    if ($onlyUnapplied) {
        $sql .= ' AND applied_at IS NULL';
    }
    $sql .= ' ORDER BY fb_created_time ASC, id ASC';
    $stmt = db()->prepare($sql);
    $stmt->execute([(int) $fb['id']]);
    return $stmt->fetchAll();
}

/**
 * Mark a local Facebook comment row as applied to the CMS post.
 */
function facebook_comment_mark_applied(int $commentRowId, ?string $appliedAt = null): void
{
    if ($commentRowId <= 0) {
        return;
    }
    $table = cs_table('facebook_comments');
    if ($appliedAt === null || $appliedAt === '') {
        $appliedAt = (new DateTimeImmutable('now', facebook_timezone()))->format('Y-m-d H:i:s');
    }
    $stmt = db()->prepare(
        "UPDATE `{$table}` SET applied_at = ? WHERE id = ? AND applied_at IS NULL"
    );
    $stmt->execute([$appliedAt, $commentRowId]);
}

/**
 * Apply Page UPDATE | / CLEARED | comments onto a CMS incident post.
 * Only processes comments not yet marked applied. Already-cleared posts ignore
 * further CLEARED comments (comment is still marked applied).
 *
 * @return array{updates: int, cleared: bool, skipped: int}
 */
function facebook_apply_page_comments_to_post(int $csPostId): array
{
    $post = posts_find($csPostId);
    if ($post === null) {
        return ['updates' => 0, 'cleared' => false, 'skipped' => 0];
    }

    $alreadyCleared = trim((string) ($post['cleared_at'] ?? '')) !== '';
    $comments = facebook_page_comments_for_cs_post($csPostId, true);
    $updates = 0;
    $cleared = false;
    $skipped = 0;
    $now = (new DateTimeImmutable('now', facebook_timezone()))->format('Y-m-d H:i:s');

    foreach ($comments as $comment) {
        $commentId = (int) ($comment['id'] ?? 0);
        $message = (string) ($comment['message'] ?? '');
        $created = trim((string) ($comment['fb_created_time'] ?? ''));
        $type = facebook_comment_action_type($message);

        if ($type === null) {
            // Non-action Page comments are ignored (not marked applied).
            $skipped++;
            continue;
        }

        if ($created === '') {
            $skipped++;
            continue;
        }

        if ($type === 'update') {
            $body = facebook_comment_update_text($message);
            if ($body === '') {
                $skipped++;
                continue;
            }
            posts_add_update($csPostId, [
                'label' => null,
                'body' => $body,
                'created_at' => $created,
            ]);
            $updates++;
            if ($commentId > 0) {
                facebook_comment_mark_applied($commentId, $now);
            }
        } elseif ($type === 'cleared') {
            if ($alreadyCleared) {
                // Ignore later CLEARED when post is already cleared.
                if ($commentId > 0) {
                    facebook_comment_mark_applied($commentId, $now);
                }
                $skipped++;
                continue;
            }
            $table = posts_table();
            $stmt = db()->prepare(
                "UPDATE `{$table}` SET cleared_at = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?"
            );
            $stmt->execute([$created, $csPostId]);
            $alreadyCleared = true;
            $cleared = true;
            if ($commentId > 0) {
                facebook_comment_mark_applied($commentId, $now);
            }
        }
    }

    return [
        'updates' => $updates,
        'cleared' => $cleared,
        'skipped' => $skipped,
    ];
}

/**
 * Apply unapplied Page UPDATE/CLEARED comments for every converted Facebook post.
 *
 * @return array{posts: int, updates: int, cleared: int, skipped: int, errors: int}
 */
function facebook_apply_unapplied_comments_for_converted_posts(): array
{
    $table = cs_table('facebook_posts');
    $stmt = db()->query(
        "SELECT cs_post_id FROM `{$table}` WHERE cs_post_id IS NOT NULL ORDER BY id ASC"
    );
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $posts = 0;
    $updates = 0;
    $cleared = 0;
    $skipped = 0;
    $errors = 0;

    foreach ($ids as $csPostId) {
        $posts++;
        try {
            $result = facebook_apply_page_comments_to_post((int) $csPostId);
            $updates += $result['updates'];
            if ($result['cleared']) {
                $cleared++;
            }
            $skipped += $result['skipped'];
        } catch (Throwable) {
            $errors++;
        }
    }

    return [
        'posts' => $posts,
        'updates' => $updates,
        'cleared' => $cleared,
        'skipped' => $skipped,
        'errors' => $errors,
    ];
}

/**
 * Refresh CMS body text from Facebook message for converted posts whose
 * fb_created_time is within the last $hours hours. Title and category are unchanged.
 *
 * @return array{checked: int, updated: int, errors: int}
 */
function facebook_refresh_bodies_within_hours(int $hours = 6): array
{
    $hours = max(1, $hours);
    $tz = facebook_timezone();
    $cutoff = (new DateTimeImmutable('now', $tz))->modify('-' . $hours . ' hours')->format('Y-m-d H:i:s');

    $fbTable = cs_table('facebook_posts');
    $postsTable = posts_table();
    $stmt = db()->prepare(
        "SELECT fp.id AS fb_row_id, fp.message, fp.fb_created_time, fp.cs_post_id, p.body AS cms_body
         FROM `{$fbTable}` fp
         INNER JOIN `{$postsTable}` p ON p.id = fp.cs_post_id
         WHERE fp.cs_post_id IS NOT NULL
           AND fp.fb_created_time IS NOT NULL
           AND fp.fb_created_time >= ?
         ORDER BY fp.id ASC"
    );
    $stmt->execute([$cutoff]);
    $rows = $stmt->fetchAll();

    $checked = 0;
    $updated = 0;
    $errors = 0;
    $updateBody = db()->prepare(
        "UPDATE `{$postsTable}` SET body = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?"
    );

    foreach ($rows as $row) {
        $checked++;
        try {
            $message = trim((string) ($row['message'] ?? ''));
            if ($message === '') {
                continue;
            }
            $fbTime = !empty($row['fb_created_time']) ? (string) $row['fb_created_time'] : null;
            $parsed = facebook_message_to_title_body($message, $fbTime);
            $newBody = $parsed['body'];
            $currentBody = (string) ($row['cms_body'] ?? '');
            if ($newBody === $currentBody) {
                continue;
            }
            $updateBody->execute([$newBody, (int) $row['cs_post_id']]);
            $updated++;
        } catch (Throwable) {
            $errors++;
        }
    }

    return [
        'checked' => $checked,
        'updated' => $updated,
        'errors' => $errors,
    ];
}

/**
 * Sync comments for every converted local Facebook post.
 *
 * @return array{posts: int, fetched: int, inserted: int, updated: int, errors: int}
 */
function facebook_sync_comments_for_converted_posts(): array
{
    $table = cs_table('facebook_posts');
    $stmt = db()->query(
        "SELECT id FROM `{$table}` WHERE cs_post_id IS NOT NULL ORDER BY id ASC"
    );
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $posts = 0;
    $fetched = 0;
    $inserted = 0;
    $updated = 0;
    $errors = 0;

    foreach ($ids as $id) {
        $posts++;
        try {
            $result = facebook_sync_comments_for_post((int) $id);
            $fetched += $result['fetched'];
            $inserted += $result['inserted'];
            $updated += $result['updated'];
        } catch (Throwable) {
            $errors++;
        }
    }

    return [
        'posts' => $posts,
        'fetched' => $fetched,
        'inserted' => $inserted,
        'updated' => $updated,
        'errors' => $errors,
    ];
}

/**
 * @return array<string, string>
 */
function facebook_cron_frequencies(): array
{
    return [
        '15m' => 'Every 15 minutes',
        'hourly' => 'Hourly',
        '6h' => 'Every 6 hours',
        'daily' => 'Daily',
    ];
}

function facebook_mask_secret(string $value): string
{
    $len = strlen($value);
    if ($len === 0) {
        return '';
    }
    if ($len <= 4) {
        return str_repeat('•', $len);
    }
    return str_repeat('•', max(8, $len - 4)) . substr($value, -4);
}

/**
 * Parse Graph API datetime (ISO 8601) to MySQL DATETIME in Eastern (New York) time.
 */
function facebook_timezone(): DateTimeZone
{
    return new DateTimeZone('America/New_York');
}

/**
 * Parse Graph API datetime (ISO 8601) to MySQL DATETIME in America/New_York.
 */
function facebook_parse_graph_time(?string $value): ?string
{
    if ($value === null || trim($value) === '') {
        return null;
    }
    try {
        $dt = new DateTimeImmutable($value);
        return $dt->setTimezone(facebook_timezone())->format('Y-m-d H:i:s');
    } catch (Throwable) {
        return null;
    }
}

/**
 * Reinterpret a naive DATETIME (previously stored as UTC) into Eastern wall time.
 */
function facebook_utc_naive_to_eastern(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    try {
        $dt = new DateTimeImmutable($value, new DateTimeZone('UTC'));
        return $dt->setTimezone(facebook_timezone())->format('Y-m-d H:i:s');
    } catch (Throwable) {
        return null;
    }
}

/**
 * @return array{ok: bool, status: int, body: string, json: ?array}
 */
function facebook_graph_get(string $path, array $query): array
{
    $creds = facebook_credentials();
    $version = $creds['graph_version'];
    $query['access_token'] = $creds['page_access_token'];
    $url = 'https://graph.facebook.com/' . rawurlencode($version) . '/' . ltrim($path, '/')
        . '?' . http_build_query($query);

    $status = 0;
    $body = '';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Unable to initialize HTTP client for Facebook Graph API.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($errno !== 0) {
            throw new RuntimeException('Facebook Graph API request failed: ' . $error);
        }
        $body = is_string($raw) ? $raw : '';
    } elseif (function_exists('cs_mail_find_curl_binary') && ($curlBin = cs_mail_find_curl_binary()) !== null) {
        $cmd = [
            $curlBin,
            '-sS',
            '-w',
            "\n%{http_code}",
            '-H',
            'Accept: application/json',
            '--max-time',
            '30',
            $url,
        ];
        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = proc_open($cmd, $descriptor, $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($proc)) {
            throw new RuntimeException('Unable to start HTTP client for Facebook Graph API.');
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $code = proc_close($proc);
        if ($code !== 0 || !is_string($stdout)) {
            $detail = is_string($stderr) && $stderr !== '' ? trim($stderr) : 'curl exited with code ' . $code;
            throw new RuntimeException('Facebook Graph API request failed: ' . $detail);
        }
        $stdout = str_replace("\r\n", "\n", $stdout);
        $pos = strrpos($stdout, "\n");
        if ($pos === false) {
            throw new RuntimeException('Unexpected Facebook Graph API response.');
        }
        $body = substr($stdout, 0, $pos);
        $status = (int) trim(substr($stdout, $pos + 1));
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Accept: application/json\r\n",
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents($url, false, $context);
        if ($raw === false) {
            $last = error_get_last();
            $detail = is_array($last) && isset($last['message']) ? (string) $last['message'] : 'HTTP request failed.';
            throw new RuntimeException('Facebook Graph API request failed: ' . $detail);
        }
        $body = $raw;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $headerLine) {
                if (preg_match('#^HTTP/\S+\s+(\d+)#', (string) $headerLine, $m)) {
                    $status = (int) $m[1];
                    break;
                }
            }
        }
    }

    $json = null;
    if ($body !== '') {
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            $json = $decoded;
        }
    }

    return [
        'ok' => $status >= 200 && $status < 300,
        'status' => $status,
        'body' => $body,
        'json' => $json,
    ];
}

/**
 * Fetch last N published Page posts from Graph and upsert into CS_facebook_posts.
 * Also syncs comments for converted posts.
 *
 * @return array{
 *   fetched: int,
 *   inserted: int,
 *   updated: int,
 *   inserted_ids: list<int>,
 *   comments_posts: int,
 *   comments_fetched: int,
 *   comments_inserted: int,
 *   comments_updated: int,
 *   comments_errors: int
 * }
 */
function facebook_sync_posts(int $limit = 20): array
{
    $creds = facebook_credentials();
    if ($creds['page_id'] === '' || $creds['page_access_token'] === '') {
        throw new RuntimeException(
            'Facebook credentials are incomplete. Set Page ID and Page access token under Settings → Facebook Sync → Credentials (or in .env).'
        );
    }

    $limit = max(1, min(100, $limit));
    $path = $creds['page_id'] . '/published_posts';
    $response = facebook_graph_get($path, [
        'fields' => 'id,message,created_time,updated_time,permalink_url,full_picture,status_type',
        'limit' => $limit,
    ]);

    if (!$response['ok'] || !is_array($response['json'])) {
        $msg = 'Facebook Graph API error';
        if (is_array($response['json']) && isset($response['json']['error']['message'])) {
            $msg .= ': ' . (string) $response['json']['error']['message'];
        } else {
            $msg .= ' (HTTP ' . $response['status'] . ').';
        }
        throw new RuntimeException($msg);
    }

    $data = $response['json']['data'] ?? [];
    if (!is_array($data)) {
        $data = [];
    }

    $table = cs_table('facebook_posts');
    $pdo = db();
    $select = $pdo->prepare("SELECT id FROM `{$table}` WHERE fb_post_id = ? LIMIT 1");
    $insert = $pdo->prepare(
        "INSERT INTO `{$table}`
          (fb_post_id, message, permalink_url, status_type, full_picture, fb_created_time, fb_updated_time, is_new, first_seen_at, last_synced_at, raw_json)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW(), ?)"
    );
    $update = $pdo->prepare(
        "UPDATE `{$table}` SET
          message = ?,
          permalink_url = ?,
          status_type = ?,
          full_picture = ?,
          fb_created_time = ?,
          fb_updated_time = ?,
          last_synced_at = NOW(),
          raw_json = ?
         WHERE fb_post_id = ?"
    );

    $inserted = 0;
    $updated = 0;
    $fetched = 0;
    $insertedIds = [];

    foreach ($data as $post) {
        if (!is_array($post) || empty($post['id'])) {
            continue;
        }
        $message = isset($post['message']) ? trim((string) $post['message']) : '';
        if ($message === '') {
            continue;
        }
        $fetched++;

        $fbPostId = (string) $post['id'];
        $permalink = isset($post['permalink_url']) ? (string) $post['permalink_url'] : null;
        $statusType = isset($post['status_type']) ? (string) $post['status_type'] : null;
        $picture = isset($post['full_picture']) ? (string) $post['full_picture'] : null;
        $created = facebook_parse_graph_time(isset($post['created_time']) ? (string) $post['created_time'] : null);
        $updatedAt = facebook_parse_graph_time(isset($post['updated_time']) ? (string) $post['updated_time'] : null);
        $raw = json_encode($post, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($raw === false) {
            $raw = null;
        } elseif (strlen($raw) > 60000) {
            $raw = substr($raw, 0, 60000);
        }

        $select->execute([$fbPostId]);
        $exists = $select->fetchColumn();

        if ($exists) {
            $update->execute([
                $message,
                $permalink,
                $statusType,
                $picture,
                $created,
                $updatedAt,
                $raw,
                $fbPostId,
            ]);
            $updated++;
        } else {
            $insert->execute([
                $fbPostId,
                $message,
                $permalink,
                $statusType,
                $picture,
                $created,
                $updatedAt,
                $raw,
            ]);
            $inserted++;
            $insertedIds[] = (int) $pdo->lastInsertId();
        }
    }

    $comments = facebook_sync_comments_for_converted_posts();

    return [
        'fetched' => $fetched,
        'inserted' => $inserted,
        'updated' => $updated,
        'inserted_ids' => $insertedIds,
        'comments_posts' => $comments['posts'],
        'comments_fetched' => $comments['fetched'],
        'comments_inserted' => $comments['inserted'],
        'comments_updated' => $comments['updated'],
        'comments_errors' => $comments['errors'],
    ];
}

/**
 * Scheduled Facebook sync runner (CLI/HTTP cron endpoint).
 * When auto-post mode is on: auto-convert newly synced hashtag posts, apply
 * unapplied Page comments, and refresh bodies for posts within 6 hours of fb_created_time.
 *
 * Manual Sync Now does not call this — only the scheduled endpoint does.
 *
 * @param array{force?: bool} $options
 * @return array<string, mixed>
 */
function facebook_cron_run(array $options = []): array
{
    $force = !empty($options['force']);
    $cron = facebook_cron_settings();
    $autoPost = facebook_auto_post_enabled();

    if (!$cron['enabled'] && !$force) {
        return [
            'ok' => true,
            'ran' => false,
            'reason' => 'cron_disabled',
            'auto_post' => $autoPost,
        ];
    }

    if (!$force && !facebook_cron_is_due()) {
        return [
            'ok' => true,
            'ran' => false,
            'reason' => 'not_due',
            'auto_post' => $autoPost,
            'frequency' => $cron['frequency'],
            'last_run' => $cron['last_run'],
        ];
    }

    $sync = facebook_sync_posts(20);
    $result = [
        'ok' => true,
        'ran' => true,
        'auto_post' => $autoPost,
        'frequency' => $cron['frequency'],
        'sync' => $sync,
        'auto_convert' => null,
        'apply_comments' => null,
        'body_refresh' => null,
    ];

    if ($autoPost) {
        $result['auto_convert'] = facebook_auto_convert_new_posts($sync['inserted_ids'] ?? []);

        // Newly converted posts need comments synced (convert already tries), then apply.
        // Re-sync comments for all converted so UPDATE/CLEARED since last run are present.
        $result['sync']['comments_after_convert'] = facebook_sync_comments_for_converted_posts();
        $result['apply_comments'] = facebook_apply_unapplied_comments_for_converted_posts();
        $result['body_refresh'] = facebook_refresh_bodies_within_hours(6);
    }

    facebook_cron_mark_ran();
    $result['last_run'] = settings_get('fb_cron_last_run');

    return $result;
}

/**
 * @return list<array<string, mixed>>
 */
function facebook_posts_list(?int $limit = null): array
{
    $table = cs_table('facebook_posts');
    $sql = "SELECT * FROM `{$table}`
            WHERE message IS NOT NULL AND TRIM(message) <> ''
            ORDER BY fb_created_time DESC, id DESC";
    if ($limit !== null && $limit > 0) {
        $sql .= ' LIMIT ' . (int) $limit;
    }
    $stmt = db()->query($sql);
    return $stmt->fetchAll();
}

function facebook_posts_mark_seen(int $id): bool
{
    $table = cs_table('facebook_posts');
    $stmt = db()->prepare("UPDATE `{$table}` SET is_new = 0 WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->rowCount() > 0;
}

function facebook_posts_mark_all_seen(): int
{
    $table = cs_table('facebook_posts');
    $stmt = db()->exec("UPDATE `{$table}` SET is_new = 0 WHERE is_new = 1");
    return $stmt === false ? 0 : (int) $stmt;
}

function facebook_excerpt(?string $message, int $max = 120): string
{
    $text = trim((string) $message);
    if ($text === '') {
        return '(no message)';
    }
    if (strlen($text) <= $max) {
        return $text;
    }
    return substr($text, 0, $max - 1) . '...';
}

/**
 * Detect CLEARED | / UPDATE | comment prefixes for incident form actions.
 *
 * @return 'cleared'|'update'|null
 */
function facebook_comment_action_type(?string $message): ?string
{
    $text = ltrim((string) $message);
    if ($text === '') {
        return null;
    }
    if (preg_match('/^CLEARED\s*\|/i', $text) === 1) {
        return 'cleared';
    }
    if (preg_match('/^UPDATE\s*\|/i', $text) === 1) {
        return 'update';
    }
    return null;
}

/**
 * Update body from an UPDATE | comment: prefer text after the second |,
 * otherwise everything after the first |.
 */
function facebook_comment_update_text(string $message): string
{
    $parts = explode('|', $message);
    if (count($parts) >= 3) {
        return trim(implode('|', array_slice($parts, 2)));
    }
    if (count($parts) >= 2) {
        return trim($parts[1]);
    }
    return trim($message);
}

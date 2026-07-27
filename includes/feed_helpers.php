<?php

declare(strict_types=1);

function cs_relative_time(string $datetime): string
{
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '';
    }
    $diff = max(0, time() - $ts);
    if ($diff < 60) {
        return 'JUST NOW';
    }
    if ($diff < 3600) {
        $m = (int) floor($diff / 60);
        return $m . ' MIN' . ($m === 1 ? '' : 'S') . ' AGO';
    }
    if ($diff < 86400) {
        $h = (int) floor($diff / 3600);
        return $h . ' HOUR' . ($h === 1 ? '' : 'S') . ' AGO';
    }
    $d = (int) floor($diff / 86400);
    return $d . ' DAY' . ($d === 1 ? '' : 'S') . ' AGO';
}

/**
 * Absolute date/time; relative age in parentheses only when under 12 hours old.
 * e.g. "July 26, 2026, 4:42 PM (4 hours ago)" or "July 25, 2026, 4:42 PM"
 */
function cs_format_post_time(string $datetime): string
{
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '';
    }

    $absolute = date('F j, Y, g:i A', $ts);
    $diff = max(0, time() - $ts);

    // Over 12 hours: date/time only
    if ($diff >= 12 * 3600) {
        return $absolute;
    }

    if ($diff < 60) {
        $relative = 'just now';
    } elseif ($diff < 3600) {
        $m = (int) floor($diff / 60);
        $relative = $m . ' minute' . ($m === 1 ? '' : 's') . ' ago';
    } else {
        $h = (int) floor($diff / 3600);
        $relative = $h . ' hour' . ($h === 1 ? '' : 's') . ' ago';
    }

    return $absolute . ' (' . $relative . ')';
}

/**
 * Article page dateline: weekday + absolute date/time, no relative suffix.
 * e.g. "Sunday, July 26, 2026 10:43 PM"
 */
function cs_format_article_time(string $datetime): string
{
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '';
    }

    return date('l, F j, Y g:i A', $ts);
}

/**
 * Agency field may be "CCFR", "FIRE", or "BADGE|ORG" (e.g. CRIME|NPD / CCSO).
 *
 * @return array{0: string, 1: ?string} [badgeLabel, agencyOrg]
 */
function cs_parse_agency(?string $agency, string $category): array
{
    $raw = trim((string) $agency);
    if ($raw !== '' && str_contains($raw, '|')) {
        [$badge, $org] = explode('|', $raw, 2);
        return [strtoupper(trim($badge)), trim($org) !== '' ? trim($org) : null];
    }

    $upper = strtoupper($raw);
    if (in_array($upper, ['FIRE', 'CRIME', 'TRAFFIC', 'WEATHER', 'NEWS', 'UPDATES'], true)) {
        return [$upper, null];
    }

    $badge = match (strtoupper($category)) {
        'FIRE' => 'FIRE',
        'CRIME' => 'CRIME',
        'TRAFFIC' => 'TRAFFIC',
        default => strtoupper($category),
    };

    return [$badge, $raw !== '' ? $raw : null];
}

function cs_badge_class(string $badge): string
{
    return match (strtoupper($badge)) {
        'FIRE' => 'badge--fire',
        'CRIME', 'TRAFFIC' => 'badge--police',
        default => 'badge--default',
    };
}

/**
 * Internal article URL for news/updates/weather posts, or null if not applicable.
 *
 * @param array<string, mixed> $post
 */
function cs_article_url(array $post): ?string
{
    $id = (int) ($post['id'] ?? 0);
    if ($id <= 0) {
        return null;
    }

    $category = strtoupper((string) ($post['category'] ?? ''));
    if (cs_is_news_category($category)) {
        return '/article/news.php?id=' . $id;
    }
    if (cs_is_weather_category($category)) {
        return '/article/weather.php?id=' . $id;
    }

    return null;
}

/**
 * Href for the feed “read more” link.
 * Prefers the internal article page when article_body is set; otherwise external read_more_url.
 *
 * @param array<string, mixed> $post
 * @return array{href: string, external: bool}|null
 */
function cs_read_more_href(array $post): ?array
{
    $articleBody = trim((string) ($post['article_body'] ?? ''));
    if ($articleBody !== '') {
        $internal = cs_article_url($post);
        if ($internal !== null) {
            return ['href' => $internal, 'external' => false];
        }
    }

    $external = trim((string) ($post['read_more_url'] ?? ''));
    if ($external !== '') {
        return ['href' => $external, 'external' => true];
    }

    return null;
}

/**
 * Render one feed card as HTML.
 *
 * @param array<string, mixed> $post
 */
function cs_render_post_card(array $post, int $index = 0): string
{
    ob_start();
    $i = $index;
    require __DIR__ . '/partials/post_card.php';
    return (string) ob_get_clean();
}

/**
 * Format a chronological milestone label (NOON or MIDNIGHT).
 * e.g. "SUNDAY, JULY 26 — NOON"
 */
function cs_format_milestone_label(int $ts, string $kind): string
{
    $kind = strtoupper($kind);
    return strtoupper(date('l, F j', $ts)) . ' — ' . $kind;
}

/**
 * Noon and midnight markers strictly between two created_at values (newer → older).
 * Ordered newest-first so they appear correctly when scrolling down the feed.
 *
 * @return list<array{ts: int, kind: string, label: string}>
 */
function cs_milestones_between(string $newerCreatedAt, string $olderCreatedAt): array
{
    $newer = strtotime($newerCreatedAt);
    $older = strtotime($olderCreatedAt);
    if ($newer === false || $older === false || $older >= $newer) {
        return [];
    }

    $milestones = [];

    // Walk local midnights and noons from just below $newer down to just above $older.
    // Start at the most recent local midnight at or before $newer, then step back 12 hours.
    $cursorDay = (int) date('Ymd', $newer);
    $y = (int) substr((string) $cursorDay, 0, 4);
    $m = (int) substr((string) $cursorDay, 4, 2);
    $d = (int) substr((string) $cursorDay, 6, 2);

    // Candidates for this calendar day: noon, then midnight (newest first within the day).
    // Then move to previous day.
    $dayStart = mktime(0, 0, 0, $m, $d, $y);
    if ($dayStart === false) {
        return [];
    }

    // Safety: don't scan more than ~2 years of markers
    $minTs = $older;
    $maxSteps = 1500;
    $steps = 0;

    while ($dayStart > $minTs - 86400 && $steps < $maxSteps) {
        $noon = $dayStart + 12 * 3600;
        $midnight = $dayStart;

        foreach ([['ts' => $noon, 'kind' => 'NOON'], ['ts' => $midnight, 'kind' => 'MIDNIGHT']] as $candidate) {
            $ts = $candidate['ts'];
            if ($ts > $older && $ts < $newer) {
                $milestones[] = [
                    'ts' => $ts,
                    'kind' => $candidate['kind'],
                    'label' => cs_format_milestone_label($ts, $candidate['kind']),
                ];
            }
        }

        $dayStart -= 86400;
        $steps++;
    }

    // Already collected newest-first within each day and days newest→oldest.
    return $milestones;
}

function cs_render_milestone(string $label): string
{
    return '<div class="feed-milestone" role="presentation"><p>' . e($label) . '</p></div>';
}

/**
 * Render posts newest→oldest with noon/midnight milestones between consecutive cards.
 * First page: pass null boundary → no milestone above the newest card.
 * Infinite scroll: pass the previous page's last (newer neighbor) created_at.
 *
 * @param list<array<string, mixed>> $posts
 */
function cs_render_feed_sequence(array $posts, ?string $newerBoundaryCreatedAt = null): string
{
    $html = '';
    $prevCreatedAt = $newerBoundaryCreatedAt;

    foreach ($posts as $i => $post) {
        $createdAt = (string) ($post['created_at'] ?? '');

        if ($prevCreatedAt !== null && $createdAt !== '') {
            foreach (cs_milestones_between($prevCreatedAt, $createdAt) as $milestone) {
                $html .= cs_render_milestone($milestone['label']);
            }
        }

        $html .= cs_render_post_card($post, $i);
        $prevCreatedAt = $createdAt !== '' ? $createdAt : $prevCreatedAt;
    }

    return $html;
}

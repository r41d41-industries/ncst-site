<?php

declare(strict_types=1);

/** Allowed feed templates for categories. */
const CS_CATEGORY_TEMPLATES = ['news', 'weather', 'incident'];

/**
 * @return list<array<string, mixed>>
 */
function categories_all(bool $refresh = false): array
{
    static $cache = null;
    if ($refresh) {
        $cache = null;
    }
    if (is_array($cache)) {
        return $cache;
    }

    $table = cs_table('categories');
    $stmt = db()->query("SELECT * FROM `{$table}` ORDER BY sort_order ASC, id ASC");
    $cache = $stmt->fetchAll();
    return $cache;
}

function categories_clear_cache(): void
{
    categories_all(true);
}

/**
 * Categories shown in the public filter row (is_filter = 1).
 *
 * @return list<array<string, mixed>>
 */
function categories_for_filter(): array
{
    return array_values(array_filter(
        categories_all(),
        static fn(array $row): bool => !empty($row['is_filter'])
    ));
}

/**
 * Public filter labels: ALL + filterable category names (uppercase slug for URLs).
 *
 * @return list<string>
 */
function cs_filter_category_slugs(): array
{
    $slugs = ['ALL'];
    foreach (categories_for_filter() as $row) {
        $slugs[] = strtoupper((string) $row['slug']);
    }
    return $slugs;
}

/**
 * All post category slugs.
 *
 * @return list<string>
 */
function cs_post_category_slugs(): array
{
    $slugs = [];
    foreach (categories_all() as $row) {
        $slugs[] = strtoupper((string) $row['slug']);
    }
    return $slugs;
}

function category_by_slug(string $slug): ?array
{
    $slug = strtoupper(trim($slug));
    foreach (categories_all() as $row) {
        if (strtoupper((string) $row['slug']) === $slug) {
            return $row;
        }
    }
    return null;
}

function category_by_id(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $table = cs_table('categories');
    $stmt = db()->prepare("SELECT * FROM `{$table}` WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Template for a category slug: news|weather|incident (default news).
 */
function category_template(string $slug): string
{
    $row = category_by_slug($slug);
    if ($row === null) {
        return 'news';
    }
    $template = strtolower(trim((string) ($row['template'] ?? 'news')));
    return in_array($template, CS_CATEGORY_TEMPLATES, true) ? $template : 'news';
}

function category_color(string $slug): string
{
    $row = category_by_slug($slug);
    $color = $row !== null ? trim((string) ($row['color'] ?? '')) : '';
    return cs_normalize_hex_color($color) ?? '#f7931e';
}

/**
 * @return list<string>
 */
function category_slugs_by_template(string $template): array
{
    $template = strtolower(trim($template));
    $slugs = [];
    foreach (categories_all() as $row) {
        if (strtolower((string) ($row['template'] ?? '')) === $template) {
            $slugs[] = strtoupper((string) $row['slug']);
        }
    }
    return $slugs;
}

function cs_normalize_hex_color(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    if ($value[0] !== '#') {
        $value = '#' . $value;
    }
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
        return null;
    }
    return strtolower($value);
}

/**
 * Build a unique slug from a display name (uppercase A-Z0-9_).
 */
function category_slug_from_name(string $name, ?int $excludeId = null): string
{
    $slug = strtoupper(trim($name));
    $slug = preg_replace('/[^A-Z0-9]+/', '_', $slug) ?? '';
    $slug = trim($slug, '_');
    if ($slug === '') {
        $slug = 'CATEGORY';
    }
    if (strlen($slug) > 32) {
        $slug = substr($slug, 0, 32);
        $slug = rtrim($slug, '_');
    }

    $base = $slug;
    $n = 2;
    while (category_slug_taken($slug, $excludeId)) {
        $suffix = '_' . $n;
        $slug = substr($base, 0, max(1, 32 - strlen($suffix))) . $suffix;
        $n++;
        if ($n > 99) {
            break;
        }
    }
    return $slug;
}

function category_slug_taken(string $slug, ?int $excludeId = null): bool
{
    $table = cs_table('categories');
    $sql = "SELECT id FROM `{$table}` WHERE slug = ?";
    $params = [strtoupper($slug)];
    if ($excludeId !== null) {
        $sql .= ' AND id <> ?';
        $params[] = $excludeId;
    }
    $sql .= ' LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (bool) $stmt->fetchColumn();
}

/**
 * @param array{name: string, template: string, color: string, sort_order?: int, is_filter?: bool, slug?: string} $data
 */
function category_create(array $data): int
{
    $name = trim((string) ($data['name'] ?? ''));
    $template = strtolower(trim((string) ($data['template'] ?? 'news')));
    $color = cs_normalize_hex_color($data['color'] ?? null);
    if ($name === '') {
        throw new InvalidArgumentException('Name is required.');
    }
    if (!in_array($template, CS_CATEGORY_TEMPLATES, true)) {
        throw new InvalidArgumentException('Invalid template.');
    }
    if ($color === null) {
        throw new InvalidArgumentException('Color must be a hex value like #f7931e.');
    }

    $slug = trim((string) ($data['slug'] ?? ''));
    if ($slug === '') {
        $slug = category_slug_from_name($name);
    } else {
        $slug = strtoupper(preg_replace('/[^A-Z0-9_]+/', '', strtoupper($slug)) ?? '');
        if ($slug === '' || category_slug_taken($slug)) {
            throw new InvalidArgumentException('Slug is invalid or already in use.');
        }
    }

    $table = cs_table('categories');
    $stmt = db()->prepare(
        "INSERT INTO `{$table}` (slug, name, template, color, sort_order, is_filter)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $slug,
        $name,
        $template,
        $color,
        (int) ($data['sort_order'] ?? 100),
        !empty($data['is_filter']) ? 1 : 0,
    ]);
    categories_clear_cache();
    return (int) db()->lastInsertId();
}

/**
 * @param array{name: string, template: string, color: string, sort_order?: int, is_filter?: bool} $data
 */
function category_update(int $id, array $data): void
{
    $existing = category_by_id($id);
    if ($existing === null) {
        throw new InvalidArgumentException('Category not found.');
    }

    $name = trim((string) ($data['name'] ?? ''));
    $template = strtolower(trim((string) ($data['template'] ?? 'news')));
    $color = cs_normalize_hex_color($data['color'] ?? null);
    if ($name === '') {
        throw new InvalidArgumentException('Name is required.');
    }
    if (!in_array($template, CS_CATEGORY_TEMPLATES, true)) {
        throw new InvalidArgumentException('Invalid template.');
    }
    if ($color === null) {
        throw new InvalidArgumentException('Color must be a hex value like #f7931e.');
    }

    $table = cs_table('categories');
    $stmt = db()->prepare(
        "UPDATE `{$table}` SET
           name = ?, template = ?, color = ?, sort_order = ?, is_filter = ?,
           updated_at = CURRENT_TIMESTAMP
         WHERE id = ?"
    );
    $stmt->execute([
        $name,
        $template,
        $color,
        (int) ($data['sort_order'] ?? $existing['sort_order'] ?? 0),
        !empty($data['is_filter']) ? 1 : 0,
        $id,
    ]);
    categories_clear_cache();
}

function category_posts_count(string $slug): int
{
    $table = cs_table('posts');
    $stmt = db()->prepare("SELECT COUNT(*) FROM `{$table}` WHERE category = ? AND trashed_at IS NULL");
    $stmt->execute([strtoupper($slug)]);
    return (int) $stmt->fetchColumn();
}

/**
 * Post counts keyed by uppercase category slug (only slugs that have ≥1 post).
 *
 * @return array<string, int>
 */
function category_post_counts(): array
{
    $table = cs_table('posts');
    $stmt = db()->query(
        "SELECT category, COUNT(*) AS cnt FROM `{$table}`
         WHERE trashed_at IS NULL
         GROUP BY category HAVING cnt > 0"
    );
    $counts = [];
    foreach ($stmt->fetchAll() as $row) {
        $slug = strtoupper(trim((string) ($row['category'] ?? '')));
        if ($slug === '') {
            continue;
        }
        $counts[$slug] = (int) $row['cnt'];
    }
    return $counts;
}

/**
 * Categories that currently have at least one post, with counts.
 * Ordered by category sort_order when the slug exists in CS_categories.
 *
 * @return list<array{slug: string, name: string, count: int}>
 */
function categories_with_posts(): array
{
    $counts = category_post_counts();
    if ($counts === []) {
        return [];
    }

    $bySlug = [];
    foreach (categories_all() as $row) {
        $slug = strtoupper((string) $row['slug']);
        if (!isset($counts[$slug])) {
            continue;
        }
        $bySlug[$slug] = [
            'slug' => $slug,
            'name' => (string) $row['name'],
            'count' => $counts[$slug],
        ];
    }

    // Include orphan post categories not in CS_categories (still listed).
    foreach ($counts as $slug => $count) {
        if (isset($bySlug[$slug])) {
            continue;
        }
        $bySlug[$slug] = [
            'slug' => $slug,
            'name' => $slug,
            'count' => $count,
        ];
    }

    return array_values($bySlug);
}

/** @deprecated Keep BC aliases used by older call sites; prefer category_template(). */
const CS_FILTER_CATEGORIES = ['ALL', 'NEWS', 'UPDATES', 'TRAFFIC', 'CRIME', 'FIRE', 'WEATHER'];
const CS_POST_CATEGORIES = ['NEWS', 'UPDATES', 'TRAFFIC', 'CRIME', 'FIRE', 'WEATHER'];
const CS_INCIDENT_CATEGORIES = ['CRIME', 'FIRE', 'TRAFFIC'];
const CS_WEATHER_CATEGORIES = ['WEATHER'];
const CS_NEWS_CATEGORIES = ['NEWS', 'UPDATES'];

function cs_is_incident_category(string $category): bool
{
    return category_template($category) === 'incident';
}

function cs_is_weather_category(string $category): bool
{
    return category_template($category) === 'weather';
}

function cs_is_news_category(string $category): bool
{
    return category_template($category) === 'news';
}

function cs_post_layout_label(string $category): string
{
    return category_template($category) . ' layout';
}

<?php

declare(strict_types=1);

/**
 * Shared article page bootstrap: load post, validate publish + category, render chrome.
 *
 * Expects:
 * - $articleAllowedCategories: list<string>
 * - $articleKindLabel: string (for title / not-found copy)
 * - $articleShowValidMeta: bool
 */

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/feed_helpers.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$post = $id > 0 ? posts_find($id) : null;

$category = $post !== null ? strtoupper((string) ($post['category'] ?? '')) : '';
$isAllowedCategory = $post !== null && in_array($category, $articleAllowedCategories, true);
$found = $post !== null
    && !empty($post['published'])
    && !posts_is_trashed($post)
    && $isAllowedCategory;

$pageTitle = $found
    ? ((string) $post['title'] . ' — NCST')
    : ('Article not found — NCST');

$backHref = '/';
if ($found && $category !== '') {
    $backHref = '/?category=' . rawurlencode($category);
}

$articleBody = '';
if ($found) {
    $long = trim((string) ($post['article_body'] ?? ''));
    $articleBody = $long !== '' ? $long : (string) ($post['body'] ?? '');
}

[$badgeLabel] = $found
    ? cs_parse_agency($post['agency'] ?? null, $category)
    : ['', null];
$badgeClass = $found ? 'badge--category' : 'badge--default';
$catColor = $found ? category_color($category) : '#554335';

$hasImage = $found && !empty($post['image_path']);

$recorded = '';
$expires = '';
if ($found && !empty($articleShowValidMeta)) {
    $recordedAt = isset($post['recorded_at']) ? trim((string) $post['recorded_at']) : '';
    $expiresAt = isset($post['expires_at']) ? trim((string) $post['expires_at']) : '';
    $recorded = cs_format_event_time($recordedAt !== '' ? $recordedAt : null);
    $expires = cs_format_event_time($expiresAt !== '' ? $expiresAt : null, true);
}

$og = $found
    ? settings_resolve_og($post, site_url('article/' . (cs_is_weather_category($category) ? 'weather' : 'news') . '.php?id=' . $id))
    : settings_resolve_og(null, site_url());
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?></title>
  <?php require __DIR__ . '/og_meta.php'; ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/main.css?v=<?= e((string) filemtime(dirname(__DIR__, 2) . '/assets/css/main.css')) ?>">
</head>
<body>
  <?php require __DIR__ . '/site_header.php'; ?>

  <main class="article-wrap">
    <?php if (!$found): ?>
      <div class="article article--missing">
        <p class="article__missing">This <?= e(strtolower($articleKindLabel)) ?> article was not found or is no longer published.</p>
        <p class="article__back-wrap">
          <a class="article__back" href="/">← Back to main feed</a>
        </p>
      </div>
    <?php else: ?>
      <article class="article">
        <?php if ($hasImage): ?>
          <div class="article__media">
            <img src="/<?= e(ltrim((string) $post['image_path'], '/')) ?>" alt="" width="800" height="256">
          </div>
        <?php endif; ?>
        <div class="article__body">
          <div class="article__meta">
            <div class="article__meta-left">
              <span class="badge <?= e($badgeClass) ?>" style="--cat-color: <?= e($catColor) ?>"><?= e($badgeLabel) ?></span>
            </div>
          </div>
          <h1 class="article__title"><?= e((string) $post['title']) ?></h1>
          <div class="article__dateline">
            <time class="article__time" datetime="<?= e((string) $post['created_at']) ?>"><?= e(cs_format_article_time((string) $post['created_at'])) ?></time>
          </div>
          <div class="article__text"><?= e($articleBody) ?></div>

          <?php if (!empty($articleShowValidMeta)): ?>
            <div class="post__agency post__agency--solo article__valid">
              <p>
                VALID: <?= e($recorded !== '' ? $recorded : 'UNKNOWN') ?>
                TO
                <?= e($expires !== '' ? $expires : 'UNKNOWN') ?>
              </p>
            </div>
          <?php endif; ?>

          <p class="article__back-wrap">
            <a class="article__back" href="<?= e($backHref) ?>">← Back to main feed</a>
          </p>
        </div>
      </article>
    <?php endif; ?>
  </main>

  <?php require __DIR__ . '/site_footer.php'; ?>
</body>
</html>

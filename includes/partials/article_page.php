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
$articleIsHtml = false;
$footnotesHtml = '';
if ($found) {
    $long = trim((string) ($post['article_body'] ?? ''));
    if ($long !== '') {
        $articleIsHtml = true;
        $footnotes = posts_normalize_footnotes($post['footnotes'] ?? null);
        $rendered = article_render_body($long, $footnotes);
        $articleBody = $rendered['html'];
        $footnotesHtml = $rendered['footnotes_html'];
    } else {
        $articleBody = (string) ($post['body'] ?? '');
    }
}

[$badgeLabel] = $found
    ? cs_parse_agency($post['agency'] ?? null, $category)
    : ['', null];
$badgeClass = $found ? 'badge--category' : 'badge--default';
$catColor = $found ? category_color($category) : '#554335';

$hasImage = $found && !empty($post['image_path']);

$articleGallery = null;
$articlePlaylist = null;
if ($found) {
    $galleryId = isset($post['gallery_id']) ? (int) $post['gallery_id'] : 0;
    $playlistId = isset($post['playlist_id']) ? (int) $post['playlist_id'] : 0;
    if ($galleryId > 0) {
        $articleGallery = gallery_find($galleryId);
        if ($articleGallery !== null) {
            $imageItems = [];
            foreach ($articleGallery['items'] as $item) {
                $path = trim((string) ($item['path'] ?? ''));
                if ($path === '') {
                    continue;
                }
                $imageItems[] = $item;
            }
            if ($imageItems === []) {
                $articleGallery = null;
            } else {
                $articleGallery['items'] = $imageItems;
            }
        }
    }
    if ($playlistId > 0) {
        $articlePlaylist = playlist_find($playlistId);
        if ($articlePlaylist !== null) {
            $audioItems = [];
            foreach ($articlePlaylist['items'] as $item) {
                $path = trim((string) ($item['path'] ?? ''));
                if ($path === '') {
                    continue;
                }
                $audioItems[] = $item;
            }
            if ($audioItems === []) {
                $articlePlaylist = null;
            } else {
                $articlePlaylist['items'] = $audioItems;
            }
        }
    }
}

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
          <div class="article__text<?= $articleIsHtml ? ' article__text--html' : '' ?>">
            <?php if ($articleIsHtml): ?>
              <?= $articleBody ?>
            <?php else: ?>
              <?= e($articleBody) ?>
            <?php endif; ?>
          </div>

          <?php if ($articleGallery !== null): ?>
            <section class="article-gallery" aria-label="<?= e((string) ($articleGallery['title'] ?? 'Gallery')) ?>">
              <h2 class="article-gallery__title"><?= e((string) ($articleGallery['title'] ?? 'Gallery')) ?></h2>
              <?php if (!empty($articleGallery['description'])): ?>
                <p class="article-gallery__description"><?= e((string) $articleGallery['description']) ?></p>
              <?php endif; ?>
              <ul class="article-gallery__grid">
                <?php foreach ($articleGallery['items'] as $index => $item): ?>
                  <?php
                    $itemPath = trim((string) ($item['path'] ?? ''));
                    $itemSrc = '/' . ltrim($itemPath, '/');
                    $itemAlt = trim((string) ($item['alt_text'] ?? ''));
                    $itemCaption = trim((string) ($item['caption'] ?? ''));
                    if ($itemAlt === '') {
                        $itemAlt = $itemCaption !== '' ? $itemCaption : (string) ($item['media_title'] ?? '');
                    }
                    $openLabel = $itemAlt !== '' ? 'View image: ' . $itemAlt : 'View gallery image';
                  ?>
                  <li class="article-gallery__item">
                    <figure>
                      <button
                        type="button"
                        class="article-gallery__trigger"
                        data-gallery-index="<?= (int) $index ?>"
                        data-gallery-src="<?= e($itemSrc) ?>"
                        data-gallery-alt="<?= e($itemAlt) ?>"
                        data-gallery-caption="<?= e($itemCaption) ?>"
                        aria-label="<?= e($openLabel) ?>"
                      >
                        <img src="<?= e($itemSrc) ?>" alt="" loading="lazy">
                      </button>
                      <?php if ($itemCaption !== ''): ?>
                        <figcaption><?= e($itemCaption) ?></figcaption>
                      <?php endif; ?>
                    </figure>
                  </li>
                <?php endforeach; ?>
              </ul>
            </section>
          <?php endif; ?>

          <?php if ($articlePlaylist !== null): ?>
            <?php
              $playlistLabel = (string) ($articlePlaylist['title'] ?? 'Playlist');
              $playlistNowId = 'article-playlist-now-title';
            ?>
            <section class="article-playlist" aria-label="<?= e($playlistLabel) ?>" data-article-playlist>
              <h2 class="article-playlist__title"><?= e($playlistLabel) ?></h2>
              <?php if (!empty($articlePlaylist['description'])): ?>
                <p class="article-playlist__description"><?= e((string) $articlePlaylist['description']) ?></p>
              <?php endif; ?>

              <div class="article-playlist__player" role="group" aria-label="Audio player">
                <audio class="article-playlist__audio" preload="metadata" aria-hidden="true"></audio>

                <div class="article-playlist__now">
                  <p class="article-playlist__now-label">Now playing</p>
                  <p class="article-playlist__now-title" id="<?= e($playlistNowId) ?>">—</p>
                </div>

                <div class="article-playlist__controls">
                  <button
                    type="button"
                    class="article-playlist__play"
                    aria-label="Play"
                    disabled
                  >
                    <span class="article-playlist__play-icon" aria-hidden="true"></span>
                  </button>

                  <div class="article-playlist__timeline">
                    <span class="article-playlist__time article-playlist__time--current" aria-live="off">0:00</span>
                    <input
                      type="range"
                      class="article-playlist__seek"
                      min="0"
                      max="0"
                      value="0"
                      step="any"
                      aria-label="Seek"
                      disabled
                    >
                    <span class="article-playlist__time article-playlist__time--duration">0:00</span>
                  </div>

                  <button
                    type="button"
                    class="article-playlist__mute"
                    aria-label="Mute"
                    aria-pressed="false"
                    disabled
                  >
                    <span class="article-playlist__mute-icon" aria-hidden="true"></span>
                  </button>
                </div>
              </div>

              <ol class="article-playlist__tracks" role="list">
                <?php foreach ($articlePlaylist['items'] as $index => $item): ?>
                  <?php
                    $audioPath = trim((string) ($item['path'] ?? ''));
                    $audioSrc = '/' . ltrim($audioPath, '/');
                    $trackTitle = trim((string) ($item['title'] ?? ''));
                    if ($trackTitle === '') {
                        $trackTitle = trim((string) ($item['media_title'] ?? ''));
                    }
                    if ($trackTitle === '') {
                        $original = trim((string) ($item['original_name'] ?? ''));
                        $cleaned = $original !== '' ? pathinfo($original, PATHINFO_FILENAME) : '';
                        $trackTitle = $cleaned !== '' ? $cleaned : 'Audio';
                    }
                    $trackNum = $index + 1;
                  ?>
                  <li class="article-playlist__item">
                    <button
                      type="button"
                      class="article-playlist__track"
                      data-playlist-src="<?= e($audioSrc) ?>"
                      data-playlist-title="<?= e($trackTitle) ?>"
                      data-playlist-index="<?= (int) $index ?>"
                      aria-current="<?= $index === 0 ? 'true' : 'false' ?>"
                    >
                      <span class="article-playlist__track-num" aria-hidden="true"><?= (int) $trackNum ?></span>
                      <span class="article-playlist__track-title"><?= e($trackTitle) ?></span>
                    </button>
                  </li>
                <?php endforeach; ?>
              </ol>
            </section>
          <?php endif; ?>

          <?= $footnotesHtml ?>

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
  <?php if ($articleGallery !== null): ?>
    <script src="/assets/js/article-gallery.js?v=<?= e((string) filemtime(dirname(__DIR__, 2) . '/assets/js/article-gallery.js')) ?>" defer></script>
  <?php endif; ?>
  <?php if ($articlePlaylist !== null): ?>
    <script src="/assets/js/article-playlist.js?v=<?= e((string) filemtime(dirname(__DIR__, 2) . '/assets/js/article-playlist.js')) ?>" defer></script>
  <?php endif; ?>
</body>
</html>

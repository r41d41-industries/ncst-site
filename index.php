<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/feed_helpers.php';

$pageSize = 4;
$category = isset($_GET['category']) ? strtoupper(trim((string) $_GET['category'])) : 'ALL';
if (!in_array($category, CS_FILTER_CATEGORIES, true)) {
    $category = 'ALL';
}

$filter = $category === 'ALL' ? null : $category;
$posts = posts_list_published($filter, $pageSize, 0);
$total = posts_count_published($filter);
$hasMore = count($posts) < $total;
$nextOffset = count($posts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>NCST Main Feed</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/main.css?v=<?= e((string) filemtime(__DIR__ . '/assets/css/main.css')) ?>">
</head>
<body>
  <?php require __DIR__ . '/includes/partials/site_header.php'; ?>

  <?php
    $brandTitle = 'NCST MAIN FEED';
    require __DIR__ . '/includes/partials/site_brand.php';
  ?>

  <div class="filters" data-feed-filters role="tablist" aria-label="Filter feed">
    <?php foreach (CS_FILTER_CATEGORIES as $filterLabel): ?>
      <?php
        $isActive = $category === $filterLabel;
        $href = $filterLabel === 'ALL' ? '/' : '/?category=' . rawurlencode($filterLabel);
      ?>
      <a
        class="filter-btn<?= $isActive ? ' is-active' : '' ?>"
        href="<?= e($href) ?>"
        role="tab"
        aria-selected="<?= $isActive ? 'true' : 'false' ?>"
        data-category="<?= e($filterLabel) ?>"
      ><?= e($filterLabel) ?></a>
    <?php endforeach; ?>
  </div>

  <p class="feed-now" data-feed-now aria-live="off"></p>

  <main
    class="feed"
    id="feed"
    data-feed
    data-category="<?= e($category) ?>"
    data-offset="<?= e((string) $nextOffset) ?>"
    data-has-more="<?= $hasMore ? '1' : '0' ?>"
    data-page-size="<?= e((string) $pageSize) ?>"
  >
    <?php if ($posts === []): ?>
      <p class="feed-empty">No published posts in this category.</p>
    <?php else: ?>
      <?= cs_render_feed_sequence($posts) ?>
    <?php endif; ?>
  </main>

  <div class="feed-sentinel" data-feed-sentinel aria-hidden="true"></div>

  <div class="feed-loader" hidden aria-hidden="true" aria-live="polite" data-feed-loader>
    <div class="spinner" aria-hidden="true"></div>
    <p>Scanning for more traffic...</p>
  </div>

  <?php require __DIR__ . '/includes/partials/site_footer.php'; ?>

  <script src="/assets/js/feed.js" defer></script>
</body>
</html>

<?php

declare(strict_types=1);

/** @var array<string, mixed> $post */
/** @var int $i */

$id = (int) $post['id'];
$anchor = 'post-' . $id;
$category = strtoupper((string) $post['category']);
$isIncident = cs_is_incident_category($category);
$isWeather = cs_is_weather_category($category);
$isNews = cs_is_news_category($category);

$facebookUrl = trim((string) ($post['facebook_url'] ?? ''));
$xUrl = trim((string) ($post['x_url'] ?? ''));
$hasFacebook = $facebookUrl !== '';
$hasX = $xUrl !== '';
$hasShare = $isIncident && ($hasFacebook || $hasX);
[$badgeLabel, $agencyOrg] = cs_parse_agency($post['agency'] ?? null, $category);
$badgeClass = 'badge--category';
$catColor = category_color($category);

/** @var list<array<string, mixed>> $updates */
$updates = $post['updates'] ?? [];
if ($updates === []) {
    $legacyLabel = trim((string) ($post['update_label'] ?? ''));
    $legacyText = trim((string) ($post['update_text'] ?? ''));
    if ($legacyLabel !== '' || $legacyText !== '') {
        $updates = [[
            'label' => $legacyLabel !== '' ? $legacyLabel : null,
            'body' => $legacyText,
            'created_at' => $post['updated_at'] ?? $post['created_at'] ?? null,
        ]];
    }
}
$updateCount = count($updates);
$hasUpdate = $isIncident && $updateCount > 0;
$latest = $hasUpdate ? $updates[0] : null;
$older = $hasUpdate ? array_slice($updates, 1) : [];
$hasTimeline = count($older) > 0;

$dispatchedAt = isset($post['dispatched_at']) ? trim((string) $post['dispatched_at']) : '';
$clearedAt = isset($post['cleared_at']) ? trim((string) $post['cleared_at']) : '';
$dispatched = cs_format_event_time($dispatchedAt !== '' ? $dispatchedAt : null);
$cleared = cs_format_event_time($clearedAt !== '' ? $clearedAt : null, true);
$recordedAt = isset($post['recorded_at']) ? trim((string) $post['recorded_at']) : '';
$expiresAt = isset($post['expires_at']) ? trim((string) $post['expires_at']) : '';
$recorded = cs_format_event_time($recordedAt !== '' ? $recordedAt : null);
$expires = cs_format_event_time($expiresAt !== '' ? $expiresAt : null, true);
$showValidMeta = $isWeather;
$readMore = (!$isIncident) ? cs_read_more_href($post) : null;
$hasImage = !empty($post['image_path']);
$showReadMore = $readMore !== null;

if ($isIncident) {
    $layout = 'incident';
} elseif ($isWeather) {
    $layout = 'weather';
} elseif ($isNews) {
    $layout = 'news';
} else {
    $layout = 'news';
}

$articleClass = 'post post--' . $layout;
?>
<article class="<?= e($articleClass) ?>" id="<?= e($anchor) ?>" style="animation-delay: <?= e((string) min($i * 0.06, 0.36)) ?>s" data-category="<?= e($category) ?>" data-layout="<?= e($layout) ?>">
  <?php if ($hasImage): ?>
    <div class="post__media">
      <img src="/<?= e(ltrim((string) $post['image_path'], '/')) ?>" alt="" width="800" height="256">
    </div>
  <?php endif; ?>
  <div class="post__body">
    <div class="post__meta">
      <div class="post__meta-left">
        <span class="badge <?= e($badgeClass) ?>" style="--cat-color: <?= e($catColor) ?>"><?= e($badgeLabel) ?></span>
      </div>
      <time class="post__time" datetime="<?= e((string) $post['created_at']) ?>"><?= e(cs_format_post_time((string) $post['created_at'])) ?></time>
    </div>
    <h2 class="post__title"><?= e((string) $post['title']) ?></h2>
    <p class="post__text"><?= e((string) $post['body']) ?></p>

    <?php if ($showReadMore && is_array($readMore)): ?>
      <div class="post__read-more-wrap">
        <a
          class="post__read-more"
          href="<?= e($readMore['href']) ?>"
          <?php if (!empty($readMore['external'])): ?>target="_blank" rel="noopener noreferrer"<?php endif; ?>
        >click here to read more</a>
      </div>
    <?php endif; ?>

    <?php if ($isIncident && $hasUpdate && is_array($latest)): ?>
      <?php
        $latestTime = '';
        if (!empty($latest['created_at'])) {
            $latestTime = cs_format_event_time((string) $latest['created_at']);
        } else {
            $latestTime = cs_update_time_value($latest['label'] ?? null);
        }
        $latestBody = trim((string) ($latest['body'] ?? ''));
        $panelId = 'updates-' . $id;
      ?>
      <div class="post__update" data-update-panel>
        <div class="post__update-latest">
          <p class="post__update-label">UPDATE:<?= $latestTime !== '' ? ' ' . e($latestTime) : '' ?></p>
          <?php if ($latestBody !== ''): ?>
            <p class="post__update-text"><?= e($latestBody) ?></p>
          <?php endif; ?>
        </div>

        <?php if ($hasTimeline): ?>
          <button
            type="button"
            class="post__update-toggle"
            data-update-toggle
            aria-expanded="false"
            aria-controls="<?= e($panelId) ?>"
          >
            <span class="post__update-toggle-text">Show <?= e((string) count($older)) ?> earlier update<?= count($older) === 1 ? '' : 's' ?></span>
            <span class="post__update-toggle-chevron" aria-hidden="true"></span>
          </button>
          <div class="post__update-timeline" id="<?= e($panelId) ?>" hidden data-update-timeline>
            <ol class="update-timeline">
              <?php foreach ($older as $entry): ?>
                <?php
                  $entryTime = '';
                  if (!empty($entry['created_at'])) {
                      $entryTime = cs_format_event_time((string) $entry['created_at']);
                  } else {
                      $entryTime = cs_update_time_value($entry['label'] ?? null);
                  }
                  $entryBody = trim((string) ($entry['body'] ?? ''));
                ?>
                <li class="update-timeline__item">
                  <p class="post__update-label">UPDATE:<?= $entryTime !== '' ? ' ' . e($entryTime) : '' ?></p>
                  <?php if ($entryBody !== ''): ?>
                    <p class="post__update-text"><?= e($entryBody) ?></p>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ol>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($isIncident): ?>
      <div class="post__agency<?= $hasUpdate ? '' : ' post__agency--solo' ?>">
        <p>AGENCIES: <?= e($agencyOrg !== null ? $agencyOrg : 'UNKNOWN') ?></p>
        <p>
          DISPATCHED: <?= e($dispatched !== '' ? $dispatched : 'UNKNOWN') ?>
          |
          CLEARED: <?= e($cleared !== '' ? $cleared : 'UNKNOWN') ?>
        </p>
      </div>
    <?php elseif ($showValidMeta): ?>
      <div class="post__agency post__agency--solo">
        <p>
          VALID: <?= e($recorded !== '' ? $recorded : 'UNKNOWN') ?>
          TO
          <?= e($expires !== '' ? $expires : 'UNKNOWN') ?>
        </p>
      </div>
    <?php endif; ?>

    <?php if ($hasShare): ?>
      <div class="post__share">
        <?php if ($hasFacebook): ?>
          <a class="share-btn share-btn--facebook" href="<?= e($facebookUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="Facebook">
            <img src="/assets/img/icon-facebook.svg" alt="" width="16" height="16">
          </a>
        <?php endif; ?>
        <?php if ($hasX): ?>
          <a class="share-btn share-btn--x" href="<?= e($xUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="X">
            <img src="/assets/img/icon-x.svg" alt="" width="16" height="16">
          </a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</article>

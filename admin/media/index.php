<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
auth_require();

$kind = isset($_GET['kind']) ? strtolower(trim((string) $_GET['kind'])) : '';
if (!in_array($kind, ['image', 'audio', 'document', ''], true)) {
    $kind = '';
}
$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

$filters = [];
if ($kind !== '') {
    $filters['kind'] = $kind;
}
if ($q !== '') {
    $filters['q'] = $q;
}

$items = media_list($filters);
$flash = flash_get('success');
$error = flash_get('error');

$kindTitles = [
    'image' => 'Images',
    'audio' => 'Audio',
    'document' => 'Documents',
];
$kindLeads = [
    'image' => 'Image files in the media library.',
    'audio' => 'Audio files in the media library.',
    'document' => 'Document files in the media library.',
];

$adminPageTitle = $kind !== '' ? ($kindTitles[$kind] ?? 'Media') : 'Media library';
$adminSection = 'media';
$adminPanelTitle = 'Media';
$adminShowAdd = false;
$adminMediaPage = $kind !== '' ? $kind : 'all';
$useTable = $kind !== '';

require dirname(__DIR__, 2) . '/includes/partials/admin_shell_start.php';
?>
        <div class="admin-content__header">
          <div>
            <h1><?= e($kind !== '' ? ($kindTitles[$kind] ?? 'Media') : 'All media') ?></h1>
            <p class="admin-content__lead"><?= e($kind !== '' ? ($kindLeads[$kind] ?? '') : 'Images, audio, and documents for posts, galleries, and playlists.') ?></p>
          </div>
          <button type="button" class="tu-btn tu-btn--brand" data-media-upload-open<?= $kind !== '' ? ' data-media-kind="' . e($kind) . '"' : '' ?>>Upload media</button>
        </div>

        <?php if ($flash): ?>
          <div class="tu-alert tu-alert--success" role="status"><?= e($flash) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="tu-alert tu-alert--danger" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="media-filters">
          <?php
            $filtersNav = [
                '' => 'All',
                'image' => 'Images',
                'audio' => 'Audio',
                'document' => 'Documents',
            ];
            foreach ($filtersNav as $key => $label):
                $href = $key === '' ? '/admin/media/' : '/admin/media/?kind=' . rawurlencode($key);
                if ($q !== '') {
                    $href .= ($key === '' ? '?' : '&') . 'q=' . rawurlencode($q);
                }
                $active = $kind === $key;
          ?>
            <a class="tu-btn <?= $active ? 'tu-btn--brand is-active' : 'tu-btn--secondary' ?>" href="<?= e($href) ?>"><?= e($label) ?></a>
          <?php endforeach; ?>
          <form method="get" action="/admin/media/" style="display:flex;gap:8px;margin-left:auto;flex-wrap:wrap;">
            <?php if ($kind !== ''): ?>
              <input type="hidden" name="kind" value="<?= e($kind) ?>">
            <?php endif; ?>
            <input class="tu-input" type="search" name="q" value="<?= e($q) ?>" placeholder="Search media…" aria-label="Search media">
            <button class="tu-btn tu-btn--secondary" type="submit">Search</button>
          </form>
        </div>

        <?php if ($items === []): ?>
          <p class="tu-empty"><?= e($kind !== '' ? 'No ' . strtolower($kindTitles[$kind] ?? 'media') . ' yet.' : 'No media yet. Upload files to get started.') ?></p>
        <?php elseif ($useTable): ?>
          <div class="tu-card tu-table-wrap">
            <table class="tu-table">
              <thead>
                <tr>
                  <th scope="col">Name</th>
                  <th scope="col">Type</th>
                  <th scope="col">Size</th>
                  <th scope="col">Uploaded</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($items as $item): ?>
                  <?php $label = media_label($item); ?>
                  <tr
                    data-media-row
                    data-search="<?= e(strtolower($label . ' ' . (string) $item['original_name'])) ?>"
                  >
                    <th scope="row">
                      <a href="/admin/media/edit.php?id=<?= e((string) $item['id']) ?>"><?= e($label) ?></a>
                    </th>
                    <td><?= e((string) $item['mime']) ?></td>
                    <td><?= e(media_format_bytes((int) $item['size_bytes'])) ?></td>
                    <td><?= e((string) $item['created_at']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="media-grid">
            <?php foreach ($items as $item): ?>
              <?php
                $label = media_label($item);
                $isImage = (string) $item['kind'] === 'image';
              ?>
              <a
                class="media-grid__card"
                href="/admin/media/edit.php?id=<?= e((string) $item['id']) ?>"
                data-media-row
                data-search="<?= e(strtolower($label . ' ' . (string) $item['original_name'] . ' ' . (string) $item['kind'])) ?>"
              >
                <div class="media-grid__thumb">
                  <?php if ($isImage): ?>
                    <img src="<?= e(media_public_url($item)) ?>" alt="<?= e((string) ($item['alt_text'] ?? '')) ?>">
                  <?php else: ?>
                    <span class="media-grid__thumb-icon"><?= e(strtoupper((string) $item['kind'])) ?></span>
                  <?php endif; ?>
                </div>
                <div class="media-grid__meta">
                  <p class="media-grid__title"><?= e($label) ?></p>
                  <p class="media-grid__sub"><?= e((string) $item['kind']) ?> · <?= e(media_format_bytes((int) $item['size_bytes'])) ?></p>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
<?php
require dirname(__DIR__, 2) . '/includes/partials/admin_shell_end.php';

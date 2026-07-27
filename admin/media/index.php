<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
auth_require();

$kind = isset($_GET['kind']) ? strtolower(trim((string) $_GET['kind'])) : '';
if (!in_array($kind, ['image', 'audio', 'document', ''], true)) {
    $kind = '';
}
$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$viewRaw = isset($_GET['view']) ? strtolower(trim((string) $_GET['view'])) : '';
$view = in_array($viewRaw, ['cards', 'table'], true) ? $viewRaw : 'cards';
$viewHasParam = $viewRaw === 'cards' || $viewRaw === 'table';

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

/**
 * Build a media index URL, preserving kind / search / view.
 *
 * @param array{kind?: string, q?: string, view?: string} $overrides
 */
$mediaIndexUrl = static function (array $overrides = []) use ($kind, $q, $view): string {
    $nextKind = array_key_exists('kind', $overrides) ? (string) $overrides['kind'] : $kind;
    $nextQ = array_key_exists('q', $overrides) ? (string) $overrides['q'] : $q;
    $nextView = array_key_exists('view', $overrides) ? (string) $overrides['view'] : $view;

    $query = [];
    if ($nextKind !== '') {
        $query['kind'] = $nextKind;
    }
    if ($nextQ !== '') {
        $query['q'] = $nextQ;
    }
    // Persist non-default (table) in the URL; cards is the default.
    if ($nextView === 'table') {
        $query['view'] = 'table';
    }

    $qs = http_build_query($query);
    return '/admin/media/' . ($qs !== '' ? '?' . $qs : '');
};

$adminPageTitle = $kind !== '' ? ($kindTitles[$kind] ?? 'Media') : 'Media library';
$adminSection = 'media';
$adminPanelTitle = 'Media';
$adminShowAdd = false;
$adminMediaPage = $kind !== '' ? $kind : 'all';
$useTable = $view === 'table';

require dirname(__DIR__, 2) . '/includes/partials/admin_shell_start.php';
?>
        <?php if (!$viewHasParam): ?>
          <script>
            (function () {
              try {
                var stored = localStorage.getItem("ncst-admin-media-view");
                if (stored !== "table") return;
                var params = new URLSearchParams(window.location.search);
                params.set("view", "table");
                var qs = params.toString();
                window.location.replace(window.location.pathname + (qs ? "?" + qs : ""));
              } catch (err) { /* ignore */ }
            })();
          </script>
        <?php endif; ?>
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
                $href = $mediaIndexUrl(['kind' => $key]);
                $active = $kind === $key;
          ?>
            <a class="tu-btn <?= $active ? 'tu-btn--brand is-active' : 'tu-btn--secondary' ?>" href="<?= e($href) ?>"><?= e($label) ?></a>
          <?php endforeach; ?>
          <div class="media-filters__tools">
            <div class="media-view-toggle" role="group" aria-label="Media layout">
              <a
                class="tu-btn tu-btn--<?= $view === 'cards' ? 'brand is-active' : 'secondary' ?>"
                href="<?= e($mediaIndexUrl(['view' => 'cards'])) ?>"
                data-media-view="cards"
                <?= $view === 'cards' ? 'aria-current="true"' : '' ?>
              >Cards</a>
              <a
                class="tu-btn tu-btn--<?= $view === 'table' ? 'brand is-active' : 'secondary' ?>"
                href="<?= e($mediaIndexUrl(['view' => 'table'])) ?>"
                data-media-view="table"
                <?= $view === 'table' ? 'aria-current="true"' : '' ?>
              >Table</a>
            </div>
            <form method="get" action="/admin/media/" class="media-filters__search">
              <?php if ($kind !== ''): ?>
                <input type="hidden" name="kind" value="<?= e($kind) ?>">
              <?php endif; ?>
              <?php if ($view === 'table'): ?>
                <input type="hidden" name="view" value="table">
              <?php endif; ?>
              <input class="tu-input" type="search" name="q" value="<?= e($q) ?>" placeholder="Search media…" aria-label="Search media">
              <button class="tu-btn tu-btn--secondary" type="submit">Search</button>
            </form>
          </div>
        </div>

        <?php if ($items === []): ?>
          <p class="tu-empty"><?= e($kind !== '' ? 'No ' . strtolower($kindTitles[$kind] ?? 'media') . ' yet.' : 'No media yet. Upload files to get started.') ?></p>
        <?php elseif ($useTable): ?>
          <div class="tu-card tu-table-wrap">
            <table
              class="tu-table admin-data-table"
              data-admin-table
              data-admin-table-search-label="Filter results"
              data-admin-table-empty-message="No media match this filter."
            >
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
                    data-search="<?= e(strtolower($label . ' ' . (string) $item['original_name'] . ' ' . (string) $item['kind'])) ?>"
                  >
                    <th scope="row">
                      <a href="/admin/media/edit.php?id=<?= e((string) $item['id']) ?>"><?= e($label) ?></a>
                    </th>
                    <td><?= e((string) $item['mime']) ?></td>
                    <td data-sort-value="<?= e((string) (int) $item['size_bytes']) ?>"><?= e(media_format_bytes((int) $item['size_bytes'])) ?></td>
                    <td data-sort-value="<?= e((string) $item['created_at']) ?>"><?= e((string) $item['created_at']) ?></td>
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
        <script>
          (function () {
            document.querySelectorAll("[data-media-view]").forEach(function (link) {
              link.addEventListener("click", function () {
                try {
                  localStorage.setItem("ncst-admin-media-view", link.getAttribute("data-media-view") || "cards");
                } catch (err) { /* ignore */ }
              });
            });
          })();
        </script>
<?php
require dirname(__DIR__, 2) . '/includes/partials/admin_shell_end.php';

<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
auth_require();

$flash = flash_get('success');
$error = flash_get('error');
$galleries = galleries_list();

$adminPageTitle = 'Galleries';
$adminSection = 'media';
$adminPanelTitle = 'Media';
$adminShowAdd = false;
$adminMediaPage = 'galleries';

require dirname(__DIR__, 2) . '/includes/partials/admin_shell_start.php';
?>
        <div class="admin-content__header">
          <div>
            <h1>Galleries</h1>
            <p class="admin-content__lead">Admin-only ordered image collections. No public gallery pages yet.</p>
          </div>
          <a class="tu-btn tu-btn--brand" href="/admin/media/gallery_edit.php">New gallery</a>
        </div>

        <?php if ($flash): ?>
          <div class="tu-alert tu-alert--success" role="status"><?= e($flash) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="tu-alert tu-alert--danger" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($galleries === []): ?>
          <p class="tu-empty">No galleries yet.</p>
        <?php else: ?>
          <div class="tu-card tu-table-wrap">
            <table
              class="tu-table admin-data-table"
              data-admin-table
              data-admin-table-search-label="Filter galleries"
              data-admin-table-empty-message="No galleries match your search."
            >
              <thead>
                <tr>
                  <th scope="col">Title</th>
                  <th scope="col">Items</th>
                  <th scope="col">Updated</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($galleries as $g): ?>
                  <?php
                    $searchBlob = strtolower(implode(' ', [
                        (string) $g['title'],
                        (string) ($g['item_count'] ?? 0),
                        (string) $g['updated_at'],
                    ]));
                  ?>
                  <tr data-search="<?= e($searchBlob) ?>">
                    <th scope="row">
                      <a href="/admin/media/gallery_edit.php?id=<?= e((string) $g['id']) ?>"><?= e((string) $g['title']) ?></a>
                    </th>
                    <td data-sort-value="<?= e((string) (int) ($g['item_count'] ?? 0)) ?>"><?= e((string) ($g['item_count'] ?? 0)) ?></td>
                    <td data-sort-value="<?= e((string) $g['updated_at']) ?>"><?= e((string) $g['updated_at']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
<?php
require dirname(__DIR__, 2) . '/includes/partials/admin_shell_end.php';

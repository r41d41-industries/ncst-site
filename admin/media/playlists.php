<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
auth_require();

$flash = flash_get('success');
$error = flash_get('error');
$playlists = playlists_list();

$adminPageTitle = 'Playlists';
$adminSection = 'media';
$adminPanelTitle = 'Media';
$adminShowAdd = false;
$adminMediaPage = 'playlists';

require dirname(__DIR__, 2) . '/includes/partials/admin_shell_start.php';
?>
        <div class="admin-content__header">
          <div>
            <h1>Playlists</h1>
            <p class="admin-content__lead">Admin-only ordered audio collections. No public playlist pages yet.</p>
          </div>
          <a class="tu-btn tu-btn--brand" href="/admin/media/playlist_edit.php">New playlist</a>
        </div>

        <?php if ($flash): ?>
          <div class="tu-alert tu-alert--success" role="status"><?= e($flash) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="tu-alert tu-alert--danger" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($playlists === []): ?>
          <p class="tu-empty">No playlists yet.</p>
        <?php else: ?>
          <div class="tu-card tu-table-wrap">
            <table
              class="tu-table admin-data-table"
              data-admin-table
              data-admin-table-search-label="Filter playlists"
              data-admin-table-empty-message="No playlists match your search."
            >
              <thead>
                <tr>
                  <th scope="col">Title</th>
                  <th scope="col">Items</th>
                  <th scope="col">Updated</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($playlists as $p): ?>
                  <?php
                    $searchBlob = strtolower(implode(' ', [
                        (string) $p['title'],
                        (string) ($p['item_count'] ?? 0),
                        (string) $p['updated_at'],
                    ]));
                  ?>
                  <tr data-search="<?= e($searchBlob) ?>">
                    <th scope="row">
                      <a href="/admin/media/playlist_edit.php?id=<?= e((string) $p['id']) ?>"><?= e((string) $p['title']) ?></a>
                    </th>
                    <td data-sort-value="<?= e((string) (int) ($p['item_count'] ?? 0)) ?>"><?= e((string) ($p['item_count'] ?? 0)) ?></td>
                    <td data-sort-value="<?= e((string) $p['updated_at']) ?>"><?= e((string) $p['updated_at']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
<?php
require dirname(__DIR__, 2) . '/includes/partials/admin_shell_end.php';

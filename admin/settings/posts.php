<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
auth_require();

$categories = categories_all();
$flash = flash_get('success');
$error = flash_get('error');

$adminPageTitle = 'Settings — Posts';
$adminSection = 'settings';
$adminPanelTitle = 'Settings';
$adminShowAdd = false;
$adminSettingsPage = 'posts';

require dirname(__DIR__, 2) . '/includes/partials/admin_shell_start.php';
?>
        <div class="admin-content__header">
          <div>
            <h1>Categories</h1>
            <p class="admin-content__lead">Manage feed categories, templates, and colors used on filters and badges.</p>
          </div>
          <div class="tu-btn-row">
            <a class="tu-btn tu-btn--brand" href="/admin/settings/category_edit.php">Add category</a>
          </div>
        </div>

        <?php if ($flash): ?>
          <div class="tu-alert tu-alert--success" role="status"><?= e($flash) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="tu-alert tu-alert--danger" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="tu-table-wrap">
          <table
            class="tu-table admin-data-table"
            data-admin-table
            data-admin-table-search-label="Filter categories"
            data-admin-table-empty-message="No categories match your search."
          >
            <thead>
              <tr>
                <th scope="col">Name</th>
                <th scope="col">Slug</th>
                <th scope="col">Template</th>
                <th scope="col">Color</th>
                <th scope="col">Filter</th>
                <th scope="col" data-sortable="false"><span class="admin-sr-only">Actions</span></th>
              </tr>
            </thead>
            <tbody>
              <?php if ($categories === []): ?>
                <tr data-admin-empty-row>
                  <td colspan="6">No categories yet.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($categories as $cat): ?>
                  <?php
                    $color = cs_normalize_hex_color((string) ($cat['color'] ?? '')) ?? '#f7931e';
                    $id = (int) $cat['id'];
                    $searchBlob = strtolower(implode(' ', [
                        (string) $cat['name'],
                        (string) $cat['slug'],
                        (string) $cat['template'],
                        $color,
                        !empty($cat['is_filter']) ? 'yes' : 'no',
                    ]));
                  ?>
                  <tr data-search="<?= e($searchBlob) ?>">
                    <td><?= e((string) $cat['name']) ?></td>
                    <td><code><?= e((string) $cat['slug']) ?></code></td>
                    <td><?= e((string) $cat['template']) ?></td>
                    <td data-sort-value="<?= e($color) ?>">
                      <span class="admin-color-swatch" style="--swatch: <?= e($color) ?>" title="<?= e($color) ?>">
                        <span class="admin-color-swatch__chip" aria-hidden="true"></span>
                        <?= e($color) ?>
                      </span>
                    </td>
                    <td data-sort-value="<?= !empty($cat['is_filter']) ? '1' : '0' ?>"><?= !empty($cat['is_filter']) ? 'Yes' : 'No' ?></td>
                    <td>
                      <a class="tu-btn tu-btn--secondary" href="/admin/settings/category_edit.php?id=<?= e((string) $id) ?>">Edit</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
<?php
require dirname(__DIR__, 2) . '/includes/partials/admin_shell_end.php';

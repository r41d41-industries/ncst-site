<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
auth_require();

$codes = shortcodes_all();
$flash = flash_get('success');
$error = flash_get('error');

$adminPageTitle = 'Settings — Shortcodes';
$adminSection = 'settings';
$adminPanelTitle = 'Settings';
$adminShowAdd = false;
$adminSettingsPage = 'shortcodes';

require dirname(__DIR__, 2) . '/includes/partials/admin_shell_start.php';
?>
        <div class="admin-content__header">
          <div>
            <h1>Shortcodes</h1>
            <p class="admin-content__lead">Key/value replacements for article bodies. Use <code>[key]</code> in the editor; <code>__NOW__</code> becomes the current date/time when the page renders.</p>
          </div>
          <div class="tu-btn-row">
            <a class="tu-btn tu-btn--secondary" href="/admin/settings/posts.php">Categories</a>
            <a class="tu-btn tu-btn--brand" href="/admin/settings/shortcode_edit.php">Add shortcode</a>
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
            data-admin-table-search-label="Filter shortcodes"
            data-admin-table-empty-message="No shortcodes match your search."
          >
            <thead>
              <tr>
                <th scope="col">Key</th>
                <th scope="col">In articles</th>
                <th scope="col">Replacement</th>
                <th scope="col" data-sortable="false"><span class="admin-sr-only">Actions</span></th>
              </tr>
            </thead>
            <tbody>
              <?php if ($codes === []): ?>
                <tr data-admin-empty-row><td colspan="4">No shortcodes yet.</td></tr>
              <?php else: ?>
                <?php foreach ($codes as $sc): ?>
                  <?php
                    $searchBlob = strtolower(implode(' ', [
                        (string) $sc['code'],
                        (string) $sc['replacement'],
                    ]));
                  ?>
                  <tr data-search="<?= e($searchBlob) ?>">
                    <td><code><?= e((string) $sc['code']) ?></code></td>
                    <td><code>[<?= e((string) $sc['code']) ?>]</code></td>
                    <td><?= e((string) $sc['replacement']) ?></td>
                    <td>
                      <a class="tu-btn tu-btn--secondary" href="/admin/settings/shortcode_edit.php?id=<?= e((string) $sc['id']) ?>">Edit</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
<?php
require dirname(__DIR__, 2) . '/includes/partials/admin_shell_end.php';

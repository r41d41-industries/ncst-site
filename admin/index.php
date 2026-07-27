<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
auth_require();

$allowedStatuses = ['draft', 'published', 'trash'];
$allowedTypes = ['incident', 'article'];
$adminStatusFilter = isset($_GET['status']) ? strtolower(trim((string) $_GET['status'])) : null;
if ($adminStatusFilter !== null && !in_array($adminStatusFilter, $allowedStatuses, true)) {
    $adminStatusFilter = null;
}

$adminTypeFilter = isset($_GET['type']) ? strtolower(trim((string) $_GET['type'])) : null;
if ($adminTypeFilter === 'all' || $adminTypeFilter === '') {
    $adminTypeFilter = null;
}
if ($adminTypeFilter !== null && !in_array($adminTypeFilter, $allowedTypes, true)) {
    $adminTypeFilter = null;
}

$adminCategoryFilter = isset($_GET['category']) ? strtoupper(trim((string) $_GET['category'])) : null;
if ($adminCategoryFilter === '' || $adminCategoryFilter === 'ALL') {
    $adminCategoryFilter = null;
}
// Status views own the list; don't combine with type/category in the URL filters for nav simplicity.
if ($adminStatusFilter !== null) {
    $adminCategoryFilter = null;
    $adminTypeFilter = null;
}
// Type and category are mutually exclusive top-level filters.
if ($adminTypeFilter !== null) {
    $adminCategoryFilter = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $adminStatusFilter === 'trash') {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'empty_trash' && isset($_POST['confirm'])) {
        $deleted = posts_empty_trash();
        flash_set(
            'success',
            $deleted === 0
                ? 'Trash was already empty.'
                : ('Permanently deleted ' . $deleted . ' post' . ($deleted === 1 ? '' : 's') . '.')
        );
        redirect('/admin/?status=trash');
    }
}

$posts = posts_list_all($adminCategoryFilter, $adminStatusFilter, $adminTypeFilter);
$flash = flash_get('success');
$error = flash_get('error');

$statusTitles = [
    'draft' => 'Drafts',
    'published' => 'Published',
    'trash' => 'Trash',
];
$typeTitles = [
    'incident' => 'Incidents',
    'article' => 'Articles',
];

$adminPageTitle = $adminStatusFilter !== null
    ? ('Posts — ' . $statusTitles[$adminStatusFilter])
    : ($adminTypeFilter !== null
        ? ('Posts — ' . $typeTitles[$adminTypeFilter])
        : ($adminCategoryFilter !== null ? ('Posts — ' . $adminCategoryFilter) : 'Posts'));
$adminSection = 'posts';
$adminPanelTitle = 'Posts';
$adminShowAdd = true;

require dirname(__DIR__) . '/includes/partials/admin_shell_start.php';

$heading = 'Posts';
$lead = 'Manage scanner feed posts. Use the sidebar search to filter this list.';
if ($adminStatusFilter === 'draft') {
    $heading = 'Drafts';
    $lead = 'Unpublished posts. Publish from the edit screen when ready.';
} elseif ($adminStatusFilter === 'published') {
    $heading = 'Published';
    $lead = 'Posts currently visible on the public feed.';
} elseif ($adminStatusFilter === 'trash') {
    $heading = 'Trash';
    $lead = 'Trashed posts can be restored or permanently deleted.';
} elseif ($adminTypeFilter === 'incident') {
    $heading = 'All Incidents';
    $lead = 'Incident posts only. Use the sidebar to switch to articles or view all posts.';
} elseif ($adminTypeFilter === 'article') {
    $heading = 'All Articles';
    $lead = 'News and weather article posts only. Use the sidebar to switch to incidents or view all posts.';
} elseif ($adminCategoryFilter !== null) {
    $heading = $adminCategoryFilter . ' posts';
    $lead = 'Showing posts in this category. Use the sidebar to switch categories or view all posts.';
}

$emptyMessage = 'No posts yet. Create your first post to get started.';
if ($adminStatusFilter === 'draft') {
    $emptyMessage = 'No draft posts.';
} elseif ($adminStatusFilter === 'published') {
    $emptyMessage = 'No published posts.';
} elseif ($adminStatusFilter === 'trash') {
    $emptyMessage = 'Trash is empty.';
} elseif ($adminTypeFilter === 'incident') {
    $emptyMessage = 'No incident posts.';
} elseif ($adminTypeFilter === 'article') {
    $emptyMessage = 'No article posts.';
} elseif ($adminCategoryFilter !== null) {
    $emptyMessage = 'No posts in this category.';
}
?>
        <div class="admin-content__header">
          <div>
            <h1><?= e($heading) ?></h1>
            <p class="admin-content__lead"><?= e($lead) ?></p>
          </div>
          <div class="tu-btn-row">
            <?php if ($adminStatusFilter === 'trash'): ?>
              <form method="post" action="/admin/?status=trash" onsubmit="return confirm('Permanently delete all posts in Trash? This cannot be undone.');">
                <input type="hidden" name="action" value="empty_trash">
                <input type="hidden" name="confirm" value="1">
                <button class="tu-btn tu-btn--danger" type="submit"<?= $posts === [] ? ' disabled' : '' ?>>Empty Trash</button>
              </form>
            <?php else: ?>
              <a class="tu-btn tu-btn--secondary" href="/admin/post_incident.php">New incident</a>
              <a class="tu-btn tu-btn--brand" href="/admin/post_article.php">New article</a>
            <?php endif; ?>
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
            data-admin-table-search="external"
            data-admin-table-search-input="#admin-panel-search"
            data-admin-table-empty-message="No posts match your search."
          >
            <thead>
              <tr>
                <th scope="col">ID</th>
                <th scope="col">Title</th>
                <th scope="col">Category</th>
                <th scope="col">Status</th>
                <th scope="col">Updated</th>
                <th scope="col" data-sortable="false"><span class="admin-sr-only">Actions</span></th>
              </tr>
            </thead>
            <tbody>
              <?php if ($posts === []): ?>
                <tr data-admin-empty-row>
                  <td colspan="6" class="tu-empty"><?= e($emptyMessage) ?></td>
                </tr>
              <?php else: ?>
                <?php foreach ($posts as $post): ?>
                  <?php
                  $trashed = posts_is_trashed($post);
                  $statusLabel = $trashed ? 'trash' : (!empty($post['published']) ? 'published' : 'draft');
                  $searchBlob = implode(' ', [
                      (string) $post['id'],
                      (string) $post['title'],
                      (string) $post['category'],
                      $statusLabel,
                      (string) $post['updated_at'],
                  ]);
                  ?>
                  <tr data-post-row data-search="<?= e(strtolower($searchBlob)) ?>">
                    <td data-sort-value="<?= e((string) $post['id']) ?>"><?= e((string) $post['id']) ?></td>
                    <th scope="row"><?= e((string) $post['title']) ?></th>
                    <td><?= e((string) $post['category']) ?></td>
                    <td data-sort-value="<?= e($statusLabel) ?>">
                      <?php if ($trashed): ?>
                        <span class="tu-badge tu-badge--gray">Trash</span>
                      <?php elseif (!empty($post['published'])): ?>
                        <span class="tu-badge tu-badge--success">Published</span>
                      <?php else: ?>
                        <span class="tu-badge tu-badge--gray">Draft</span>
                      <?php endif; ?>
                    </td>
                    <td data-sort-value="<?= e((string) $post['updated_at']) ?>"><?= e((string) $post['updated_at']) ?></td>
                    <td style="white-space:nowrap;">
                      <?php if ($trashed): ?>
                        <a href="/admin/post_delete.php?id=<?= e((string) $post['id']) ?>&amp;action=restore">Restore</a>
                        <span aria-hidden="true"> · </span>
                        <a href="/admin/post_delete.php?id=<?= e((string) $post['id']) ?>&amp;action=delete" style="color:var(--tu-fg-danger-strong);">Delete permanently</a>
                      <?php else: ?>
                        <a href="<?= e(posts_edit_url($post)) ?>">Edit</a>
                        <span aria-hidden="true"> · </span>
                        <a href="/admin/post_delete.php?id=<?= e((string) $post['id']) ?>" style="color:var(--tu-fg-danger-strong);">Trash</a>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
<?php
require dirname(__DIR__) . '/includes/partials/admin_shell_end.php';

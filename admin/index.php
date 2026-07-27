<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
auth_require();

$allowedStatuses = ['draft', 'published', 'trash'];
$adminStatusFilter = isset($_GET['status']) ? strtolower(trim((string) $_GET['status'])) : null;
if ($adminStatusFilter !== null && !in_array($adminStatusFilter, $allowedStatuses, true)) {
    $adminStatusFilter = null;
}

$adminCategoryFilter = isset($_GET['category']) ? strtoupper(trim((string) $_GET['category'])) : null;
if ($adminCategoryFilter === '' || $adminCategoryFilter === 'ALL') {
    $adminCategoryFilter = null;
}
// Status views own the list; don't combine with category in the URL filters for nav simplicity.
if ($adminStatusFilter !== null) {
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

$posts = posts_list_all($adminCategoryFilter, $adminStatusFilter);
$flash = flash_get('success');
$error = flash_get('error');

$statusTitles = [
    'draft' => 'Drafts',
    'published' => 'Published',
    'trash' => 'Trash',
];

$adminPageTitle = $adminStatusFilter !== null
    ? ('Posts — ' . $statusTitles[$adminStatusFilter])
    : ($adminCategoryFilter !== null ? ('Posts — ' . $adminCategoryFilter) : 'Posts');
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
              <a class="tu-btn tu-btn--brand" href="/admin/post_edit.php">New post</a>
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
          <table class="tu-table">
            <thead>
              <tr>
                <th scope="col">ID</th>
                <th scope="col">Title</th>
                <th scope="col">Category</th>
                <th scope="col">Status</th>
                <th scope="col">Updated</th>
                <th scope="col"><span class="admin-sr-only">Actions</span></th>
              </tr>
            </thead>
            <tbody>
              <?php if ($posts === []): ?>
                <tr>
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
                    <td><?= e((string) $post['id']) ?></td>
                    <th scope="row"><?= e((string) $post['title']) ?></th>
                    <td><?= e((string) $post['category']) ?></td>
                    <td>
                      <?php if ($trashed): ?>
                        <span class="tu-badge tu-badge--gray">Trash</span>
                      <?php elseif (!empty($post['published'])): ?>
                        <span class="tu-badge tu-badge--success">Published</span>
                      <?php else: ?>
                        <span class="tu-badge tu-badge--gray">Draft</span>
                      <?php endif; ?>
                    </td>
                    <td><?= e((string) $post['updated_at']) ?></td>
                    <td style="white-space:nowrap;">
                      <?php if ($trashed): ?>
                        <a href="/admin/post_delete.php?id=<?= e((string) $post['id']) ?>&amp;action=restore">Restore</a>
                        <span aria-hidden="true"> · </span>
                        <a href="/admin/post_delete.php?id=<?= e((string) $post['id']) ?>&amp;action=delete" style="color:var(--tu-fg-danger-strong);">Delete permanently</a>
                      <?php else: ?>
                        <a href="/admin/post_edit.php?id=<?= e((string) $post['id']) ?>">Edit</a>
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
        <p id="admin-posts-empty-filter" class="tu-empty" hidden>No posts match your search.</p>
<?php
require dirname(__DIR__) . '/includes/partials/admin_shell_end.php';

<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
auth_require();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$post = $id > 0 ? posts_find($id) : null;

if ($post === null) {
    flash_set('error', 'Post not found.');
    redirect('/admin/');
}

$isTrashed = posts_is_trashed($post);
$action = (string) ($_GET['action'] ?? ($isTrashed ? 'delete' : 'trash'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirm = isset($_POST['confirm']);
    $postedAction = (string) ($_POST['action'] ?? $action);

    if ($confirm) {
        if ($postedAction === 'restore') {
            posts_restore($id);
            flash_set('success', 'Post restored.');
            redirect('/admin/?status=draft');
        }
        if ($postedAction === 'delete') {
            posts_delete($id);
            flash_set('success', 'Post permanently deleted.');
            redirect('/admin/?status=trash');
        }
        // Default: move to trash
        posts_trash($id);
        flash_set('success', 'Post moved to Trash.');
        redirect('/admin/?status=trash');
    }
    redirect('/admin/');
}

$adminPageTitle = $action === 'restore' ? 'Restore Post' : ($action === 'delete' && $isTrashed ? 'Delete Post' : 'Trash Post');
$adminSection = 'posts';
$adminPanelTitle = 'Posts';
$adminShowAdd = true;
$adminStatusFilter = $isTrashed ? 'trash' : null;

require dirname(__DIR__) . '/includes/partials/admin_shell_start.php';

if ($action === 'restore') {
    $heading = 'Restore post';
    $lead = 'Move this post out of Trash.';
    $warning = 'Restore <strong>' . e((string) $post['title']) . '</strong>? It will return as a draft or published post based on its previous setting.';
    $button = 'Restore';
    $buttonClass = 'tu-btn tu-btn--brand';
} elseif ($action === 'delete' && $isTrashed) {
    $heading = 'Delete permanently';
    $lead = 'This action cannot be undone.';
    $warning = 'Permanently delete <strong>' . e((string) $post['title']) . '</strong>?';
    $button = 'Delete permanently';
    $buttonClass = 'tu-btn tu-btn--danger';
} else {
    $heading = 'Move to Trash';
    $lead = 'The post can be restored later from Trash.';
    $warning = 'Move <strong>' . e((string) $post['title']) . '</strong> to Trash?';
    $button = 'Move to Trash';
    $buttonClass = 'tu-btn tu-btn--danger';
    $action = 'trash';
}
?>
        <div class="admin-content__header">
          <div>
            <h1><?= e($heading) ?></h1>
            <p class="admin-content__lead"><?= e($lead) ?></p>
          </div>
        </div>

        <div class="tu-card" style="max-width:520px;">
          <div class="tu-alert tu-alert--warning" role="status">
            <?= $warning ?>
          </div>
          <form method="post" action="">
            <input type="hidden" name="action" value="<?= e($action) ?>">
            <div class="tu-actions">
              <button class="<?= e($buttonClass) ?>" type="submit" name="confirm" value="1"><?= e($button) ?></button>
              <a class="tu-btn tu-btn--secondary" href="<?= $isTrashed ? '/admin/?status=trash' : '/admin/' ?>">Cancel</a>
            </div>
          </form>
        </div>
<?php
require dirname(__DIR__) . '/includes/partials/admin_shell_end.php';

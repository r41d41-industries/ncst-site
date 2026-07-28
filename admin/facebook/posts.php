<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
auth_require();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        flash_set('error', 'Invalid request. Please try again.');
        redirect('/admin/facebook/posts.php');
    }

    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'sync') {
            $result = facebook_sync_posts(20);
            $newLabel = $result['inserted'] === 1 ? '1 new' : ($result['inserted'] . ' new');
            $updatedLabel = $result['updated'] === 1 ? '1 updated' : ($result['updated'] . ' updated');
            $msg = 'Synced ' . $result['fetched'] . ' post' . ($result['fetched'] === 1 ? '' : 's')
                . ' with message text (' . $newLabel . ', ' . $updatedLabel . ').';
            if ($result['comments_posts'] > 0) {
                $msg .= ' Comments: ' . $result['comments_fetched'] . ' fetched across '
                    . $result['comments_posts'] . ' converted post'
                    . ($result['comments_posts'] === 1 ? '' : 's') . '.';
                if ($result['comments_errors'] > 0) {
                    $msg .= ' (' . $result['comments_errors'] . ' comment sync error'
                        . ($result['comments_errors'] === 1 ? '' : 's') . '.)';
                }
            }
            flash_set('success', $msg);
        } elseif ($action === 'mark_seen') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0 || !facebook_posts_mark_seen($id)) {
                flash_set('error', 'Post not found.');
            } else {
                flash_set('success', 'Marked post as seen.');
            }
        } elseif ($action === 'mark_all_seen') {
            $count = facebook_posts_mark_all_seen();
            flash_set(
                'success',
                $count === 0
                    ? 'No new posts to mark.'
                    : ('Marked ' . $count . ' post' . ($count === 1 ? '' : 's') . ' as seen.')
            );
        } elseif ($action === 'convert') {
            $id = (int) ($_POST['id'] ?? 0);
            $category = strtoupper(trim((string) ($_POST['category'] ?? '')));
            $postId = facebook_convert_to_post($id, $category);
            $cmsPost = posts_find($postId);
            $editUrl = $cmsPost !== null ? posts_edit_url($cmsPost) : ('/admin/post_edit.php?id=' . $postId);
            $status = facebook_import_status();
            if ($status === 'published_with_comments') {
                flash_set(
                    'success',
                    'Published feed post #' . $postId . ' (Page comments applied). <a href="' . e($editUrl) . '">Edit post</a>'
                );
            } elseif ($status === 'published') {
                flash_set('success', 'Published feed post #' . $postId . '. <a href="' . e($editUrl) . '">Edit post</a>');
            } else {
                flash_set('success', 'Created draft post #' . $postId . '. <a href="' . e($editUrl) . '">Edit draft</a>');
            }
        } else {
            flash_set('error', 'Unknown action.');
        }
    } catch (Throwable $e) {
        flash_set('error', $e->getMessage());
    }

    redirect('/admin/facebook/posts.php');
}

$posts = facebook_posts_list();
$categoryOptions = facebook_convert_category_options();
$flash = flash_get('success');
$error = flash_get('error');
$newCount = 0;
foreach ($posts as $row) {
    if (!empty($row['is_new']) && empty($row['cs_post_id'])) {
        $newCount++;
    }
}

$adminPageTitle = 'Posts — Facebook';
$adminSection = 'posts';
$adminPanelTitle = 'Posts';
$adminShowAdd = true;
$adminPostsPage = 'facebook';

require dirname(__DIR__, 2) . '/includes/partials/admin_shell_start.php';
?>
        <div class="admin-content__header">
          <div>
            <h1>Facebook</h1>
            <p class="admin-content__lead">Synced Page posts with message text. Assign a topic and create a feed post. Suggested topics come from hashtag mappings.</p>
          </div>
          <div class="tu-btn-row">
            <?php if ($newCount > 0): ?>
              <form method="post" action="" style="display:inline;">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="mark_all_seen">
                <button type="submit" class="tu-btn tu-btn--secondary">Mark all seen</button>
              </form>
            <?php endif; ?>
            <form method="post" action="" style="display:inline;">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="sync">
              <button type="submit" class="tu-btn tu-btn--brand">Sync Now</button>
            </form>
          </div>
        </div>

        <?php if ($flash): ?>
          <div class="tu-alert tu-alert--success" role="status"><?= $flash ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="tu-alert tu-alert--danger" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="tu-table-wrap">
          <table
            class="tu-table admin-data-table"
            data-admin-table
            data-admin-table-search-label="Filter Facebook posts"
            data-admin-table-empty-message="No Facebook posts match your search."
          >
            <thead>
              <tr>
                <th scope="col">Status</th>
                <th scope="col">Created</th>
                <th scope="col">Message</th>
                <th scope="col">Suggested</th>
                <th scope="col">Type</th>
                <th scope="col">Link</th>
                <th scope="col" data-sortable="false">Convert</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($posts === []): ?>
                <tr data-admin-empty-row>
                  <td colspan="7" class="tu-empty">No Facebook posts with message text yet. Click Sync Now to fetch the latest posts.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($posts as $post): ?>
                  <?php
                    $isNew = !empty($post['is_new']);
                    $csPostId = isset($post['cs_post_id']) && $post['cs_post_id'] !== null && $post['cs_post_id'] !== ''
                        ? (int) $post['cs_post_id']
                        : 0;
                    $converted = $csPostId > 0;
                    $message = (string) ($post['message'] ?? '');
                    $excerpt = facebook_excerpt($message);
                    $created = (string) ($post['fb_created_time'] ?? '');
                    $statusType = (string) ($post['status_type'] ?? '');
                    $permalink = (string) ($post['permalink_url'] ?? '');
                    $suggested = facebook_suggest_category($message);
                    $cmsPost = $converted ? posts_find($csPostId) : null;
                    $editUrl = $cmsPost !== null ? posts_edit_url($cmsPost) : null;
                    $searchBlob = strtolower(implode(' ', [
                        $isNew ? 'new' : 'seen',
                        $converted ? 'converted' : 'pending',
                        $created,
                        $message,
                        $statusType,
                        (string) ($suggested ?? ''),
                        (string) ($post['fb_post_id'] ?? ''),
                    ]));
                  ?>
                  <tr data-search="<?= e($searchBlob) ?>">
                    <td data-sort-value="<?= $converted ? '2' : ($isNew ? '0' : '1') ?>">
                      <?php if ($converted): ?>
                        <span class="tu-badge tu-badge--success">Converted</span>
                      <?php elseif ($isNew): ?>
                        <span class="tu-badge tu-badge--warning">New</span>
                      <?php else: ?>
                        <span class="tu-badge tu-badge--gray">Seen</span>
                      <?php endif; ?>
                    </td>
                    <td data-sort-value="<?= e($created) ?>"><?= e($created !== '' ? $created : '—') ?></td>
                    <td><?= e($excerpt) ?></td>
                    <td>
                      <?php if ($suggested !== null): ?>
                        <span class="tu-badge tu-badge--gray"><?= e($suggested) ?></span>
                      <?php else: ?>
                        —
                      <?php endif; ?>
                    </td>
                    <td><?= e($statusType !== '' ? $statusType : '—') ?></td>
                    <td>
                      <?php if ($permalink !== ''): ?>
                        <a href="<?= e($permalink) ?>" target="_blank" rel="noopener noreferrer">View</a>
                      <?php else: ?>
                        —
                      <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;">
                      <?php if ($converted): ?>
                        <?php if ($editUrl !== null): ?>
                          <a href="<?= e($editUrl) ?>">Post #<?= e((string) $csPostId) ?></a>
                        <?php else: ?>
                          Post #<?= e((string) $csPostId) ?>
                        <?php endif; ?>
                      <?php else: ?>
                        <form method="post" action="" style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
                          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                          <input type="hidden" name="action" value="convert">
                          <input type="hidden" name="id" value="<?= e((string) $post['id']) ?>">
                          <label class="admin-sr-only" for="fb-cat-<?= e((string) $post['id']) ?>">Category</label>
                          <select id="fb-cat-<?= e((string) $post['id']) ?>" name="category" required>
                            <option value="">Topic…</option>
                            <?php foreach ($categoryOptions as $opt): ?>
                              <option
                                value="<?= e($opt['slug']) ?>"
                                <?= $suggested !== null && $suggested === $opt['slug'] ? ' selected' : '' ?>
                              >
                                <?= e($opt['name']) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                          <button type="submit" class="tu-btn tu-btn--brand">Create post</button>
                          <?php if ($isNew): ?>
                            <button
                              type="submit"
                              class="tu-btn tu-btn--secondary"
                              name="action"
                              value="mark_seen"
                              formnovalidate
                            >Mark seen</button>
                          <?php endif; ?>
                        </form>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
<?php
require dirname(__DIR__, 2) . '/includes/partials/admin_shell_end.php';

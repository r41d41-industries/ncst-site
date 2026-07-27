<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
auth_require();

$posts = posts_list_all();
$flash = flash_get('success');
$error = flash_get('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Posts — NCST Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
  <div class="admin-wrap">
    <div class="admin-actions" style="justify-content:space-between;align-items:center;">
      <div>
        <strong style="letter-spacing:1px;text-transform:uppercase;">NCST Admin</strong>
        <span style="color:var(--muted-60);margin-left:8px;"><?= e((string) auth_username()) ?></span>
      </div>
      <div style="display:flex;gap:8px;">
        <a class="btn" href="/">View feed</a>
        <a class="btn" href="/admin/logout.php">Log out</a>
      </div>
    </div>

    <div class="admin-card">
      <div class="admin-actions">
        <h1 style="margin:0;flex:1;">Posts</h1>
        <a class="btn btn-primary" href="/admin/post_edit.php">New post</a>
      </div>

      <?php if ($flash): ?>
        <div class="admin-flash"><?= e($flash) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="admin-flash admin-flash--error"><?= e($error) ?></div>
      <?php endif; ?>

      <table class="admin-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Category</th>
            <th>Published</th>
            <th>Updated</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if ($posts === []): ?>
            <tr><td colspan="6">No posts yet.</td></tr>
          <?php else: ?>
            <?php foreach ($posts as $post): ?>
              <tr>
                <td><?= e((string) $post['id']) ?></td>
                <td><?= e((string) $post['title']) ?></td>
                <td><?= e((string) $post['category']) ?></td>
                <td><?= !empty($post['published']) ? 'Yes' : 'No' ?></td>
                <td><?= e((string) $post['updated_at']) ?></td>
                <td style="white-space:nowrap;">
                  <a href="/admin/post_edit.php?id=<?= e((string) $post['id']) ?>">Edit</a>
                  ·
                  <a href="/admin/post_delete.php?id=<?= e((string) $post['id']) ?>" style="color:var(--badge-fire);">Delete</a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirm = isset($_POST['confirm']);
    if ($confirm) {
        posts_delete($id);
        flash_set('success', 'Post deleted.');
        redirect('/admin/');
    }
    redirect('/admin/');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Delete Post — NCST Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
  <div class="admin-wrap">
    <div class="admin-card" style="max-width:520px;">
      <h1>Delete post</h1>
      <p>Delete <strong><?= e((string) $post['title']) ?></strong>? This cannot be undone.</p>
      <form method="post" action="">
        <button class="btn btn-danger" type="submit" name="confirm" value="1">Delete permanently</button>
        <a class="btn" href="/admin/">Cancel</a>
      </form>
    </div>
  </div>
</body>
</html>

<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
auth_require();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    redirect('/admin/');
}

$post = posts_find($id);
if ($post === null) {
    redirect('/admin/');
}

if (posts_is_trashed($post)) {
    flash_set('error', 'Restore this post from Trash before editing.');
    redirect('/admin/?status=trash');
}

if (category_template((string) ($post['category'] ?? '')) === 'incident') {
    redirect('/admin/post_incident.php?id=' . $id);
}

redirect('/admin/post_article.php?id=' . $id);

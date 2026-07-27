<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
auth_require();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$post = $id > 0 ? posts_find($id) : null;
$isEdit = $post !== null;

$error = null;
$form = [
    'category' => $post['category'] ?? 'NEWS',
    'title' => $post['title'] ?? '',
    'body' => $post['body'] ?? '',
    'article_body' => $post['article_body'] ?? '',
    'agency' => $post['agency'] ?? '',
    'dispatched_at' => cs_datetime_local_value($post['dispatched_at'] ?? null),
    'cleared_at' => cs_datetime_local_value($post['cleared_at'] ?? null),
    'recorded_at' => cs_datetime_local_value($post['recorded_at'] ?? null),
    'expires_at' => cs_datetime_local_value($post['expires_at'] ?? null),
    'image_path' => $post['image_path'] ?? '',
    'facebook_url' => $post['facebook_url'] ?? '',
    'x_url' => $post['x_url'] ?? '',
    'read_more_url' => $post['read_more_url'] ?? '',
    'published' => !empty($post['published']),
    'new_update_at' => '',
    'new_update_text' => '',
];

$updates = $isEdit ? ($post['updates'] ?? []) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'save');

    if ($isEdit && $action === 'delete_update') {
        $updateId = (int) ($_POST['update_id'] ?? 0);
        if ($updateId > 0) {
            posts_delete_update($updateId, $id);
            flash_set('success', 'Update removed.');
        }
        redirect('/admin/post_edit.php?id=' . $id);
    }

    $form['category'] = strtoupper(trim((string) ($_POST['category'] ?? 'NEWS')));
    $form['title'] = trim((string) ($_POST['title'] ?? ''));
    $form['body'] = trim((string) ($_POST['body'] ?? ''));
    $form['article_body'] = trim((string) ($_POST['article_body'] ?? ''));
    $form['agency'] = trim((string) ($_POST['agency'] ?? ''));
    $form['dispatched_at'] = trim((string) ($_POST['dispatched_at'] ?? ''));
    $form['cleared_at'] = trim((string) ($_POST['cleared_at'] ?? ''));
    $form['recorded_at'] = trim((string) ($_POST['recorded_at'] ?? ''));
    $form['expires_at'] = trim((string) ($_POST['expires_at'] ?? ''));
    $form['facebook_url'] = trim((string) ($_POST['facebook_url'] ?? ''));
    $form['x_url'] = trim((string) ($_POST['x_url'] ?? ''));
    $form['read_more_url'] = trim((string) ($_POST['read_more_url'] ?? ''));
    $form['published'] = isset($_POST['published']);
    $form['new_update_at'] = trim((string) ($_POST['new_update_at'] ?? ''));
    $form['new_update_text'] = trim((string) ($_POST['new_update_text'] ?? ''));
    $removeImage = isset($_POST['remove_image']);

    $facebookUrl = cs_normalize_url($form['facebook_url']);
    $xUrl = cs_normalize_url($form['x_url']);
    $readMoreUrl = cs_normalize_url($form['read_more_url']);
    $facebookInvalid = $form['facebook_url'] !== '' && $facebookUrl === null;
    $xInvalid = $form['x_url'] !== '' && $xUrl === null;
    $readMoreInvalid = $form['read_more_url'] !== '' && $readMoreUrl === null;

    $dispatchedAt = cs_parse_datetime_input($form['dispatched_at']);
    $clearedAt = cs_parse_datetime_input($form['cleared_at']);
    $recordedAt = cs_parse_datetime_input($form['recorded_at']);
    $expiresAt = cs_parse_datetime_input($form['expires_at']);
    $newUpdateAt = cs_parse_datetime_input($form['new_update_at']);
    $dispatchedInvalid = $form['dispatched_at'] !== '' && $dispatchedAt === null;
    $clearedInvalid = $form['cleared_at'] !== '' && $clearedAt === null;
    $recordedInvalid = $form['recorded_at'] !== '' && $recordedAt === null;
    $expiresInvalid = $form['expires_at'] !== '' && $expiresAt === null;
    $updateAtInvalid = $form['new_update_at'] !== '' && $newUpdateAt === null;

    if (!in_array($form['category'], CS_POST_CATEGORIES, true)) {
        $error = 'Invalid category.';
    } elseif ($form['title'] === '' || $form['body'] === '') {
        $error = 'Title and body are required.';
    } elseif ($facebookInvalid || $xInvalid || $readMoreInvalid) {
        $error = 'Links must be valid http(s) URLs.';
    } elseif ($dispatchedInvalid || $clearedInvalid || $recordedInvalid || $expiresInvalid || $updateAtInvalid) {
        $error = 'Dates must be valid date/time values.';
    } elseif ($form['new_update_text'] !== '' && $newUpdateAt === null) {
        $error = 'Update date/time is required when adding an update.';
    } elseif ($form['new_update_at'] !== '' && $form['new_update_text'] === '') {
        $error = 'Update text is required when adding an update.';
    } else {
        try {
            $existingPath = $isEdit ? ($post['image_path'] ?? null) : null;
            if ($removeImage) {
                $existingPath = null;
            }
            $imagePath = posts_handle_upload($_FILES['image'] ?? ['error' => UPLOAD_ERR_NO_FILE], $existingPath);

            $payload = [
                'category' => $form['category'],
                'title' => $form['title'],
                'body' => $form['body'],
                'article_body' => $form['article_body'] !== '' ? $form['article_body'] : null,
                'update_label' => null,
                'update_text' => null,
                'agency' => $form['agency'] !== '' ? $form['agency'] : null,
                'dispatched_at' => $dispatchedAt,
                'cleared_at' => $clearedAt,
                'recorded_at' => $recordedAt,
                'expires_at' => $expiresAt,
                'dispatched_text' => null,
                'status_text' => null,
                'image_path' => $imagePath,
                'facebook_url' => $facebookUrl,
                'x_url' => $xUrl,
                'read_more_url' => $readMoreUrl,
                'published' => $form['published'],
            ];

            if ($isEdit) {
                posts_update($id, $payload);
                $postId = $id;
            } else {
                $postId = posts_create($payload);
            }

            if ($form['new_update_text'] !== '' && $newUpdateAt !== null) {
                posts_add_update($postId, [
                    'label' => null,
                    'body' => $form['new_update_text'],
                    'created_at' => $newUpdateAt,
                ]);
            }

            flash_set('success', $isEdit ? 'Post updated.' : 'Post created.');
            redirect('/admin/');
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $isEdit ? 'Edit' : 'New' ?> Post — NCST Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
  <div class="admin-wrap">
    <div class="admin-actions">
      <a class="btn" href="/admin/">← Back</a>
    </div>
    <div class="admin-card">
      <h1><?= $isEdit ? 'Edit post' : 'New post' ?></h1>
      <?php if ($error): ?>
        <div class="admin-flash admin-flash--error"><?= e($error) ?></div>
      <?php endif; ?>

      <?php if ($isEdit && $updates !== []): ?>
        <div class="form-row">
          <label>Update timeline (newest first)</label>
          <ul class="admin-update-list">
            <?php foreach ($updates as $u): ?>
              <li class="admin-update-list__item">
                <div>
                  <strong>UPDATE: <?= e(cs_format_event_time((string) ($u['created_at'] ?? ''))) ?></strong>
                  <p><?= e((string) ($u['body'] ?? '')) ?></p>
                  <small><?= e((string) ($u['created_at'] ?? '')) ?></small>
                </div>
                <?php if (!empty($u['id'])): ?>
                  <form method="post" action="" onsubmit="return confirm('Delete this update?');">
                    <input type="hidden" name="action" value="delete_update">
                    <input type="hidden" name="update_id" value="<?= e((string) $u['id']) ?>">
                    <button class="btn" type="submit">Delete</button>
                  </form>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data" action="">
        <input type="hidden" name="action" value="save">
        <div class="form-row">
          <label for="category">Category (filter)</label>
          <select id="category" name="category" required>
            <?php foreach (CS_POST_CATEGORIES as $cat): ?>
              <option value="<?= e($cat) ?>"<?= $form['category'] === $cat ? ' selected' : '' ?>><?= e($cat) ?> — <?= e(cs_post_layout_label($cat)) ?></option>
            <?php endforeach; ?>
          </select>
          <p style="margin:8px 0 0;font-size:13px;color:var(--muted-60);">
            CRIME / FIRE / TRAFFIC use the incident card. WEATHER uses image + body + optional read-more + VALID range. NEWS / UPDATES use the news card.
          </p>
        </div>
        <div class="form-row">
          <label for="title">Title</label>
          <input id="title" name="title" type="text" required value="<?= e((string) $form['title']) ?>">
        </div>
        <div class="form-row">
          <label for="body">Body (feed teaser)</label>
          <textarea id="body" name="body" required><?= e((string) $form['body']) ?></textarea>
        </div>
        <div class="form-row">
          <label for="article_body">Full article (optional)</label>
          <textarea id="article_body" name="article_body"><?= e((string) $form['article_body']) ?></textarea>
          <p style="margin:8px 0 0;font-size:13px;color:var(--muted-60);">
            For NEWS / UPDATES / WEATHER: powers the internal “read more” article page. When set, the feed link goes to that page instead of an external URL.
          </p>
        </div>
        <div class="form-row">
          <label for="new_update_at">Add update date/time (shown after UPDATE:)</label>
          <input id="new_update_at" name="new_update_at" type="datetime-local" value="<?= e((string) $form['new_update_at']) ?>">
        </div>
        <div class="form-row">
          <label for="new_update_text">Add update text<?= $isEdit ? '' : ' (optional)' ?></label>
          <textarea id="new_update_text" name="new_update_text"><?= e((string) $form['new_update_text']) ?></textarea>
        </div>
        <div class="form-row">
          <label for="agency">Agencies value (ORG or BADGE|ORG) — shown after AGENCIES:</label>
          <input id="agency" name="agency" type="text" value="<?= e((string) $form['agency']) ?>">
        </div>
        <div class="form-row">
          <label for="dispatched_at">Dispatched date/time (shown after DISPATCHED:)</label>
          <input id="dispatched_at" name="dispatched_at" type="datetime-local" value="<?= e((string) $form['dispatched_at']) ?>">
        </div>
        <div class="form-row">
          <label for="cleared_at">Cleared date/time (optional — shows UNKNOWN if empty)</label>
          <input id="cleared_at" name="cleared_at" type="datetime-local" value="<?= e((string) $form['cleared_at']) ?>">
        </div>
        <div class="form-row">
          <label for="recorded_at">Valid from date/time (weather layout — shown after VALID:)</label>
          <input id="recorded_at" name="recorded_at" type="datetime-local" value="<?= e((string) $form['recorded_at']) ?>">
        </div>
        <div class="form-row">
          <label for="expires_at">Valid to date/time (optional — shows UNKNOWN if empty)</label>
          <input id="expires_at" name="expires_at" type="datetime-local" value="<?= e((string) $form['expires_at']) ?>">
        </div>
        <div class="form-row">
          <label for="image">Image</label>
          <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp,image/gif">
          <?php if (!empty($form['image_path'])): ?>
            <p style="margin:8px 0 0;font-size:13px;color:var(--muted-60);">
              Current: <?= e((string) $form['image_path']) ?>
            </p>
            <label class="form-check" style="margin-top:8px;">
              <input type="checkbox" name="remove_image" value="1">
              Remove current image
            </label>
          <?php endif; ?>
        </div>
        <div class="form-row">
          <label for="facebook_url">Facebook link (optional — icon shows only if set)</label>
          <input id="facebook_url" name="facebook_url" type="url" placeholder="https://www.facebook.com/..." value="<?= e((string) $form['facebook_url']) ?>">
        </div>
        <div class="form-row">
          <label for="x_url">X link (optional — icon shows only if set)</label>
          <input id="x_url" name="x_url" type="url" placeholder="https://x.com/..." value="<?= e((string) $form['x_url']) ?>">
        </div>
        <div class="form-row">
          <label for="read_more_url">External read more link (optional — used only when Full article is empty)</label>
          <input id="read_more_url" name="read_more_url" type="url" placeholder="https://..." value="<?= e((string) $form['read_more_url']) ?>">
        </div>
        <div class="form-row">
          <label class="form-check">
            <input type="checkbox" name="published" value="1"<?= !empty($form['published']) ? ' checked' : '' ?>>
            Published (visible on public feed)
          </label>
        </div>
        <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Save changes' : 'Create post' ?></button>
      </form>
    </div>
  </div>
</body>
</html>

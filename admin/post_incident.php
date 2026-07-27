<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
auth_require();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$post = $id > 0 ? posts_find($id) : null;
$isEdit = $post !== null;

if ($id > 0 && !$isEdit) {
    redirect('/admin/');
}
if ($isEdit && posts_is_trashed($post)) {
    flash_set('error', 'Restore this post from Trash before editing.');
    redirect('/admin/?status=trash');
}
if ($isEdit && category_template((string) ($post['category'] ?? '')) !== 'incident') {
    redirect('/admin/post_article.php?id=' . $id);
}

$categories = array_values(array_filter(
    categories_all(),
    static fn(array $category): bool => strtolower((string) ($category['template'] ?? '')) === 'incident'
));
$incidentSlugs = category_slugs_by_template('incident');
$defaultCategory = $incidentSlugs[0] ?? '';
$error = null;
$form = [
    'category' => strtoupper((string) ($post['category'] ?? $defaultCategory)),
    'title' => $post['title'] ?? '',
    'body' => $post['body'] ?? '',
    'agency' => $post['agency'] ?? '',
    'dispatched_at' => cs_datetime_local_value($post['dispatched_at'] ?? null),
    'cleared_at' => cs_datetime_local_value($post['cleared_at'] ?? null),
    'image_path' => $post['image_path'] ?? '',
    'image_media_id' => $post['image_media_id'] ?? '',
    'facebook_url' => $post['facebook_url'] ?? '',
    'x_url' => $post['x_url'] ?? '',
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
        redirect('/admin/post_incident.php?id=' . $id);
    }

    $form['category'] = strtoupper(trim((string) ($_POST['category'] ?? '')));
    $form['title'] = trim((string) ($_POST['title'] ?? ''));
    $form['body'] = trim((string) ($_POST['body'] ?? ''));
    $form['agency'] = trim((string) ($_POST['agency'] ?? ''));
    $form['dispatched_at'] = trim((string) ($_POST['dispatched_at'] ?? ''));
    $form['cleared_at'] = trim((string) ($_POST['cleared_at'] ?? ''));
    $form['image_media_id'] = trim((string) ($_POST['image_media_id'] ?? ''));
    $form['image_path'] = trim((string) ($_POST['image_path'] ?? ''));
    $form['facebook_url'] = trim((string) ($_POST['facebook_url'] ?? ''));
    $form['x_url'] = trim((string) ($_POST['x_url'] ?? ''));
    $form['published'] = isset($_POST['published']);
    $form['new_update_at'] = trim((string) ($_POST['new_update_at'] ?? ''));
    $form['new_update_text'] = trim((string) ($_POST['new_update_text'] ?? ''));
    $removeImage = isset($_POST['remove_image']);

    $facebookUrl = cs_normalize_url($form['facebook_url']);
    $xUrl = cs_normalize_url($form['x_url']);
    $dispatchedAt = cs_parse_datetime_input($form['dispatched_at']);
    $clearedAt = cs_parse_datetime_input($form['cleared_at']);
    $newUpdateAt = cs_parse_datetime_input($form['new_update_at']);

    if (!in_array($form['category'], $incidentSlugs, true)
        || category_template($form['category']) !== 'incident') {
        $error = 'Select an incident category.';
    } elseif ($form['title'] === '' || $form['body'] === '') {
        $error = 'Title and body are required.';
    } elseif (($form['facebook_url'] !== '' && $facebookUrl === null)
        || ($form['x_url'] !== '' && $xUrl === null)) {
        $error = 'Links must be valid http(s) URLs.';
    } elseif (($form['dispatched_at'] !== '' && $dispatchedAt === null)
        || ($form['cleared_at'] !== '' && $clearedAt === null)
        || ($form['new_update_at'] !== '' && $newUpdateAt === null)) {
        $error = 'Dates must be valid date/time values.';
    } elseif ($form['new_update_text'] !== '' && $newUpdateAt === null) {
        $error = 'Update date/time is required when adding an update.';
    } elseif ($form['new_update_at'] !== '' && $form['new_update_text'] === '') {
        $error = 'Update text is required when adding an update.';
    } else {
        try {
            $imageMediaId = $form['image_media_id'] !== '' ? (int) $form['image_media_id'] : null;
            $imagePath = $form['image_path'] !== '' ? $form['image_path'] : null;
            if ($removeImage) {
                $imageMediaId = null;
                $imagePath = null;
            } else {
                [$imageMediaId, $imagePath] = media_resolve_selection($imageMediaId, $imagePath);
                $uploadedMediaId = null;
                $imagePath = posts_handle_upload(
                    $_FILES['image'] ?? ['error' => UPLOAD_ERR_NO_FILE],
                    $imagePath,
                    $uploadedMediaId
                );
                if ($uploadedMediaId !== null) {
                    $imageMediaId = $uploadedMediaId;
                }
            }

            $payload = [
                'category' => $form['category'],
                'title' => $form['title'],
                'body' => $form['body'],
                'article_body' => null,
                'footnotes' => null,
                'update_label' => null,
                'update_text' => null,
                'agency' => $form['agency'] !== '' ? $form['agency'] : null,
                'dispatched_at' => $dispatchedAt,
                'cleared_at' => $clearedAt,
                'recorded_at' => null,
                'expires_at' => null,
                'dispatched_text' => null,
                'status_text' => null,
                'image_path' => $imagePath,
                'image_media_id' => $imageMediaId,
                'facebook_url' => $facebookUrl,
                'x_url' => $xUrl,
                'read_more_url' => null,
                'og_title' => null,
                'og_description' => null,
                'og_image_path' => null,
                'og_image_media_id' => null,
                'gallery_id' => null,
                'playlist_id' => null,
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

            flash_set('success', $isEdit ? 'Incident updated.' : 'Incident created.');
            redirect('/admin/');
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$adminPageTitle = ($isEdit ? 'Edit' : 'New') . ' Incident';
$adminSection = 'posts';
$adminPanelTitle = 'Posts';
$adminShowAdd = true;
require dirname(__DIR__) . '/includes/partials/admin_shell_start.php';
?>
        <div class="admin-content__header">
          <div>
            <h1><?= $isEdit ? 'Edit incident' : 'New incident' ?></h1>
            <p class="admin-content__lead">Manage incident details, timeline updates, and publishing status.</p>
          </div>
          <a class="tu-btn tu-btn--secondary" href="/admin/">Back to posts</a>
        </div>

        <?php if ($error): ?>
          <div class="tu-alert tu-alert--danger" role="alert"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if ($categories === []): ?>
          <div class="tu-alert tu-alert--danger" role="alert">Create an incident category under Settings → Posts before adding an incident.</div>
        <?php endif; ?>

        <div class="admin-incident-layout">
          <div class="tu-card">
            <form id="incident-form" method="post" enctype="multipart/form-data" action="">
              <input type="hidden" name="action" value="save">
              <div class="tu-form-row">
                <label for="category">Incident category</label>
                <select id="category" name="category" required<?= $categories === [] ? ' disabled' : '' ?>>
                  <?php foreach ($categories as $category): ?>
                    <?php $slug = strtoupper((string) $category['slug']); ?>
                    <option value="<?= e($slug) ?>"<?= $form['category'] === $slug ? ' selected' : '' ?>><?= e((string) $category['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="tu-form-row">
                <label for="title">Title</label>
                <input id="title" name="title" type="text" required value="<?= e((string) $form['title']) ?>">
              </div>
              <div class="tu-form-row">
                <label for="body">Body</label>
                <textarea id="body" name="body" required><?= e((string) $form['body']) ?></textarea>
              </div>
              <div class="tu-form-row">
                <label for="agency">Agencies value (ORG or BADGE|ORG)</label>
                <input id="agency" name="agency" type="text" value="<?= e((string) $form['agency']) ?>">
              </div>
              <div class="tu-form-row">
                <label for="dispatched_at">Dispatched date/time</label>
                <input id="dispatched_at" name="dispatched_at" type="datetime-local" value="<?= e((string) $form['dispatched_at']) ?>">
              </div>
              <div class="tu-form-row">
                <label for="cleared_at">Cleared date/time</label>
                <input id="cleared_at" name="cleared_at" type="datetime-local" value="<?= e((string) $form['cleared_at']) ?>">
              </div>
              <div class="tu-form-row">
                <label class="tu-check" for="published">
                  <input id="published" type="checkbox" name="published" value="1"<?= !empty($form['published']) ? ' checked' : '' ?>>
                  Published (visible on public feed)
                </label>
              </div>
              <div class="tu-actions">
                <button class="tu-btn tu-btn--brand" type="submit"<?= $categories === [] ? ' disabled' : '' ?>><?= $isEdit ? 'Save changes' : 'Create incident' ?></button>
                <a class="tu-btn tu-btn--tertiary" href="/admin/">Cancel</a>
              </div>
            </form>
          </div>

          <div class="admin-incident-layout__aside">
            <div class="tu-card">
              <h2>Add update</h2>
              <?php if ($isEdit && $updates !== []): ?>
                <div class="tu-form-row">
                  <span class="tu-label" id="update-timeline-label">Update timeline (newest first)</span>
                  <ul class="tu-update-list" aria-labelledby="update-timeline-label">
                    <?php foreach ($updates as $update): ?>
                      <li class="tu-update-list__item">
                        <div>
                          <strong>UPDATE: <?= e(cs_format_event_time((string) ($update['created_at'] ?? ''))) ?></strong>
                          <p><?= e((string) ($update['body'] ?? '')) ?></p>
                          <small><?= e((string) ($update['created_at'] ?? '')) ?></small>
                        </div>
                        <?php if (!empty($update['id'])): ?>
                          <form method="post" action="" onsubmit="return confirm('Delete this update?');">
                            <input type="hidden" name="action" value="delete_update">
                            <input type="hidden" name="update_id" value="<?= e((string) $update['id']) ?>">
                            <button class="tu-btn tu-btn--secondary" type="submit">Delete</button>
                          </form>
                        <?php endif; ?>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              <?php endif; ?>
              <div class="tu-form-row">
                <label for="new_update_at">Add update date/time</label>
                <input id="new_update_at" name="new_update_at" type="datetime-local" form="incident-form" value="<?= e((string) $form['new_update_at']) ?>">
              </div>
              <div class="tu-form-row">
                <label for="new_update_text">Add update text</label>
                <textarea id="new_update_text" name="new_update_text" form="incident-form"><?= e((string) $form['new_update_text']) ?></textarea>
              </div>
            </div>

            <div class="tu-card">
              <h2>Image</h2>
              <div class="tu-form-row">
                <div class="media-field" data-media-field data-media-kind="image">
                  <input type="hidden" name="image_media_id" form="incident-form" data-media-id value="<?= e((string) $form['image_media_id']) ?>">
                  <input type="hidden" name="image_path" form="incident-form" data-media-path value="<?= e((string) $form['image_path']) ?>">
                  <div class="media-field__preview">
                    <img class="media-field__thumb" data-media-thumb src="<?= !empty($form['image_path']) ? e('/' . ltrim((string) $form['image_path'], '/')) : '' ?>" alt=""<?= empty($form['image_path']) ? ' hidden' : '' ?>>
                    <span data-media-label><?= !empty($form['image_path']) || !empty($form['image_media_id']) ? e((string) ($form['image_path'] ?: ('Media #' . $form['image_media_id']))) : 'No media selected' ?></span>
                  </div>
                  <div class="media-field__actions">
                    <button type="button" class="tu-btn tu-btn--secondary" data-media-choose>Choose from library</button>
                    <button type="button" class="tu-btn tu-btn--tertiary" data-media-clear>Clear</button>
                  </div>
                  <label class="tu-check" style="margin-top:8px;">
                    <input type="checkbox" name="remove_image" value="1" form="incident-form">
                    Remove image on save
                  </label>
                  <p class="tu-help" style="margin-top:8px;">Or upload a new file:</p>
                  <label class="admin-sr-only" for="image">Upload image</label>
                  <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp,image/gif" form="incident-form">
                </div>
              </div>
            </div>

            <div class="tu-card">
              <h2>Social</h2>
              <div class="tu-form-row">
                <label for="facebook_url">Facebook link</label>
                <input id="facebook_url" name="facebook_url" type="url" form="incident-form" placeholder="https://www.facebook.com/..." value="<?= e((string) $form['facebook_url']) ?>">
              </div>
              <div class="tu-form-row">
                <label for="x_url">X link</label>
                <input id="x_url" name="x_url" type="url" form="incident-form" placeholder="https://x.com/..." value="<?= e((string) $form['x_url']) ?>">
              </div>
            </div>
          </div>
        </div>
<?php
require dirname(__DIR__) . '/includes/partials/admin_shell_end.php';

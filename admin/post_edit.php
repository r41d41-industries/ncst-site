<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
auth_require();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$post = $id > 0 ? posts_find($id) : null;
$isEdit = $post !== null;
$categories = categories_all();

if ($isEdit && posts_is_trashed($post)) {
    flash_set('error', 'Restore this post from Trash before editing.');
    redirect('/admin/?status=trash');
}

$error = null;
$form = [
    'category' => $post['category'] ?? ($categories[0]['slug'] ?? 'NEWS'),
    'title' => $post['title'] ?? '',
    'body' => $post['body'] ?? '',
    'article_body' => $post['article_body'] ?? '',
    'agency' => $post['agency'] ?? '',
    'dispatched_at' => cs_datetime_local_value($post['dispatched_at'] ?? null),
    'cleared_at' => cs_datetime_local_value($post['cleared_at'] ?? null),
    'recorded_at' => cs_datetime_local_value($post['recorded_at'] ?? null),
    'expires_at' => cs_datetime_local_value($post['expires_at'] ?? null),
    'image_path' => $post['image_path'] ?? '',
    'image_media_id' => $post['image_media_id'] ?? '',
    'facebook_url' => $post['facebook_url'] ?? '',
    'x_url' => $post['x_url'] ?? '',
    'read_more_url' => $post['read_more_url'] ?? '',
    'og_title' => $post['og_title'] ?? '',
    'og_description' => $post['og_description'] ?? '',
    'og_image_path' => $post['og_image_path'] ?? '',
    'og_image_media_id' => $post['og_image_media_id'] ?? '',
    'published' => !empty($post['published']),
    'new_update_at' => '',
    'new_update_text' => '',
];

$updates = $isEdit ? ($post['updates'] ?? []) : [];
$postCategorySlugs = cs_post_category_slugs();
$showOgOverrides = in_array(category_template((string) $form['category']), ['news', 'weather'], true);

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
    $form['og_title'] = trim((string) ($_POST['og_title'] ?? ''));
    $form['og_description'] = trim((string) ($_POST['og_description'] ?? ''));
    $form['published'] = isset($_POST['published']);
    $form['new_update_at'] = trim((string) ($_POST['new_update_at'] ?? ''));
    $form['new_update_text'] = trim((string) ($_POST['new_update_text'] ?? ''));
    $form['image_media_id'] = trim((string) ($_POST['image_media_id'] ?? ''));
    $form['image_path'] = trim((string) ($_POST['image_path'] ?? ''));
    $form['og_image_media_id'] = trim((string) ($_POST['og_image_media_id'] ?? ''));
    $form['og_image_path'] = trim((string) ($_POST['og_image_path'] ?? ''));
    $removeImage = isset($_POST['remove_image']);
    $removeOgImage = isset($_POST['remove_og_image']);
    $showOgOverrides = in_array(category_template($form['category']), ['news', 'weather'], true);

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

    if (!in_array($form['category'], $postCategorySlugs, true)) {
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
            $imageMediaId = $form['image_media_id'] !== '' ? (int) $form['image_media_id'] : null;
            $imagePath = $form['image_path'] !== '' ? $form['image_path'] : null;
            if ($removeImage) {
                $imageMediaId = null;
                $imagePath = null;
            } else {
                [$imageMediaId, $imagePath] = media_resolve_selection($imageMediaId, $imagePath);
                // Legacy direct file upload still supported.
                $uploadedMediaId = null;
                $uploadedPath = posts_handle_upload(
                    $_FILES['image'] ?? ['error' => UPLOAD_ERR_NO_FILE],
                    $imagePath,
                    $uploadedMediaId
                );
                if ($uploadedMediaId !== null) {
                    $imageMediaId = $uploadedMediaId;
                    $imagePath = $uploadedPath;
                } else {
                    $imagePath = $uploadedPath;
                }
            }

            $ogImageMediaId = null;
            $ogImagePath = null;
            if (!$showOgOverrides) {
                $form['og_title'] = '';
                $form['og_description'] = '';
            } else {
                $ogImageMediaId = $form['og_image_media_id'] !== '' ? (int) $form['og_image_media_id'] : null;
                $ogImagePath = $form['og_image_path'] !== '' ? $form['og_image_path'] : null;
                if ($removeOgImage) {
                    $ogImageMediaId = null;
                    $ogImagePath = null;
                } else {
                    [$ogImageMediaId, $ogImagePath] = media_resolve_selection($ogImageMediaId, $ogImagePath);
                    $uploadedOgId = null;
                    $uploadedOgPath = settings_handle_og_upload(
                        $_FILES['og_image'] ?? ['error' => UPLOAD_ERR_NO_FILE],
                        $ogImagePath,
                        $uploadedOgId
                    );
                    if ($uploadedOgId !== null) {
                        $ogImageMediaId = $uploadedOgId;
                        $ogImagePath = $uploadedOgPath;
                    } else {
                        $ogImagePath = $uploadedOgPath;
                    }
                }
            }

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
                'image_media_id' => $imageMediaId,
                'facebook_url' => $facebookUrl,
                'x_url' => $xUrl,
                'read_more_url' => $readMoreUrl,
                'og_title' => $showOgOverrides && $form['og_title'] !== '' ? $form['og_title'] : null,
                'og_description' => $showOgOverrides && $form['og_description'] !== '' ? $form['og_description'] : null,
                'og_image_path' => $showOgOverrides ? $ogImagePath : null,
                'og_image_media_id' => $showOgOverrides ? $ogImageMediaId : null,
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
$adminPageTitle = ($isEdit ? 'Edit' : 'New') . ' Post';
$adminSection = 'posts';
$adminPanelTitle = 'Posts';
$adminShowAdd = true;

require dirname(__DIR__) . '/includes/partials/admin_shell_start.php';
?>
        <div class="admin-content__header">
          <div>
            <h1><?= $isEdit ? 'Edit post' : 'New post' ?></h1>
            <p class="admin-content__lead">Update scanner feed fields and publishing status.</p>
          </div>
          <a class="tu-btn tu-btn--secondary" href="/admin/">Back to posts</a>
        </div>

        <?php if ($error): ?>
          <div class="tu-alert tu-alert--danger" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="tu-card">
          <?php if ($isEdit && $updates !== []): ?>
            <div class="tu-form-row">
              <span class="tu-label" id="update-timeline-label">Update timeline (newest first)</span>
              <ul class="tu-update-list" aria-labelledby="update-timeline-label">
                <?php foreach ($updates as $u): ?>
                  <li class="tu-update-list__item">
                    <div>
                      <strong>UPDATE: <?= e(cs_format_event_time((string) ($u['created_at'] ?? ''))) ?></strong>
                      <p><?= e((string) ($u['body'] ?? '')) ?></p>
                      <small><?= e((string) ($u['created_at'] ?? '')) ?></small>
                    </div>
                    <?php if (!empty($u['id'])): ?>
                      <form method="post" action="" onsubmit="return confirm('Delete this update?');">
                        <input type="hidden" name="action" value="delete_update">
                        <input type="hidden" name="update_id" value="<?= e((string) $u['id']) ?>">
                        <button class="tu-btn tu-btn--secondary" type="submit">Delete</button>
                      </form>
                    <?php endif; ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <form method="post" enctype="multipart/form-data" action="">
            <input type="hidden" name="action" value="save">
            <div class="tu-form-row">
              <label for="category">Category (filter)</label>
              <select id="category" name="category" required>
                <?php foreach ($categories as $cat): ?>
                  <?php
                    $slug = strtoupper((string) $cat['slug']);
                    $label = (string) $cat['name'];
                    $tpl = (string) $cat['template'];
                  ?>
                  <option value="<?= e($slug) ?>"<?= $form['category'] === $slug ? ' selected' : '' ?>><?= e($label) ?> — <?= e($tpl) ?> layout</option>
                <?php endforeach; ?>
              </select>
              <p class="tu-help">
                Templates are managed under Settings → Posts. Incident = updates + agencies; weather = VALID range; news = article card.
              </p>
            </div>
            <div class="tu-form-row">
              <label for="title">Title</label>
              <input id="title" name="title" type="text" required value="<?= e((string) $form['title']) ?>">
            </div>
            <div class="tu-form-row">
              <label for="body">Body (feed teaser)</label>
              <textarea id="body" name="body" required><?= e((string) $form['body']) ?></textarea>
            </div>
            <div class="tu-form-row">
              <label for="article_body">Full article (optional)</label>
              <textarea id="article_body" name="article_body"><?= e((string) $form['article_body']) ?></textarea>
              <p class="tu-help">
                For NEWS / UPDATES / WEATHER: powers the internal “read more” article page. When set, the feed link goes to that page instead of an external URL.
              </p>
            </div>
            <div class="tu-form-row">
              <label for="new_update_at">Add update date/time (shown after UPDATE:)</label>
              <input id="new_update_at" name="new_update_at" type="datetime-local" value="<?= e((string) $form['new_update_at']) ?>">
            </div>
            <div class="tu-form-row">
              <label for="new_update_text">Add update text<?= $isEdit ? '' : ' (optional)' ?></label>
              <textarea id="new_update_text" name="new_update_text"><?= e((string) $form['new_update_text']) ?></textarea>
            </div>
            <div class="tu-form-row">
              <label for="agency">Agencies value (ORG or BADGE|ORG) — shown after AGENCIES:</label>
              <input id="agency" name="agency" type="text" value="<?= e((string) $form['agency']) ?>">
            </div>
            <div class="tu-form-row">
              <label for="dispatched_at">Dispatched date/time (shown after DISPATCHED:)</label>
              <input id="dispatched_at" name="dispatched_at" type="datetime-local" value="<?= e((string) $form['dispatched_at']) ?>">
            </div>
            <div class="tu-form-row">
              <label for="cleared_at">Cleared date/time (optional — shows UNKNOWN if empty)</label>
              <input id="cleared_at" name="cleared_at" type="datetime-local" value="<?= e((string) $form['cleared_at']) ?>">
            </div>
            <div class="tu-form-row">
              <label for="recorded_at">Valid from date/time (weather layout — shown after VALID:)</label>
              <input id="recorded_at" name="recorded_at" type="datetime-local" value="<?= e((string) $form['recorded_at']) ?>">
            </div>
            <div class="tu-form-row">
              <label for="expires_at">Valid to date/time (optional — shows UNKNOWN if empty)</label>
              <input id="expires_at" name="expires_at" type="datetime-local" value="<?= e((string) $form['expires_at']) ?>">
            </div>
            <div class="tu-form-row">
              <span class="tu-label">Image</span>
              <div class="media-field" data-media-field data-media-kind="image">
                <input type="hidden" name="image_media_id" data-media-id value="<?= e((string) $form['image_media_id']) ?>">
                <input type="hidden" name="image_path" data-media-path value="<?= e((string) $form['image_path']) ?>">
                <div class="media-field__preview">
                  <img
                    class="media-field__thumb"
                    data-media-thumb
                    src="<?= !empty($form['image_path']) ? e('/' . ltrim((string) $form['image_path'], '/')) : '' ?>"
                    alt=""
                    <?= empty($form['image_path']) ? 'hidden' : '' ?>
                  >
                  <span data-media-label><?= !empty($form['image_path']) || !empty($form['image_media_id']) ? e((string) ($form['image_path'] ?: ('Media #' . $form['image_media_id']))) : 'No media selected' ?></span>
                </div>
                <div class="media-field__actions">
                  <button type="button" class="tu-btn tu-btn--secondary" data-media-choose>Choose from library</button>
                  <button type="button" class="tu-btn tu-btn--tertiary" data-media-clear>Clear</button>
                </div>
                <label class="tu-check" style="margin-top:8px;">
                  <input type="checkbox" name="remove_image" value="1">
                  Remove image on save
                </label>
                <p class="tu-help" style="margin-top:8px;">Or upload a new file (also adds it to the library):</p>
                <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp,image/gif">
              </div>
            </div>
            <div class="tu-form-row">
              <label for="facebook_url">Facebook link (optional — icon shows only if set)</label>
              <input id="facebook_url" name="facebook_url" type="url" placeholder="https://www.facebook.com/..." value="<?= e((string) $form['facebook_url']) ?>">
            </div>
            <div class="tu-form-row">
              <label for="x_url">X link (optional — icon shows only if set)</label>
              <input id="x_url" name="x_url" type="url" placeholder="https://x.com/..." value="<?= e((string) $form['x_url']) ?>">
            </div>
            <div class="tu-form-row">
              <label for="read_more_url">External read more link (optional — used only when Full article is empty)</label>
              <input id="read_more_url" name="read_more_url" type="url" placeholder="https://..." value="<?= e((string) $form['read_more_url']) ?>">
            </div>
            <div id="og-overrides" class="tu-form-row"<?= $showOgOverrides ? '' : ' hidden' ?>>
              <h2 style="margin:0 0 12px;font-size:16px;">Open Graph overrides (optional)</h2>
              <p class="tu-help" style="margin-top:0;">Leave blank to use site defaults / post title &amp; body / post image. Applies to news and weather templates.</p>
              <label for="og_title">OG title</label>
              <input id="og_title" name="og_title" type="text" maxlength="255" value="<?= e((string) $form['og_title']) ?>">
              <label for="og_description" style="margin-top:12px;display:block;">OG description</label>
              <textarea id="og_description" name="og_description" rows="3"><?= e((string) $form['og_description']) ?></textarea>
              <label style="margin-top:12px;display:block;" class="tu-label">OG image</label>
              <div class="media-field" data-media-field data-media-kind="image">
                <input type="hidden" name="og_image_media_id" data-media-id value="<?= e((string) $form['og_image_media_id']) ?>">
                <input type="hidden" name="og_image_path" data-media-path value="<?= e((string) $form['og_image_path']) ?>">
                <div class="media-field__preview">
                  <img
                    class="media-field__thumb"
                    data-media-thumb
                    src="<?= !empty($form['og_image_path']) ? e('/' . ltrim((string) $form['og_image_path'], '/')) : '' ?>"
                    alt=""
                    <?= empty($form['og_image_path']) ? 'hidden' : '' ?>
                  >
                  <span data-media-label><?= !empty($form['og_image_path']) || !empty($form['og_image_media_id']) ? e((string) ($form['og_image_path'] ?: ('Media #' . $form['og_image_media_id']))) : 'No media selected' ?></span>
                </div>
                <div class="media-field__actions">
                  <button type="button" class="tu-btn tu-btn--secondary" data-media-choose>Choose from library</button>
                  <button type="button" class="tu-btn tu-btn--tertiary" data-media-clear>Clear</button>
                </div>
                <label class="tu-check" style="margin-top:8px;">
                  <input type="checkbox" name="remove_og_image" value="1">
                  Remove OG image on save
                </label>
                <p class="tu-help" style="margin-top:8px;">Or upload a new file:</p>
                <input id="og_image" name="og_image" type="file" accept="image/jpeg,image/png,image/webp,image/gif">
              </div>
            </div>
            <div class="tu-form-row">
              <label class="tu-check">
                <input type="checkbox" name="published" value="1"<?= !empty($form['published']) ? ' checked' : '' ?>>
                Published (visible on public feed)
              </label>
            </div>
            <div class="tu-actions">
              <button class="tu-btn tu-btn--brand" type="submit"><?= $isEdit ? 'Save changes' : 'Create post' ?></button>
              <a class="tu-btn tu-btn--tertiary" href="/admin/">Cancel</a>
            </div>
          </form>
        </div>
        <script>
          (function () {
            var select = document.getElementById("category");
            var panel = document.getElementById("og-overrides");
            if (!select || !panel) return;
            var templates = <?= json_encode(array_reduce(
                $categories,
                static function (array $carry, array $c): array {
                    $carry[strtoupper((string) $c['slug'])] = (string) $c['template'];
                    return $carry;
                },
                []
            ), JSON_UNESCAPED_SLASHES) ?>;
            function syncOg() {
              var tpl = templates[select.value] || "news";
              var show = tpl === "news" || tpl === "weather";
              panel.hidden = !show;
            }
            select.addEventListener("change", syncOg);
            syncOg();
          })();
        </script>
<?php
require dirname(__DIR__) . '/includes/partials/admin_shell_end.php';

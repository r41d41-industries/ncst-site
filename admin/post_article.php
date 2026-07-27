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
if ($isEdit && category_template((string) ($post['category'] ?? '')) === 'incident') {
    redirect('/admin/post_incident.php?id=' . $id);
}

$categories = array_values(array_filter(
    categories_all(),
    static fn(array $category): bool => in_array(
        strtolower((string) ($category['template'] ?? '')),
        ['news', 'weather'],
        true
    )
));
$articleSlugs = array_values(array_merge(
    category_slugs_by_template('news'),
    category_slugs_by_template('weather')
));
$defaultCategory = $articleSlugs[0] ?? '';
$footnotes = posts_normalize_footnotes($post['footnotes'] ?? null);
$galleries = galleries_list();
$playlists = playlists_list();
$allowedStatuses = ['draft', 'published', 'trash'];
$error = null;
$form = [
    'category' => strtoupper((string) ($post['category'] ?? $defaultCategory)),
    'title' => $post['title'] ?? '',
    'body' => $post['body'] ?? '',
    'article_body' => $post['article_body'] ?? '',
    'image_path' => $post['image_path'] ?? '',
    'image_media_id' => $post['image_media_id'] ?? '',
    'read_more_url' => $post['read_more_url'] ?? '',
    'recorded_at' => cs_datetime_local_value($post['recorded_at'] ?? null),
    'expires_at' => cs_datetime_local_value($post['expires_at'] ?? null),
    'og_title' => $post['og_title'] ?? '',
    'og_description' => $post['og_description'] ?? '',
    'og_image_path' => $post['og_image_path'] ?? '',
    'og_image_media_id' => $post['og_image_media_id'] ?? '',
    'gallery_id' => $post['gallery_id'] ?? '',
    'playlist_id' => $post['playlist_id'] ?? '',
    'status' => $isEdit
        ? (!empty($post['published']) ? 'published' : 'draft')
        : 'draft',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['category'] = strtoupper(trim((string) ($_POST['category'] ?? '')));
    $form['title'] = trim((string) ($_POST['title'] ?? ''));
    $form['body'] = trim((string) ($_POST['body'] ?? ''));
    $form['article_body'] = trim((string) ($_POST['article_body'] ?? ''));
    $form['image_media_id'] = trim((string) ($_POST['image_media_id'] ?? ''));
    $form['image_path'] = trim((string) ($_POST['image_path'] ?? ''));
    $form['read_more_url'] = trim((string) ($_POST['read_more_url'] ?? ''));
    $form['recorded_at'] = trim((string) ($_POST['recorded_at'] ?? ''));
    $form['expires_at'] = trim((string) ($_POST['expires_at'] ?? ''));
    $form['og_title'] = trim((string) ($_POST['og_title'] ?? ''));
    $form['og_description'] = trim((string) ($_POST['og_description'] ?? ''));
    $form['og_image_media_id'] = trim((string) ($_POST['og_image_media_id'] ?? ''));
    $form['og_image_path'] = trim((string) ($_POST['og_image_path'] ?? ''));
    $form['gallery_id'] = trim((string) ($_POST['gallery_id'] ?? ''));
    $form['playlist_id'] = trim((string) ($_POST['playlist_id'] ?? ''));
    $statusInput = strtolower(trim((string) ($_POST['status'] ?? 'draft')));
    $form['status'] = in_array($statusInput, $allowedStatuses, true) ? $statusInput : 'draft';
    $removeImage = isset($_POST['remove_image']);
    $removeOgImage = isset($_POST['remove_og_image']);

    $footnotesRaw = (string) ($_POST['footnotes_json'] ?? '[]');
    $footnotesDecoded = json_decode($footnotesRaw, true);
    $footnotesInvalid = !is_array($footnotesDecoded);
    $footnotes = $footnotesInvalid ? [] : posts_normalize_footnotes($footnotesDecoded);

    $readMoreUrl = cs_normalize_url($form['read_more_url']);
    $recordedAt = cs_parse_datetime_input($form['recorded_at']);
    $expiresAt = cs_parse_datetime_input($form['expires_at']);
    $isWeather = category_template($form['category']) === 'weather';
    $galleryId = $form['gallery_id'] !== '' ? (int) $form['gallery_id'] : null;
    $playlistId = $form['playlist_id'] !== '' ? (int) $form['playlist_id'] : null;
    // Trash keeps the prior published flag so restore returns draft/published correctly.
    $published = $form['status'] === 'published'
        || ($form['status'] === 'trash' && $isEdit && !empty($post['published']));

    if (!in_array($form['category'], $articleSlugs, true)
        || !in_array(category_template($form['category']), ['news', 'weather'], true)) {
        $error = 'Select a news or weather category.';
    } elseif ($form['title'] === '' || $form['body'] === '') {
        $error = 'Title and body are required.';
    } elseif ($footnotesInvalid) {
        $error = 'Footnotes could not be parsed. Reload the page and try again.';
    } elseif ($form['read_more_url'] !== '' && $readMoreUrl === null) {
        $error = 'The read more link must be a valid http(s) URL.';
    } elseif ($isWeather && (
        ($form['recorded_at'] !== '' && $recordedAt === null)
        || ($form['expires_at'] !== '' && $expiresAt === null)
    )) {
        $error = 'Dates must be valid date/time values.';
    } elseif ($galleryId !== null && gallery_find($galleryId) === null) {
        $error = 'Select a valid gallery or None.';
    } elseif ($playlistId !== null && playlist_find($playlistId) === null) {
        $error = 'Select a valid playlist or None.';
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

            $ogImageMediaId = $form['og_image_media_id'] !== '' ? (int) $form['og_image_media_id'] : null;
            $ogImagePath = $form['og_image_path'] !== '' ? $form['og_image_path'] : null;
            if ($removeOgImage) {
                $ogImageMediaId = null;
                $ogImagePath = null;
            } else {
                [$ogImageMediaId, $ogImagePath] = media_resolve_selection($ogImageMediaId, $ogImagePath);
                $uploadedOgId = null;
                $ogImagePath = settings_handle_og_upload(
                    $_FILES['og_image'] ?? ['error' => UPLOAD_ERR_NO_FILE],
                    $ogImagePath,
                    $uploadedOgId
                );
                if ($uploadedOgId !== null) {
                    $ogImageMediaId = $uploadedOgId;
                }
            }

            $sanitizedArticleBody = article_sanitize_html($form['article_body']);
            $payload = [
                'category' => $form['category'],
                'title' => $form['title'],
                'body' => $form['body'],
                'article_body' => $sanitizedArticleBody !== '' ? $sanitizedArticleBody : null,
                'footnotes' => $footnotes,
                'update_label' => null,
                'update_text' => null,
                'agency' => null,
                'dispatched_at' => null,
                'cleared_at' => null,
                'recorded_at' => $isWeather ? $recordedAt : null,
                'expires_at' => $isWeather ? $expiresAt : null,
                'dispatched_text' => null,
                'status_text' => null,
                'image_path' => $imagePath,
                'image_media_id' => $imageMediaId,
                'facebook_url' => null,
                'x_url' => null,
                'read_more_url' => $readMoreUrl,
                'og_title' => $form['og_title'] !== '' ? $form['og_title'] : null,
                'og_description' => $form['og_description'] !== '' ? $form['og_description'] : null,
                'og_image_path' => $ogImagePath,
                'og_image_media_id' => $ogImageMediaId,
                'gallery_id' => $galleryId,
                'playlist_id' => $playlistId,
                'published' => $published,
            ];

            if ($isEdit) {
                posts_update($id, $payload);
                $postId = $id;
            } else {
                $postId = posts_create($payload);
            }
            if ($form['status'] === 'trash') {
                posts_trash($postId);
                flash_set('success', $isEdit ? 'Article moved to Trash.' : 'Article created and moved to Trash.');
                redirect('/admin/?status=trash');
            }
            flash_set('success', $isEdit ? 'Article updated.' : 'Article created.');
            redirect('/admin/?status=' . ($published ? 'published' : 'draft'));
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$templateBySlug = [];
foreach ($categories as $category) {
    $templateBySlug[strtoupper((string) $category['slug'])] = strtolower((string) $category['template']);
}
$showValid = category_template((string) $form['category']) === 'weather';
$footnotesJson = json_encode($footnotes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($footnotesJson)) {
    $footnotesJson = '[]';
}
$selectedGalleryId = (string) ($form['gallery_id'] ?? '');
$selectedPlaylistId = (string) ($form['playlist_id'] ?? '');

$adminPageTitle = ($isEdit ? 'Edit' : 'New') . ' Article';
$adminSection = 'posts';
$adminPanelTitle = 'Posts';
$adminShowAdd = true;
$adminExtraHead = '<script src="https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js" referrerpolicy="origin"></script>';
require dirname(__DIR__) . '/includes/partials/admin_shell_start.php';
?>
        <div class="admin-content__header">
          <div>
            <h1><?= $isEdit ? 'Edit article' : 'New article' ?></h1>
            <p class="admin-content__lead">Create news and weather articles with rich content and footnotes.</p>
          </div>
          <a class="tu-btn tu-btn--secondary" href="/admin/">Back to posts</a>
        </div>

        <?php if ($error): ?>
          <div class="tu-alert tu-alert--danger" role="alert"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if ($categories === []): ?>
          <div class="tu-alert tu-alert--danger" role="alert">Create a news or weather category under Settings → Posts before adding an article.</div>
        <?php endif; ?>

        <div class="admin-article-layout">
          <div class="tu-card">
            <form id="article-form" method="post" enctype="multipart/form-data" action="">
              <div class="tu-form-row">
                <label for="category">Article category</label>
                <select id="category" name="category" required<?= $categories === [] ? ' disabled' : '' ?>>
                  <?php foreach ($categories as $category): ?>
                    <?php
                      $slug = strtoupper((string) $category['slug']);
                      $template = strtolower((string) $category['template']);
                    ?>
                    <option value="<?= e($slug) ?>"<?= $form['category'] === $slug ? ' selected' : '' ?>><?= e((string) $category['name']) ?> — <?= e($template) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="tu-form-row">
                <label for="title">Title</label>
                <input id="title" name="title" type="text" required value="<?= e((string) $form['title']) ?>">
              </div>
              <div class="tu-form-row">
                <label for="body">Body teaser</label>
                <textarea id="body" name="body" required><?= e((string) $form['body']) ?></textarea>
              </div>
              <div class="tu-form-row">
                <label for="article_body">Full article</label>
                <textarea id="article_body" name="article_body"><?= e((string) $form['article_body']) ?></textarea>
                <p class="tu-help">Use the Footnote button to insert a numbered marker and add its text below.</p>
              </div>
              <div class="tu-form-row">
                <span class="tu-label" id="footnotes-label">Footnotes</span>
                <input id="footnotes_json" type="hidden" name="footnotes_json" value="<?= e($footnotesJson) ?>">
                <div id="footnotes-list" aria-labelledby="footnotes-label"></div>
                <p id="footnotes-empty" class="tu-help">No footnotes added.</p>
              </div>
              <div id="weather-valid"<?= $showValid ? '' : ' hidden' ?>>
                <div class="tu-form-row">
                  <label for="recorded_at">Valid from date/time</label>
                  <input id="recorded_at" name="recorded_at" type="datetime-local" value="<?= e((string) $form['recorded_at']) ?>">
                </div>
                <div class="tu-form-row">
                  <label for="expires_at">Valid to date/time</label>
                  <input id="expires_at" name="expires_at" type="datetime-local" value="<?= e((string) $form['expires_at']) ?>">
                </div>
              </div>
            </form>
          </div>

          <div class="admin-article-layout__aside">
            <div class="tu-card admin-collapse-card" data-admin-collapse>
              <button
                type="button"
                class="admin-collapse-card__toggle"
                id="article-aside-status-toggle"
                aria-expanded="true"
                aria-controls="article-aside-status"
              >
                <span class="admin-collapse-card__title">Status</span>
                <svg class="admin-collapse-card__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </button>
              <div class="admin-collapse-card__body" id="article-aside-status" role="region" aria-labelledby="article-aside-status-toggle">
                <div class="tu-form-row">
                  <label for="status">Post status</label>
                  <select id="status" name="status" form="article-form">
                    <option value="draft"<?= $form['status'] === 'draft' ? ' selected' : '' ?>>Draft</option>
                    <option value="published"<?= $form['status'] === 'published' ? ' selected' : '' ?>>Published</option>
                    <option value="trash"<?= $form['status'] === 'trash' ? ' selected' : '' ?>>Trash</option>
                  </select>
                  <p class="tu-help">Draft and Published control the public feed. Trash moves the post out of the library (restore from Trash).</p>
                </div>
                <div class="tu-actions">
                  <button class="tu-btn tu-btn--brand" type="submit" form="article-form"<?= $categories === [] ? ' disabled' : '' ?>>Save</button>
                  <a class="tu-btn tu-btn--tertiary" href="/admin/">Cancel</a>
                </div>
              </div>
            </div>

            <div class="tu-card admin-collapse-card" data-admin-collapse>
              <button
                type="button"
                class="admin-collapse-card__toggle"
                id="article-aside-readmore-toggle"
                aria-expanded="true"
                aria-controls="article-aside-readmore"
              >
                <span class="admin-collapse-card__title">Read more</span>
                <svg class="admin-collapse-card__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </button>
              <div class="admin-collapse-card__body" id="article-aside-readmore" role="region" aria-labelledby="article-aside-readmore-toggle">
                <div class="tu-form-row">
                  <label for="read_more_url">External read more link</label>
                  <input id="read_more_url" name="read_more_url" type="url" form="article-form" placeholder="https://..." value="<?= e((string) $form['read_more_url']) ?>">
                  <p class="tu-help">Used only when the full article is empty.</p>
                </div>
              </div>
            </div>

            <div class="tu-card admin-collapse-card" data-admin-collapse>
              <button
                type="button"
                class="admin-collapse-card__toggle"
                id="article-aside-image-toggle"
                aria-expanded="true"
                aria-controls="article-aside-image"
              >
                <span class="admin-collapse-card__title">Image</span>
                <svg class="admin-collapse-card__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </button>
              <div class="admin-collapse-card__body" id="article-aside-image" role="region" aria-labelledby="article-aside-image-toggle">
                <div class="tu-form-row">
                  <div class="media-field" data-media-field data-media-kind="image">
                    <input type="hidden" name="image_media_id" form="article-form" data-media-id value="<?= e((string) $form['image_media_id']) ?>">
                    <input type="hidden" name="image_path" form="article-form" data-media-path value="<?= e((string) $form['image_path']) ?>">
                    <div class="media-field__preview">
                      <img class="media-field__thumb" data-media-thumb src="<?= !empty($form['image_path']) ? e('/' . ltrim((string) $form['image_path'], '/')) : '' ?>" alt=""<?= empty($form['image_path']) ? ' hidden' : '' ?>>
                      <span data-media-label><?= !empty($form['image_path']) || !empty($form['image_media_id']) ? e((string) ($form['image_path'] ?: ('Media #' . $form['image_media_id']))) : 'No media selected' ?></span>
                    </div>
                    <div class="media-field__actions">
                      <button type="button" class="tu-btn tu-btn--secondary" data-media-choose>Choose from library</button>
                      <button type="button" class="tu-btn tu-btn--tertiary" data-media-clear>Clear</button>
                    </div>
                    <label class="tu-check" style="margin-top:8px;">
                      <input type="checkbox" name="remove_image" value="1" form="article-form">
                      Remove image on save
                    </label>
                    <p class="tu-help" style="margin-top:8px;">Or upload a new file:</p>
                    <label class="admin-sr-only" for="image">Upload image</label>
                    <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp,image/gif" form="article-form">
                  </div>
                </div>
              </div>
            </div>

            <div class="tu-card admin-collapse-card" data-admin-collapse>
              <button
                type="button"
                class="admin-collapse-card__toggle"
                id="article-aside-og-toggle"
                aria-expanded="false"
                aria-controls="article-aside-og"
              >
                <span class="admin-collapse-card__title">Open Graph</span>
                <svg class="admin-collapse-card__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </button>
              <div class="admin-collapse-card__body" id="article-aside-og" role="region" aria-labelledby="article-aside-og-toggle" hidden>
                <p class="tu-help" style="margin-top:0;">Leave blank to use the post title, teaser, and image.</p>
                <div class="tu-form-row">
                  <label for="og_title">OG title</label>
                  <input id="og_title" name="og_title" type="text" maxlength="255" form="article-form" value="<?= e((string) $form['og_title']) ?>">
                </div>
                <div class="tu-form-row">
                  <label for="og_description">OG description</label>
                  <textarea id="og_description" name="og_description" rows="3" form="article-form"><?= e((string) $form['og_description']) ?></textarea>
                </div>
                <div class="tu-form-row">
                  <span class="tu-label">OG image</span>
                  <div class="media-field" data-media-field data-media-kind="image">
                    <input type="hidden" name="og_image_media_id" form="article-form" data-media-id value="<?= e((string) $form['og_image_media_id']) ?>">
                    <input type="hidden" name="og_image_path" form="article-form" data-media-path value="<?= e((string) $form['og_image_path']) ?>">
                    <div class="media-field__preview">
                      <img class="media-field__thumb" data-media-thumb src="<?= !empty($form['og_image_path']) ? e('/' . ltrim((string) $form['og_image_path'], '/')) : '' ?>" alt=""<?= empty($form['og_image_path']) ? ' hidden' : '' ?>>
                      <span data-media-label><?= !empty($form['og_image_path']) || !empty($form['og_image_media_id']) ? e((string) ($form['og_image_path'] ?: ('Media #' . $form['og_image_media_id']))) : 'No media selected' ?></span>
                    </div>
                    <div class="media-field__actions">
                      <button type="button" class="tu-btn tu-btn--secondary" data-media-choose>Choose from library</button>
                      <button type="button" class="tu-btn tu-btn--tertiary" data-media-clear>Clear</button>
                    </div>
                    <label class="tu-check" style="margin-top:8px;">
                      <input type="checkbox" name="remove_og_image" value="1" form="article-form">
                      Remove OG image on save
                    </label>
                    <p class="tu-help" style="margin-top:8px;">Or upload a new file:</p>
                    <label class="admin-sr-only" for="og_image">Upload OG image</label>
                    <input id="og_image" name="og_image" type="file" accept="image/jpeg,image/png,image/webp,image/gif" form="article-form">
                  </div>
                </div>
              </div>
            </div>

            <div class="tu-card admin-collapse-card" data-admin-collapse>
              <button
                type="button"
                class="admin-collapse-card__toggle"
                id="article-aside-gallery-toggle"
                aria-expanded="false"
                aria-controls="article-aside-gallery"
              >
                <span class="admin-collapse-card__title">Gallery</span>
                <svg class="admin-collapse-card__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </button>
              <div class="admin-collapse-card__body" id="article-aside-gallery" role="region" aria-labelledby="article-aside-gallery-toggle" hidden>
                <div class="tu-form-row">
                  <label for="gallery_id">Associated gallery</label>
                  <select id="gallery_id" name="gallery_id" form="article-form">
                    <option value="">None</option>
                    <?php foreach ($galleries as $gallery): ?>
                      <?php $gid = (string) ($gallery['id'] ?? ''); ?>
                      <option value="<?= e($gid) ?>"<?= $selectedGalleryId === $gid ? ' selected' : '' ?>><?= e((string) ($gallery['title'] ?? ('Gallery #' . $gid))) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <p class="tu-help">Manage galleries under Media → Galleries.</p>
                </div>
              </div>
            </div>

            <div class="tu-card admin-collapse-card" data-admin-collapse>
              <button
                type="button"
                class="admin-collapse-card__toggle"
                id="article-aside-playlist-toggle"
                aria-expanded="false"
                aria-controls="article-aside-playlist"
              >
                <span class="admin-collapse-card__title">Playlist</span>
                <svg class="admin-collapse-card__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </button>
              <div class="admin-collapse-card__body" id="article-aside-playlist" role="region" aria-labelledby="article-aside-playlist-toggle" hidden>
                <div class="tu-form-row">
                  <label for="playlist_id">Associated playlist</label>
                  <select id="playlist_id" name="playlist_id" form="article-form">
                    <option value="">None</option>
                    <?php foreach ($playlists as $playlist): ?>
                      <?php $pid = (string) ($playlist['id'] ?? ''); ?>
                      <option value="<?= e($pid) ?>"<?= $selectedPlaylistId === $pid ? ' selected' : '' ?>><?= e((string) ($playlist['title'] ?? ('Playlist #' . $pid))) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <p class="tu-help">Manage playlists under Media → Playlists.</p>
                </div>
              </div>
            </div>
          </div>
        </div>
        <script>
          (function () {
            "use strict";
            var category = document.getElementById("category");
            var weatherValid = document.getElementById("weather-valid");
            var templates = <?= json_encode($templateBySlug, JSON_UNESCAPED_SLASHES) ?>;
            var footnotes = <?= json_encode($footnotes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
            var footnotesList = document.getElementById("footnotes-list");
            var footnotesEmpty = document.getElementById("footnotes-empty");
            var footnotesInput = document.getElementById("footnotes_json");
            var articleForm = document.getElementById("article-form");

            function syncWeatherFields() {
              if (!category || !weatherValid) return;
              weatherValid.hidden = templates[category.value] !== "weather";
            }

            function syncFootnotes() {
              footnotes.sort(function (a, b) { return Number(a.n) - Number(b.n); });
              footnotesInput.value = JSON.stringify(footnotes);
              footnotesEmpty.hidden = footnotes.length > 0;
            }

            function renderFootnotes() {
              footnotesList.textContent = "";
              footnotes.forEach(function (footnote, index) {
                var row = document.createElement("div");
                row.className = "tu-form-row";

                var label = document.createElement("label");
                var inputId = "footnote-content-" + String(footnote.n) + "-" + String(index);
                label.htmlFor = inputId;
                label.textContent = "Footnote " + String(footnote.n);

                var input = document.createElement("textarea");
                input.id = inputId;
                input.rows = 2;
                input.value = String(footnote.content || "");
                input.addEventListener("input", function () {
                  footnotes[index].content = input.value;
                  syncFootnotes();
                });

                var remove = document.createElement("button");
                remove.type = "button";
                remove.className = "tu-btn tu-btn--tertiary";
                remove.textContent = "Delete footnote " + String(footnote.n);
                remove.addEventListener("click", function () {
                  footnotes.splice(index, 1);
                  renderFootnotes();
                });

                row.appendChild(label);
                row.appendChild(input);
                row.appendChild(remove);
                footnotesList.appendChild(row);
              });
              syncFootnotes();
            }

            function nextFootnoteNumber() {
              return footnotes.reduce(function (max, footnote) {
                return Math.max(max, Number(footnote.n) || 0);
              }, 0) + 1;
            }

            if (category) {
              category.addEventListener("change", syncWeatherFields);
              syncWeatherFields();
            }
            renderFootnotes();

            if (articleForm) {
              articleForm.addEventListener("submit", function () {
                syncFootnotes();
                // Save is outside the form (form="article-form"); sync TinyMCE before POST.
                if (window.tinymce) {
                  window.tinymce.triggerSave();
                }
              });
            }

            if (!window.tinymce) return;
            window.tinymce.init({
              selector: "#article_body",
              license_key: "gpl",
              height: 520,
              menubar: false,
              plugins: "link image lists code",
              toolbar: "undo redo | blocks | bold italic underline | bullist numlist blockquote | link articleimage footnote | code",
              content_style: "img.float-left{float:left;margin:0 1rem 1rem 0}img.float-right{float:right;margin:0 0 1rem 1rem}img.block{display:block;max-width:100%;height:auto;margin:1rem auto}",
              setup: function (editor) {
                editor.ui.registry.addButton("footnote", {
                  text: "Footnote",
                  tooltip: "Insert footnote",
                  onAction: function () {
                    var n = nextFootnoteNumber();
                    editor.windowManager.open({
                      title: "Add footnote " + String(n),
                      body: {
                        type: "panel",
                        items: [
                          { type: "htmlpanel", html: "<p>The next footnote number is <strong>" + String(n) + "</strong>.</p>" },
                          { type: "textarea", name: "content", label: "Footnote content" }
                        ]
                      },
                      buttons: [
                        { type: "cancel", text: "Cancel" },
                        { type: "submit", text: "Add footnote", primary: true }
                      ],
                      onSubmit: function (api) {
                        var content = String(api.getData().content || "").trim();
                        if (!content) return;
                        footnotes.push({ n: n, content: content });
                        renderFootnotes();
                        editor.insertContent("{" + String(n) + "}");
                        api.close();
                      }
                    });
                  }
                });

                editor.ui.registry.addButton("articleimage", {
                  text: "Image",
                  tooltip: "Insert image by URL or path",
                  onAction: function () {
                    editor.windowManager.open({
                      title: "Insert article image",
                      body: {
                        type: "panel",
                        items: [
                          { type: "input", name: "src", label: "Image URL or site path" },
                          {
                            type: "selectbox",
                            name: "className",
                            label: "Alignment",
                            items: [
                              { text: "Float left", value: "float-left" },
                              { text: "Float right", value: "float-right" },
                              { text: "Block", value: "block" }
                            ]
                          }
                        ]
                      },
                      initialData: { className: "float-left" },
                      buttons: [
                        { type: "cancel", text: "Cancel" },
                        { type: "submit", text: "Insert", primary: true }
                      ],
                      onSubmit: function (api) {
                        var data = api.getData();
                        var src = String(data.src || "").trim();
                        if (!src) return;
                        var escapeHtml = function (value) {
                          return value.replace(/[&<>"']/g, function (character) {
                            return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" }[character];
                          });
                        };
                        editor.insertContent('<img src="' + escapeHtml(src) + '" class="' + escapeHtml(String(data.className || "block")) + '" alt="">');
                        api.close();
                      }
                    });
                  }
                });
              }
            });
          })();
        </script>
<?php
require dirname(__DIR__) . '/includes/partials/admin_shell_end.php';

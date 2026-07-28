<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
auth_require();

$error = null;
$flash = flash_get('success');
$categories = categories_all();
$map = facebook_hashtag_map();

$rows = [];
foreach ($map as $tag => $slug) {
    $rows[] = ['tag' => (string) $tag, 'category' => (string) $slug];
}
if ($rows === []) {
    $rows[] = ['tag' => '', 'category' => ''];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $error = 'Invalid request. Please try again.';
    } else {
        $tags = $_POST['tag'] ?? [];
        $cats = $_POST['category'] ?? [];
        if (!is_array($tags)) {
            $tags = [];
        }
        if (!is_array($cats)) {
            $cats = [];
        }
        $posted = [];
        $max = max(count($tags), count($cats));
        for ($i = 0; $i < $max; $i++) {
            $posted[] = [
                'tag' => trim((string) ($tags[$i] ?? '')),
                'category' => trim((string) ($cats[$i] ?? '')),
            ];
        }
        $rows = $posted !== [] ? $posted : [['tag' => '', 'category' => '']];

        try {
            facebook_hashtag_map_save($posted);
            flash_set('success', 'Hashtag mappings saved.');
            redirect('/admin/settings/facebook/hashtags.php');
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$adminPageTitle = 'Settings — Facebook Hashtags';
$adminSection = 'settings';
$adminPanelTitle = 'Settings';
$adminShowAdd = false;
$adminSettingsPage = 'facebook-hashtags';

require dirname(__DIR__, 3) . '/includes/partials/admin_shell_start.php';
?>
        <div class="admin-content__header">
          <div>
            <h1>Facebook Hashtags</h1>
            <p class="admin-content__lead">Map hashtags in Facebook messages to feed categories. The first matching hashtag becomes the suggested topic when converting a post.</p>
          </div>
        </div>

        <?php if ($flash): ?>
          <div class="tu-alert tu-alert--success" role="status"><?= e($flash) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="tu-alert tu-alert--danger" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="tu-card">
          <form method="post" action="" id="fb-hashtag-form">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <div class="tu-table-wrap">
              <table class="tu-table" id="fb-hashtag-table">
                <thead>
                  <tr>
                    <th scope="col">Hashtag</th>
                    <th scope="col">Category</th>
                    <th scope="col" data-sortable="false"><span class="admin-sr-only">Remove</span></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($rows as $row): ?>
                    <tr>
                      <td>
                        <input
                          type="text"
                          name="tag[]"
                          maxlength="32"
                          placeholder="TRAFFIC"
                          value="<?= e(ltrim((string) $row['tag'], '#')) ?>"
                          aria-label="Hashtag without #"
                        >
                      </td>
                      <td>
                        <select name="category[]" aria-label="Category">
                          <option value="">— Select —</option>
                          <?php foreach ($categories as $cat): ?>
                            <?php $slug = strtoupper((string) $cat['slug']); ?>
                            <option value="<?= e($slug) ?>"<?= strtoupper((string) $row['category']) === $slug ? ' selected' : '' ?>>
                              <?= e((string) $cat['name']) ?> (<?= e($slug) ?>)
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </td>
                      <td>
                        <button type="button" class="tu-btn tu-btn--secondary" data-fb-hashtag-remove>Remove</button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <div class="tu-btn-row" style="margin-top:1rem;">
              <button type="button" class="tu-btn tu-btn--secondary" id="fb-hashtag-add">Add mapping</button>
              <button type="submit" class="tu-btn tu-btn--brand">Save hashtag mappings</button>
              <a class="tu-btn tu-btn--secondary" href="/admin/facebook/posts.php">Open Facebook posts</a>
            </div>
          </form>
        </div>

        <template id="fb-hashtag-row-template">
          <tr>
            <td>
              <input type="text" name="tag[]" maxlength="32" placeholder="TRAFFIC" value="" aria-label="Hashtag without #">
            </td>
            <td>
              <select name="category[]" aria-label="Category">
                <option value="">— Select —</option>
                <?php foreach ($categories as $cat): ?>
                  <?php $slug = strtoupper((string) $cat['slug']); ?>
                  <option value="<?= e($slug) ?>"><?= e((string) $cat['name']) ?> (<?= e($slug) ?>)</option>
                <?php endforeach; ?>
              </select>
            </td>
            <td>
              <button type="button" class="tu-btn tu-btn--secondary" data-fb-hashtag-remove>Remove</button>
            </td>
          </tr>
        </template>
        <script>
          (function () {
            var table = document.getElementById('fb-hashtag-table');
            var tbody = table && table.querySelector('tbody');
            var addBtn = document.getElementById('fb-hashtag-add');
            var tpl = document.getElementById('fb-hashtag-row-template');
            if (!tbody || !addBtn || !tpl) return;

            addBtn.addEventListener('click', function () {
              var node = tpl.content.cloneNode(true);
              tbody.appendChild(node);
            });

            tbody.addEventListener('click', function (e) {
              var btn = e.target.closest('[data-fb-hashtag-remove]');
              if (!btn) return;
              var row = btn.closest('tr');
              if (!row) return;
              if (tbody.querySelectorAll('tr').length <= 1) {
                row.querySelectorAll('input, select').forEach(function (el) {
                  el.value = '';
                });
                return;
              }
              row.remove();
            });
          })();
        </script>
<?php
require dirname(__DIR__, 3) . '/includes/partials/admin_shell_end.php';

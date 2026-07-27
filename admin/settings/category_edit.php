<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
auth_require();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$category = $id > 0 ? category_by_id($id) : null;
$isEdit = $category !== null;

$error = null;
$form = [
    'name' => $category['name'] ?? '',
    'slug' => $category['slug'] ?? '',
    'template' => $category['template'] ?? 'news',
    'color' => cs_normalize_hex_color((string) ($category['color'] ?? '#f7931e')) ?? '#f7931e',
    'sort_order' => (string) ($category['sort_order'] ?? '100'),
    'is_filter' => $isEdit ? !empty($category['is_filter']) : true,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['name'] = trim((string) ($_POST['name'] ?? ''));
    $form['slug'] = strtoupper(trim((string) ($_POST['slug'] ?? '')));
    $form['template'] = strtolower(trim((string) ($_POST['template'] ?? 'news')));
    $form['color'] = trim((string) ($_POST['color'] ?? ''));
    $form['sort_order'] = trim((string) ($_POST['sort_order'] ?? '100'));
    $form['is_filter'] = isset($_POST['is_filter']);

    try {
        $payload = [
            'name' => $form['name'],
            'template' => $form['template'],
            'color' => $form['color'],
            'sort_order' => (int) $form['sort_order'],
            'is_filter' => $form['is_filter'],
        ];

        if ($isEdit) {
            category_update($id, $payload);
            flash_set('success', 'Category updated.');
        } else {
            if ($form['slug'] !== '') {
                $payload['slug'] = $form['slug'];
            }
            category_create($payload);
            flash_set('success', 'Category created.');
        }
        redirect('/admin/settings/posts.php');
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $normalized = cs_normalize_hex_color($form['color']);
        if ($normalized !== null) {
            $form['color'] = $normalized;
        }
    }
}

$adminPageTitle = ($isEdit ? 'Edit' : 'Add') . ' Category';
$adminSection = 'settings';
$adminPanelTitle = 'Settings';
$adminShowAdd = false;
$adminSettingsPage = 'posts';

require dirname(__DIR__, 2) . '/includes/partials/admin_shell_start.php';
?>
        <div class="admin-content__header">
          <div>
            <h1><?= $isEdit ? 'Edit category' : 'Add category' ?></h1>
            <p class="admin-content__lead">Set the display name, feed template, and assigned color.</p>
          </div>
          <a class="tu-btn tu-btn--secondary" href="/admin/settings/posts.php">Back to categories</a>
        </div>

        <?php if ($error): ?>
          <div class="tu-alert tu-alert--danger" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="tu-card">
          <form method="post" action="" id="category-form">
            <div class="tu-form-row">
              <label for="name">Name</label>
              <input id="name" name="name" type="text" required maxlength="64" value="<?= e((string) $form['name']) ?>">
            </div>
            <div class="tu-form-row">
              <label for="slug">Slug<?= $isEdit ? '' : ' (optional)' ?></label>
              <input
                id="slug"
                name="slug"
                type="text"
                maxlength="32"
                value="<?= e((string) $form['slug']) ?>"
                <?= $isEdit ? 'readonly' : '' ?>
                pattern="[A-Za-z0-9_]+"
                title="Uppercase letters, numbers, and underscores"
              >
              <p class="tu-help">
                <?= $isEdit
                    ? 'Slug is locked after create so existing posts keep their category key.'
                    : 'Leave blank to generate from the name. Used as the post category key and filter value.' ?>
              </p>
            </div>
            <div class="tu-form-row">
              <label for="template">Template</label>
              <select id="template" name="template" required>
                <?php foreach (CS_CATEGORY_TEMPLATES as $tpl): ?>
                  <option value="<?= e($tpl) ?>"<?= $form['template'] === $tpl ? ' selected' : '' ?>><?= e($tpl) ?></option>
                <?php endforeach; ?>
              </select>
              <p class="tu-help">news = article card, weather = bulletin + VALID range, incident = updates + agencies.</p>
            </div>
            <div class="tu-form-row">
              <label for="color">Assigned color</label>
              <div class="admin-color-field">
                <input
                  id="color-picker"
                  class="admin-color-field__picker"
                  type="color"
                  value="<?= e((string) $form['color']) ?>"
                  aria-label="Color picker"
                >
                <input
                  id="color"
                  name="color"
                  class="admin-color-field__hex"
                  type="text"
                  required
                  pattern="#[0-9A-Fa-f]{6}"
                  maxlength="7"
                  value="<?= e((string) $form['color']) ?>"
                  placeholder="#f7931e"
                  autocomplete="off"
                >
              </div>
              <p class="tu-help">Used on feed filter buttons and post category badges.</p>
            </div>
            <div class="tu-form-row">
              <label for="sort_order">Sort order</label>
              <input id="sort_order" name="sort_order" type="number" value="<?= e((string) $form['sort_order']) ?>">
            </div>
            <div class="tu-form-row">
              <label class="tu-check">
                <input type="checkbox" name="is_filter" value="1"<?= !empty($form['is_filter']) ? ' checked' : '' ?>>
                Show in public feed filters
              </label>
            </div>
            <div class="tu-actions">
              <button class="tu-btn tu-btn--brand" type="submit"><?= $isEdit ? 'Save changes' : 'Create category' ?></button>
              <a class="tu-btn tu-btn--tertiary" href="/admin/settings/posts.php">Cancel</a>
            </div>
          </form>
        </div>
        <script>
          (function () {
            var picker = document.getElementById("color-picker");
            var hex = document.getElementById("color");
            if (!picker || !hex) return;

            function normalize(value) {
              value = String(value || "").trim();
              if (value.charAt(0) !== "#") value = "#" + value;
              if (!/^#[0-9A-Fa-f]{6}$/.test(value)) return null;
              return value.toLowerCase();
            }

            picker.addEventListener("input", function () {
              hex.value = picker.value;
            });

            hex.addEventListener("input", function () {
              var next = normalize(hex.value);
              if (next) picker.value = next;
            });

            hex.addEventListener("blur", function () {
              var next = normalize(hex.value);
              if (next) {
                hex.value = next;
                picker.value = next;
              }
            });
          })();
        </script>
<?php
require dirname(__DIR__, 2) . '/includes/partials/admin_shell_end.php';

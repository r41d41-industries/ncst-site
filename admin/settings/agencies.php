<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
auth_require();

$error = null;
$flash = flash_get('success');
$agencies = incident_agencies_list();
if ($agencies === []) {
    $agencies = [''];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $error = 'Invalid request. Please try again.';
    } else {
        $posted = $_POST['agency'] ?? [];
        if (!is_array($posted)) {
            $posted = [];
        }
        $agencies = array_map(static fn($v): string => trim((string) $v), $posted);
        if ($agencies === []) {
            $agencies = [''];
        }
        try {
            incident_agencies_save($posted);
            flash_set('success', 'Agencies saved.');
            redirect('/admin/settings/agencies.php');
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$adminPageTitle = 'Settings — Agencies';
$adminSection = 'settings';
$adminPanelTitle = 'Settings';
$adminShowAdd = false;
$adminSettingsPage = 'agencies';

require dirname(__DIR__, 2) . '/includes/partials/admin_shell_start.php';
?>
        <div class="admin-content__header">
          <div>
            <h1>Agencies</h1>
            <p class="admin-content__lead">Quick-fill values for the incident agency field. Use <code>ORG</code> or <code>BADGE|ORG</code> format.</p>
          </div>
        </div>

        <?php if ($flash): ?>
          <div class="tu-alert tu-alert--success" role="status"><?= e($flash) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="tu-alert tu-alert--danger" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="tu-card">
          <form method="post" action="" id="agencies-form">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <div class="tu-table-wrap">
              <table class="tu-table" id="agencies-table">
                <thead>
                  <tr>
                    <th scope="col">Agency value</th>
                    <th scope="col" data-sortable="false"><span class="admin-sr-only">Remove</span></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($agencies as $agency): ?>
                    <tr>
                      <td>
                        <input
                          type="text"
                          name="agency[]"
                          maxlength="128"
                          placeholder="NCSO or 123|NPD"
                          value="<?= e((string) $agency) ?>"
                          aria-label="Agency value"
                        >
                      </td>
                      <td>
                        <button type="button" class="tu-btn tu-btn--secondary" data-agency-remove>Remove</button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <div class="tu-btn-row" style="margin-top:1rem;">
              <button type="button" class="tu-btn tu-btn--secondary" id="agency-add">Add agency</button>
              <button type="submit" class="tu-btn tu-btn--brand">Save agencies</button>
            </div>
          </form>
        </div>

        <template id="agency-row-template">
          <tr>
            <td>
              <input type="text" name="agency[]" maxlength="128" placeholder="NCSO or 123|NPD" value="" aria-label="Agency value">
            </td>
            <td>
              <button type="button" class="tu-btn tu-btn--secondary" data-agency-remove>Remove</button>
            </td>
          </tr>
        </template>
        <script>
          (function () {
            var table = document.getElementById('agencies-table');
            var tbody = table && table.querySelector('tbody');
            var addBtn = document.getElementById('agency-add');
            var tpl = document.getElementById('agency-row-template');
            if (!tbody || !addBtn || !tpl) return;

            addBtn.addEventListener('click', function () {
              tbody.appendChild(tpl.content.cloneNode(true));
            });

            tbody.addEventListener('click', function (e) {
              var btn = e.target.closest('[data-agency-remove]');
              if (!btn) return;
              var row = btn.closest('tr');
              if (!row) return;
              if (tbody.querySelectorAll('tr').length <= 1) {
                var input = row.querySelector('input');
                if (input) input.value = '';
                return;
              }
              row.remove();
            });
          })();
        </script>
<?php
require dirname(__DIR__, 2) . '/includes/partials/admin_shell_end.php';

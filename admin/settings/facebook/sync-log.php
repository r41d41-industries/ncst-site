<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
auth_require();

try {
    facebook_ensure_sync_logs_schema();
} catch (Throwable) {
    // Best-effort; page still loads with empty state / error.
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;
$sources = facebook_sync_log_sources();

try {
    $list = facebook_sync_logs_list($page, $perPage);
} catch (Throwable $e) {
    $list = [
        'rows' => [],
        'total' => 0,
        'page' => 1,
        'per_page' => $perPage,
        'pages' => 1,
    ];
    $loadError = $e->getMessage();
}

$adminPageTitle = 'Settings — Facebook Sync log';
$adminSection = 'settings';
$adminPanelTitle = 'Settings';
$adminShowAdd = false;
$adminSettingsPage = 'facebook-sync-log';

require dirname(__DIR__, 3) . '/includes/partials/admin_shell_start.php';
?>
        <div class="admin-content__header">
          <div>
            <h1>Facebook Sync log</h1>
            <p class="admin-content__lead">History of manual, cron, and auto-post sync runs with post, comment, trigger, and failure counts.</p>
          </div>
        </div>

        <?php if (!empty($loadError)): ?>
          <div class="tu-alert tu-alert--danger" role="alert"><?= e($loadError) ?></div>
        <?php endif; ?>

        <div class="tu-card">
          <div class="tu-table-wrap">
            <table class="tu-table" aria-label="Facebook sync log">
              <thead>
                <tr>
                  <th scope="col">Ran at</th>
                  <th scope="col">Source</th>
                  <th scope="col">Created</th>
                  <th scope="col">Updated</th>
                  <th scope="col">New comments</th>
                  <th scope="col">Triggers</th>
                  <th scope="col">Failures</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($list['rows'] === []): ?>
                  <tr>
                    <td colspan="7">No sync runs logged yet.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($list['rows'] as $row): ?>
                    <?php
                      $sourceKey = (string) ($row['source'] ?? '');
                      $sourceLabel = $sources[$sourceKey] ?? $sourceKey;
                      $failures = (int) ($row['failures'] ?? 0);
                      $errorMessage = trim((string) ($row['error_message'] ?? ''));
                      $ok = (int) ($row['ok'] ?? 1) === 1;
                    ?>
                    <tr>
                      <td><?= e((string) ($row['ran_at'] ?? '')) ?></td>
                      <td><?= e($sourceLabel) ?></td>
                      <td><?= (int) ($row['posts_created'] ?? 0) ?></td>
                      <td><?= (int) ($row['posts_updated'] ?? 0) ?></td>
                      <td><?= (int) ($row['comments_new'] ?? 0) ?></td>
                      <td><?= (int) ($row['triggers_processed'] ?? 0) ?></td>
                      <td>
                        <?php if ($failures > 0 || !$ok): ?>
                          <span><?= $failures > 0 ? $failures : 1 ?></span>
                          <?php if ($errorMessage !== ''): ?>
                            <span class="tu-help" style="display:block;margin:0.25rem 0 0;"><?= e($errorMessage) ?></span>
                          <?php endif; ?>
                        <?php else: ?>
                          0
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <?php if ((int) $list['pages'] > 1): ?>
            <div class="tu-btn-row" style="margin-top:1rem;align-items:center;">
              <?php if ((int) $list['page'] > 1): ?>
                <a class="tu-btn tu-btn--secondary" href="/admin/settings/facebook/sync-log.php?page=<?= (int) $list['page'] - 1 ?>">Previous</a>
              <?php endif; ?>
              <p class="tu-help" style="margin:0;">
                Page <?= (int) $list['page'] ?> of <?= (int) $list['pages'] ?>
                (<?= (int) $list['total'] ?> run<?= (int) $list['total'] === 1 ? '' : 's' ?>)
              </p>
              <?php if ((int) $list['page'] < (int) $list['pages']): ?>
                <a class="tu-btn tu-btn--secondary" href="/admin/settings/facebook/sync-log.php?page=<?= (int) $list['page'] + 1 ?>">Next</a>
              <?php endif; ?>
            </div>
          <?php elseif ((int) $list['total'] > 0): ?>
            <p class="tu-help" style="margin-top:1rem;">
              <?= (int) $list['total'] ?> run<?= (int) $list['total'] === 1 ? '' : 's' ?>
            </p>
          <?php endif; ?>
        </div>
<?php
require dirname(__DIR__, 3) . '/includes/partials/admin_shell_end.php';

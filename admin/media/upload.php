<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
auth_require();

// Thin entry that hosts the upload modal; redirects to library after close/reload.
$adminPageTitle = 'Upload media';
$adminSection = 'media';
$adminPanelTitle = 'Media';
$adminShowAdd = false;
$adminMediaPage = 'all';

require dirname(__DIR__, 2) . '/includes/partials/admin_shell_start.php';
?>
        <div class="admin-content__header">
          <div>
            <h1>Upload media</h1>
            <p class="admin-content__lead">Add images, audio, or documents to the library. Crop and metadata are available on each item after upload.</p>
          </div>
          <a class="tu-btn tu-btn--secondary" href="/admin/media/">Back to library</a>
        </div>

        <div class="tu-card">
          <p class="tu-help" style="margin-top:0;">Use the upload dialog for bulk drag-and-drop with per-file progress.</p>
          <button type="button" class="tu-btn tu-btn--brand" data-media-upload-open id="media-upload-auto-open">Upload media</button>
        </div>
        <script>
          document.addEventListener("DOMContentLoaded", function () {
            var btn = document.getElementById("media-upload-auto-open");
            if (btn) btn.click();
          });
        </script>
<?php
require dirname(__DIR__, 2) . '/includes/partials/admin_shell_end.php';

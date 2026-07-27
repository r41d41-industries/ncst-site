</main>
    </div>
  </div>
  <?php require __DIR__ . '/admin_account_modal.php'; ?>
  <?php require __DIR__ . '/media_upload_modal.php'; ?>
  <?php require __DIR__ . '/media_picker.php'; ?>
  <?php if (!empty($adminExtraScripts)) {
      echo $adminExtraScripts;
  } ?>
  <script src="/assets/js/admin.js" defer></script>
  <script src="/assets/js/admin-tables.js" defer></script>
  <script src="/assets/js/admin-media-upload.js" defer></script>
  <script src="/assets/js/admin-media.js" defer></script>
</body>
</html>

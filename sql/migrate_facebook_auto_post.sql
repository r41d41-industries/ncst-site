-- Facebook auto-post mode: track which Page comments have been applied to CMS posts.
-- Safe to run on fresh installs; use apply_facebook_auto_post.php for idempotent apply.

ALTER TABLE `CS_facebook_comments`
  ADD COLUMN `applied_at` DATETIME DEFAULT NULL AFTER `last_synced_at`,
  ADD KEY `idx_cs_facebook_comments_applied` (`facebook_post_id`, `applied_at`);

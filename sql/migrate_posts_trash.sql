-- Soft-delete / trash for CS_posts.
-- Safe to run once on existing installs.

ALTER TABLE `CS_posts`
  ADD COLUMN `trashed_at` DATETIME DEFAULT NULL AFTER `published`,
  ADD KEY `idx_cs_posts_trashed_at` (`trashed_at`);

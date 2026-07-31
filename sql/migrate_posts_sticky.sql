-- Sticky posts: pin to top of public feed.
-- Safe to run on fresh installs; use apply_posts_sticky.php for idempotent apply.

ALTER TABLE `CS_posts`
  ADD COLUMN `is_sticky` TINYINT(1) NOT NULL DEFAULT 0 AFTER `published`,
  ADD KEY `idx_cs_posts_published_sticky_created` (`published`, `is_sticky`, `created_at`);

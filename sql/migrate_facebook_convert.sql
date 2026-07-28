-- Link converted Facebook posts to CMS feed posts.
-- Safe to run once; use apply_facebook_convert.php for idempotent apply.

ALTER TABLE `CS_facebook_posts`
  ADD COLUMN `cs_post_id` INT UNSIGNED DEFAULT NULL AFTER `is_new`,
  ADD UNIQUE KEY `uq_cs_facebook_posts_cs_post_id` (`cs_post_id`);

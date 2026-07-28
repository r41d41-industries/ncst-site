-- Facebook comments synced from Graph API for converted Page posts.
-- Safe to run on fresh installs; use apply_facebook_comments.php for idempotent apply.

CREATE TABLE IF NOT EXISTS `CS_facebook_comments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `facebook_post_id` INT UNSIGNED NOT NULL,
  `fb_comment_id` VARCHAR(64) NOT NULL,
  `message` TEXT DEFAULT NULL,
  `from_id` VARCHAR(64) DEFAULT NULL,
  `from_name` VARCHAR(255) DEFAULT NULL,
  `is_page` TINYINT(1) NOT NULL DEFAULT 0,
  `fb_created_time` DATETIME DEFAULT NULL,
  `last_synced_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `raw_json` MEDIUMTEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cs_facebook_comments_fb_comment_id` (`fb_comment_id`),
  KEY `idx_cs_facebook_comments_post_created` (`facebook_post_id`, `fb_created_time`),
  KEY `idx_cs_facebook_comments_post_page` (`facebook_post_id`, `is_page`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

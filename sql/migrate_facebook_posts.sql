-- Facebook Page posts synced from Graph API for admin review.
-- Safe to run on fresh installs; use apply_facebook_posts.php for idempotent apply.

CREATE TABLE IF NOT EXISTS `CS_facebook_posts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `fb_post_id` VARCHAR(64) NOT NULL,
  `message` TEXT DEFAULT NULL,
  `permalink_url` VARCHAR(1024) DEFAULT NULL,
  `status_type` VARCHAR(64) DEFAULT NULL,
  `full_picture` VARCHAR(1024) DEFAULT NULL,
  `fb_created_time` DATETIME DEFAULT NULL,
  `fb_updated_time` DATETIME DEFAULT NULL,
  `is_new` TINYINT(1) NOT NULL DEFAULT 1,
  `first_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_synced_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `raw_json` MEDIUMTEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cs_facebook_posts_fb_post_id` (`fb_post_id`),
  KEY `idx_cs_facebook_posts_created` (`fb_created_time`),
  KEY `idx_cs_facebook_posts_is_new` (`is_new`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

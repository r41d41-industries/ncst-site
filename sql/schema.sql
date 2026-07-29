-- NCST Main Feed — CS_* tables only. Do not ALTER/DROP unprefixed tables.
-- Charset: utf8mb4

CREATE TABLE IF NOT EXISTS `CS_users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(64) NOT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `display_name` VARCHAR(128) DEFAULT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cs_users_username` (`username`),
  UNIQUE KEY `uq_cs_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `CS_password_resets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cs_password_resets_token_hash` (`token_hash`),
  KEY `idx_cs_password_resets_user` (`user_id`),
  KEY `idx_cs_password_resets_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `CS_categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(32) NOT NULL,
  `name` VARCHAR(64) NOT NULL,
  `template` VARCHAR(16) NOT NULL,
  `color` CHAR(7) NOT NULL DEFAULT '#f7931e',
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_filter` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cs_categories_slug` (`slug`),
  KEY `idx_cs_categories_sort` (`sort_order`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `CS_settings` (
  `setting_key` VARCHAR(64) NOT NULL,
  `setting_value` TEXT DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `CS_shortcodes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) NOT NULL,
  `replacement` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cs_shortcodes_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `CS_posts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category` VARCHAR(32) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `body` TEXT NOT NULL,
  `article_body` TEXT DEFAULT NULL,
  `footnotes` JSON DEFAULT NULL,
  `update_label` VARCHAR(64) DEFAULT NULL,
  `update_text` VARCHAR(255) DEFAULT NULL,
  `agency` VARCHAR(64) DEFAULT NULL,
  `dispatched_at` DATETIME DEFAULT NULL,
  `cleared_at` DATETIME DEFAULT NULL,
  `recorded_at` DATETIME DEFAULT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  `dispatched_text` VARCHAR(255) DEFAULT NULL,
  `status_text` VARCHAR(255) DEFAULT NULL,
  `image_path` VARCHAR(512) DEFAULT NULL,
  `image_media_id` INT UNSIGNED DEFAULT NULL,
  `facebook_url` VARCHAR(512) DEFAULT NULL,
  `x_url` VARCHAR(512) DEFAULT NULL,
  `read_more_url` VARCHAR(512) DEFAULT NULL,
  `og_title` VARCHAR(255) DEFAULT NULL,
  `og_description` TEXT DEFAULT NULL,
  `og_image_path` VARCHAR(512) DEFAULT NULL,
  `og_image_media_id` INT UNSIGNED DEFAULT NULL,
  `gallery_id` INT UNSIGNED DEFAULT NULL,
  `playlist_id` INT UNSIGNED DEFAULT NULL,
  `published` TINYINT(1) NOT NULL DEFAULT 0,
  `trashed_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cs_posts_published_created` (`published`, `created_at`),
  KEY `idx_cs_posts_category` (`category`),
  KEY `idx_cs_posts_trashed_at` (`trashed_at`),
  KEY `idx_cs_posts_image_media` (`image_media_id`),
  KEY `idx_cs_posts_og_image_media` (`og_image_media_id`),
  KEY `idx_cs_posts_gallery` (`gallery_id`),
  KEY `idx_cs_posts_playlist` (`playlist_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `CS_media` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kind` ENUM('image','audio','document') NOT NULL,
  `path` VARCHAR(512) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `mime` VARCHAR(128) NOT NULL,
  `size_bytes` INT UNSIGNED NOT NULL DEFAULT 0,
  `title` VARCHAR(255) DEFAULT NULL,
  `alt_text` VARCHAR(255) DEFAULT NULL,
  `caption` VARCHAR(512) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `width` INT UNSIGNED DEFAULT NULL,
  `height` INT UNSIGNED DEFAULT NULL,
  `duration_seconds` INT UNSIGNED DEFAULT NULL,
  `uploaded_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cs_media_kind` (`kind`),
  KEY `idx_cs_media_created` (`created_at`),
  KEY `idx_cs_media_uploaded_by` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `CS_galleries` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cs_galleries_updated` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `CS_gallery_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `gallery_id` INT UNSIGNED NOT NULL,
  `media_id` INT UNSIGNED NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `caption` VARCHAR(512) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cs_gallery_items_gallery` (`gallery_id`, `sort_order`, `id`),
  KEY `idx_cs_gallery_items_media` (`media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `CS_playlists` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cs_playlists_updated` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `CS_playlist_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `playlist_id` INT UNSIGNED NOT NULL,
  `media_id` INT UNSIGNED NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `title` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cs_playlist_items_playlist` (`playlist_id`, `sort_order`, `id`),
  KEY `idx_cs_playlist_items_media` (`media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `CS_post_updates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` INT UNSIGNED NOT NULL,
  `label` VARCHAR(64) DEFAULT NULL,
  `body` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cs_post_updates_post_created` (`post_id`, `created_at`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `cs_post_id` INT UNSIGNED DEFAULT NULL,
  `first_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_synced_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `raw_json` MEDIUMTEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cs_facebook_posts_fb_post_id` (`fb_post_id`),
  UNIQUE KEY `uq_cs_facebook_posts_cs_post_id` (`cs_post_id`),
  KEY `idx_cs_facebook_posts_created` (`fb_created_time`),
  KEY `idx_cs_facebook_posts_is_new` (`is_new`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `applied_at` DATETIME DEFAULT NULL,
  `raw_json` MEDIUMTEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cs_facebook_comments_fb_comment_id` (`fb_comment_id`),
  KEY `idx_cs_facebook_comments_post_created` (`facebook_post_id`, `fb_created_time`),
  KEY `idx_cs_facebook_comments_post_page` (`facebook_post_id`, `is_page`),
  KEY `idx_cs_facebook_comments_applied` (`facebook_post_id`, `applied_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `CS_facebook_sync_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ran_at` DATETIME NOT NULL,
  `source` VARCHAR(16) NOT NULL,
  `posts_created` INT UNSIGNED NOT NULL DEFAULT 0,
  `posts_updated` INT UNSIGNED NOT NULL DEFAULT 0,
  `comments_new` INT UNSIGNED NOT NULL DEFAULT 0,
  `triggers_processed` INT UNSIGNED NOT NULL DEFAULT 0,
  `failures` INT UNSIGNED NOT NULL DEFAULT 0,
  `ok` TINYINT(1) NOT NULL DEFAULT 1,
  `error_message` TEXT DEFAULT NULL,
  `details_json` MEDIUMTEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cs_facebook_sync_logs_ran_at` (`ran_at`),
  KEY `idx_cs_facebook_sync_logs_source` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

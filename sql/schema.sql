-- NCST Main Feed — CS_* tables only. Do not ALTER/DROP unprefixed tables.
-- Charset: utf8mb4

CREATE TABLE IF NOT EXISTS `CS_users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(64) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cs_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `CS_posts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category` VARCHAR(32) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `body` TEXT NOT NULL,
  `article_body` TEXT DEFAULT NULL,
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
  `facebook_url` VARCHAR(512) DEFAULT NULL,
  `x_url` VARCHAR(512) DEFAULT NULL,
  `read_more_url` VARCHAR(512) DEFAULT NULL,
  `published` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cs_posts_published_created` (`published`, `created_at`),
  KEY `idx_cs_posts_category` (`category`)
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

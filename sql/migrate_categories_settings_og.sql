-- Categories, site settings, and per-post Open Graph overrides.
-- Safe to run once on existing installs.

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

ALTER TABLE `CS_posts`
  ADD COLUMN `og_title` VARCHAR(255) DEFAULT NULL AFTER `read_more_url`,
  ADD COLUMN `og_description` TEXT DEFAULT NULL AFTER `og_title`,
  ADD COLUMN `og_image_path` VARCHAR(512) DEFAULT NULL AFTER `og_description`;

INSERT INTO `CS_categories` (`slug`, `name`, `template`, `color`, `sort_order`, `is_filter`)
VALUES
  ('NEWS', 'NEWS', 'news', '#2563eb', 10, 1),
  ('UPDATES', 'UPDATES', 'news', '#7c3aed', 20, 1),
  ('TRAFFIC', 'TRAFFIC', 'incident', '#f7931e', 30, 1),
  ('CRIME', 'CRIME', 'incident', '#8d4f00', 40, 1),
  ('FIRE', 'FIRE', 'incident', '#ba1a1a', 50, 1),
  ('WEATHER', 'WEATHER', 'weather', '#0284c7', 60, 1)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `template` = VALUES(`template`),
  `color` = VALUES(`color`),
  `sort_order` = VALUES(`sort_order`),
  `is_filter` = VALUES(`is_filter`);

INSERT INTO `CS_settings` (`setting_key`, `setting_value`)
VALUES
  ('og_title', 'NCST Main Feed'),
  ('og_description', 'Newnan Coweta Scanner Traffic — live scanner feed for Newnan and Coweta County.'),
  ('og_site_name', 'Newnan Coweta Scanner Traffic'),
  ('og_type', 'website'),
  ('og_image_path', NULL)
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;

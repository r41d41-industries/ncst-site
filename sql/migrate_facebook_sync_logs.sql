-- Facebook sync run log for admin Sync log page.
-- Safe to run on fresh installs; use apply_facebook_sync_logs.php for idempotent apply.

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

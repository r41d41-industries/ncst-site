-- Add email/display_name to CS_users and password-reset tokens.
-- Safe to run once on existing installs; skip manually if columns already exist.

ALTER TABLE `CS_users`
  ADD COLUMN `email` VARCHAR(255) DEFAULT NULL AFTER `username`,
  ADD COLUMN `display_name` VARCHAR(128) DEFAULT NULL AFTER `email`,
  ADD UNIQUE KEY `uq_cs_users_email` (`email`);

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

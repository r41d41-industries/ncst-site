-- Shortcodes + post footnotes JSON.
-- Safe to run once on existing installs.

CREATE TABLE IF NOT EXISTS `CS_shortcodes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) NOT NULL,
  `replacement` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cs_shortcodes_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `CS_shortcodes` (`code`, `replacement`)
VALUES ('current time', '__NOW__')
ON DUPLICATE KEY UPDATE `code` = `code`;

ALTER TABLE `CS_posts`
  ADD COLUMN `footnotes` JSON DEFAULT NULL AFTER `article_body`;

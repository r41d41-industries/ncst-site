-- Timeline updates for feed posts (CS_* only)

CREATE TABLE IF NOT EXISTS `CS_post_updates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` INT UNSIGNED NOT NULL,
  `label` VARCHAR(64) DEFAULT NULL,
  `body` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cs_post_updates_post_created` (`post_id`, `created_at`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copy legacy single-update fields into timeline (once)
INSERT INTO `CS_post_updates` (`post_id`, `label`, `body`, `created_at`)
SELECT p.`id`, p.`update_label`, p.`update_text`, COALESCE(p.`updated_at`, p.`created_at`)
FROM `CS_posts` p
WHERE (
  (p.`update_label` IS NOT NULL AND TRIM(p.`update_label`) <> '')
  OR (p.`update_text` IS NOT NULL AND TRIM(p.`update_text`) <> '')
)
AND NOT EXISTS (
  SELECT 1 FROM `CS_post_updates` u WHERE u.`post_id` = p.`id`
);

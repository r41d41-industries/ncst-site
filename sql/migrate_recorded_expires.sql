-- News layout meta line: RECORDED: … | EXPIRES: …
ALTER TABLE `CS_posts`
  ADD COLUMN `recorded_at` DATETIME DEFAULT NULL AFTER `cleared_at`,
  ADD COLUMN `expires_at` DATETIME DEFAULT NULL AFTER `recorded_at`;

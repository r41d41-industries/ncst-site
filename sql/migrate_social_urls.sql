-- Add optional social links for feed share buttons (Simple Icons).
-- Safe to run on existing CS_posts; ignores if columns already exist via manual check.

ALTER TABLE `CS_posts`
  ADD COLUMN `facebook_url` VARCHAR(512) DEFAULT NULL AFTER `image_path`,
  ADD COLUMN `x_url` VARCHAR(512) DEFAULT NULL AFTER `facebook_url`;

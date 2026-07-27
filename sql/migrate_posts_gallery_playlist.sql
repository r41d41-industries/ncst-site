-- Associate galleries and playlists with posts.
-- Prefer: php sql/apply_posts_gallery_playlist.php (idempotent).

ALTER TABLE `CS_posts`
  ADD COLUMN `gallery_id` INT UNSIGNED DEFAULT NULL AFTER `og_image_media_id`,
  ADD COLUMN `playlist_id` INT UNSIGNED DEFAULT NULL AFTER `gallery_id`,
  ADD KEY `idx_cs_posts_gallery` (`gallery_id`),
  ADD KEY `idx_cs_posts_playlist` (`playlist_id`);

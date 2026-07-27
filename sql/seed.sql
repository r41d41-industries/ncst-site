-- Seed data for NCST Main Feed (CS_* only)
-- Default admin: username `admin` / password `changeme` — change after first login.
-- Email comes from ADMIN_EMAIL in .env when applied by operators; seed uses a documented default.
-- Agency format: "ORG" or "BADGE|ORG" (e.g. CRIME|NPD / CCSO)
-- One published placeholder per topic, no images, created_at 8 hours apart (newest first).

INSERT INTO `CS_users` (`username`, `email`, `display_name`, `password_hash`)
VALUES (
  'admin',
  'contact@r41d41.com',
  'Admin',
  '$2y$12$NM9lKTE7XrDcNUKO4l8o.uZATJjFUoCEnQBM.wmcC5HDUgKJlyZUy'
)
ON DUPLICATE KEY UPDATE
  `email` = COALESCE(VALUES(`email`), `email`),
  `display_name` = COALESCE(VALUES(`display_name`), `display_name`);

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

DELETE FROM `CS_post_updates`;
DELETE FROM `CS_posts`;

INSERT INTO `CS_posts` (
  `category`, `title`, `body`,
  `agency`, `dispatched_at`, `cleared_at`, `recorded_at`, `expires_at`,
  `image_path`, `read_more_url`, `published`, `created_at`
) VALUES
(
  'NEWS',
  'Placeholder News Post',
  'This is a placeholder news post with no image. Replace with a real headline and body when ready.',
  NULL, NULL, NULL, NULL, NULL,
  NULL, NULL, 1,
  NOW()
),
(
  'UPDATES',
  'Placeholder Updates Post',
  'This is a placeholder updates post with no image. Replace with a real headline and body when ready.',
  NULL, NULL, NULL, NULL, NULL,
  NULL, NULL, 1,
  NOW() - INTERVAL 8 HOUR
),
(
  'TRAFFIC',
  'Placeholder Traffic Post',
  'This is a placeholder traffic incident post with no image. Units responding; details to follow.',
  'UNKNOWN', NOW() - INTERVAL 16 HOUR, NULL, NULL, NULL,
  NULL, NULL, 1,
  NOW() - INTERVAL 16 HOUR
),
(
  'CRIME',
  'Placeholder Crime Post',
  'This is a placeholder crime incident post with no image. Officers on scene; details to follow.',
  'UNKNOWN', NOW() - INTERVAL 24 HOUR, NULL, NULL, NULL,
  NULL, NULL, 1,
  NOW() - INTERVAL 24 HOUR
),
(
  'FIRE',
  'Placeholder Fire Post',
  'This is a placeholder fire incident post with no image. Units on scene; details to follow.',
  'UNKNOWN', NOW() - INTERVAL 32 HOUR, NULL, NULL, NULL,
  NULL, NULL, 1,
  NOW() - INTERVAL 32 HOUR
),
(
  'WEATHER',
  'Placeholder Weather Post',
  'This is a placeholder weather post with no image. Stay tuned for watches, warnings, and guidance.',
  NULL, NULL, NULL, NULL, NULL,
  NULL, NULL, 1,
  NOW() - INTERVAL 40 HOUR
);

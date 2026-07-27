-- Add long-form article body for internal news/weather article pages.
ALTER TABLE `CS_posts`
  ADD COLUMN `article_body` TEXT DEFAULT NULL AFTER `body`;

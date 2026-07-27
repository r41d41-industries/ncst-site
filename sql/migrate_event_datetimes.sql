-- Datetime fields for dispatched / cleared (CS_* only)

ALTER TABLE `CS_posts`
  ADD COLUMN `dispatched_at` DATETIME DEFAULT NULL AFTER `agency`,
  ADD COLUMN `cleared_at` DATETIME DEFAULT NULL AFTER `dispatched_at`;

-- Best-effort backfill from free-text fields when they look like "TODAY AT h:mm AM/PM"
UPDATE `CS_posts`
SET `dispatched_at` = STR_TO_DATE(
  CONCAT(DATE(`created_at`), ' ', TRIM(REGEXP_REPLACE(`dispatched_text`, '^(?i)(DISPATCHED:\\s*)?(TODAY AT\\s*)?', ''))),
  '%Y-%m-%d %h:%i %p'
)
WHERE `dispatched_at` IS NULL
  AND `dispatched_text` IS NOT NULL
  AND `dispatched_text` REGEXP '(?i)[0-9]{1,2}:[0-9]{2}\\s*[AP]M';

UPDATE `CS_posts`
SET `cleared_at` = STR_TO_DATE(
  CONCAT(DATE(`created_at`), ' ', TRIM(REGEXP_REPLACE(`status_text`, '^(?i)(CLEARED:|STATUS:)\\s*', ''))),
  '%Y-%m-%d %h:%i %p'
)
WHERE `cleared_at` IS NULL
  AND `status_text` IS NOT NULL
  AND UPPER(TRIM(`status_text`)) NOT IN ('UNKNOWN', 'CLEARED: UNKNOWN')
  AND `status_text` REGEXP '(?i)[0-9]{1,2}:[0-9]{2}\\s*[AP]M';

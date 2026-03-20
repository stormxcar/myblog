-- Add slug column to posts for SEO-friendly URLs
ALTER TABLE `posts`
ADD COLUMN `slug` VARCHAR(255) NULL AFTER `title`;

-- Backfill slug for existing rows (safe for MySQL 5.7+/MariaDB)
UPDATE `posts`
SET `slug` = CONCAT('post-', `id`)
WHERE `slug` IS NULL OR `slug` = '';

-- Ensure uniqueness and lookup performance
ALTER TABLE `posts`
ADD UNIQUE KEY `uniq_posts_slug` (`slug`),
ADD KEY `idx_posts_slug` (`slug`);

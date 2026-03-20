-- Community module migration
-- Run this file on the same database used by the blog application.

CREATE TABLE IF NOT EXISTS `community_posts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `user_name` VARCHAR(120) NOT NULL,
    `content` TEXT NOT NULL,
    `privacy` ENUM('public','followers','private') NOT NULL DEFAULT 'public',
    `status` ENUM('published','draft','hidden','deleted') NOT NULL DEFAULT 'published',
    `total_reactions` INT UNSIGNED NOT NULL DEFAULT 0,
    `total_comments` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_community_posts_user_id` (`user_id`),
    KEY `idx_community_posts_status_created_at` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `community_posts`
    MODIFY COLUMN `privacy` ENUM('public','followers','private') NOT NULL DEFAULT 'public',
    MODIFY COLUMN `status` ENUM('published','draft','hidden','deleted') NOT NULL DEFAULT 'published';

CREATE TABLE IF NOT EXISTS `community_post_media` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `post_id` INT UNSIGNED NOT NULL,
    `media_type` ENUM('image') NOT NULL DEFAULT 'image',
    `file_path` VARCHAR(255) NOT NULL,
    `original_name` VARCHAR(255) DEFAULT NULL,
    `sort_order` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_community_post_media_post_id` (`post_id`),
    CONSTRAINT `fk_community_post_media_post` FOREIGN KEY (`post_id`) REFERENCES `community_posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `community_post_links` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `post_id` INT UNSIGNED NOT NULL,
    `url` VARCHAR(500) NOT NULL,
    `host` VARCHAR(120) DEFAULT NULL,
    `title` VARCHAR(255) DEFAULT NULL,
    `description` VARCHAR(500) DEFAULT NULL,
    `preview_image` VARCHAR(500) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_community_post_url` (`post_id`, `url`(191)),
    KEY `idx_community_post_links_post_id` (`post_id`),
    CONSTRAINT `fk_community_post_links_post` FOREIGN KEY (`post_id`) REFERENCES `community_posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `community_post_reactions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `post_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `reaction` TINYINT NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_community_post_user_reaction` (`post_id`, `user_id`),
    KEY `idx_community_post_reactions_post_id` (`post_id`),
    CONSTRAINT `fk_community_post_reactions_post` FOREIGN KEY (`post_id`) REFERENCES `community_posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `community_post_comments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `post_id` INT UNSIGNED NOT NULL,
    `parent_comment_id` INT UNSIGNED NULL DEFAULT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `user_name` VARCHAR(120) NOT NULL,
    `comment` TEXT NOT NULL,
    `status` ENUM('active','deleted') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_community_post_comments_post_id` (`post_id`),
    KEY `idx_community_post_comments_parent` (`parent_comment_id`),
    CONSTRAINT `fk_community_post_comments_post` FOREIGN KEY (`post_id`) REFERENCES `community_posts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_community_post_comments_parent` FOREIGN KEY (`parent_comment_id`) REFERENCES `community_post_comments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `community_topics` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug` VARCHAR(80) NOT NULL,
    `name` VARCHAR(120) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_community_topics_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `community_post_topics` (
    `post_id` INT UNSIGNED NOT NULL,
    `topic_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`post_id`, `topic_id`),
    KEY `idx_community_post_topics_topic_id` (`topic_id`),
    CONSTRAINT `fk_community_post_topics_post` FOREIGN KEY (`post_id`) REFERENCES `community_posts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_community_post_topics_topic` FOREIGN KEY (`topic_id`) REFERENCES `community_topics` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

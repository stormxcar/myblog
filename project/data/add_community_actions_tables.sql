-- Saved posts + report tables for community feed actions

CREATE TABLE IF NOT EXISTS `community_saved_posts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `post_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_community_saved_post_user` (`post_id`, `user_id`),
    KEY `idx_community_saved_posts_user_id` (`user_id`),
    CONSTRAINT `fk_community_saved_posts_post` FOREIGN KEY (`post_id`) REFERENCES `community_posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `community_post_reports` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `post_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `reason` VARCHAR(1000) DEFAULT NULL,
    `status` ENUM('pending','reviewed','dismissed') NOT NULL DEFAULT 'pending',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_community_post_report_user` (`post_id`, `user_id`),
    KEY `idx_community_post_reports_post_id` (`post_id`),
    KEY `idx_community_post_reports_status` (`status`),
    CONSTRAINT `fk_community_post_reports_post` FOREIGN KEY (`post_id`) REFERENCES `community_posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

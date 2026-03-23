<?php

if (!function_exists('community_ensure_tables')) {
    function community_ensure_tables($conn)
    {
        static $initialized = false;
        if ($initialized) {
            return;
        }

        $conn->exec("CREATE TABLE IF NOT EXISTS `community_posts` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `user_name` VARCHAR(120) NOT NULL,
            `post_title` VARCHAR(300) NOT NULL,
            `post_type` ENUM('text','media','link','poll') NOT NULL DEFAULT 'text',
            `content` TEXT NOT NULL,
            `privacy` ENUM('public','followers','private') NOT NULL DEFAULT 'public',
            `status` ENUM('published','draft','hidden','deleted') NOT NULL DEFAULT 'published',
            `total_reactions` INT UNSIGNED NOT NULL DEFAULT 0,
            `total_upvotes` INT UNSIGNED NOT NULL DEFAULT 0,
            `total_downvotes` INT UNSIGNED NOT NULL DEFAULT 0,
            `vote_score` INT NOT NULL DEFAULT 0,
            `total_comments` INT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_community_posts_user_id` (`user_id`),
            KEY `idx_community_posts_status_created_at` (`status`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Upgrade-safe: ensure old schemas support private posts and draft status.
        try {
            $conn->exec("ALTER TABLE `community_posts` MODIFY COLUMN `privacy` ENUM('public','followers','private') NOT NULL DEFAULT 'public'");
            $conn->exec("ALTER TABLE `community_posts` MODIFY COLUMN `status` ENUM('published','draft','hidden','deleted') NOT NULL DEFAULT 'published'");
            $conn->exec("ALTER TABLE `community_posts` MODIFY COLUMN `post_type` ENUM('text','media','link','poll') NOT NULL DEFAULT 'text'");
        } catch (Exception $e) {
            // Ignore ALTER failures in restricted environments; table can still operate with existing enum.
        }

        $ensureColumn = function ($column, $sql) use ($conn) {
            try {
                if (function_exists('blog_db_has_column') && blog_db_has_column($conn, 'community_posts', $column)) {
                    return;
                }
                $conn->exec($sql);
            } catch (Exception $e) {
                // Ignore schema drift errors; this runs as a best-effort migration.
            }
        };

        $ensureColumn('total_upvotes', "ALTER TABLE `community_posts` ADD COLUMN `total_upvotes` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `total_reactions`");
        $ensureColumn('total_downvotes', "ALTER TABLE `community_posts` ADD COLUMN `total_downvotes` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `total_upvotes`");
        $ensureColumn('vote_score', "ALTER TABLE `community_posts` ADD COLUMN `vote_score` INT NOT NULL DEFAULT 0 AFTER `total_downvotes`");
        $ensureColumn('post_title', "ALTER TABLE `community_posts` ADD COLUMN `post_title` VARCHAR(300) NOT NULL DEFAULT '' AFTER `user_name`");
        $ensureColumn('post_type', "ALTER TABLE `community_posts` ADD COLUMN `post_type` ENUM('text','media','link','poll') NOT NULL DEFAULT 'text' AFTER `post_title`");

        try {
            $conn->exec("UPDATE community_posts SET post_title = TRIM(SUBSTRING_INDEX(content, '\\n', 1)) WHERE post_title = '' OR post_title IS NULL");
        } catch (Exception $e) {
            // Best-effort backfill only.
        }

        $conn->exec("CREATE TABLE IF NOT EXISTS `community_post_media` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $conn->exec("CREATE TABLE IF NOT EXISTS `community_post_links` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $conn->exec("CREATE TABLE IF NOT EXISTS `community_post_reactions` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `post_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `reaction` TINYINT NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_community_post_user_reaction` (`post_id`, `user_id`),
            KEY `idx_community_post_reactions_post_id` (`post_id`),
            CONSTRAINT `fk_community_post_reactions_post` FOREIGN KEY (`post_id`) REFERENCES `community_posts` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $conn->exec("CREATE TABLE IF NOT EXISTS `community_post_comments` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $conn->exec("CREATE TABLE IF NOT EXISTS `community_topics` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `slug` VARCHAR(80) NOT NULL,
            `name` VARCHAR(120) NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_community_topics_slug` (`slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $conn->exec("CREATE TABLE IF NOT EXISTS `community_post_topics` (
            `post_id` INT UNSIGNED NOT NULL,
            `topic_id` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`post_id`, `topic_id`),
            KEY `idx_community_post_topics_topic_id` (`topic_id`),
            CONSTRAINT `fk_community_post_topics_post` FOREIGN KEY (`post_id`) REFERENCES `community_posts` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_community_post_topics_topic` FOREIGN KEY (`topic_id`) REFERENCES `community_topics` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $conn->exec("CREATE TABLE IF NOT EXISTS `community_saved_posts` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `post_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_community_saved_post_user` (`post_id`, `user_id`),
            KEY `idx_community_saved_posts_user_id` (`user_id`),
            CONSTRAINT `fk_community_saved_posts_post` FOREIGN KEY (`post_id`) REFERENCES `community_posts` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $conn->exec("CREATE TABLE IF NOT EXISTS `community_user_follows` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `follower_user_id` INT UNSIGNED NOT NULL,
            `following_user_id` INT UNSIGNED NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_community_follow_pair` (`follower_user_id`, `following_user_id`),
            KEY `idx_community_following_user` (`following_user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $conn->exec("CREATE TABLE IF NOT EXISTS `community_user_pins` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `post_id` INT UNSIGNED NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_community_user_pin` (`user_id`, `post_id`),
            KEY `idx_community_user_pin_post` (`post_id`),
            CONSTRAINT `fk_community_user_pin_post` FOREIGN KEY (`post_id`) REFERENCES `community_posts` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $conn->exec("CREATE TABLE IF NOT EXISTS `community_notification_preferences` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `follow_events_enabled` TINYINT(1) NOT NULL DEFAULT 1,
            `new_post_events_enabled` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_community_notification_pref_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $conn->exec("CREATE TABLE IF NOT EXISTS `community_post_reports` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $conn->exec("CREATE TABLE IF NOT EXISTS `community_poll_options` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `post_id` INT UNSIGNED NOT NULL,
            `option_text` VARCHAR(255) NOT NULL,
            `sort_order` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_community_poll_options_post` (`post_id`),
            CONSTRAINT `fk_community_poll_options_post` FOREIGN KEY (`post_id`) REFERENCES `community_posts` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $conn->exec("CREATE TABLE IF NOT EXISTS `community_poll_votes` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `post_id` INT UNSIGNED NOT NULL,
            `option_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_community_poll_vote` (`post_id`, `user_id`),
            KEY `idx_community_poll_votes_option` (`option_id`),
            KEY `idx_community_poll_votes_post` (`post_id`),
            CONSTRAINT `fk_community_poll_votes_post` FOREIGN KEY (`post_id`) REFERENCES `community_posts` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_community_poll_votes_option` FOREIGN KEY (`option_id`) REFERENCES `community_poll_options` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $conn->exec("CREATE TABLE IF NOT EXISTS `notification_digest_preferences` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `frequency` ENUM('daily','weekly','off') NOT NULL DEFAULT 'daily',
            `enabled` TINYINT(1) NOT NULL DEFAULT 1,
            `last_sent_at` TIMESTAMP NULL DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_digest_user` (`user_id`),
            KEY `idx_digest_enabled_frequency` (`enabled`, `frequency`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $initialized = true;
    }
}

if (!function_exists('community_visibility_badge')) {
    function community_visibility_badge($privacy, $status)
    {
        $privacy = (string)$privacy;
        $status = (string)$status;
        if ($status === 'draft' || $privacy === 'private') {
            return 'Chi minh toi (Ban nhap)';
        }
        if ($privacy === 'followers') {
            return 'Follower';
        }
        return 'Public';
    }
}

if (!function_exists('community_get_follower_counts_by_author_ids')) {
    function community_get_follower_counts_by_author_ids($conn, array $authorIds)
    {
        $authorIds = array_values(array_unique(array_filter(array_map('intval', $authorIds), function ($id) {
            return $id > 0;
        })));

        if (empty($authorIds)) {
            return [];
        }

        $counts = array_fill_keys($authorIds, 0);
        $placeholders = implode(',', array_fill(0, count($authorIds), '?'));

        $stmt = $conn->prepare("SELECT following_user_id, COUNT(*) AS follower_count
            FROM community_user_follows
            WHERE following_user_id IN ({$placeholders})
            GROUP BY following_user_id");
        $stmt->execute($authorIds);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $authorId = (int)($row['following_user_id'] ?? 0);
            if ($authorId > 0) {
                $counts[$authorId] = (int)($row['follower_count'] ?? 0);
            }
        }

        return $counts;
    }
}

if (!function_exists('community_get_viewer_following_author_map')) {
    function community_get_viewer_following_author_map($conn, $viewerUserId, array $authorIds)
    {
        $viewerUserId = (int)$viewerUserId;
        $authorIds = array_values(array_unique(array_filter(array_map('intval', $authorIds), function ($id) {
            return $id > 0;
        })));

        $map = [];
        foreach ($authorIds as $authorId) {
            $map[$authorId] = false;
        }

        if ($viewerUserId <= 0 || empty($authorIds)) {
            return $map;
        }

        $placeholders = implode(',', array_fill(0, count($authorIds), '?'));
        $params = array_merge([$viewerUserId], $authorIds);
        $stmt = $conn->prepare("SELECT following_user_id
            FROM community_user_follows
            WHERE follower_user_id = ? AND following_user_id IN ({$placeholders})");
        $stmt->execute($params);

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $followedAuthorId) {
            $authorId = (int)$followedAuthorId;
            if (isset($map[$authorId])) {
                $map[$authorId] = true;
            }
        }

        return $map;
    }
}

if (!function_exists('community_get_authors_following_viewer_map')) {
    function community_get_authors_following_viewer_map($conn, $viewerUserId, array $authorIds)
    {
        $viewerUserId = (int)$viewerUserId;
        $authorIds = array_values(array_unique(array_filter(array_map('intval', $authorIds), function ($id) {
            return $id > 0;
        })));

        $map = [];
        foreach ($authorIds as $authorId) {
            $map[$authorId] = false;
        }

        if ($viewerUserId <= 0 || empty($authorIds)) {
            return $map;
        }

        $placeholders = implode(',', array_fill(0, count($authorIds), '?'));
        $params = array_merge([$viewerUserId], $authorIds);
        $stmt = $conn->prepare("SELECT follower_user_id
            FROM community_user_follows
            WHERE following_user_id = ? AND follower_user_id IN ({$placeholders})");
        $stmt->execute($params);

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $authorWhoFollowedViewer) {
            $authorId = (int)$authorWhoFollowedViewer;
            if (isset($map[$authorId])) {
                $map[$authorId] = true;
            }
        }

        return $map;
    }
}

if (!function_exists('community_get_follower_user_ids')) {
    function community_get_follower_user_ids($conn, $targetUserId)
    {
        $targetUserId = (int)$targetUserId;
        if ($targetUserId <= 0) {
            return [];
        }

        $stmt = $conn->prepare('SELECT follower_user_id FROM community_user_follows WHERE following_user_id = ?');
        $stmt->execute([$targetUserId]);
        return array_values(array_unique(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
    }
}

if (!function_exists('community_normalize_url')) {
    function community_normalize_url($rawUrl)
    {
        $rawUrl = trim((string)$rawUrl);
        if ($rawUrl === '') {
            return '';
        }

        if (!preg_match('/^https?:\/\//i', $rawUrl)) {
            $rawUrl = 'https://' . $rawUrl;
        }

        $sanitized = filter_var($rawUrl, FILTER_SANITIZE_URL);
        if (!filter_var($sanitized, FILTER_VALIDATE_URL)) {
            return '';
        }

        return $sanitized;
    }
}

if (!function_exists('community_extract_links_from_textarea')) {
    function community_extract_links_from_textarea($raw)
    {
        $lines = preg_split('/\r\n|\r|\n/', (string)$raw);
        $links = [];

        foreach ($lines as $line) {
            $url = community_normalize_url($line);
            if ($url !== '') {
                $links[] = $url;
            }
        }

        return array_values(array_unique($links));
    }
}

if (!function_exists('community_slugify_topic')) {
    function community_slugify_topic($value)
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }

        if (function_exists('mb_strtolower')) {
            $value = mb_strtolower($value, 'UTF-8');
        } else {
            $value = strtolower($value);
        }

        $value = preg_replace('/[^\pL\pN]+/u', '-', $value);
        $value = trim((string)$value, '-');
        $value = mb_substr($value, 0, 80, 'UTF-8');

        return $value;
    }
}

if (!function_exists('community_extract_topics_from_text')) {
    function community_extract_topics_from_text($text)
    {
        $topics = [];
        $text = trim((string)$text);
        if ($text === '') {
            return [];
        }

        if (preg_match_all('/#([\pL\pN_\-]{2,60})/u', $text, $matches)) {
            foreach ($matches[1] as $rawTag) {
                $name = trim(str_replace('_', ' ', (string)$rawTag));
                $slug = community_slugify_topic($name);
                if ($slug === '' || mb_strlen($slug, 'UTF-8') < 2) {
                    continue;
                }
                $topics[$slug] = mb_substr($name, 0, 120, 'UTF-8');
            }
        }

        if (empty($topics)) {
            $words = preg_split('/\s+/u', preg_replace('/[^\pL\pN\s]+/u', ' ', $text));
            foreach ($words as $word) {
                $word = trim((string)$word);
                if ($word === '' || mb_strlen($word, 'UTF-8') < 4) {
                    continue;
                }
                $slug = community_slugify_topic($word);
                if ($slug !== '' && !isset($topics[$slug])) {
                    $topics[$slug] = mb_substr($word, 0, 120, 'UTF-8');
                }
                if (count($topics) >= 3) {
                    break;
                }
            }
        }

        return array_slice($topics, 0, 5, true);
    }
}

if (!function_exists('community_attach_topics_to_post')) {
    function community_attach_topics_to_post($conn, $postId, $content)
    {
        $postId = (int)$postId;
        if ($postId <= 0) {
            return;
        }

        $deleteMapStmt = $conn->prepare('DELETE FROM community_post_topics WHERE post_id = ?');
        $deleteMapStmt->execute([$postId]);

        $topics = community_extract_topics_from_text($content);
        if (empty($topics)) {
            return;
        }

        $insertTopicStmt = $conn->prepare('INSERT INTO community_topics (slug, name) VALUES (?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name)');
        $fetchTopicStmt = $conn->prepare('SELECT id FROM community_topics WHERE slug = ? LIMIT 1');
        $insertMapStmt = $conn->prepare('INSERT IGNORE INTO community_post_topics (post_id, topic_id) VALUES (?, ?)');

        foreach ($topics as $slug => $name) {
            $insertTopicStmt->execute([$slug, $name]);
            $fetchTopicStmt->execute([$slug]);
            $topicId = (int)$fetchTopicStmt->fetchColumn();
            if ($topicId > 0) {
                $insertMapStmt->execute([$postId, $topicId]);
            }
        }
    }
}

if (!function_exists('community_get_trending_topics')) {
    function community_get_trending_topics($conn, $userId = 0, $limit = 8)
    {
        $userId = (int)$userId;
        $limit = max(1, min(20, (int)$limit));

        $sql = "SELECT t.slug, t.name, COUNT(*) AS post_count
            FROM community_topics t
            INNER JOIN community_post_topics pt ON pt.topic_id = t.id
            INNER JOIN community_posts p ON p.id = pt.post_id
            WHERE (p.status = 'published'";

        $params = [];
        if ($userId > 0) {
            $sql .= " OR (p.status = 'draft' AND p.user_id = ?)";
            $params[] = $userId;
        }

        $sql .= ')';

        $sql .= " GROUP BY t.id, t.slug, t.name
                  ORDER BY post_count DESC, t.name ASC
                  LIMIT {$limit}";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('community_fetch_link_metadata')) {
    function community_fetch_link_metadata($url)
    {
        $url = (string)$url;
        $host = parse_url($url, PHP_URL_HOST);
        $result = [
            'url' => $url,
            'host' => $host ? mb_substr((string)$host, 0, 120, 'UTF-8') : null,
            'title' => null,
            'description' => null,
            'preview_image' => null,
        ];

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 4,
                'follow_location' => 1,
                'max_redirects' => 2,
                'user_agent' => 'MyBlogMetadataBot/1.0',
            ],
        ]);

        $html = @file_get_contents($url, false, $context, 0, 220000);
        if (!is_string($html) || $html === '') {
            return $result;
        }

        $extractMeta = function ($property, $isName = false) use ($html) {
            $quoted = preg_quote($property, '/');
            $attr = $isName ? 'name' : 'property';
            $patternA = '/<meta[^>]*' . $attr . '\s*=\s*["\']' . $quoted . '["\'][^>]*content\s*=\s*["\']([^"\']+)["\'][^>]*>/i';
            $patternB = '/<meta[^>]*content\s*=\s*["\']([^"\']+)["\'][^>]*' . $attr . '\s*=\s*["\']' . $quoted . '["\'][^>]*>/i';
            if (preg_match($patternA, $html, $m) || preg_match($patternB, $html, $m)) {
                return trim(html_entity_decode((string)$m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
            return null;
        };

        $title = null;
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $titleMatch)) {
            $title = trim(html_entity_decode(strip_tags((string)$titleMatch[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        $ogTitle = $extractMeta('og:title');
        $ogDescription = $extractMeta('og:description');
        $metaDescription = $extractMeta('description', true);
        $ogImage = $extractMeta('og:image');

        $result['title'] = $ogTitle ?: $title;
        $result['description'] = $ogDescription ?: $metaDescription;
        $result['preview_image'] = $ogImage ?: null;

        if ($result['title'] !== null) {
            $result['title'] = mb_substr((string)$result['title'], 0, 255, 'UTF-8');
        }
        if ($result['description'] !== null) {
            $result['description'] = mb_substr((string)$result['description'], 0, 500, 'UTF-8');
        }
        if ($result['preview_image'] !== null) {
            $result['preview_image'] = mb_substr((string)$result['preview_image'], 0, 500, 'UTF-8');
        }

        return $result;
    }
}

if (!function_exists('community_sync_post_counters')) {
    function community_sync_post_counters($conn, $postId)
    {
        $postId = (int)$postId;
        if ($postId <= 0) {
            return;
        }

        $reactionStmt = $conn->prepare('SELECT
            COALESCE(SUM(CASE WHEN reaction = 1 THEN 1 ELSE 0 END), 0) AS upvotes,
            COALESCE(SUM(CASE WHEN reaction = -1 THEN 1 ELSE 0 END), 0) AS downvotes,
            COALESCE(SUM(reaction), 0) AS vote_score,
            COUNT(*) AS total_reactions
            FROM community_post_reactions WHERE post_id = ?');
        $reactionStmt->execute([$postId]);
        $reactionRow = $reactionStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $totalUpvotes = (int)($reactionRow['upvotes'] ?? 0);
        $totalDownvotes = (int)($reactionRow['downvotes'] ?? 0);
        $voteScore = (int)($reactionRow['vote_score'] ?? 0);
        $totalReactions = (int)($reactionRow['total_reactions'] ?? 0);

        $commentStmt = $conn->prepare("SELECT COUNT(*) FROM community_post_comments WHERE post_id = ? AND status = 'active'");
        $commentStmt->execute([$postId]);
        $totalComments = (int)$commentStmt->fetchColumn();

        try {
            $updateStmt = $conn->prepare('UPDATE community_posts SET total_reactions = ?, total_upvotes = ?, total_downvotes = ?, vote_score = ?, total_comments = ? WHERE id = ?');
            $updateStmt->execute([$totalReactions, $totalUpvotes, $totalDownvotes, $voteScore, $totalComments, $postId]);
        } catch (Exception $e) {
            $fallbackStmt = $conn->prepare('UPDATE community_posts SET total_reactions = ?, total_comments = ? WHERE id = ?');
            $fallbackStmt->execute([$totalReactions, $totalComments, $postId]);
        }
    }
}

if (!function_exists('community_render_comment_item')) {
    function community_render_comment_item(array $comment, $canReply = true, $isReply = false, $childrenHtml = '', $replyCount = 0)
    {
        $commentId = (int)($comment['id'] ?? 0);
        $author = (string)($comment['user_name'] ?? 'User');
        $bodyRaw = html_entity_decode((string)($comment['comment'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $body = nl2br(htmlspecialchars($bodyRaw, ENT_QUOTES, 'UTF-8'));
        $time = community_time_ago((string)($comment['created_at'] ?? ''));
        $initial = strtoupper(mb_substr($author, 0, 1, 'UTF-8'));
        // Replies are visually indented to the right (left padding) in the comment thread
        $replyClass = $isReply ? ' ml-10 border-l-4 border-main/40 pl-5' : '';
        $replyAction = '';
        $replyLabel = $isReply ? '<span class="text-xs text-gray-500 dark:text-gray-400 mr-2">↳ Reply</span>' : '';

        if ($canReply && !$isReply) {
            $replyAction = '<button type="button" class="text-xs text-main hover:underline" onclick="communityReplyToComment(' . $commentId . ')">Tra loi</button>';
        }

        $html = '<div class="rounded-xl bg-gray-50 dark:bg-gray-700 p-3' . $replyClass . '" data-community-comment-id="' . $commentId . '">'
            . '<div class="flex items-center justify-between gap-2">'
            . '<div class="flex items-center gap-2">'
            . '<div class="w-8 h-8 rounded-full bg-main text-white text-xs font-semibold flex items-center justify-center">' . htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') . '</div>'
            . '<div><p class="text-sm font-semibold text-gray-900 dark:text-white">' . $replyLabel . htmlspecialchars($author, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p class="text-[11px] text-gray-500 dark:text-gray-400">' . htmlspecialchars($time, ENT_QUOTES, 'UTF-8') . '</p></div></div>'
            . $replyAction
            . '</div>'
            . '<div class="mt-2 text-sm text-gray-700 dark:text-gray-200 leading-relaxed">' . $body . '</div>';

        if (!$isReply) {
            $replyCount = max(0, (int)$replyCount);
            if ($replyCount > 0) {
                $html .= '<div class="mt-2"><button type="button" class="text-xs text-main hover:underline" data-community-replies-toggle data-target="community-replies-' . $commentId . '" onclick="toggleCommunityReplies(' . $commentId . ', this)">Ẩn phản hồi (' . $replyCount . ')</button></div>';
            }
            $html .= '<div id="community-replies-' . $commentId . '" class="mt-3 space-y-2" data-community-replies-parent="' . $commentId . '">' . $childrenHtml . '</div>';
        } elseif ($childrenHtml !== '') {
            $html .= '<div class="mt-3 space-y-2">' . $childrenHtml . '</div>';
        }

        $html .= '</div>';

        return $html;
    }
}

if (!function_exists('community_time_ago')) {
    function community_time_ago($dateTimeString)
    {
        $timestamp = strtotime((string)$dateTimeString);
        if ($timestamp === false) {
            return 'Vua xong';
        }

        $delta = time() - $timestamp;
        if ($delta < 60) {
            return 'Vua xong';
        }
        if ($delta < 3600) {
            return floor($delta / 60) . ' phut truoc';
        }
        if ($delta < 86400) {
            return floor($delta / 3600) . ' gio truoc';
        }
        if ($delta < 604800) {
            return floor($delta / 86400) . ' ngay truoc';
        }
        if ($delta < 2592000) {
            return floor($delta / 604800) . ' tuan truoc';
        }
        if ($delta < 31536000) {
            return floor($delta / 2592000) . ' thang truoc';
        }

        return floor($delta / 31536000) . ' nam truoc';
    }
}

if (!function_exists('community_extract_title')) {
    function community_extract_title($content)
    {
        $raw = trim((string)html_entity_decode((string)$content, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($raw === '') {
            return 'Bai dang cong dong';
        }

        $lines = preg_split('/\R/u', $raw) ?: [];
        $title = trim((string)($lines[0] ?? ''));
        if ($title === '') {
            $title = preg_replace('/\s+/u', ' ', strip_tags($raw));
        }
        $title = trim((string)$title);
        if ($title === '') {
            return 'Bai dang cong dong';
        }

        return mb_substr($title, 0, 120, 'UTF-8');
    }
}

if (!function_exists('community_extract_body')) {
    function community_extract_body($content)
    {
        $content = trim((string)$content);
        if ($content === '') {
            return '';
        }

        $lines = preg_split('/\r\n|\r|\n/', $content);
        if (!is_array($lines) || empty($lines)) {
            return $content;
        }

        $firstLine = trim((string)$lines[0]);
        $title = community_extract_title($content);

        $normalize = function ($text) {
            $text = trim((string)$text);
            $text = preg_replace('/\s+/u', ' ', $text);
            $text = preg_replace('/["\'“”‘’.,:;!?()\[\]{}]+/u', '', (string)$text);
            return mb_strtolower((string)$text, 'UTF-8');
        };

        if ($firstLine !== '' && $normalize($firstLine) === $normalize($title)) {
            array_shift($lines);
        }

        $body = trim(implode("\n", $lines));
        return $body === '' ? $content : $body;
    }
}

if (!function_exists('community_resolve_topic_theme')) {
    function community_resolve_topic_theme($slug)
    {
        $slug = trim((string)$slug);
        $themes = [
            'technology' => ['card' => 'bg-slate-100 dark:bg-slate-800', 'badge' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-200', 'accent' => 'text-sky-600'],
            'tech' => ['card' => 'bg-slate-100 dark:bg-slate-800', 'badge' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-200', 'accent' => 'text-sky-600'],
            'ai' => ['card' => 'bg-indigo-50 dark:bg-indigo-950', 'badge' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-200', 'accent' => 'text-indigo-600'],
            'business' => ['card' => 'bg-amber-50 dark:bg-amber-950', 'badge' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-200', 'accent' => 'text-amber-600'],
            'news' => ['card' => 'bg-yellow-50 dark:bg-yellow-950', 'badge' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-200', 'accent' => 'text-yellow-600'],
            'lifestyle' => ['card' => 'bg-pink-50 dark:bg-pink-950', 'badge' => 'bg-pink-100 text-pink-700 dark:bg-pink-900/40 dark:text-pink-200', 'accent' => 'text-pink-600'],
            'fashion' => ['card' => 'bg-fuchsia-50 dark:bg-fuchsia-950', 'badge' => 'bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-900/40 dark:text-fuchsia-200', 'accent' => 'text-fuchsia-600'],
            'food' => ['card' => 'bg-orange-50 dark:bg-orange-950', 'badge' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-200', 'accent' => 'text-orange-600'],
            'travel' => ['card' => 'bg-emerald-50 dark:bg-emerald-950', 'badge' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200', 'accent' => 'text-emerald-600'],
            'education' => ['card' => 'bg-cyan-50 dark:bg-cyan-950', 'badge' => 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/40 dark:text-cyan-200', 'accent' => 'text-cyan-600'],
            'sports' => ['card' => 'bg-lime-50 dark:bg-lime-950', 'badge' => 'bg-lime-100 text-lime-700 dark:bg-lime-900/40 dark:text-lime-200', 'accent' => 'text-lime-700'],
            'music' => ['card' => 'bg-violet-50 dark:bg-violet-950', 'badge' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-200', 'accent' => 'text-violet-600'],
            'gaming' => ['card' => 'bg-purple-50 dark:bg-purple-950', 'badge' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-200', 'accent' => 'text-purple-600'],
            'health' => ['card' => 'bg-teal-50 dark:bg-teal-950', 'badge' => 'bg-teal-100 text-teal-700 dark:bg-teal-900/40 dark:text-teal-200', 'accent' => 'text-teal-600'],
            'design' => ['card' => 'bg-rose-50 dark:bg-rose-950', 'badge' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-200', 'accent' => 'text-rose-600'],
        ];

        if ($slug !== '' && isset($themes[$slug])) {
            return $themes[$slug];
        }

        return ['card' => 'bg-gray-100 dark:bg-gray-800', 'badge' => 'bg-main/10 text-main', 'accent' => 'text-main'];
    }
}

if (!function_exists('community_set_digest_preference')) {
    function community_set_digest_preference($conn, $userId, $frequency)
    {
        $userId = (int)$userId;
        $frequency = trim((string)$frequency);
        if (!in_array($frequency, ['daily', 'weekly', 'off'], true) || $userId <= 0) {
            return false;
        }

        $enabled = $frequency === 'off' ? 0 : 1;
        $stmt = $conn->prepare("INSERT INTO notification_digest_preferences (user_id, frequency, enabled)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE frequency = VALUES(frequency), enabled = VALUES(enabled), updated_at = CURRENT_TIMESTAMP");
        return $stmt->execute([$userId, $frequency, $enabled]);
    }
}

if (!function_exists('community_get_digest_preference')) {
    function community_get_digest_preference($conn, $userId)
    {
        $userId = (int)$userId;
        if ($userId <= 0) {
            return ['frequency' => 'off', 'enabled' => 0];
        }

        $stmt = $conn->prepare('SELECT frequency, enabled, last_sent_at FROM notification_digest_preferences WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['frequency' => 'daily', 'enabled' => 1, 'last_sent_at' => null];
        }
        return [
            'frequency' => (string)($row['frequency'] ?? 'daily'),
            'enabled' => (int)($row['enabled'] ?? 1),
            'last_sent_at' => $row['last_sent_at'] ?? null,
        ];
    }
}

if (!function_exists('community_get_weekly_top_contributor_ids')) {
    function community_get_weekly_top_contributor_ids($conn, $limit = 5)
    {
        $limit = max(1, min(20, (int)$limit));
        $sql = "SELECT s.user_id
            FROM (
                SELECT p.user_id, (COUNT(*) * 3) AS points
                FROM community_posts p
                WHERE p.status = 'published' AND p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY p.user_id
                UNION ALL
                SELECT c.user_id, COUNT(*) AS points
                FROM community_post_comments c
                WHERE c.status = 'active' AND c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY c.user_id
                UNION ALL
                SELECT p.user_id, COUNT(*) AS points
                FROM community_posts p
                INNER JOIN community_post_reactions r ON r.post_id = p.id AND r.reaction = 1
                WHERE p.status IN ('published', 'draft', 'hidden') AND r.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY p.user_id
            ) s
            GROUP BY s.user_id
            ORDER BY SUM(s.points) DESC, s.user_id ASC
            LIMIT {$limit}";

        $stmt = $conn->prepare($sql);
        $stmt->execute();
        return array_values(array_unique(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
    }
}

if (!function_exists('community_build_user_badges_map')) {
    function community_build_user_badges_map($conn, array $userIds)
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), function ($id) {
            return $id > 0;
        })));

        if (empty($userIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));

        $postCounts = [];
        $postStmt = $conn->prepare("SELECT user_id, COUNT(*) AS total_posts FROM community_posts WHERE user_id IN ({$placeholders}) AND status IN ('published', 'draft', 'hidden') GROUP BY user_id");
        $postStmt->execute($userIds);
        foreach ($postStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $postCounts[(int)$row['user_id']] = (int)$row['total_posts'];
        }

        $upvoteCounts = [];
        $upvoteStmt = $conn->prepare("SELECT p.user_id, COALESCE(SUM(CASE WHEN r.reaction = 1 THEN 1 ELSE 0 END), 0) AS total_upvotes
            FROM community_posts p
            LEFT JOIN community_post_reactions r ON r.post_id = p.id
            WHERE p.user_id IN ({$placeholders}) AND p.status IN ('published', 'draft', 'hidden')
            GROUP BY p.user_id");
        $upvoteStmt->execute($userIds);
        foreach ($upvoteStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $upvoteCounts[(int)$row['user_id']] = (int)$row['total_upvotes'];
        }

        $weeklyTopIds = community_get_weekly_top_contributor_ids($conn, 5);
        $weeklyTopLookup = [];
        foreach ($weeklyTopIds as $uid) {
            $weeklyTopLookup[(int)$uid] = true;
        }

        $badgesMap = [];
        foreach ($userIds as $uid) {
            $badges = [];
            if ((int)($postCounts[$uid] ?? 0) >= 10) {
                $badges[] = [
                    'key' => 'post10',
                    'label' => '10 bai',
                    'class' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-200'
                ];
            }
            if ((int)($upvoteCounts[$uid] ?? 0) >= 100) {
                $badges[] = [
                    'key' => 'upvote100',
                    'label' => '100 upvote',
                    'class' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-200'
                ];
            }
            if (!empty($weeklyTopLookup[$uid])) {
                $badges[] = [
                    'key' => 'weekly_top',
                    'label' => 'Top tuan',
                    'class' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200'
                ];
            }
            $badgesMap[$uid] = $badges;
        }

        return $badgesMap;
    }
}

if (!function_exists('community_get_featured_posts_24h')) {
    function community_get_featured_posts_24h($conn, $limit = 5)
    {
        $limit = max(1, min(20, (int)$limit));
        $stmt = $conn->prepare("SELECT p.id, p.post_title, p.content, p.total_upvotes, p.total_comments, p.created_at
            FROM community_posts p
            WHERE p.status = 'published' AND p.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY p.vote_score DESC, p.total_upvotes DESC, p.total_comments DESC, p.created_at DESC
            LIMIT {$limit}");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('community_get_posts_near_user_interests')) {
    function community_get_posts_near_user_interests($conn, $userId, $limit = 5)
    {
        $userId = (int)$userId;
        $limit = max(1, min(20, (int)$limit));
        if ($userId <= 0) {
            return [];
        }

        $topicStmt = $conn->prepare("SELECT t.slug, t.name, COUNT(*) AS affinity
            FROM community_topics t
            INNER JOIN community_post_topics pt ON pt.topic_id = t.id
            INNER JOIN community_posts p ON p.id = pt.post_id
            LEFT JOIN community_post_reactions r ON r.post_id = p.id AND r.user_id = ?
            LEFT JOIN community_post_comments c ON c.post_id = p.id AND c.user_id = ? AND c.status = 'active'
            LEFT JOIN community_saved_posts s ON s.post_id = p.id AND s.user_id = ?
            WHERE (r.user_id IS NOT NULL OR c.user_id IS NOT NULL OR s.user_id IS NOT NULL)
            GROUP BY t.id, t.slug, t.name
            ORDER BY affinity DESC, t.name ASC
            LIMIT 4");
        $topicStmt->execute([$userId, $userId, $userId]);
        $topicRows = $topicStmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($topicRows)) {
            return [];
        }

        $topicSlugs = array_values(array_filter(array_map(function ($row) {
            return trim((string)($row['slug'] ?? ''));
        }, $topicRows), function ($slug) {
            return $slug !== '';
        }));
        if (empty($topicSlugs)) {
            return [];
        }

        $topicPlaceholders = implode(',', array_fill(0, count($topicSlugs), '?'));
        $postParams = array_merge([$userId], $topicSlugs);
        $postStmt = $conn->prepare("SELECT DISTINCT p.id, p.post_title, p.content, p.created_at, p.total_upvotes, p.total_comments
            FROM community_posts p
            INNER JOIN community_post_topics pt ON pt.post_id = p.id
            INNER JOIN community_topics t ON t.id = pt.topic_id
            WHERE p.status = 'published'
              AND p.user_id <> ?
              AND t.slug IN ({$topicPlaceholders})
            ORDER BY p.created_at DESC
            LIMIT {$limit}");
        $postStmt->execute($postParams);
        return $postStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('community_post_slug')) {
    function community_post_slug($title, $id)
    {
        $id = (int)$id;
        $base = function_exists('slugify') ? slugify((string)$title) : community_slugify_topic((string)$title);
        if ($base === '') {
            $base = 'community-post';
        }
        return $base . '-' . $id;
    }
}

if (!function_exists('community_post_path')) {
    function community_post_path($id, $title = '')
    {
        $id = (int)$id;
        $slug = community_post_slug((string)$title, $id);
        return site_url('community/p/' . rawurlencode($slug));
    }
}

if (!function_exists('community_extract_post_id_from_slug')) {
    function community_extract_post_id_from_slug($slug)
    {
        $slug = trim((string)$slug);
        if ($slug === '') {
            return 0;
        }

        if (preg_match('/^(\d+)$/', $slug, $m)) {
            return (int)$m[1];
        }

        if (preg_match('/-(\d+)$/', $slug, $m)) {
            return (int)$m[1];
        }

        return 0;
    }
}

if (!function_exists('community_profile_path')) {
    function community_profile_path($userId, $name = '')
    {
        $userId = (int)$userId;
        $slugBase = function_exists('slugify') ? slugify((string)$name) : community_slugify_topic((string)$name);
        if ($slugBase === '') {
            $slugBase = 'user';
        }
        return site_url('community/u/' . rawurlencode($slugBase . '-' . $userId));
    }
}

if (!function_exists('community_extract_profile_user_id')) {
    function community_extract_profile_user_id($slug)
    {
        $slug = trim((string)$slug);
        if ($slug === '') {
            return 0;
        }

        if (preg_match('/^(\d+)$/', $slug, $m)) {
            return (int)$m[1];
        }

        if (preg_match('/-(\d+)$/', $slug, $m)) {
            return (int)$m[1];
        }

        return 0;
    }
}

if (!function_exists('community_get_follow_suggestions')) {
    function community_get_follow_suggestions($conn, $viewerUserId, $limit = 6)
    {
        $viewerUserId = (int)$viewerUserId;
        $limit = max(1, min(20, (int)$limit));
        if ($viewerUserId <= 0) {
            return [];
        }

        $sql = "SELECT
                p.user_id,
                p.user_name,
                COUNT(*) AS matched_posts,
                COALESCE(SUM(p.vote_score), 0) AS total_score
            FROM community_posts p
            INNER JOIN community_post_topics pt ON pt.post_id = p.id
            INNER JOIN community_topics t ON t.id = pt.topic_id
            WHERE p.status = 'published'
              AND p.user_id <> :viewer_id
              AND t.slug IN (
                SELECT DISTINCT t2.slug
                FROM community_posts p2
                INNER JOIN community_post_topics pt2 ON pt2.post_id = p2.id
                INNER JOIN community_topics t2 ON t2.id = pt2.topic_id
                LEFT JOIN community_post_reactions r2 ON r2.post_id = p2.id AND r2.user_id = :viewer_react
                LEFT JOIN community_post_comments c2 ON c2.post_id = p2.id AND c2.user_id = :viewer_comment AND c2.status = 'active'
                LEFT JOIN community_saved_posts s2 ON s2.post_id = p2.id AND s2.user_id = :viewer_saved
                WHERE r2.user_id IS NOT NULL OR c2.user_id IS NOT NULL OR s2.user_id IS NOT NULL
              )
              AND NOT EXISTS (
                SELECT 1 FROM community_user_follows f
                WHERE f.follower_user_id = :viewer_follow
                AND f.following_user_id = p.user_id
              )
            GROUP BY p.user_id, p.user_name
            ORDER BY matched_posts DESC, total_score DESC, p.user_id DESC
            LIMIT {$limit}";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':viewer_id', $viewerUserId, PDO::PARAM_INT);
        $stmt->bindValue(':viewer_react', $viewerUserId, PDO::PARAM_INT);
        $stmt->bindValue(':viewer_comment', $viewerUserId, PDO::PARAM_INT);
        $stmt->bindValue(':viewer_saved', $viewerUserId, PDO::PARAM_INT);
        $stmt->bindValue(':viewer_follow', $viewerUserId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('community_get_notification_preference')) {
    function community_get_notification_preference($conn, $userId)
    {
        $userId = (int)$userId;
        if ($userId <= 0) {
            return [
                'follow_events_enabled' => 1,
                'new_post_events_enabled' => 1,
            ];
        }

        $stmt = $conn->prepare('SELECT follow_events_enabled, new_post_events_enabled FROM community_notification_preferences WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return [
                'follow_events_enabled' => 1,
                'new_post_events_enabled' => 1,
            ];
        }

        return [
            'follow_events_enabled' => (int)($row['follow_events_enabled'] ?? 1),
            'new_post_events_enabled' => (int)($row['new_post_events_enabled'] ?? 1),
        ];
    }
}

if (!function_exists('community_set_notification_preference')) {
    function community_set_notification_preference($conn, $userId, array $changes)
    {
        $userId = (int)$userId;
        if ($userId <= 0) {
            return false;
        }

        $followEvents = isset($changes['follow_events_enabled']) ? (int)((int)$changes['follow_events_enabled'] > 0 ? 1 : 0) : null;
        $newPostEvents = isset($changes['new_post_events_enabled']) ? (int)((int)$changes['new_post_events_enabled'] > 0 ? 1 : 0) : null;

        $current = community_get_notification_preference($conn, $userId);
        if ($followEvents === null) {
            $followEvents = (int)$current['follow_events_enabled'];
        }
        if ($newPostEvents === null) {
            $newPostEvents = (int)$current['new_post_events_enabled'];
        }

        $stmt = $conn->prepare('INSERT INTO community_notification_preferences (user_id, follow_events_enabled, new_post_events_enabled)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE follow_events_enabled = VALUES(follow_events_enabled), new_post_events_enabled = VALUES(new_post_events_enabled), updated_at = CURRENT_TIMESTAMP');
        return $stmt->execute([$userId, $followEvents, $newPostEvents]);
    }
}

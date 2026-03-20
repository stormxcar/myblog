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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Upgrade-safe: ensure old schemas support private posts and draft status.
        try {
            $conn->exec("ALTER TABLE `community_posts` MODIFY COLUMN `privacy` ENUM('public','followers','private') NOT NULL DEFAULT 'public'");
            $conn->exec("ALTER TABLE `community_posts` MODIFY COLUMN `status` ENUM('published','draft','hidden','deleted') NOT NULL DEFAULT 'published'");
        } catch (Exception $e) {
            // Ignore ALTER failures in restricted environments; table can still operate with existing enum.
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

        $reactionStmt = $conn->prepare('SELECT COUNT(*) FROM community_post_reactions WHERE post_id = ?');
        $reactionStmt->execute([$postId]);
        $totalReactions = (int)$reactionStmt->fetchColumn();

        $commentStmt = $conn->prepare("SELECT COUNT(*) FROM community_post_comments WHERE post_id = ? AND status = 'active'");
        $commentStmt->execute([$postId]);
        $totalComments = (int)$commentStmt->fetchColumn();

        $updateStmt = $conn->prepare('UPDATE community_posts SET total_reactions = ?, total_comments = ? WHERE id = ?');
        $updateStmt->execute([$totalReactions, $totalComments, $postId]);
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

        $html = '<div class="rounded-xl bg-gray-50 dark:bg-gray-700/40 p-3' . $replyClass . '" data-community-comment-id="' . $commentId . '">'
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

        return floor($delta / 86400) . ' ngay truoc';
    }
}

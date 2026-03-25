<?php

if (!function_exists('site_url')) {
    require_once __DIR__ . '/seo_helpers.php';
}

if (!function_exists('blog_ensure_feature_tables')) {
    function blog_ensure_feature_tables($conn)
    {
        static $initialized = false;
        if ($initialized) {
            return;
        }

        $conn->exec("CREATE TABLE IF NOT EXISTS `comment_votes` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `comment_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `vote` TINYINT NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_comment_user_vote` (`comment_id`, `user_id`),
            KEY `idx_comment_votes_comment_id` (`comment_id`),
            KEY `idx_comment_votes_user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $conn->exec("CREATE TABLE IF NOT EXISTS `notifications` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `type` VARCHAR(32) NOT NULL,
            `title` VARCHAR(160) NOT NULL,
            `message` VARCHAR(255) NOT NULL,
            `link` VARCHAR(255) DEFAULT NULL,
            `is_read` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_notifications_user_read` (`user_id`, `is_read`),
            KEY `idx_notifications_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $conn->exec("CREATE TABLE IF NOT EXISTS `user_read_events` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `post_id` INT UNSIGNED NOT NULL,
            `seconds_read` INT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_user_read_events_user_id` (`user_id`),
            KEY `idx_user_read_events_post_id` (`post_id`),
            KEY `idx_user_read_events_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        if (function_exists('blog_db_has_column') && !blog_db_has_column($conn, 'comments', 'parent_comment_id')) {
            $conn->exec("ALTER TABLE `comments` ADD COLUMN `parent_comment_id` INT UNSIGNED NULL DEFAULT NULL AFTER `id`");
            $conn->exec("ALTER TABLE `comments` ADD KEY `idx_comments_parent_comment_id` (`parent_comment_id`)");
        }

        $initialized = true;
    }
}

if (!function_exists('blog_generate_quick_summary')) {
    function blog_generate_quick_summary($html, $maxWords = 85)
    {
        $plain = trim(preg_replace('/\s+/', ' ', strip_tags((string)$html)));
        if ($plain === '') {
            return 'Bai viet nay chua co noi dung de tom tat.';
        }

        $words = preg_split('/\s+/', $plain);
        if (count($words) <= $maxWords) {
            return $plain;
        }

        return implode(' ', array_slice($words, 0, $maxWords)) . '...';
    }
}

if (!function_exists('blog_answer_from_context')) {
    function blog_answer_from_context($question, $html)
    {
        $plain = trim(preg_replace('/\s+/', ' ', strip_tags((string)$html)));
        $question = trim((string)$question);

        if ($plain === '') {
            return 'Minh chua tim thay noi dung de tra loi trong bai viet nay.';
        }

        $sentences = preg_split('/(?<=[.!?])\s+/', $plain);
        $keywords = array_filter(preg_split('/\s+/', mb_strtolower($question, 'UTF-8')), function ($token) {
            return mb_strlen($token, 'UTF-8') >= 4;
        });

        $bestSentence = '';
        $bestScore = 0;

        foreach ($sentences as $sentence) {
            $score = 0;
            $normalized = mb_strtolower($sentence, 'UTF-8');
            foreach ($keywords as $keyword) {
                if (mb_strpos($normalized, $keyword, 0, 'UTF-8') !== false) {
                    $score++;
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestSentence = $sentence;
            }
        }

        if ($bestScore > 0 && $bestSentence !== '') {
            return 'Theo bai viet: ' . trim($bestSentence);
        }

        return 'Minh chua thay cau tra loi chinh xac trong bai viet. Tom tat nhanh: ' . blog_generate_quick_summary($html, 50);
    }
}

if (!function_exists('blog_detect_spam_or_toxic')) {
    function blog_detect_spam_or_toxic($text)
    {
        $normalized = mb_strtolower(trim((string)$text), 'UTF-8');
        if ($normalized === '') {
            return 'Binh luan khong duoc de trong.';
        }

        if (preg_match('/(.)\1{7,}/u', $normalized)) {
            return 'Binh luan co dau hieu spam lap ky tu.';
        }

        preg_match_all('/https?:\/\//i', $normalized, $urlMatches);
        if (count($urlMatches[0]) > 2) {
            return 'Binh luan chua qua nhieu lien ket, vui long rut gon.';
        }

        $blockedWords = [
            'dm',
            'dit',
            'clm',
            'vkl',
            'fuck',
            'shit',
            'ngu vkl',
            'do ngu',
            'thang cho',
            'con cho'
        ];

        foreach ($blockedWords as $badWord) {
            if (mb_strpos($normalized, $badWord, 0, 'UTF-8') !== false) {
                return 'Binh luan chua tu ngu khong phu hop. Vui long dieu chinh noi dung.';
            }
        }

        return '';
    }
}

if (!function_exists('blog_extract_mentions')) {
    function blog_extract_mentions($text)
    {
        preg_match_all('/@([a-zA-Z0-9_\.]{3,30})/', (string)$text, $matches);
        $names = isset($matches[1]) ? $matches[1] : [];
        $names = array_values(array_unique(array_map('strtolower', $names)));
        return $names;
    }
}

if (!function_exists('blog_push_notification')) {
    function blog_push_notification($conn, $userId, $type, $title, $message, $link = null)
    {
        if ((int)$userId <= 0) {
            return;
        }

        $linkValue = $link;
        if (function_exists('blog_normalize_site_link')) {
            $linkValue = blog_normalize_site_link($link);
        }

        $insert = $conn->prepare('INSERT INTO `notifications` (user_id, type, title, message, link, is_read) VALUES (?, ?, ?, ?, ?, 0)');
        $insert->execute([(int)$userId, (string)$type, (string)$title, (string)$message, $linkValue]);

        $newNotificationId = (int)$conn->lastInsertId();
        $unreadStmt = $conn->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $unreadStmt->execute([(int)$userId]);
        $unreadCount = (int)$unreadStmt->fetchColumn();

        blog_pusher_publish((int)$userId, [
            'event' => 'notification:new',
            'user_id' => (int)$userId,
            'type' => (string)$type,
            'title' => (string)$title,
            'message' => (string)$message,
            'link' => $linkValue,
            'notification_id' => $newNotificationId,
            'unread_count' => $unreadCount,
            'created_at' => date('c')
        ]);
    }
}

if (!function_exists('blog_notify_all_users')) {
    function blog_notify_all_users($conn, $type, $title, $message, $link = null, $excludeUserId = 0)
    {
        $sql = 'SELECT id FROM users WHERE id > 0';
        $params = [];

        if (function_exists('blog_has_admin_role_column') && blog_has_admin_role_column($conn)) {
            $sql .= " AND role != 'admin'";
        }

        if ((int)$excludeUserId > 0) {
            $sql .= ' AND id != ?';
            $params[] = (int)$excludeUserId;
        }

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($users as $row) {
            $uid = (int)($row['id'] ?? 0);
            if ($uid > 0) {
                blog_push_notification($conn, $uid, $type, $title, $message, $link);
            }
        }
    }
}

if (!function_exists('blog_get_pusher_config')) {
    function blog_get_pusher_config()
    {
        $appId = trim((string)(getenv('PUSHER_APP_ID') ?: ''));
        $key = trim((string)(getenv('PUSHER_KEY') ?: ''));
        $secret = trim((string)(getenv('PUSHER_SECRET') ?: ''));
        $cluster = trim((string)(getenv('PUSHER_CLUSTER') ?: ''));

        if ($appId === '' || $key === '' || $secret === '' || $cluster === '') {
            return null;
        }

        return [
            'app_id' => $appId,
            'key' => $key,
            'secret' => $secret,
            'cluster' => $cluster,
            'useTLS' => true,
        ];
    }
}

if (!function_exists('blog_get_pusher_client')) {
    function blog_get_pusher_client()
    {
        static $client = false;

        if ($client !== false) {
            return $client;
        }

        if (!class_exists('Pusher\\Pusher')) {
            $client = null;
            return null;
        }

        $cfg = blog_get_pusher_config();
        if (!is_array($cfg)) {
            $client = null;
            return null;
        }

        try {
            $client = new Pusher\Pusher(
                $cfg['key'],
                $cfg['secret'],
                $cfg['app_id'],
                ['cluster' => $cfg['cluster'], 'useTLS' => true]
            );
            return $client;
        } catch (Exception $e) {
            $client = null;
            return null;
        }
    }
}

if (!function_exists('blog_pusher_publish')) {
    function blog_pusher_publish($userId, $payload)
    {
        $uid = (int)$userId;
        if ($uid <= 0) {
            return;
        }

        $client = blog_get_pusher_client();
        if (!$client) {
            return;
        }

        try {
            $channel = 'private-user-notifications-' . $uid;
            $client->trigger($channel, 'notification:new', $payload);
        } catch (Exception $e) {
            // Keep notification persistence even when realtime push fails.
        }
    }
}

if (!function_exists('blog_user_badge')) {
    function blog_user_badge($interactionScore)
    {
        $score = (int)$interactionScore;
        if ($score >= 120) {
            return ['label' => 'Legend', 'class' => 'bg-purple-100 text-purple-700'];
        }
        if ($score >= 60) {
            return ['label' => 'Pro', 'class' => 'bg-emerald-100 text-emerald-700'];
        }
        if ($score >= 20) {
            return ['label' => 'Active', 'class' => 'bg-blue-100 text-blue-700'];
        }
        return ['label' => 'Newbie', 'class' => 'bg-gray-100 text-gray-600'];
    }
}

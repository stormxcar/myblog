<?php

if (!function_exists('blog_ensure_post_tags_tables')) {
    function blog_ensure_post_tags_tables($conn)
    {
        static $initialized = false;
        if ($initialized) {
            return;
        }

        $conn->exec(
            "CREATE TABLE IF NOT EXISTS `tags` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(80) NOT NULL,
                `slug` VARCHAR(120) NOT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_tags_slug` (`slug`),
                UNIQUE KEY `uniq_tags_name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $conn->exec(
            "CREATE TABLE IF NOT EXISTS `post_tags` (
                `post_id` INT NOT NULL,
                `tag_id` INT NOT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`post_id`, `tag_id`),
                KEY `idx_post_tags_tag_id` (`tag_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $initialized = true;
    }
}

if (!function_exists('blog_normalize_tag_name')) {
    function blog_normalize_tag_name($raw)
    {
        $value = trim((string)$raw);
        $value = ltrim($value, '#');
        $value = preg_replace('/\s+/u', ' ', $value);
        if ($value === '') {
            return '';
        }
        return mb_substr($value, 0, 80, 'UTF-8');
    }
}

if (!function_exists('blog_tag_slugify')) {
    function blog_tag_slugify($name)
    {
        $name = trim(mb_strtolower((string)$name, 'UTF-8'));
        if ($name === '') {
            return '';
        }

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
            if (is_string($converted) && $converted !== '') {
                $name = $converted;
            }
        }

        $name = preg_replace('/[^a-z0-9]+/i', '-', $name);
        $name = trim((string)$name, '-');
        return mb_substr($name, 0, 120, 'UTF-8');
    }
}

if (!function_exists('blog_parse_tags_input')) {
    function blog_parse_tags_input($input)
    {
        $raw = (string)$input;
        $parts = preg_split('/[,\n;]+/u', $raw);
        $tags = [];

        foreach ($parts as $part) {
            $name = blog_normalize_tag_name($part);
            if ($name === '') {
                continue;
            }

            $key = mb_strtolower($name, 'UTF-8');
            if (!isset($tags[$key])) {
                $tags[$key] = $name;
            }

            if (count($tags) >= 20) {
                break;
            }
        }

        return array_values($tags);
    }
}

if (!function_exists('blog_get_or_create_tag_ids')) {
    function blog_get_or_create_tag_ids($conn, array $tagNames)
    {
        blog_ensure_post_tags_tables($conn);
        $tagIds = [];

        foreach ($tagNames as $name) {
            $name = blog_normalize_tag_name($name);
            if ($name === '') {
                continue;
            }

            $slug = blog_tag_slugify($name);
            if ($slug === '') {
                continue;
            }

            $select = $conn->prepare('SELECT id FROM tags WHERE slug = ? LIMIT 1');
            $select->execute([$slug]);
            $row = $select->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $tagIds[] = (int)$row['id'];
                continue;
            }

            $insert = $conn->prepare('INSERT INTO tags (name, slug) VALUES (?, ?)');
            try {
                $insert->execute([$name, $slug]);
                $tagIds[] = (int)$conn->lastInsertId();
            } catch (Throwable $e) {
                $retry = $conn->prepare('SELECT id FROM tags WHERE slug = ? LIMIT 1');
                $retry->execute([$slug]);
                $retryRow = $retry->fetch(PDO::FETCH_ASSOC);
                if ($retryRow) {
                    $tagIds[] = (int)$retryRow['id'];
                }
            }
        }

        return array_values(array_unique(array_map('intval', $tagIds)));
    }
}

if (!function_exists('blog_sync_post_tags')) {
    function blog_sync_post_tags($conn, $postId, array $tagNames)
    {
        blog_ensure_post_tags_tables($conn);
        $postId = (int)$postId;
        if ($postId <= 0) {
            return;
        }

        $tagIds = blog_get_or_create_tag_ids($conn, $tagNames);

        $delete = $conn->prepare('DELETE FROM post_tags WHERE post_id = ?');
        $delete->execute([$postId]);

        if (empty($tagIds)) {
            return;
        }

        $insert = $conn->prepare('INSERT INTO post_tags (post_id, tag_id) VALUES (?, ?)');
        foreach ($tagIds as $tagId) {
            $insert->execute([$postId, (int)$tagId]);
        }
    }
}

if (!function_exists('blog_get_post_tags')) {
    function blog_get_post_tags($conn, $postId)
    {
        blog_ensure_post_tags_tables($conn);
        $stmt = $conn->prepare(
            'SELECT t.id, t.name, t.slug
             FROM post_tags pt
             INNER JOIN tags t ON t.id = pt.tag_id
             WHERE pt.post_id = ?
             ORDER BY t.name ASC'
        );
        $stmt->execute([(int)$postId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('blog_get_tags_map_for_posts')) {
    function blog_get_tags_map_for_posts($conn, array $postIds)
    {
        blog_ensure_post_tags_tables($conn);

        $ids = array_values(array_unique(array_filter(array_map('intval', $postIds), function ($v) {
            return $v > 0;
        })));

        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $conn->prepare(
            "SELECT pt.post_id, t.id, t.name, t.slug
             FROM post_tags pt
             INNER JOIN tags t ON t.id = pt.tag_id
             WHERE pt.post_id IN ({$placeholders})
             ORDER BY t.name ASC"
        );
        $stmt->execute($ids);

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $postId = (int)$row['post_id'];
            if (!isset($map[$postId])) {
                $map[$postId] = [];
            }

            $map[$postId][] = [
                'id' => (int)$row['id'],
                'name' => (string)$row['name'],
                'slug' => (string)$row['slug'],
            ];
        }

        return $map;
    }
}

if (!function_exists('blog_get_post_tag_names_csv')) {
    function blog_get_post_tag_names_csv($conn, $postId)
    {
        $tags = blog_get_post_tags($conn, $postId);
        $names = array_map(function ($row) {
            return (string)($row['name'] ?? '');
        }, $tags);
        $names = array_values(array_filter($names, function ($v) {
            return $v !== '';
        }));
        return implode(', ', $names);
    }
}

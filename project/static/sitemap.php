<?php
include '../components/connect.php';
include '../components/seo_helpers.php';
include '../components/community_engine.php';

header('Content-Type: application/xml; charset=UTF-8');

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base = $scheme . '://' . $host;

$toAbsolute = static function ($path) use ($base) {
    $path = (string)$path;
    if ($path === '') {
        return $base . '/';
    }
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
        return $path;
    }
    return $base . '/' . ltrim($path, '/');
};

$urls = [];

$addUrl = static function (&$urls, $loc, $lastmod = null, $changefreq = null, $priority = null) {
    $urls[] = [
        'loc' => (string)$loc,
        'lastmod' => $lastmod,
        'changefreq' => $changefreq,
        'priority' => $priority,
    ];
};

$now = gmdate('c');
$addUrl($urls, $toAbsolute('/'), $now, 'daily', '1.0');
$addUrl($urls, $toAbsolute(site_url('posts')), $now, 'daily', '0.9');
$addUrl($urls, $toAbsolute(site_url('search')), $now, 'daily', '0.7');
$addUrl($urls, $toAbsolute(site_url('contact')), $now, 'monthly', '0.5');
$addUrl($urls, $toAbsolute(site_url('introduce')), $now, 'monthly', '0.5');
$addUrl($urls, $toAbsolute(site_url('all_photos')), $now, 'weekly', '0.6');
$addUrl($urls, $toAbsolute(site_url('static/community_feed.php')), $now, 'daily', '0.8');

try {
    $stmt = $conn->prepare("SELECT id, title, date FROM posts WHERE status = 'active' ORDER BY id DESC LIMIT 5000");
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $id = (int)($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }

        $title = (string)($row['title'] ?? 'post');
        $postUrl = $toAbsolute(post_path($id, $title));
        $lastmod = null;
        if (!empty($row['date'])) {
            $ts = strtotime((string)$row['date']);
            if ($ts !== false) {
                $lastmod = gmdate('c', $ts);
            }
        }

        $addUrl($urls, $postUrl, $lastmod, 'weekly', '0.8');
    }
} catch (Throwable $e) {
    // Keep sitemap available even if the DB query fails.
}

try {
    community_ensure_tables($conn);

    $communityPostStmt = $conn->prepare("SELECT id, post_title, created_at
        FROM community_posts
        WHERE status = 'published' AND privacy = 'public'
        ORDER BY id DESC
        LIMIT 5000");
    $communityPostStmt->execute();

    foreach ($communityPostStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $id = (int)($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }

        $title = (string)($row['post_title'] ?? 'community-post');
        $loc = $toAbsolute(community_post_path($id, $title));
        $lastmod = null;
        if (!empty($row['created_at'])) {
            $ts = strtotime((string)$row['created_at']);
            if ($ts !== false) {
                $lastmod = gmdate('c', $ts);
            }
        }

        $addUrl($urls, $loc, $lastmod, 'weekly', '0.7');
    }

    $communityProfileStmt = $conn->prepare("SELECT DISTINCT u.id, u.name
        FROM users u
        INNER JOIN community_posts p ON p.user_id = u.id
        WHERE p.status = 'published' AND p.privacy = 'public'
        ORDER BY u.id DESC
        LIMIT 5000");
    $communityProfileStmt->execute();

    foreach ($communityProfileStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $userId = (int)($row['id'] ?? 0);
        if ($userId <= 0) {
            continue;
        }

        $name = (string)($row['name'] ?? 'user');
        $loc = $toAbsolute(community_profile_path($userId, $name));
        $addUrl($urls, $loc, $now, 'weekly', '0.6');
    }
} catch (Throwable $e) {
    // Keep sitemap available even if community query fails.
}

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
foreach ($urls as $url) {
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars((string)$url['loc'], ENT_QUOTES, 'UTF-8') . "</loc>\n";
    if (!empty($url['lastmod'])) {
        echo '    <lastmod>' . htmlspecialchars((string)$url['lastmod'], ENT_QUOTES, 'UTF-8') . "</lastmod>\n";
    }
    if (!empty($url['changefreq'])) {
        echo '    <changefreq>' . htmlspecialchars((string)$url['changefreq'], ENT_QUOTES, 'UTF-8') . "</changefreq>\n";
    }
    if (!empty($url['priority'])) {
        echo '    <priority>' . htmlspecialchars((string)$url['priority'], ENT_QUOTES, 'UTF-8') . "</priority>\n";
    }
    echo "  </url>\n";
}
echo "</urlset>\n";

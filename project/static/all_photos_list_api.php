<?php
include '../components/connect.php';
include '../components/seo_helpers.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$page = max(1, $page);
$limit = max(1, min(40, $limit));
$offset = ($page - 1) * $limit;

$categoryFilter = isset($_GET['category']) ? trim((string)$_GET['category']) : '';
$sortOrder = isset($_GET['sort']) ? trim((string)$_GET['sort']) : 'newest';

$whereClause = "WHERE p.status = 'active' AND p.image != ''";
$params = [];

if ($categoryFilter !== '') {
    $whereClause .= ' AND p.category = ?';
    $params[] = $categoryFilter;
}

$orderClause = 'ORDER BY p.date DESC';
if ($sortOrder === 'oldest') {
    $orderClause = 'ORDER BY p.date ASC';
} elseif ($sortOrder === 'popular') {
    $orderClause = 'ORDER BY like_count DESC, p.date DESC';
} elseif ($sortOrder === 'category') {
    $orderClause = 'ORDER BY p.category ASC, p.date DESC';
}

$sql = "
    SELECT p.id, p.title, p.image, p.category, p.date, p.name as author,
           COALESCE(l.like_count, 0) as like_count,
           COALESCE(c.comment_count, 0) as comment_count
    FROM `posts` p
    LEFT JOIN (
        SELECT post_id, COUNT(*) as like_count
        FROM `likes`
        GROUP BY post_id
    ) l ON p.id = l.post_id
    LEFT JOIN (
        SELECT post_id, COUNT(*) as comment_count
        FROM `comments`
        GROUP BY post_id
    ) c ON p.id = c.post_id
    {$whereClause}
    {$orderClause}
    LIMIT :fetch_limit OFFSET :fetch_offset
";

$stmt = $conn->prepare($sql);
foreach ($params as $i => $value) {
    $stmt->bindValue($i + 1, $value, PDO::PARAM_STR);
}
$stmt->bindValue(':fetch_limit', $limit + 1, PDO::PARAM_INT);
$stmt->bindValue(':fetch_offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$hasMore = count($rows) > $limit;
if ($hasMore) {
    array_pop($rows);
}

$masonryClasses = ['masonry-h-md', 'masonry-h-lg', 'masonry-h-sm', 'masonry-h-xl'];
$startIndex = $offset;

ob_start();
foreach ($rows as $index => $photo) {
    $heightClass = $masonryClasses[($startIndex + $index) % count($masonryClasses)];
    $postId = (int)$photo['id'];
    $postTitle = (string)$photo['title'];
    $postPath = post_path($postId, $postTitle);
?>
    <div class="photo-card masonry-item <?= $heightClass; ?> group cursor-pointer" data-photo-id="<?= $postId; ?>" data-post-url="<?= htmlspecialchars($postPath, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="relative overflow-hidden rounded-xl bg-white dark:bg-gray-800 shadow-lg border border-gray-200 dark:border-gray-700 transition-all duration-300 hover:shadow-2xl">
            <div class="h-full overflow-hidden">
                <img src="<?= htmlspecialchars(blog_post_image_src((string)$photo['image'], '../uploaded_img/', '../uploaded_img/default_img.jpg'), ENT_QUOTES, 'UTF-8'); ?>"
                    alt="<?= htmlspecialchars($postTitle, ENT_QUOTES, 'UTF-8'); ?>"
                    class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-110"
                    loading="lazy">
            </div>

            <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-50 transition-all duration-300 flex items-center justify-center">
                <div class="opacity-0 group-hover:opacity-100 transition-opacity duration-300 text-white text-center">
                    <a href="<?= htmlspecialchars($postPath, ENT_QUOTES, 'UTF-8'); ?>" class="bg-white text-gray-900 px-4 py-2 rounded-lg font-medium hover:bg-gray-100 transition-colors inline-flex items-center">
                        <i class="fas fa-eye mr-2"></i>
                        Đọc bài viết
                    </a>
                </div>
            </div>

            <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/80 to-transparent p-4 text-white transform translate-y-full group-hover:translate-y-0 transition-transform duration-300">
                <h3 class="font-semibold text-sm line-clamp-2 mb-2"><?= htmlspecialchars($postTitle, ENT_QUOTES, 'UTF-8'); ?></h3>
                <div class="flex items-center justify-between text-xs">
                    <div class="flex items-center space-x-2">
                        <span class="bg-white/20 px-2 py-1 rounded"><?= htmlspecialchars((string)$photo['category'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <span>by <?= htmlspecialchars((string)$photo['author'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="flex items-center space-x-1">
                            <i class="fas fa-heart text-red-400"></i>
                            <span><?= (int)$photo['like_count']; ?></span>
                        </span>
                        <span class="flex items-center space-x-1">
                            <i class="fas fa-comment text-blue-400"></i>
                            <span><?= (int)$photo['comment_count']; ?></span>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php
}
$html = (string)ob_get_clean();

echo json_encode([
    'ok' => true,
    'html' => $html,
    'has_more' => $hasMore,
    'next_page' => $hasMore ? ($page + 1) : null,
], JSON_UNESCAPED_UNICODE);

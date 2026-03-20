<?php
include '../components/connect.php';
include '../components/seo_helpers.php';

session_start();

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} else {
    $user_id = '';
}

include '../components/like_post.php';
include '../components/save_post.php';

// Lấy danh mục từ URL
if (!function_exists('renderCategoryCardsHtml')) {
    function renderCategoryCardsHtml($conn, $postsRows, $user_id)
    {
        ob_start();
        if (!empty($postsRows)) {
            foreach ($postsRows as $fetch_posts) {
                $post_id = (int)$fetch_posts['id'];

                $confirm_likes = $conn->prepare("SELECT 1 FROM `likes` WHERE user_id = ? AND post_id = ? LIMIT 1");
                $confirm_likes->execute([$user_id, $post_id]);
                $isLiked = $confirm_likes->rowCount() > 0;

                $confirm_save = $conn->prepare("SELECT 1 FROM `favorite_posts` WHERE user_id = ? AND post_id = ? LIMIT 1");
                $confirm_save->execute([$user_id, $post_id]);
                $isSaved = $confirm_save->rowCount() > 0;
?>
                <article class="card dark:bg-gray-700 group blog-card-shared hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2">
                    <form method="post" class="h-full flex flex-col">
                        <input type="hidden" name="post_id" value="<?= $post_id; ?>">
                        <input type="hidden" name="admin_id" value="<?= (int)$fetch_posts['admin_id']; ?>">

                        <div class="p-4 flex items-center justify-between border-b border-gray-200 dark:border-gray-600">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-main rounded-full flex items-center justify-center text-white text-sm font-semibold">
                                    <?= strtoupper(substr((string)$fetch_posts['name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <a href="author_posts.php?author=<?= urlencode((string)$fetch_posts['name']); ?>"
                                        class="font-semibold text-gray-900 dark:text-white hover:text-main transition-colors text-sm">
                                        <?= htmlspecialchars((string)$fetch_posts['name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        <?= date('d/m/Y H:i', strtotime((string)$fetch_posts['date'])); ?>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" name="save_post" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                                <i class="fas fa-bookmark text-sm <?= $isSaved ? 'text-yellow-500' : 'text-gray-400' ?>"></i>
                            </button>
                        </div>

                        <?php if (!empty($fetch_posts['image'])) : ?>
                            <div class="relative overflow-hidden h-48 rounded-lg mx-4 mt-4">
                                <img src="../uploaded_img/<?= htmlspecialchars((string)$fetch_posts['image'], ENT_QUOTES, 'UTF-8'); ?>"
                                    alt="<?= htmlspecialchars((string)$fetch_posts['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                    class="blog-card-image">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent"></div>
                            </div>
                        <?php endif; ?>

                        <div class="p-4 flex-1 flex flex-col">
                            <h3 class="font-bold text-gray-900 dark:text-white mb-3 line-clamp-2 group-hover:text-main transition-colors">
                                <a href="<?= post_path($post_id); ?>">
                                    <?= htmlspecialchars((string)$fetch_posts['title'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </h3>

                            <div class="text-gray-600 dark:text-gray-300 text-sm mb-4 line-clamp-3 flex-1">
                                <?= htmlspecialchars(strip_tags((string)$fetch_posts['content']), ENT_QUOTES, 'UTF-8'); ?>
                            </div>

                            <a href="<?= post_path($post_id); ?>"
                                class="text-main font-semibold hover:text-main transition-colors text-sm mb-4 inline-flex items-center group">
                                Đọc thêm
                                <i class="fas fa-arrow-right ml-2 transform group-hover:translate-x-1 transition-transform"></i>
                            </a>
                        </div>

                        <div class="p-4 border-t border-gray-200 dark:border-gray-600 flex items-center justify-between">
                            <div class="flex items-center space-x-4">
                                <div class="flex items-center space-x-1 text-gray-500 dark:text-gray-400 text-sm">
                                    <i class="fas fa-comment"></i>
                                    <span><?= (int)$fetch_posts['comments']; ?></span>
                                </div>

                                <button type="submit" name="like_post"
                                    class="flex items-center space-x-1 text-gray-500 dark:text-gray-400 hover:text-red-500 transition-colors text-sm">
                                    <i class="fas fa-heart <?= $isLiked ? 'text-red-500' : '' ?>"></i>
                                    <span><?= (int)$fetch_posts['likes']; ?></span>
                                </button>
                            </div>

                            <div class="text-xs text-gray-400">
                                <?= date('d/m/Y', strtotime((string)$fetch_posts['date'])); ?>
                            </div>
                        </div>
                    </form>
                </article>
        <?php
            }
        }
        return ob_get_clean();
    }
}
$category = isset($_GET['category']) ? $_GET['category'] : '';
if (empty($category)) {
    $defaultCategoryStmt = $conn->prepare("SELECT category FROM `posts` WHERE status = 'active' AND category IS NOT NULL AND category != '' GROUP BY category ORDER BY category ASC LIMIT 1");
    $defaultCategoryStmt->execute();
    $category = (string)($defaultCategoryStmt->fetchColumn() ?: '');

    if ($category === '') {
        header('Location: posts.php');
        exit;
    }
}

// Xác định trang hiện tại
$allowed_page_sizes = [9, 12, 18, 24];
$items_per_page = isset($_GET['size']) ? (int)$_GET['size'] : 9;
if (!in_array($items_per_page, $allowed_page_sizes, true)) {
    $items_per_page = 9;
}
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page <= 0) {
    $current_page = 1;
}

// Lấy filter từ URL (sort)
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'latest';

// Xây dựng query ORDER BY
switch ($sort) {
    case 'oldest':
        $order_by = 'date ASC';
        break;
    case 'popular':
        $order_by = 'likes DESC, date DESC';
        break;
    case 'comments':
        $order_by = 'comments DESC, date DESC';
        break;
    default:
        $order_by = 'date DESC';
        break;
}

// Đếm tổng số bài viết trong danh mục
$count_posts = $conn->prepare("SELECT COUNT(*) FROM `posts` WHERE status = ? AND category = ?");
$count_posts->execute(['active', $category]);
$total_posts = $count_posts->fetchColumn();

$total_pages = ceil($total_posts / $items_per_page);
$offset = ($current_page - 1) * $items_per_page;

// Truy vấn bài viết với JOIN để lấy số lượng likes và comments
$select_posts = $conn->prepare("
    SELECT p.*, 
           COALESCE(l.like_count, 0) as likes,
           COALESCE(c.comment_count, 0) as comments
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
    WHERE p.status = ? AND p.category = ? 
    ORDER BY $order_by 
    LIMIT $items_per_page OFFSET $offset
");
$select_posts->execute(['active', $category]);

$posts_rows = $select_posts->fetchAll(PDO::FETCH_ASSOC);
// Lấy thống kê danh mục
$category_stats = $conn->prepare("
    SELECT 
        COUNT(*) as total_posts,
        COUNT(DISTINCT admin_id) as total_authors,
        COALESCE(SUM(l.like_count), 0) as total_likes,
        COALESCE(SUM(c.comment_count), 0) as total_comments
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
    WHERE p.status = 'active' AND p.category = ?
");
$category_stats->execute([$category]);
$stats = $category_stats->fetch(PDO::FETCH_ASSOC);

$isAjaxRequest = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if ($isAjaxRequest) {
    $cards_html = renderCategoryCardsHtml($conn, $posts_rows, $user_id);

    $pagination_html = '';
    if ($total_pages > 1) {
        ob_start();
        ?>
        <div class="flex items-center justify-center space-x-4">
            <?php if ($current_page > 1) : ?>
                <a href="?category=<?= urlencode($category) ?>&sort=<?= urlencode($sort) ?>&size=<?= $items_per_page ?>&page=<?= $current_page - 1 ?>"
                    class="category-page-link flex items-center justify-center w-10 h-10 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-main hover:text-white hover:border-main transition-all duration-300 shadow-lg hover:shadow-xl"
                    data-page="<?= $current_page - 1 ?>">
                    <i class="fas fa-chevron-left"></i>
                </a>
            <?php endif; ?>

            <div class="flex items-center space-x-2">
                <?php
                $start = max(1, $current_page - 2);
                $end = min($total_pages, $current_page + 2);

                for ($i = $start; $i <= $end; $i++) :
                    $pageLinkClass = 'category-page-link flex items-center justify-center w-10 h-10 rounded-lg transition-all duration-300 shadow-lg hover:shadow-xl ';
                    if ($i === $current_page) {
                        $pageLinkClass .= 'bg-main text-white border-main';
                    } else {
                        $pageLinkClass .= 'bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-main hover:text-white hover:border-main';
                    }
                ?>
                    <a href="?category=<?= urlencode($category) ?>&sort=<?= urlencode($sort) ?>&size=<?= $items_per_page ?>&page=<?= $i ?>"
                        class="<?= $pageLinkClass; ?>"
                        data-page="<?= $i ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>

            <?php if ($current_page < $total_pages) : ?>
                <a href="?category=<?= urlencode($category) ?>&sort=<?= urlencode($sort) ?>&size=<?= $items_per_page ?>&page=<?= $current_page + 1 ?>"
                    class="category-page-link flex items-center justify-center w-10 h-10 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-main hover:text-white hover:border-main transition-all duration-300 shadow-lg hover:shadow-xl"
                    data-page="<?= $current_page + 1 ?>">
                    <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
<?php
        $pagination_html = ob_get_clean();
    }

    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'ok' => true,
        'cards_html' => $cards_html,
        'pagination_html' => $pagination_html,
        'summary_text' => "Trang {$current_page} trong tổng số {$total_pages} trang ({$total_posts} bài viết trong danh mục \"{$category}\")",
        'stats' => [
            'posts' => (int)($stats['total_posts'] ?? 0),
            'authors' => (int)($stats['total_authors'] ?? 0),
            'likes' => (int)($stats['total_likes'] ?? 0),
            'comments' => (int)($stats['total_comments'] ?? 0),
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
// Lấy danh sách tất cả categories để hiển thị
$all_categories = $conn->prepare("SELECT DISTINCT category FROM `posts` WHERE status = 'active' ORDER BY category");
$all_categories->execute();
?>

<?php include '../components/layout_header.php'; ?>

<?php
// Include breadcrumb component
include '../components/breadcrumb.php';

// Generate breadcrumb for category page
$category_title = htmlspecialchars($category);
$breadcrumb_items = auto_breadcrumb("Danh mục: $category_title");
render_breadcrumb($breadcrumb_items);
?>

<main class="min-h-screen bg-gray-50 dark:bg-gray-900 ">
    <div class="container-custom py-8">
        <!-- Breadcrumb -->
        <nav class="flex items-center space-x-2 text-sm text-gray-600 dark:text-gray-400 mb-8">
            <a href="../static/home.php" class="hover:text-main transition-colors">
                <i class="fas fa-home"></i>
                Trang chủ
            </a>
            <i class="fas fa-chevron-right text-xs"></i>
            <a href="posts.php" class="hover:text-main transition-colors">Bài viết</a>
            <i class="fas fa-chevron-right text-xs"></i>
            <span class="text-main font-medium"><?= htmlspecialchars($category) ?></span>
        </nav>

        <!-- Category Header -->
        <div class="text-center mb-12">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-main to-blue-600 rounded-full mb-6">
                <i class="fas fa-tag text-white text-3xl"></i>
            </div>
            <h1 class="text-4xl md:text-5xl font-bold text-gray-900 dark:text-white mb-4">
                Danh mục: <span class="gradient-text"><?= htmlspecialchars($category) ?></span>
            </h1>
            <div class="section-divider mb-6"></div>
            <p class="text-lg text-gray-600 dark:text-gray-300 max-w-2xl mx-auto">
                Khám phá những bài viết thú vị trong danh mục <?= htmlspecialchars($category) ?>
            </p>
        </div>

        <!-- Category Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 text-center border border-gray-200 dark:border-gray-700 shadow-lg">
                <div class="text-3xl font-bold text-main mb-2"><?= $stats['total_posts'] ?></div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Bài viết</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 text-center border border-gray-200 dark:border-gray-700 shadow-lg">
                <div class="text-3xl font-bold text-blue-500 mb-2"><?= $stats['total_authors'] ?></div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Tác giả</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 text-center border border-gray-200 dark:border-gray-700 shadow-lg">
                <div class="text-3xl font-bold text-red-500 mb-2"><?= $stats['total_likes'] ?></div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Lượt thích</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 text-center border border-gray-200 dark:border-gray-700 shadow-lg">
                <div class="text-3xl font-bold text-green-500 mb-2"><?= $stats['total_comments'] ?></div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Bình luận</div>
            </div>
        </div>

        <!-- Filter and Sort Section -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-6 mb-8 border border-gray-200 dark:border-gray-700">
            <div class="flex flex-col lg:flex-row items-center justify-between space-y-4 lg:space-y-0">
                <!-- Sort Options -->
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-sort text-main"></i>
                        <span class="font-semibold text-gray-900 dark:text-white">Sắp xếp:</span>
                    </div>
                    <div class="flex space-x-2" id="category-sort-options">
                        <?php
                        $sort_options = [
                            'latest' => ['icon' => 'clock', 'label' => 'Mới nhất'],
                            'oldest' => ['icon' => 'history', 'label' => 'Cũ nhất'],
                            'popular' => ['icon' => 'heart', 'label' => 'Phổ biến'],
                            'comments' => ['icon' => 'comments', 'label' => 'Nhiều bình luận']
                        ];
                        foreach ($sort_options as $sort_key => $sort_info) :
                            $sortClass = 'px-4 py-2 rounded-lg text-sm font-medium transition-all duration-300 flex items-center space-x-2 ';
                            if ($sort === $sort_key) {
                                $sortClass .= 'bg-main text-white shadow-lg';
                            } else {
                                $sortClass .= 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-main hover:text-white';
                            }
                        ?>
                            <a href="?category=<?= urlencode($category) ?>&sort=<?= $sort_key ?>&size=<?= $items_per_page ?>&page=1"
                                data-category-sort="<?= $sort_key ?>"
                                class="<?= $sortClass; ?>">
                                <i class="fas fa-<?= $sort_info['icon'] ?>"></i>
                                <span><?= $sort_info['label'] ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Category Dropdown -->
                <div class="relative">
                    <select id="category-switch" onchange="location = this.value"
                        class="appearance-none bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg px-4 py-2 pr-8 text-gray-700 dark:text-gray-300 focus:outline-none focus:ring-2 focus:ring-main focus:border-transparent">
                        <option value="">Chọn danh mục khác</option>
                        <?php while ($cat = $all_categories->fetch(PDO::FETCH_ASSOC)) : ?>
                            <option value="?category=<?= urlencode($cat['category']) ?>&sort=<?= urlencode($sort) ?>&size=<?= $items_per_page ?>&page=1"
                                <?= $cat['category'] == $category ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['category']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none">
                        <i class="fas fa-chevron-down text-gray-400"></i>
                    </div>
                </div>
            </div>

            <div class="mt-4 flex items-center justify-end">
                <form method="get" class="flex items-center gap-2" id="category-size-form">
                    <input type="hidden" name="category" value="<?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort, ENT_QUOTES, 'UTF-8'); ?>">
                    <label for="size" class="text-sm text-gray-600 dark:text-gray-300">Hiển thị:</label>
                    <select id="size" name="size" onchange="this.form.submit()" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-200">
                        <?php foreach ($allowed_page_sizes as $size_option) : ?>
                            <option value="<?= $size_option; ?>" <?= $items_per_page === $size_option ? 'selected' : ''; ?>><?= $size_option; ?>/trang</option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="page" value="1">
                </form>
            </div>
        </div>

        <?php if (!empty($posts_rows)) : ?>
            <!-- Posts Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-12" id="category-posts-grid">
                <?= renderCategoryCardsHtml($conn, $posts_rows, $user_id); ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1) : ?>
                <div class="flex items-center justify-center space-x-4" id="category-pagination">
                    <?php if ($current_page > 1) : ?>
                        <a href="?category=<?= urlencode($category) ?>&sort=<?= $sort ?>&size=<?= $items_per_page ?>&page=<?= $current_page - 1 ?>"
                            class="category-page-link flex items-center justify-center w-10 h-10 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-main hover:text-white hover:border-main transition-all duration-300 shadow-lg hover:shadow-xl"
                            data-page="<?= $current_page - 1 ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>

                    <div class="flex items-center space-x-2">
                        <?php
                        $start = max(1, $current_page - 2);
                        $end = min($total_pages, $current_page + 2);

                        for ($i = $start; $i <= $end; $i++) :
                            $pageLinkClass = 'category-page-link flex items-center justify-center w-10 h-10 rounded-lg transition-all duration-300 shadow-lg hover:shadow-xl ';
                            if ($i === $current_page) {
                                $pageLinkClass .= 'bg-main text-white border-main';
                            } else {
                                $pageLinkClass .= 'bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-main hover:text-white hover:border-main';
                            }
                        ?>
                            <a href="?category=<?= urlencode($category) ?>&sort=<?= $sort ?>&size=<?= $items_per_page ?>&page=<?= $i ?>"
                                data-page="<?= $i ?>"
                                class="<?= $pageLinkClass; ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                    </div>

                    <?php if ($current_page < $total_pages) : ?>
                        <a href="?category=<?= urlencode($category) ?>&sort=<?= $sort ?>&size=<?= $items_per_page ?>&page=<?= $current_page + 1 ?>"
                            data-page="<?= $current_page + 1 ?>"
                            class="category-page-link flex items-center justify-center w-10 h-10 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-main hover:text-white hover:border-main transition-all duration-300 shadow-lg hover:shadow-xl">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Page Info -->
                <div class="text-center mt-6" id="category-page-info">
                    <span class="text-gray-600 dark:text-gray-400">
                        Trang <?= $current_page ?> trong tổng số <?= $total_pages ?> trang
                        (<?= $total_posts ?> bài viết trong danh mục "<?= htmlspecialchars($category) ?>")
                    </span>
                </div>
            <?php endif; ?>

        <?php else : ?>
            <!-- Empty State -->
            <div class="text-center py-16">
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-12 shadow-lg border border-gray-200 dark:border-gray-700 max-w-md mx-auto">
                    <div class="w-20 h-20 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-folder-open text-4xl text-gray-400"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">Danh mục trống</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-6">
                        Chưa có bài viết nào trong danh mục "<?= htmlspecialchars($category) ?>"
                    </p>

                    <div class="space-y-3">
                        <a href="posts.php" class="block w-full btn-primary">
                            <i class="fas fa-list mr-2"></i>
                            Xem tất cả bài viết
                        </a>
                        <a href="../static/home.php" class="block w-full btn-secondary">
                            <i class="fas fa-home mr-2"></i>
                            Về trang chủ
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Related Categories -->
        <div class="mt-12 bg-gradient-to-r from-main/10 to-blue-600/10 rounded-2xl p-8">
            <div class="text-center mb-8">
                <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">Khám phá danh mục khác</h3>
                <p class="text-gray-600 dark:text-gray-400">Tìm hiểu thêm những chủ đề thú vị khác</p>
            </div>

            <?php
            $all_categories->execute(); // Re-execute to get fresh results
            $categories = $all_categories->fetchAll(PDO::FETCH_ASSOC);
            ?>

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <?php foreach ($categories as $cat) : ?>
                    <?php if ($cat['category'] != $category) : ?>
                        <a href="?category=<?= urlencode($cat['category']) ?>"
                            class="bg-white dark:bg-gray-800 rounded-lg p-4 text-center hover:shadow-lg transition-all duration-300 border border-gray-200 dark:border-gray-700 hover:border-main group">
                            <div class="w-8 h-8 bg-main/10 rounded-full flex items-center justify-center mx-auto mb-2 group-hover:bg-main group-hover:text-white transition-colors">
                                <i class="fas fa-tag text-main group-hover:text-white"></i>
                            </div>
                            <div class="font-medium text-gray-900 dark:text-white group-hover:text-main transition-colors">
                                <?= htmlspecialchars($cat['category']) ?>
                            </div>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</main>

<!-- Enhanced JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const state = {
            category: '<?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?>',
            sort: '<?= htmlspecialchars($sort, ENT_QUOTES, 'UTF-8'); ?>',
            size: <?= (int)$items_per_page; ?>,
            page: <?= (int)$current_page; ?>
        };

        const postsGrid = document.getElementById('category-posts-grid');
        const paginationWrap = document.getElementById('category-pagination');
        const pageInfo = document.getElementById('category-page-info');
        const sortOptions = document.getElementById('category-sort-options');
        const sizeSelect = document.getElementById('size');
        const categorySwitch = document.getElementById('category-switch');

        const applySortVisual = () => {
            if (!sortOptions) return;
            sortOptions.querySelectorAll('a[data-category-sort]').forEach((link) => {
                const isActive = link.dataset.categorySort === state.sort;
                link.className = isActive ?
                    'px-4 py-2 rounded-lg text-sm font-medium transition-all duration-300 flex items-center space-x-2 bg-main text-white shadow-lg' :
                    'px-4 py-2 rounded-lg text-sm font-medium transition-all duration-300 flex items-center space-x-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-main hover:text-white';
            });
        };

        const buildQueryString = () => new URLSearchParams({
            category: state.category,
            sort: state.sort,
            size: String(state.size),
            page: String(state.page)
        }).toString();

        const fetchCategoryPosts = async () => {
            if (!postsGrid) return;
            postsGrid.innerHTML = '<p class="text-center col-span-full text-gray-500">Đang tải bài viết...</p>';

            const params = new URLSearchParams({
                category: state.category,
                sort: state.sort,
                size: String(state.size),
                page: String(state.page)
            });

            try {
                const response = await fetch('category.php?' + params.toString(), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });
                const data = await response.json();

                if (!data.ok) {
                    throw new Error('Không tải được dữ liệu');
                }

                postsGrid.innerHTML = data.cards_html || '<p class="text-center col-span-full text-gray-500">Không có bài viết.</p>';
                if (paginationWrap) {
                    paginationWrap.innerHTML = data.pagination_html || '';
                }
                if (pageInfo) {
                    pageInfo.innerHTML = '<span class="text-gray-600 dark:text-gray-400">' + (data.summary_text || '') + '</span>';
                }

                applySortVisual();
                const nextUrl = 'category.php?' + buildQueryString();
                window.history.replaceState({}, '', nextUrl);
            } catch (error) {
                postsGrid.innerHTML = '<p class="text-center col-span-full text-red-500">Không thể tải bài viết, vui lòng thử lại.</p>';
            }
        };

        sortOptions?.addEventListener('click', function(e) {
            const link = e.target.closest('a[data-category-sort]');
            if (!link) return;
            e.preventDefault();
            state.sort = link.dataset.categorySort || 'latest';
            state.page = 1;
            fetchCategoryPosts();
        });

        paginationWrap?.addEventListener('click', function(e) {
            const link = e.target.closest('a.category-page-link');
            if (!link) return;
            e.preventDefault();
            state.page = parseInt(link.dataset.page || '1', 10);
            fetchCategoryPosts();
        });

        sizeSelect?.addEventListener('change', function(e) {
            state.size = parseInt(e.target.value || '9', 10);
            state.page = 1;
            fetchCategoryPosts();
        });

        categorySwitch?.addEventListener('change', function(e) {
            const val = e.target.value || '';
            if (!val) return;
            const url = new URL(val, window.location.origin);
            state.category = url.searchParams.get('category') || state.category;
            state.sort = url.searchParams.get('sort') || 'latest';
            state.size = parseInt(url.searchParams.get('size') || String(state.size), 10);
            state.page = 1;
            fetchCategoryPosts();
        });

        applySortVisual();

        // Message notification
        const message = document.getElementById('message');
        if (message) {
            setTimeout(() => {
                message.classList.remove('translate-x-full');
            }, 100);

            setTimeout(() => {
                message.classList.add('translate-x-full');
            }, 3000);
        }

        // Enhanced post card animations
        const postCards = document.querySelectorAll('.post-card');
        postCards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';

            setTimeout(() => {
                card.style.transition = 'all 0.6s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);

            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-8px) scale(1.02)';
                this.style.boxShadow = '0 25px 50px rgba(0, 0, 0, 0.15)';
            });

            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
                this.style.boxShadow = '';
            });
        });

        // Form submission with loading states
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    const originalContent = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                    submitBtn.disabled = true;

                    setTimeout(() => {
                        submitBtn.innerHTML = originalContent;
                        submitBtn.disabled = false;
                    }, 1000);
                }
            });
        });

        // Stats counter animation
        const statsElements = document.querySelectorAll('.text-3xl.font-bold');
        statsElements.forEach(element => {
            const finalValue = parseInt(element.textContent);
            const duration = 2000;
            const increment = finalValue / (duration / 16);
            let current = 0;

            const timer = setInterval(() => {
                current += increment;
                if (current >= finalValue) {
                    element.textContent = finalValue;
                    clearInterval(timer);
                } else {
                    element.textContent = Math.floor(current);
                }
            }, 16);
        });
    });

    // Lazy loading for images
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                if (img.dataset.src) {
                    img.src = img.dataset.src;
                    img.classList.remove('opacity-0');
                    img.classList.add('opacity-100');
                    observer.unobserve(img);
                }
            }
        });
    });

    document.querySelectorAll('img[data-src]').forEach(img => imageObserver.observe(img));
</script>

<?php include '../components/layout_footer.php'; ?>
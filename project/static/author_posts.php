<?php
include '../components/connect.php';
include '../components/seo_helpers.php';
include_once '../components/breadcrumb.php';

if (function_exists('blog_inject_lazy_loading_into_html')) {
    ob_start('blog_inject_lazy_loading_into_html');
}

session_start();

$user_id = (string)($_SESSION['user_id'] ?? '');

include '../components/like_post.php';
include '../components/save_post.php';

// Lấy tên tác giả từ URL
$author = isset($_GET['author']) ? $_GET['author'] : '';
if (empty($author)) {
    header('Location: posts.php');
    exit;
}

// Xác định trang hiện tại
$items_per_page = 9;
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

// Đếm tổng số bài viết của tác giả
$count_posts = $conn->prepare("SELECT COUNT(*) FROM `posts` WHERE status = ? AND name = ?");
$count_posts->execute(['active', $author]);
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
    WHERE p.status = ? AND p.name = ? 
    ORDER BY $order_by 
    LIMIT $items_per_page OFFSET $offset
");
$select_posts->execute(['active', $author]);
$posts_rows = $select_posts->fetchAll(PDO::FETCH_ASSOC);

// Lấy thông tin chi tiết về tác giả
$author_info = $conn->prepare("
    SELECT 
        name,
        admin_id,
        MIN(date) as first_post_date,
        MAX(date) as last_post_date,
        COUNT(*) as total_posts,
        COALESCE(SUM(l.like_count), 0) as total_likes,
        COALESCE(SUM(c.comment_count), 0) as total_comments,
        COUNT(DISTINCT category) as categories_count
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
    WHERE p.status = 'active' AND p.name = ?
    GROUP BY name, admin_id
");
$author_info->execute([$author]);
$author_data = $author_info->fetch(PDO::FETCH_ASSOC);

// Lấy danh mục mà tác giả đã viết
$author_categories = $conn->prepare("
    SELECT category, COUNT(*) as post_count 
    FROM `posts` 
    WHERE status = 'active' AND name = ? 
    GROUP BY category 
    ORDER BY post_count DESC
");
$author_categories->execute([$author]);

// Lấy bài viết phổ biến nhất của tác giả
$popular_posts = $conn->prepare("
    SELECT p.*, COALESCE(l.like_count, 0) as likes
    FROM `posts` p
    LEFT JOIN (
        SELECT post_id, COUNT(*) as like_count 
        FROM `likes` 
        GROUP BY post_id
    ) l ON p.id = l.post_id
    WHERE p.status = 'active' AND p.name = ? 
    ORDER BY likes DESC, p.date DESC 
    LIMIT 3
");
$popular_posts->execute([$author]);
$display_author_name = blog_decode_html_entities_deep((string)$author);

function render_author_posts_section(PDO $conn, array $posts_rows, ?string $user_id, string $author, string $sort, int $current_page, int $total_pages): string
{
    $user_id = (string)($user_id ?? '');
    ob_start();
?>
    <?php if (!empty($posts_rows)) : ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-12">
            <?php foreach ($posts_rows as $fetch_posts) {
                $post_id = (int)($fetch_posts['id'] ?? 0);
                $displayTitle = blog_decode_html_entities_deep((string)($fetch_posts['title'] ?? ''));
                $displayAuthor = blog_decode_html_entities_deep((string)($fetch_posts['name'] ?? ''));
                $displayCategory = blog_decode_html_entities_deep((string)($fetch_posts['category'] ?? ''));
                $displaySnippet = trim(strip_tags(blog_decode_html_entities_deep((string)($fetch_posts['content'] ?? ''))));

                $confirm_likes = $conn->prepare("SELECT 1 FROM `likes` WHERE user_id = ? AND post_id = ? LIMIT 1");
                $confirm_likes->execute([$user_id, $post_id]);

                $confirm_save = $conn->prepare("SELECT 1 FROM `favorite_posts` WHERE user_id = ? AND post_id = ? LIMIT 1");
                $confirm_save->execute([$user_id, $post_id]);
            ?>
                <article class="post-card group">
                    <form method="post" class="h-full flex flex-col" data-post-action-form="1">
                        <input type="hidden" name="post_id" value="<?= $post_id; ?>">
                        <input type="hidden" name="admin_id" value="<?= (int)($fetch_posts['admin_id'] ?? 0); ?>">

                        <div class="p-4 flex items-center justify-between border-b border-gray-200 dark:border-gray-600">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-main rounded-full flex items-center justify-center text-white text-sm font-semibold">
                                    <?= strtoupper(substr((string)$displayAuthor, 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="font-semibold text-gray-900 dark:text-white text-sm">
                                        <?= htmlspecialchars($displayAuthor, ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        <?= date('d/m/Y H:i', strtotime((string)$fetch_posts['date'])); ?>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" name="save_post" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                                <i class="fas fa-bookmark text-sm <?= $confirm_save->fetchColumn() ? 'text-yellow-500' : 'text-gray-400' ?>"></i>
                            </button>
                        </div>

                        <?php if (!empty($fetch_posts['image'])) : ?>
                            <div class="relative overflow-hidden h-48">
                                <img src="<?= htmlspecialchars(blog_post_image_src((string)$fetch_posts['image'], '../uploaded_img/', '../uploaded_img/default_img.jpg'), ENT_QUOTES, 'UTF-8'); ?>"
                                    alt="<?= htmlspecialchars($displayTitle, ENT_QUOTES, 'UTF-8'); ?>"
                                    class="post-image">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent"></div>
                            </div>
                        <?php endif; ?>

                        <div class="p-4 flex-1 flex flex-col">
                            <h3 class="font-bold text-gray-900 dark:text-white mb-3 line-clamp-2 group-hover:text-main transition-colors">
                                <a href="<?= post_path($post_id); ?>">
                                    <?= htmlspecialchars($displayTitle, ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </h3>

                            <div class="text-gray-600 dark:text-gray-300 text-sm mb-4 line-clamp-3 flex-1">
                                <?= htmlspecialchars($displaySnippet, ENT_QUOTES, 'UTF-8'); ?>
                            </div>

                            <a href="<?= post_path($post_id); ?>"
                                class="text-main font-semibold hover:text-blue-700 transition-colors text-sm mb-4 inline-flex items-center group">
                                Đọc thêm
                                <i class="fas fa-arrow-right ml-2 transform group-hover:translate-x-1 transition-transform"></i>
                            </a>

                            <a href="category.php?category=<?= urlencode($displayCategory); ?>"
                                class="inline-flex items-center space-x-1 bg-main/10 text-main px-3 py-1 rounded-full text-xs font-medium hover:bg-main/20 transition-colors w-fit">
                                <i class="fas fa-tag"></i>
                                <span><?= htmlspecialchars($displayCategory, ENT_QUOTES, 'UTF-8'); ?></span>
                            </a>
                        </div>

                        <div class="p-4 border-t border-gray-200 dark:border-gray-600 flex items-center justify-between">
                            <div class="flex items-center space-x-4">
                                <div class="flex items-center space-x-1 text-gray-500 dark:text-gray-400 text-sm">
                                    <i class="fas fa-comment"></i>
                                    <span><?= (int)($fetch_posts['comments'] ?? 0); ?></span>
                                </div>

                                <button type="submit" name="like_post"
                                    class="flex items-center space-x-1 text-gray-500 dark:text-gray-400 hover:text-red-500 transition-colors text-sm">
                                    <i class="fas fa-heart <?= $confirm_likes->fetchColumn() ? 'text-red-500' : '' ?>"></i>
                                    <span><?= (int)($fetch_posts['likes'] ?? 0); ?></span>
                                </button>
                            </div>

                            <div class="text-xs text-gray-400">
                                <?= date('d/m/Y', strtotime((string)$fetch_posts['date'])); ?>
                            </div>
                        </div>
                    </form>
                </article>
            <?php } ?>
        </div>

        <?php if ($total_pages > 1) : ?>
            <div class="flex items-center justify-center space-x-4">
                <?php if ($current_page > 1) : ?>
                    <a data-author-ajax-link="1" href="?author=<?= urlencode($author) ?>&sort=<?= urlencode($sort) ?>&page=<?= $current_page - 1 ?>"
                        class="flex items-center justify-center w-10 h-10 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-main hover:text-white hover:border-main transition-all duration-300 shadow-lg hover:shadow-xl">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>

                <div class="flex items-center space-x-2">
                    <?php
                    $start = max(1, $current_page - 2);
                    $end = min($total_pages, $current_page + 2);

                    for ($i = $start; $i <= $end; $i++) :
                        $pageClass = $i === $current_page
                            ? 'flex items-center justify-center w-10 h-10 rounded-lg transition-all duration-300 shadow-lg bg-main text-white border-main'
                            : 'flex items-center justify-center w-10 h-10 rounded-lg transition-all duration-300 shadow-lg hover:shadow-xl bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-main hover:text-white hover:border-main';
                    ?>
                        <a data-author-ajax-link="1" href="?author=<?= urlencode($author) ?>&sort=<?= urlencode($sort) ?>&page=<?= $i ?>"
                            class="<?= $pageClass; ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>

                <?php if ($current_page < $total_pages) : ?>
                    <a data-author-ajax-link="1" href="?author=<?= urlencode($author) ?>&sort=<?= urlencode($sort) ?>&page=<?= $current_page + 1 ?>"
                        class="flex items-center justify-center w-10 h-10 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-main hover:text-white hover:border-main transition-all duration-300 shadow-lg hover:shadow-xl">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>

            <div class="text-center mt-6">
                <span class="text-gray-600 dark:text-gray-400">
                    Trang <?= $current_page ?> trong tổng số <?= $total_pages ?> trang
                </span>
            </div>
        <?php endif; ?>

    <?php else : ?>
        <div class="text-center py-16">
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-12 shadow-lg border border-gray-200 dark:border-gray-700">
                <div class="w-20 h-20 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-user-edit text-4xl text-gray-400"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">Chưa có bài viết</h3>
                <p class="text-gray-600 dark:text-gray-400 mb-6">
                    Tác giả "<?= htmlspecialchars(blog_decode_html_entities_deep($author), ENT_QUOTES, 'UTF-8'); ?>" chưa có bài viết nào
                </p>

                <div class="space-y-3">
                    <a href="posts.php" class="block w-full btn-primary max-w-sm mx-auto">
                        <i class="fas fa-list mr-2"></i>
                        Xem tất cả bài viết
                    </a>
                </div>
            </div>
        </div>
    <?php endif;

    return ob_get_clean();
}

function render_author_main_content(PDO $conn, array $posts_rows, ?string $user_id, string $author, string $sort, int $total_posts, int $current_page, int $total_pages): string
{
    $user_id = (string)($user_id ?? '');
    ob_start();
    ?>
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-6 mb-8 border border-gray-200 dark:border-gray-700">
        <div class="flex flex-col md:flex-row items-center justify-between space-y-4 md:space-y-0">
            <div class="flex items-center space-x-4">
                <div class="flex items-center space-x-2">
                    <i class="fas fa-sort text-main"></i>
                    <span class="font-semibold text-gray-900 dark:text-white">Sắp xếp:</span>
                </div>
                <div class="flex space-x-2">
                    <?php
                    $sort_options = [
                        'latest' => ['icon' => 'clock', 'label' => 'Mới nhất'],
                        'oldest' => ['icon' => 'history', 'label' => 'Cũ nhất'],
                        'popular' => ['icon' => 'heart', 'label' => 'Phổ biến'],
                        'comments' => ['icon' => 'comments', 'label' => 'Nhiều bình luận']
                    ];
                    foreach ($sort_options as $sort_key => $sort_info) :
                        $isActiveSort = $sort === $sort_key;
                        $sortClass = $isActiveSort
                            ? 'px-4 py-2 rounded-lg text-sm font-medium transition-all duration-300 flex items-center space-x-2 bg-main text-white shadow-lg'
                            : 'px-4 py-2 rounded-lg text-sm font-medium transition-all duration-300 flex items-center space-x-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-main hover:text-white';
                    ?>
                        <a data-author-ajax-link="1" href="?author=<?= urlencode($author) ?>&sort=<?= $sort_key ?>&page=1"
                            class="<?= $sortClass; ?>">
                            <i class="fas fa-<?= $sort_info['icon'] ?>"></i>
                            <span><?= $sort_info['label'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="text-gray-600 dark:text-gray-400">
                <strong class="text-gray-900 dark:text-white"><?= $total_posts ?></strong> bài viết
            </div>
        </div>
    </div>

    <?= render_author_posts_section($conn, $posts_rows, $user_id, $author, $sort, $current_page, $total_pages); ?>
<?php

    return ob_get_clean();
}

$is_ajax_request = isset($_GET['ajax']) && $_GET['ajax'] === '1';
$render_user_id = (string)($user_id ?? '');
if ($is_ajax_request) {
    echo render_author_main_content($conn, $posts_rows, $render_user_id, $author, $sort, (int)$total_posts, $current_page, (int)$total_pages);
    exit;
}
?>

<?php include '../components/layout_header.php'; ?>

<?php
$breadcrumb_items = [
    ['title' => 'Trang chủ', 'url' => '../static/home.php'],
    ['title' => 'Bài viết', 'url' => 'posts.php'],
    ['title' => 'Tác giả: ' . $display_author_name, 'url' => '']
];
render_breadcrumb($breadcrumb_items);
?>

<main class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <div class="container-custom py-8">
        <!-- Author Profile Header -->
        <?php if ($author_data) : ?>
            <div class="bg-gradient-to-r from-main to-blue-600 rounded-3xl p-8 mb-8 text-white relative overflow-hidden">
                <div class="absolute inset-0 bg-black/10"></div>
                <div class="relative z-10">
                    <div class="flex flex-col md:flex-row items-center space-y-6 md:space-y-0 md:space-x-8">
                        <!-- Avatar -->
                        <div class="relative">
                            <div class="w-24 h-24 bg-white/20 rounded-full flex items-center justify-center text-4xl font-bold backdrop-blur-sm border-4 border-white/30">
                                <?= strtoupper(substr($author, 0, 1)) ?>
                            </div>
                            <div class="absolute -bottom-2 -right-2 bg-green-500 rounded-full p-2">
                                <i class="fas fa-check text-white text-sm"></i>
                            </div>
                        </div>

                        <!-- Author Info -->
                        <div class="flex-1 text-center md:text-left">
                            <h1 class="text-4xl font-bold mb-2"><?= htmlspecialchars($display_author_name, ENT_QUOTES, 'UTF-8') ?></h1>
                            <p class="text-white/80 text-lg mb-4">Tác giả tại Blog Website</p>

                            <div class="flex flex-wrap gap-4 justify-center md:justify-start text-sm">
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-calendar-alt"></i>
                                    <span>Tham gia từ <?= date('m/Y', strtotime($author_data['first_post_date'])) ?></span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-clock"></i>
                                    <span>Bài viết gần nhất: <?= date('d/m/Y', strtotime($author_data['last_post_date'])) ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Stats -->
                        <div class="grid grid-cols-2 gap-4 md:gap-6">
                            <div class="text-center">
                                <div class="text-3xl font-bold"><?= $author_data['total_posts'] ?></div>
                                <div class="text-white/80 text-sm">Bài viết</div>
                            </div>
                            <div class="text-center">
                                <div class="text-3xl font-bold"><?= $author_data['categories_count'] ?></div>
                                <div class="text-white/80 text-sm">Danh mục</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <!-- Main Content -->
            <div class="lg:col-span-3" id="author-main-content">
                <?= render_author_main_content($conn, $posts_rows, $render_user_id, $author, $sort, (int)$total_posts, $current_page, (int)$total_pages); ?>
            </div>

            <!-- Sidebar -->
            <div class="lg:col-span-1 space-y-8">
                <!-- Author Stats -->
                <?php if ($author_data) : ?>
                    <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-lg border border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                            <i class="fas fa-chart-bar text-main mr-2"></i>
                            Thống kê tác giả
                        </h3>

                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-file-alt text-blue-500"></i>
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Tổng bài viết</span>
                                </div>
                                <span class="font-bold text-gray-900 dark:text-white"><?= $author_data['total_posts'] ?></span>
                            </div>

                            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-heart text-red-500"></i>
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Tổng lượt thích</span>
                                </div>
                                <span class="font-bold text-gray-900 dark:text-white"><?= $author_data['total_likes'] ?></span>
                            </div>

                            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-comments text-green-500"></i>
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Tổng bình luận</span>
                                </div>
                                <span class="font-bold text-gray-900 dark:text-white"><?= $author_data['total_comments'] ?></span>
                            </div>

                            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-tags text-purple-500"></i>
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Danh mục</span>
                                </div>
                                <span class="font-bold text-gray-900 dark:text-white"><?= $author_data['categories_count'] ?></span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Author Categories -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-lg border border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                        <i class="fas fa-layer-group text-main mr-2"></i>
                        Danh mục đã viết
                    </h3>

                    <div class="space-y-2">
                        <?php while ($cat = $author_categories->fetch(PDO::FETCH_ASSOC)) : ?>
                            <?php $displayCategoryName = blog_decode_html_entities_deep((string)($cat['category'] ?? '')); ?>
                            <a href="category.php?category=<?= urlencode($displayCategoryName) ?>"
                                class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-main/10 transition-colors group">
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-tag text-main group-hover:text-blue-600"></i>
                                    <span class="text-sm text-gray-700 dark:text-gray-300 group-hover:text-main font-medium">
                                        <?= htmlspecialchars($displayCategoryName, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </div>
                                <span class="text-xs bg-main/20 text-main px-2 py-1 rounded-full">
                                    <?= $cat['post_count'] ?>
                                </span>
                            </a>
                        <?php endwhile; ?>
                    </div>
                </div>

                <!-- Popular Posts -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-lg border border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                        <i class="fas fa-fire text-main mr-2"></i>
                        Bài viết nổi bật
                    </h3>

                    <div class="space-y-4">
                        <?php while ($popular = $popular_posts->fetch(PDO::FETCH_ASSOC)) : ?>
                            <?php $displayPopularTitle = blog_decode_html_entities_deep((string)($popular['title'] ?? '')); ?>
                            <a href="<?= post_path($popular['id']); ?>"
                                class="block p-3 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-main/10 transition-colors group">
                                <h4 class="font-medium text-gray-900 dark:text-white text-sm mb-2 line-clamp-2 group-hover:text-main">
                                    <?= htmlspecialchars($displayPopularTitle, ENT_QUOTES, 'UTF-8') ?>
                                </h4>
                                <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                    <span><?= date('d/m/Y', strtotime($popular['date'])) ?></span>
                                    <div class="flex items-center space-x-1 text-red-500">
                                        <i class="fas fa-heart"></i>
                                        <span><?= $popular['likes'] ?></span>
                                    </div>
                                </div>
                            </a>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Enhanced JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const message = document.getElementById('message');
        const mainContent = document.getElementById('author-main-content');
        let isLoading = false;

        function showMessageToast() {
            if (!message) {
                return;
            }

            setTimeout(() => {
                message.classList.remove('translate-x-full');
            }, 100);

            setTimeout(() => {
                message.classList.add('translate-x-full');
            }, 3000);
        }

        function animatePostCards(scope = document) {
            const postCards = scope.querySelectorAll('.post-card');
            postCards.forEach((card, index) => {
                if (card.dataset.postFxReady === '1') {
                    return;
                }

                card.dataset.postFxReady = '1';
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
        }

        function animateStatsOnce() {
            const statsElements = document.querySelectorAll('.text-3xl.font-bold');
            statsElements.forEach(element => {
                if (element.dataset.counterAnimated === '1') {
                    return;
                }

                element.dataset.counterAnimated = '1';
                const finalValue = parseInt(element.textContent, 10);

                if (Number.isNaN(finalValue)) {
                    return;
                }

                const duration = 2000;
                const increment = finalValue / (duration / 16);
                let current = 0;

                const timer = setInterval(() => {
                    current += increment;
                    if (current >= finalValue) {
                        element.textContent = String(finalValue);
                        clearInterval(timer);
                    } else {
                        element.textContent = String(Math.floor(current));
                    }
                }, 16);
            });
        }

        function bindPostActionForms(scope = document) {
            const forms = scope.querySelectorAll('form[data-post-action-form="1"]');
            forms.forEach(form => {
                if (form.dataset.loadingBound === '1') {
                    return;
                }

                form.dataset.loadingBound = '1';
                form.addEventListener('submit', function() {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (!submitBtn) {
                        return;
                    }

                    const originalContent = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                    submitBtn.disabled = true;

                    setTimeout(() => {
                        submitBtn.innerHTML = originalContent;
                        submitBtn.disabled = false;
                    }, 1000);
                });
            });
        }

        function animateSidebarOnce() {
            const sidebarItems = document.querySelectorAll('.lg\\:col-span-1 > div');
            sidebarItems.forEach((item, index) => {
                if (item.dataset.sidebarFxReady === '1') {
                    return;
                }

                item.dataset.sidebarFxReady = '1';
                item.style.opacity = '0';
                item.style.transform = 'translateX(20px)';

                setTimeout(() => {
                    item.style.transition = 'all 0.6s ease';
                    item.style.opacity = '1';
                    item.style.transform = 'translateX(0)';
                }, 200 + (index * 100));
            });
        }

        function attachLazyImages(scope = document) {
            const images = scope.querySelectorAll('img[data-src]');
            images.forEach(img => imageObserver.observe(img));
        }

        function initMainScope(scope = document) {
            animatePostCards(scope);
            bindPostActionForms(scope);
            attachLazyImages(scope);
        }

        function renderMainContentSkeleton() {
            return `
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-6 mb-8 border border-gray-200 dark:border-gray-700 animate-pulse">
                    <div class="h-6 w-40 rounded bg-gray-200 dark:bg-gray-700 mb-4"></div>
                    <div class="flex gap-2 flex-wrap">
                        <div class="h-9 w-24 rounded-lg bg-gray-200 dark:bg-gray-700"></div>
                        <div class="h-9 w-24 rounded-lg bg-gray-200 dark:bg-gray-700"></div>
                        <div class="h-9 w-24 rounded-lg bg-gray-200 dark:bg-gray-700"></div>
                        <div class="h-9 w-28 rounded-lg bg-gray-200 dark:bg-gray-700"></div>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-12">
                    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-4 animate-pulse">
                        <div class="h-5 w-1/2 rounded bg-gray-200 dark:bg-gray-700 mb-3"></div>
                        <div class="h-40 rounded-lg bg-gray-200 dark:bg-gray-700 mb-4"></div>
                        <div class="h-4 rounded bg-gray-200 dark:bg-gray-700 mb-2"></div>
                        <div class="h-4 w-4/5 rounded bg-gray-200 dark:bg-gray-700 mb-4"></div>
                        <div class="h-8 w-24 rounded-full bg-gray-200 dark:bg-gray-700"></div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-4 animate-pulse">
                        <div class="h-5 w-1/2 rounded bg-gray-200 dark:bg-gray-700 mb-3"></div>
                        <div class="h-40 rounded-lg bg-gray-200 dark:bg-gray-700 mb-4"></div>
                        <div class="h-4 rounded bg-gray-200 dark:bg-gray-700 mb-2"></div>
                        <div class="h-4 w-4/5 rounded bg-gray-200 dark:bg-gray-700 mb-4"></div>
                        <div class="h-8 w-24 rounded-full bg-gray-200 dark:bg-gray-700"></div>
                    </div>
                </div>
            `;
        }

        async function loadAuthorMainContent(url, pushState = true) {
            if (!mainContent || isLoading) {
                return;
            }

            isLoading = true;
            mainContent.classList.add('pointer-events-none');
            mainContent.innerHTML = renderMainContentSkeleton();

            try {
                const requestUrl = new URL(url, window.location.href);
                requestUrl.searchParams.set('ajax', '1');

                const response = await fetch(requestUrl.toString(), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!response.ok) {
                    throw new Error('Không thể tải dữ liệu bài viết tác giả.');
                }

                const html = await response.text();
                mainContent.innerHTML = html;
                initMainScope(mainContent);

                if (pushState) {
                    const cleanUrl = new URL(url, window.location.href);
                    window.history.pushState({
                        authorAjax: true
                    }, '', cleanUrl.toString());
                }
            } catch (error) {
                window.location.href = url;
            } finally {
                mainContent.classList.remove('pointer-events-none');
                isLoading = false;
            }
        }

        if (mainContent) {
            mainContent.addEventListener('click', function(event) {
                const link = event.target.closest('a[data-author-ajax-link="1"]');
                if (!link) {
                    return;
                }

                event.preventDefault();
                loadAuthorMainContent(link.href, true);
            });

            window.addEventListener('popstate', function() {
                loadAuthorMainContent(window.location.href, false);
            });
        }

        showMessageToast();
        animateStatsOnce();
        animateSidebarOnce();
        initMainScope(document);
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
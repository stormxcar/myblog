<?php
include '../components/connect.php';
include '../components/seo_helpers.php';

if (function_exists('blog_inject_lazy_loading_into_html')) {
    ob_start('blog_inject_lazy_loading_into_html');
}

// Disable error display for AJAX requests to prevent HTML error output
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
} else {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
}
error_reporting(E_ALL);

session_start();
$message = [];

if (!defined('BLOG_LAYOUT_ASSETS')) {
    define('BLOG_LAYOUT_ASSETS', true);
}

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} else {
    $user_id = '';
};

include '../components/like_post.php';
include '../components/save_post.php';

if (isset($_POST['submit_contact'])) {
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    try {
        $user_email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $user_name = trim(strip_tags((string)($_POST['name'] ?? '')));
        $user_message = trim((string)($_POST['noi_dung'] ?? ''));

        if ($user_email === '' || $user_name === '' || $user_message === '') {
            throw new Exception('Thông tin liên hệ chưa đầy đủ');
        }

        // TODO: tích hợp gửi email thực tế tại đây.
        $successMessage = 'Cảm ơn bạn đã liên hệ! Chúng tôi sẽ phản hồi sớm nhất có thể.';

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => $successMessage
            ]);
            exit;
        }

        $message[] = $successMessage;
    } catch (Exception $e) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi gửi tin nhắn!'
            ]);
            exit;
        }

        $message[] = 'Có lỗi xảy ra khi gửi tin nhắn!';
    }
}

$page_title = 'My Blog - Blog du lich, cong nghe va trai nghiem song moi ngay';
$page_description = 'Doc bai viet moi nhat ve du lich, cong nghe va trai nghiem song. Cap nhat lien tuc, de tim kiem tren Google va de theo doi xu huong nhanh nhat.';
$page_robots = 'index,follow,max-image-preview:large';
$page_canonical = canonical_current_url();
$page_og_image = site_url('uploaded_img/logo-removebg.png');

?>
<?php include '../components/layout_header.php'; ?>

<?php
try {
    $authorJoin = 'LEFT JOIN users u ON u.id = p.admin_id';
    if (blog_has_legacy_admin_id_column($conn)) {
        $authorJoin = 'LEFT JOIN users u ON (u.legacy_admin_id = p.admin_id OR u.id = p.admin_id)';
    }
    if (blog_has_admin_role_column($conn)) {
        $authorJoin .= " AND u.role = 'admin'";
    }

    $featuredSql = "SELECT p.*,
                       COALESCE(u.name, 'Admin') AS author_name,
                               COALESCE(l.like_count, 0) AS likes_count,
                               COALESCE(c.comment_count, 0) AS comments_count
                        FROM posts p
                        LEFT JOIN (
                            SELECT post_id, COUNT(*) AS like_count
                            FROM likes
                            GROUP BY post_id
                        ) l ON l.post_id = p.id
                        LEFT JOIN (
                            SELECT post_id, COUNT(*) AS comment_count
                            FROM comments
                            GROUP BY post_id
                        ) c ON c.post_id = p.id
                        {$authorJoin}
                        WHERE p.status = 'active'
                        ORDER BY (COALESCE(l.like_count, 0) + COALESCE(c.comment_count, 0)) DESC, p.id DESC
                        LIMIT 5";

    $featured_stmt = $conn->prepare($featuredSql);
    $featured_stmt->execute();
    $featured_posts = $featured_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $featured_posts = [];
}

try {
    $latest_by_category_stmt = $conn->prepare("SELECT p.id, p.title, p.category, p.date
                                                   FROM posts p
                                                   INNER JOIN (
                                                      SELECT category, MAX(id) AS max_id
                                                      FROM posts
                                                      WHERE status = 'active' AND category IS NOT NULL AND category != ''
                                                      GROUP BY category
                                                   ) x ON x.max_id = p.id
                                                   ORDER BY p.date DESC, p.id DESC
                                                   LIMIT 7");
    $latest_by_category_stmt->execute();
    $category_posts = $latest_by_category_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $category_posts = [];
}

try {
    $latest_index_stmt = $conn->prepare("SELECT id, title, category, content, date FROM posts WHERE status = 'active' ORDER BY id DESC LIMIT 12");
    $latest_index_stmt->execute();
    $latest_index_posts = $latest_index_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $latest_index_posts = [];
}
?>

<section class="py-12 bg-white dark:bg-gray-800">
    <div class="container-custom">
        <div class="flex flex-col md:flex-row gap-8 items-stretch md:flex-nowrap">
            <div class="w-full md:w-3/5 md:flex-shrink-0">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 dark:text-white">Nổi bật và gần đây</h2>
                    <a href="posts.php" class="text-main font-semibold hover:opacity-80">Xem tất cả</a>
                </div>
                <swiper-container class="w-full h-96 md:h-[500px] rounded-2xl overflow-hidden shadow-2xl"
                    pagination="true"
                    pagination-clickable="true"
                    navigation="true"
                    autoplay-delay="4500"
                    autoplay-disable-on-interaction="false">
                    <?php if (!empty($featured_posts)): ?>
                        <?php foreach ($featured_posts as $post): ?>
                            <swiper-slide class="relative w-full h-full">
                                <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('<?= htmlspecialchars(blog_post_image_src((string)($post['image'] ?? ''), '../uploaded_img/', '../uploaded_img/default_img.jpg'), ENT_QUOTES, 'UTF-8'); ?>');"></div>
                                <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/25 to-transparent"></div>
                                <div class="absolute bottom-6 left-6 right-6 z-10 text-white">
                                    <div class="inline-flex items-center gap-2 mb-3 text-xs uppercase tracking-wide bg-white/20 backdrop-blur px-3 py-1 rounded-full">
                                        <span><?= htmlspecialchars(blog_decode_html_entities_deep((string)($post['category'] ?? 'Tin mới')), ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <h3 class="text-2xl md:text-4xl font-bold leading-tight line-clamp-2\"><?= htmlspecialchars(blog_decode_html_entities_deep((string)($post['title'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></h3>
                                    <div class="mt-3 flex flex-wrap items-center gap-4 text-sm opacity-95">
                                        <span><i class="fas fa-user mr-1"></i><?= htmlspecialchars($post['author_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span><i class="fas fa-calendar mr-1"></i><?= htmlspecialchars($post['date'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span><i class="fas fa-heart mr-1"></i><?= (int)$post['likes_count']; ?></span>
                                        <span><i class="fas fa-comment mr-1"></i><?= (int)$post['comments_count']; ?></span>
                                    </div>
                                    <a href="<?= post_path((int)$post['id']); ?>" class="inline-flex items-center mt-4 bg-main text-white px-5 py-2.5 rounded-lg hover:opacity-90 transition">
                                        Đọc ngay <i class="fas fa-arrow-right ml-2"></i>
                                    </a>
                                </div>
                            </swiper-slide>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <swiper-slide class="relative w-full h-full">
                            <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('../uploaded_img/default_img.jpg');"></div>
                            <div class="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent"></div>
                            <div class="absolute bottom-6 left-6 right-6 z-10 text-white">
                                <h3 class="text-2xl font-bold">Chưa có bài viết nổi bật</h3>
                            </div>
                        </swiper-slide>
                    <?php endif; ?>
                </swiper-container>
            </div>

            <aside class="w-full md:w-2/5 bg-gray-50 dark:bg-gray-900/60 rounded-2xl p-5 shadow-lg">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">Theo danh mục</h3>
                    <a href="posts.php" class="text-main text-sm font-semibold">Xem tất cả</a>
                </div>
                <div class="space-y-3">
                    <?php if (!empty($category_posts)): ?>
                        <?php foreach ($category_posts as $cp): ?>
                            <a href="category.php?category=<?= urlencode($cp['category']); ?>" class="block p-3 rounded-xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 hover:border-main/40 hover:shadow transition">
                                <p class="text-xs uppercase tracking-wide text-main mb-1"><?= htmlspecialchars($cp['category'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white line-clamp-2\"><?= htmlspecialchars(blog_decode_html_entities_deep((string)($cp['title'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($cp['date'], ENT_QUOTES, 'UTF-8'); ?></p>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-sm text-gray-500">Chưa có dữ liệu danh mục.</p>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    </div>
</section>

<section class="py-10 bg-gray-50 dark:bg-gray-900/40" aria-label="Liên kết bài viết mới nhất">
    <div class="container-custom">
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Liên kết bài viết mới nhất</h2>
                <a href="posts.php" class="text-sm text-main font-semibold hover:underline">Xem danh sách đầy đủ</a>
            </div>
            <?php if (!empty($latest_index_posts)): ?>
                <ul class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 text-sm">
                    <?php foreach ($latest_index_posts as $index_post): ?>
                        <?php
                        $index_title = blog_decode_html_entities_deep((string)($index_post['title'] ?? ''));
                        $index_category = blog_decode_html_entities_deep((string)($index_post['category'] ?? 'Chung'));
                        $index_snippet = trim(strip_tags(blog_decode_html_entities_deep((string)($index_post['content'] ?? ''))));
                        if (function_exists('mb_substr')) {
                            $index_snippet = mb_substr($index_snippet, 0, 95, 'UTF-8');
                        } else {
                            $index_snippet = substr($index_snippet, 0, 95);
                        }
                        ?>
                        <li>
                            <a href="<?= post_path((int)$index_post['id'], $index_title); ?>" class="block rounded-xl border border-gray-200 dark:border-gray-700 px-3 py-3 hover:border-main/40 hover:bg-main/5 transition-colors">
                                <span class="inline-flex items-center text-[11px] px-2 py-0.5 rounded-full bg-main/10 text-main font-semibold">
                                    <?= htmlspecialchars($index_category, ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <span class="block mt-2 text-gray-800 dark:text-gray-100 font-semibold leading-snug line-clamp-2">
                                    <?= htmlspecialchars($index_title, ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <span class="block mt-1 text-xs text-gray-500 dark:text-gray-400 line-clamp-2">
                                    <?= htmlspecialchars($index_snippet !== '' ? ($index_snippet . '...') : 'Đọc bài viết chi tiết để xem nội dung đầy đủ.', ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <span class="block mt-1 text-[11px] text-gray-400 dark:text-gray-500">
                                    <?= htmlspecialchars((string)($index_post['date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="text-sm text-gray-500 dark:text-gray-400">Chưa có dữ liệu bài viết mới nhất.</p>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ========================================
        INTRODUCE SECTION
        ======================================== -->
<?php
// Lấy thông tin giới thiệu từ database
$select_gioithieu = $conn->prepare("
       SELECT setting_key, setting_value 
       FROM `settings` 
       WHERE setting_key IN ('gioithieu_tieude', 'gioithieu_slogan', 'gioithieu_tieude_1', 'gioithieu_noidung_1', 'gioithieu_tieude_2', 'gioithieu_noidung_2')
   ");
$select_gioithieu->execute();
$introduce_settings = $select_gioithieu->fetchAll(PDO::FETCH_KEY_PAIR);

$tieude_text = $introduce_settings['gioithieu_tieude'] ?? 'Chào mừng đến với Blog của tôi';
$slogan_text = $introduce_settings['gioithieu_slogan'] ?? 'Nơi chia sẻ những câu chuyện đầy cảm hứng';
$tieude_text_1 = $introduce_settings['gioithieu_tieude_1'] ?? 'Sứ mệnh';
$noidung_text_1 = $introduce_settings['gioithieu_noidung_1'] ?? 'Mang đến những nội dung chất lượng và hữu ích';
$tieude_text_2 = $introduce_settings['gioithieu_tieude_2'] ?? 'Tầm nhìn';
$noidung_text_2 = $introduce_settings['gioithieu_noidung_2'] ?? 'Trở thành nguồn cảm hứng cho cộng đồng';
?>

<section class="py-20 bg-gradient-to-br from-blue-50 to-indigo-100 dark:from-gray-800 dark:to-gray-900" id="introduce">
    <div class="container-custom">
        <!-- Main Title -->
        <div class="text-center mb-16">
            <h2 class="text-5xl md:text-6xl font-bold text-gray-900 dark:text-white mb-6">
                Giới Thiệu
            </h2>
            <div class="w-24 h-1 bg-main mx-auto mb-8"></div>
            <h3 class="text-2xl md:text-3xl font-semibold text-gray-700 dark:text-gray-300 mb-4">
                <?= htmlspecialchars($tieude_text); ?>
            </h3>
            <p class="text-xl text-gray-600 dark:text-gray-400 italic max-w-3xl mx-auto">
                "<?= htmlspecialchars($slogan_text); ?>"
            </p>
        </div>

        <!-- Content Cards -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 max-w-6xl mx-auto">
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-8 shadow-xl hover:shadow-2xl transition-all duration-500 transform hover:-translate-y-2">
                <div class="flex items-center mb-6">
                    <div class="w-12 h-12 bg-main rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-bullseye text-white text-xl"></i>
                    </div>
                    <h4 class="text-2xl font-bold text-gray-900 dark:text-white">
                        <?= htmlspecialchars($tieude_text_1); ?>
                    </h4>
                </div>
                <p class="text-gray-600 dark:text-gray-300 leading-relaxed text-lg">
                    <?= htmlspecialchars($noidung_text_1); ?>
                </p>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl p-8 shadow-xl hover:shadow-2xl transition-all duration-500 transform hover:-translate-y-2">
                <div class="flex items-center mb-6">
                    <div class="w-12 h-12 bg-main rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-eye text-white text-xl"></i>
                    </div>
                    <h4 class="text-2xl font-bold text-gray-900 dark:text-white">
                        <?= htmlspecialchars($tieude_text_2); ?>
                    </h4>
                </div>
                <p class="text-gray-600 dark:text-gray-300 leading-relaxed text-lg">
                    <?= htmlspecialchars($noidung_text_2); ?>
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ========================================
        POPULAR POSTS SECTION
        ======================================== -->
<section class="py-20 bg-white dark:bg-gray-800 overflow-hidden" id="popular-posts">
    <div class="container-custom">
        <div class="flex flex-col md:flex-row items-center justify-between mb-12">
            <h2 class="text-4xl md:text-5xl font-bold text-gray-900 dark:text-white mb-4 md:mb-0">
                Bài viết phổ biến
            </h2>

            <?php
            try {
                $count_posts = $conn->prepare("SELECT COUNT(*) FROM `posts` WHERE status = 'active'");
                $count_posts->execute();
                $num_posts = $count_posts->fetchColumn();

                if ($num_posts >= 4):
            ?>
                    <div class="flex space-x-2">
                        <button id="prevBtn" class="w-12 h-12 bg-main hover:bg-opacity-90 text-white rounded-full flex items-center justify-center transition-all duration-200 hover:scale-110">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button id="nextBtn" class="w-12 h-12 bg-main hover:bg-opacity-90 text-white rounded-full flex items-center justify-center transition-all duration-200 hover:scale-110">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                <?php endif; ?>
            <?php
            } catch (Exception $e) {
                // If database error, don't show navigation buttons
            }
            ?>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8" id="popular-posts-container">
            <?php
            try {
                $select_popular_posts = $conn->prepare("
                    SELECT p.*, COALESCE(u.name, p.name, 'Admin') as author_name,
                           (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as like_count,
                           (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count
                    FROM posts p 
                    LEFT JOIN users u ON (u.legacy_admin_id = p.admin_id OR u.id = p.admin_id) AND u.role = 'admin'
                    WHERE p.status = 'active' 
                    ORDER BY like_count DESC, comment_count DESC, p.id DESC 
                    LIMIT 6
                ");
                $select_popular_posts->execute();
            } catch (Exception $e) {
                $select_popular_posts = $conn->prepare("
                    SELECT p.*, COALESCE(p.name, 'Admin') as author_name,
                           (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as like_count,
                           (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count
                    FROM posts p
                    WHERE p.status = 'active'
                    ORDER BY like_count DESC, comment_count DESC, p.id DESC
                    LIMIT 6
                ");
                $select_popular_posts->execute();
            }

            try {
                if ($select_popular_posts->rowCount() > 0) {
                    while ($post = $select_popular_posts->fetch(PDO::FETCH_ASSOC)) {
                        $post_id = $post['id'];
                        $popularTitle = blog_decode_html_entities_deep((string)($post['title'] ?? ''));
                        $popularCategory = blog_decode_html_entities_deep((string)($post['category'] ?? ''));

                        // Check if user liked/saved this post
                        $confirm_likes = $conn->prepare("SELECT * FROM `likes` WHERE user_id = ? AND post_id = ?");
                        $confirm_likes->execute([$user_id, $post_id]);

                        $confirm_save = $conn->prepare("SELECT * FROM `favorite_posts` WHERE user_id = ? AND post_id = ?");
                        $confirm_save->execute([$user_id, $post_id]);
            ?>
                        <article class="bg-white dark:bg-gray-700 rounded-2xl overflow-hidden shadow-lg hover:shadow-2xl transition-all duration-500 transform hover:-translate-y-2 group">
                            <!-- Post Image -->
                            <?php if (!empty($post['image'])): ?>
                                <div class="relative overflow-hidden h-48">
                                    <img src="<?= htmlspecialchars(blog_post_image_src((string)$post['image'], '../uploaded_img/', '../uploaded_img/default_img.jpg'), ENT_QUOTES, 'UTF-8'); ?>"
                                        alt="<?= htmlspecialchars($popularTitle, ENT_QUOTES, 'UTF-8'); ?>"
                                        class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">

                                    <!-- Save Button Overlay -->
                                    <?php if ($user_id): ?>
                                        <form method="post" class="absolute top-3 right-3">
                                            <input type="hidden" name="post_id" value="<?= $post_id; ?>">
                                            <button type="submit" name="save_post"
                                                class="w-10 h-10 bg-white/90 hover:bg-white rounded-full flex items-center justify-center text-gray-700 hover:text-red-500 transition-all duration-200 shadow-lg">
                                                <i class="fas fa-bookmark <?= $confirm_save->rowCount() > 0 ? 'text-yellow-500' : ''; ?>"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Post Content -->
                            <div class="p-6">
                                <!-- Author Info -->
                                <div class="flex items-center justify-between mb-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 bg-main rounded-full flex items-center justify-center">
                                            <i class="fas fa-user text-white text-sm"></i>
                                        </div>
                                        <div>
                                            <a href="author_posts.php?author=<?= urlencode($post['author_name'] ?? 'Admin'); ?>"
                                                class="text-sm font-semibold text-gray-900 dark:text-white hover:text-main transition-colors">
                                                <?= htmlspecialchars($post['author_name'] ?? 'Admin'); ?>
                                            </a>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                <?= date('d/m/Y', strtotime($post['date'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Post Title -->
                                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-3 line-clamp-2 group-hover:text-main transition-colors">
                                    <a href="<?= post_path($post_id); ?>">
                                        <?= htmlspecialchars($popularTitle, ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                </h3>

                                <!-- Post Content Preview -->
                                <p class="text-gray-600 dark:text-gray-300 mb-4 line-clamp-3">
                                    <?= substr(strip_tags($post['content']), 0, 120); ?>...
                                </p>

                                <!-- Read More Button -->
                                <a href="<?= post_path($post_id); ?>"
                                    class="inline-flex items-center text-main hover:text-main/80 font-semibold transition-colors mb-4">
                                    Đọc thêm
                                    <i class="fas fa-arrow-right ml-2 group-hover:translate-x-1 transition-transform"></i>
                                </a>

                                <!-- Post Meta -->
                                <div class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-gray-600">
                                    <!-- Category -->
                                    <a href="category.php?category=<?= urlencode($post['category']); ?>"
                                        class="inline-flex items-center px-3 py-1 bg-gray-100 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-full text-sm hover:bg-main hover:text-white transition-colors">
                                        <i class="fas fa-tag mr-1"></i>
                                        <?= htmlspecialchars($popularCategory, ENT_QUOTES, 'UTF-8'); ?>
                                    </a>

                                    <!-- Engagement Stats -->
                                    <div class="flex items-center space-x-4 text-sm text-gray-500 dark:text-gray-400">
                                        <a href="<?= post_path($post_id); ?>" class="flex items-center hover:text-main transition-colors">
                                            <i class="fas fa-comment mr-1"></i>
                                            <span><?= $post['comment_count']; ?></span>
                                        </a>

                                        <form method="post" class="inline">
                                            <input type="hidden" name="post_id" value="<?= $post_id; ?>">
                                            <button type="submit" name="like_post" class="flex items-center hover:text-red-500 transition-colors">
                                                <i class="fas fa-heart mr-1 <?= $confirm_likes->rowCount() > 0 ? 'text-red-500' : ''; ?>"></i>
                                                <span><?= $post['like_count']; ?></span>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </article>
            <?php
                    }
                } else {
                    echo '<div class="col-span-full text-center py-12">
                        <i class="fas fa-file-alt text-6xl text-gray-400 mb-4"></i>
                        <p class="text-xl text-gray-500">Chưa có bài viết nào!</p>
                     </div>';
                }
            } catch (Exception $e) {
                echo '<div class="col-span-full text-center py-12">
                        <i class="fas fa-exclamation-triangle text-6xl text-gray-400 mb-4"></i>
                        <p class="text-xl text-gray-500">Có lỗi khi tải bài viết!</p>
                     </div>';
            }
            ?>
        </div>

        <div class="text-center mt-12">
            <a href="posts.php"
                class="btn-primary inline-flex items-center space-x-2 text-lg">
                <span>Xem tất cả bài viết</span>
                <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </div>
</section>

<!-- ========================================
        PHOTO GALLERY SECTION
        ======================================== -->
<section class="py-20 bg-gray-50 dark:bg-gray-900" id="gallery">
    <div class="container-custom">
        <div class="text-center mb-12">
            <h2 class="text-4xl md:text-5xl font-bold text-gray-900 dark:text-white mb-4">
                Bộ sưu tập ảnh
            </h2>
            <p class="text-xl text-gray-600 dark:text-gray-400">
                Những hình ảnh đẹp từ các bài viết của chúng tôi
            </p>
        </div>

        <div class="masonry-grid" id="gallery-grid">
            <div class="grid-sizer"></div>
            <?php
            try {
                $select_photos = $conn->prepare("SELECT image, title, id FROM `posts` WHERE status = 'active' AND image != '' ORDER BY id DESC LIMIT 12");
                $select_photos->execute();

                $count_photos = $conn->prepare("SELECT COUNT(*) FROM `posts` WHERE status = 'active' AND image != ''");
                $count_photos->execute();
                $total_photos = $count_photos->fetchColumn();

                if ($select_photos->rowCount() > 0) {
                    $photos = $select_photos->fetchAll(PDO::FETCH_ASSOC);
                    $masonry_classes = ['masonry-h-md', 'masonry-h-lg', 'masonry-h-sm', 'masonry-h-xl'];
                    foreach ($photos as $index => $photo) {
                        $photo_url = blog_post_image_src((string)$photo['image'], '../uploaded_img/', '../uploaded_img/default_img.jpg');
                        $photo_alt = htmlspecialchars($photo['title']);
                        $height_class = $masonry_classes[$index % count($masonry_classes)];

                        if ($index == 11 && $total_photos > 12) {
                            // Last image shows remaining count
                            $remaining_photos = $total_photos - 12;
            ?>
                            <div class="gallery-item masonry-item <?= $height_class; ?> relative group cursor-pointer overflow-hidden rounded-xl">
                                <img src="<?= $photo_url; ?>" alt="<?= $photo_alt; ?>"
                                    class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                                <div class="absolute inset-0 bg-black/60 flex items-center justify-center">
                                    <div class="text-center text-white">
                                        <span class="text-3xl font-bold">+<?= $remaining_photos; ?></span>
                                        <p class="text-sm">ảnh khác</p>
                                    </div>
                                </div>
                                <a href="all_photos.php" class="absolute inset-0"></a>
                            </div>
                        <?php
                        } else {
                        ?>
                            <div class="gallery-item masonry-item <?= $height_class; ?> relative group cursor-pointer overflow-hidden rounded-xl hover:shadow-2xl transition-all duration-300">
                                <img src="<?= $photo_url; ?>" alt="<?= $photo_alt; ?>"
                                    class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                    <div class="absolute bottom-4 left-4 right-4">
                                        <p class="text-white text-sm font-medium line-clamp-2"><?= $photo_alt; ?></p>
                                    </div>
                                </div>
                                <a href="<?= post_path($photo['id']); ?>" class="absolute inset-0"></a>
                            </div>
            <?php
                        }
                    }
                } else {
                    echo '<div class="col-span-full text-center py-12">
                            <i class="fas fa-images text-6xl text-gray-400 mb-4"></i>
                            <p class="text-xl text-gray-500">Chưa có ảnh nào!</p>
                         </div>';
                }
            } catch (Exception $e) {
                echo '<div class="w-full text-center py-12">
                        <i class="fas fa-exclamation-triangle text-6xl text-red-400 mb-4"></i>
                        <p class="text-xl text-red-500">Có lỗi khi tải bộ sưu tập ảnh!</p>
                     </div>';
            }
            ?>
        </div>

        <div class="text-center mt-12">
            <a href="all_photos.php"
                class="btn-secondary inline-flex items-center space-x-2 text-lg">
                <span>Xem tất cả ảnh</span>
                <i class="fas fa-images"></i>
            </a>
        </div>
    </div>
</section>

<!-- ========================================
        CONTACT SECTION
        ======================================== -->
<?php
// Get contact settings
try {
    $select_contact = $conn->prepare("
           SELECT setting_key, setting_value 
           FROM `settings` 
           WHERE setting_key IN ('lienhe_image', 'lienhe_tieude', 'lienhe_noidung', 'lienhe_email', 'lienhe_name')
       ");
    $select_contact->execute();
    $contact_settings = $select_contact->fetchAll(PDO::FETCH_KEY_PAIR);

    $contact_image = $contact_settings['lienhe_image'] ?? '../uploaded_img/contact.avif';
    $contact_title = $contact_settings['lienhe_tieude'] ?? 'Liên hệ với chúng tôi';
    $contact_content = $contact_settings['lienhe_noidung'] ?? 'Hãy gửi tin nhắn cho chúng tôi';
    $contact_email = $contact_settings['lienhe_email'] ?? 'contact@myblog.com';
    $contact_name = $contact_settings['lienhe_name'] ?? 'My Blog';
} catch (Exception $e) {
    // Fallback values if database query fails
    $contact_image = '../uploaded_img/contact.avif';
    $contact_title = 'Liên hệ với chúng tôi';
    $contact_content = 'Hãy gửi tin nhắn cho chúng tôi';
    $contact_email = 'contact@myblog.com';
    $contact_name = 'My Blog';
}
?>

<section class="py-20 bg-white dark:bg-gray-800" id="contact">
    <div class="container-custom">
        <div class="text-center mb-16">
            <h2 class="text-4xl md:text-5xl font-bold text-gray-900 dark:text-white mb-6">
                Liên hệ với chúng tôi
            </h2>
            <p class="text-xl text-gray-600 dark:text-gray-400 max-w-3xl mx-auto">
                <?= htmlspecialchars($contact_content); ?>
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 max-w-6xl mx-auto">
            <!-- Contact Form -->
            <div class="bg-gray-50 dark:bg-gray-700 rounded-2xl p-8 shadow-xl">
                <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">
                    Gửi tin nhắn
                </h3>

                <form method="post" class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Họ và tên *
                        </label>
                        <input type="text" name="name" required
                            class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg 
                                   bg-white dark:bg-gray-800 text-gray-900 dark:text-white
                                   focus:outline-none focus:ring-2 focus:ring-main focus:border-transparent
                                   transition-all duration-200">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Email *
                        </label>
                        <input type="email" name="email" required
                            class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg 
                                   bg-white dark:bg-gray-800 text-gray-900 dark:text-white
                                   focus:outline-none focus:ring-2 focus:ring-main focus:border-transparent
                                   transition-all duration-200">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Nội dung tin nhắn *
                        </label>
                        <textarea name="noi_dung" rows="6" required
                            class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg 
                                      bg-white dark:bg-gray-800 text-gray-900 dark:text-white
                                      focus:outline-none focus:ring-2 focus:ring-main focus:border-transparent
                                      transition-all duration-200 resize-none"></textarea>
                    </div>

                    <button type="submit" name="submit_contact"
                        class="w-full btn-primary text-lg py-4 hover:shadow-lg transition-all duration-300">
                        <i class="fas fa-paper-plane mr-2"></i>
                        Gửi tin nhắn
                    </button>
                </form>
            </div>

            <!-- Contact Image & Info -->
            <div class="space-y-8">
                <div class="relative overflow-hidden rounded-2xl shadow-xl">
                    <img src="<?= $contact_image; ?>" alt="Contact us"
                        class="w-full h-64 lg:h-80 object-cover">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent">
                        <div class="absolute bottom-6 left-6">
                            <h4 class="text-2xl font-bold text-white mb-2">
                                <?= htmlspecialchars($contact_title); ?>
                            </h4>
                            <p class="text-gray-200">
                                Chúng tôi luôn sẵn sàng lắng nghe
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="bg-gray-50 dark:bg-gray-700 rounded-2xl p-8 shadow-xl">
                    <h4 class="text-xl font-bold text-gray-900 dark:text-white mb-6">
                        Thông tin liên hệ
                    </h4>

                    <div class="space-y-4">
                        <div class="flex items-center space-x-4">
                            <div class="w-10 h-10 bg-main rounded-full flex items-center justify-center">
                                <i class="fas fa-envelope text-white"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Email</p>
                                <a href="mailto:<?= $contact_email; ?>"
                                    class="text-gray-600 dark:text-gray-400 hover:text-main transition-colors">
                                    <?= htmlspecialchars($contact_email); ?>
                                </a>
                            </div>
                        </div>

                        <div class="flex items-center space-x-4">
                            <div class="w-10 h-10 bg-main rounded-full flex items-center justify-center">
                                <i class="fas fa-clock text-white"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Thời gian phản hồi</p>
                                <p class="text-gray-600 dark:text-gray-400">Trong vòng 24 giờ</p>
                            </div>
                        </div>

                        <div class="flex items-center space-x-4">
                            <div class="w-10 h-10 bg-main rounded-full flex items-center justify-center">
                                <i class="fas fa-heart text-white"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Cam kết</p>
                                <p class="text-gray-600 dark:text-gray-400">Hỗ trợ tận tình và chuyên nghiệp</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
    // Hide loader when page loads
    window.addEventListener('load', () => {
        document.getElementById('loader-wrapper').style.display = 'none';
    });

    // Show PHP messages if any
    <?php if (!empty($message)): ?>
        showNotification('<?= implode(', ', $message); ?>', 'success');
    <?php endif; ?>

    // Popular posts carousel functionality
    let currentSlide = 0;
    const postsContainer = document.getElementById('popular-posts-container');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');

    if (postsContainer && prevBtn && nextBtn) {
        const posts = postsContainer.children;
        const postsPerView = window.innerWidth >= 1024 ? 3 : window.innerWidth >= 768 ? 2 : 1;
        const maxSlide = Math.max(0, posts.length - postsPerView);

        function updateCarousel() {
            const translateX = currentSlide * (100 / postsPerView);
            postsContainer.style.transform = `translateX(-${translateX}%)`;

            // Update button states
            prevBtn.disabled = currentSlide === 0;
            nextBtn.disabled = currentSlide >= maxSlide;

            prevBtn.classList.toggle('opacity-50', currentSlide === 0);
            nextBtn.classList.toggle('opacity-50', currentSlide >= maxSlide);
        }

        prevBtn.addEventListener('click', () => {
            if (currentSlide > 0) {
                currentSlide--;
                updateCarousel();
            }
        });

        nextBtn.addEventListener('click', () => {
            if (currentSlide < maxSlide) {
                currentSlide++;
                updateCarousel();
            }
        });

        // Initialize carousel
        updateCarousel();

        // Reset on window resize
        window.addEventListener('resize', () => {
            currentSlide = 0;
            updateCarousel();
        });
    }

    // Gallery masonry layout (if Masonry is available)
    if (typeof Masonry !== 'undefined') {
        window.addEventListener('load', () => {
            const galleryGrid = document.getElementById('gallery-grid');
            if (galleryGrid) {
                new Masonry(galleryGrid, {
                    itemSelector: '.masonry-item',
                    columnWidth: '.grid-sizer',
                    percentPosition: true,
                    gutter: 16,
                    transitionDuration: '0.25s'
                });
            }
        });
    }

    // Smooth scrolling for navigation links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Contact form validation
    const contactForm = document.querySelector('#contact form');
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            const name = this.querySelector('input[name="name"]');
            const email = this.querySelector('input[name="email"]');
            const message = this.querySelector('textarea[name="noi_dung"]');

            let isValid = true;

            // Remove previous error styles
            [name, email, message].forEach(field => {
                field.classList.remove('border-red-500');
            });

            // Validate name
            if (!name.value.trim()) {
                name.classList.add('border-red-500');
                isValid = false;
            }

            // Validate email
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email.value)) {
                email.classList.add('border-red-500');
                isValid = false;
            }

            // Validate message
            if (!message.value.trim() || message.value.length < 10) {
                message.classList.add('border-red-500');
                isValid = false;
            }

            if (!isValid) {
                e.preventDefault();
                showMessage('Vui lòng kiểm tra lại thông tin form!', 'error');
            }
        });
    }

    // Intersection Observer for animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-fadeInUp');
            }
        });
    }, observerOptions);

    // Observe elements for animation
    document.querySelectorAll('.card, article, .gallery-item').forEach(el => {
        observer.observe(el);
    });

    // Add CSS animation class
    const style = document.createElement('style');
    style.textContent = `
         @keyframes fadeInUp {
            from {
               opacity: 0;
               transform: translateY(30px);
            }
            to {
               opacity: 1;
               transform: translateY(0);
            }
         }
         .animate-fadeInUp {
            animation: fadeInUp 0.6s ease-out forwards;
         }
         .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
         }
         .line-clamp-3 {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
         }
      `;
    document.head.appendChild(style);

    // Toastify notification functions
    function showNotification(message, type = 'success') {
        const colors = {
            success: '#10B981',
            error: '#EF4444',
            warning: '#F59E0B',
            info: '#3B82F6'
        };

        Toastify({
            text: message,
            duration: 4000,
            gravity: "top",
            position: "right",
            style: {
                background: colors[type] || colors.success
            },
            stopOnFocus: true,
            className: "custom-toast",
            onClick: function() {}
        }).showToast();
    }

    function showMessage(message, type = 'error') {
        showNotification(message, type);
    }

    // Handle all save_post forms - using regular form submission
    document.addEventListener('DOMContentLoaded', function() {
        // Handle contact form
        const contactForm = document.querySelector('#contact form');
        if (contactForm) {
            contactForm.addEventListener('submit', function(e) {
                e.preventDefault(); // Prevent page reload

                const formData = new FormData(this);
                const submitButton = this.querySelector('button[name="submit_contact"]');
                const originalText = submitButton.innerHTML;

                // Disable button and show loading state
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Đang gửi...';

                // AJAX disabled - using regular form submission
                submitButton.form.submit();
            });
        }

    });
</script>

<?php include '../components/layout_footer.php'; ?>
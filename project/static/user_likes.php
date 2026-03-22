<?php
include '../components/connect.php';
include '../components/seo_helpers.php';

if (function_exists('blog_inject_lazy_loading_into_html')) {
    ob_start('blog_inject_lazy_loading_into_html');
}

session_start();
$message = [];

// Kiểm tra đăng nhập
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} else {
    $user_id = '';
    header('location: login.php');
    exit;
}

include '../components/like_post.php';
include '../components/save_post.php';

// Pagination
$items_per_page = 9;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page <= 0) {
    $current_page = 1;
}

// Count total liked posts
$count_likes = $conn->prepare("
    SELECT COUNT(*) 
    FROM `likes` l 
    JOIN `posts` p ON l.post_id = p.id 
    WHERE l.user_id = ? AND p.status = 'active'
");
$count_likes->execute([$user_id]);
$total_likes = $count_likes->fetchColumn();

$total_pages = ceil($total_likes / $items_per_page);
$offset = ($current_page - 1) * $items_per_page;

// Get liked posts with additional information
$select_liked_posts = $conn->prepare("
    SELECT p.*, 
           COALESCE(lc.like_count, 0) as total_likes,
           COALESCE(cc.comment_count, 0) as total_comments
    FROM `likes` l 
    JOIN `posts` p ON l.post_id = p.id
    LEFT JOIN (
        SELECT post_id, COUNT(*) as like_count 
        FROM `likes` 
        GROUP BY post_id
    ) lc ON p.id = lc.post_id
    LEFT JOIN (
        SELECT post_id, COUNT(*) as comment_count 
        FROM `comments` 
        GROUP BY post_id
    ) cc ON p.id = cc.post_id
    WHERE l.user_id = ? AND p.status = 'active' 
    ORDER BY p.date DESC 
    LIMIT $items_per_page OFFSET $offset
");
$select_liked_posts->execute([$user_id]);

// Get user info
$select_user = $conn->prepare("SELECT name, email FROM `users` WHERE id = ?");
$select_user->execute([$user_id]);
$user_info = $select_user->fetch(PDO::FETCH_ASSOC);

// Get user stats
$user_stats = $conn->prepare("
    SELECT 
        (SELECT COUNT(*) FROM comments WHERE user_id = ?) as comments,
        (SELECT COUNT(*) FROM likes WHERE user_id = ?) as likes,
        (SELECT COUNT(*) FROM favorite_posts WHERE user_id = ?) as saved_posts
");
$user_stats->execute([$user_id, $user_id, $user_id]);
$stats = $user_stats->fetch(PDO::FETCH_ASSOC);
?>

<?php include '../components/layout_header.php'; ?>

<?php
// Include breadcrumb component
include '../components/breadcrumb.php';

// Generate breadcrumb for user likes page
$breadcrumb_items = auto_breadcrumb('Bài viết yêu thích');
render_breadcrumb($breadcrumb_items);
?>

<main class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <div class="container-custom py-8">
        <!-- Header Section -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-red-500 to-pink-600 rounded-full mb-4">
                <i class="fas fa-heart text-white text-2xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">Bài viết đã thích</h1>
            <p class="text-gray-600 dark:text-gray-400">
                Danh sách các bài viết mà bạn đã thích
            </p>
        </div>

        <!-- Alert Messages -->
        <?php if (isset($_SESSION['success_message'])) : ?>
            <div id="successMessage" class="mb-6 p-4 rounded-lg border bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800 text-green-800 dark:text-green-200">
                <div class="flex items-start space-x-3">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-green-500"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium"><?= $_SESSION['success_message'] ?></p>
                    </div>
                    <button onclick="this.parentElement.parentElement.style.display='none'" class="flex-shrink-0 text-green-400 hover:text-green-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8 profile-shell">
            <!-- Sidebar -->
            <div class="lg:col-span-1 order-2 lg:order-1 profile-sidebar">
                <!-- User Info -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-200 dark:border-gray-700 p-6 mb-6 profile-card">
                    <div class="text-center">
                        <div class="w-16 h-16 bg-main rounded-full flex items-center justify-center text-white text-xl font-bold mx-auto mb-4">
                            <?= strtoupper(substr($user_info['name'], 0, 1)) ?>
                        </div>
                        <h3 class="font-bold text-gray-900 dark:text-white mb-1"><?= htmlspecialchars($user_info['name']) ?></h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400"><?= htmlspecialchars($user_info['email']) ?></p>
                    </div>
                </div>

                <!-- Stats -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-200 dark:border-gray-700 p-6 mb-6 profile-card">
                    <h3 class="font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                        <i class="fas fa-chart-bar text-main mr-2"></i>
                        Hoạt động của bạn
                    </h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-3 bg-red-50 dark:bg-red-900/20 rounded-lg">
                            <div class="flex items-center space-x-2">
                                <i class="fas fa-heart text-red-500"></i>
                                <span class="text-sm text-gray-600 dark:text-gray-400">Đã thích</span>
                            </div>
                            <span class="font-bold text-red-500"><?= $stats['likes'] ?></span>
                        </div>

                        <div class="flex items-center justify-between p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                            <div class="flex items-center space-x-2">
                                <i class="fas fa-comments text-blue-500"></i>
                                <span class="text-sm text-gray-600 dark:text-gray-400">Bình luận</span>
                            </div>
                            <span class="font-bold text-blue-500"><?= $stats['comments'] ?></span>
                        </div>

                        <div class="flex items-center justify-between p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                            <div class="flex items-center space-x-2">
                                <i class="fas fa-bookmark text-yellow-500"></i>
                                <span class="text-sm text-gray-600 dark:text-gray-400">Đã lưu</span>
                            </div>
                            <span class="font-bold text-yellow-500"><?= $stats['saved_posts'] ?></span>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-200 dark:border-gray-700 p-6 profile-card">
                    <h3 class="font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                        <i class="fas fa-bolt text-main mr-2"></i>
                        Truy cập nhanh
                    </h3>
                    <div class="space-y-2">
                        <a href="user_comments.php" class="flex items-center space-x-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-main/10 transition-colors group">
                            <i class="fas fa-comments text-blue-500 group-hover:text-blue-600"></i>
                            <span class="text-sm text-gray-700 dark:text-gray-300 group-hover:text-main">Bình luận của tôi</span>
                        </a>
                        <a href="update.php" class="flex items-center space-x-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-main/10 transition-colors group">
                            <i class="fas fa-user-edit text-main group-hover:text-blue-600"></i>
                            <span class="text-sm text-gray-700 dark:text-gray-300 group-hover:text-main">Cập nhật hồ sơ</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="lg:col-span-3 order-1 lg:order-2 profile-main">
                <?php if ($select_liked_posts->rowCount() > 0) : ?>
                    <!-- Liked Posts Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                        <?php while ($fetch_post = $select_liked_posts->fetch(PDO::FETCH_ASSOC)) {
                            $post_id = $fetch_post['id'];

                            $confirm_likes = $conn->prepare("SELECT * FROM `likes` WHERE user_id = ? AND post_id = ?");
                            $confirm_likes->execute([$user_id, $post_id]);

                            $confirm_save = $conn->prepare("SELECT * FROM `favorite_posts` WHERE user_id = ? AND post_id = ?");
                            $confirm_save->execute([$user_id, $post_id]);
                        ?>
                            <article class="post-card group relative">
                                <!-- Liked Badge -->
                                <div class="absolute top-4 left-4 z-10">
                                    <span class="bg-red-500 text-white text-xs font-bold px-3 py-1 rounded-full shadow-lg flex items-center space-x-1">
                                        <i class="fas fa-heart"></i>
                                        <span>ĐÃ THÍCH</span>
                                    </span>
                                </div>

                                <form method="post" class="h-full flex flex-col">
                                    <input type="hidden" name="post_id" value="<?= $post_id; ?>">
                                    <input type="hidden" name="admin_id" value="<?= $fetch_post['admin_id']; ?>">

                                    <!-- Post Header -->
                                    <div class="p-4 flex items-center justify-between border-b border-gray-200 dark:border-gray-600">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-10 h-10 bg-main rounded-full flex items-center justify-center text-white text-sm font-semibold">
                                                <?= strtoupper(substr($fetch_post['name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <a href="author_posts.php?author=<?= $fetch_post['name']; ?>"
                                                    class="font-semibold text-gray-900 dark:text-white hover:text-main transition-colors text-sm">
                                                    <?= $fetch_post['name']; ?>
                                                </a>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    <?= date('d/m/Y', strtotime($fetch_post['date'])); ?>
                                                </div>
                                            </div>
                                        </div>

                                        <button type="submit" name="save_post" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                                            <i class="fas fa-bookmark text-sm <?= $confirm_save->rowCount() > 0 ? 'text-yellow-500' : 'text-gray-400' ?>"></i>
                                        </button>
                                    </div>

                                    <!-- Post Image -->
                                    <?php if ($fetch_post['image'] != '') : ?>
                                        <div class="relative overflow-hidden h-48">
                                            <img src="<?= htmlspecialchars(blog_post_image_src((string)$fetch_post['image'], '../uploaded_img/', '../uploaded_img/default_img.jpg'), ENT_QUOTES, 'UTF-8'); ?>"
                                                alt="<?= $fetch_post['title']; ?>"
                                                class="post-image">
                                            <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent"></div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Post Content -->
                                    <div class="p-4 flex-1 flex flex-col">
                                        <h3 class="font-bold text-gray-900 dark:text-white mb-3 line-clamp-2 group-hover:text-main transition-colors">
                                            <a href="<?= post_path($post_id); ?>">
                                                <?= $fetch_post['title']; ?>
                                            </a>
                                        </h3>

                                        <div class="text-gray-600 dark:text-gray-300 text-sm mb-4 line-clamp-3 flex-1">
                                            <?= strip_tags($fetch_post['content']); ?>
                                        </div>

                                        <a href="<?= post_path($post_id); ?>"
                                            class="text-main font-semibold hover:text-blue-700 transition-colors text-sm mb-4 inline-flex items-center group">
                                            Đọc thêm
                                            <i class="fas fa-arrow-right ml-2 transform group-hover:translate-x-1 transition-transform"></i>
                                        </a>

                                        <!-- Category -->
                                        <a href="category.php?category=<?= $fetch_post['category']; ?>"
                                            class="inline-flex items-center space-x-1 bg-main/10 text-main px-3 py-1 rounded-full text-xs font-medium hover:bg-main/20 transition-colors w-fit">
                                            <i class="fas fa-tag"></i>
                                            <span><?= $fetch_post['category']; ?></span>
                                        </a>
                                    </div>

                                    <!-- Post Actions -->
                                    <div class="p-4 border-t border-gray-200 dark:border-gray-600 flex items-center justify-between">
                                        <div class="flex items-center space-x-4">
                                            <div class="flex items-center space-x-1 text-gray-500 dark:text-gray-400 text-sm">
                                                <i class="fas fa-comment"></i>
                                                <span><?= $fetch_post['total_comments']; ?></span>
                                            </div>

                                            <button type="submit" name="like_post"
                                                class="flex items-center space-x-1 text-red-500 hover:text-red-600 transition-colors text-sm">
                                                <i class="fas fa-heart"></i>
                                                <span><?= $fetch_post['total_likes']; ?></span>
                                            </button>
                                        </div>

                                        <div class="text-xs text-gray-400">
                                            Đã thích bài viết này
                                        </div>
                                    </div>
                                </form>
                            </article>
                        <?php } ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1) : ?>
                        <div class="flex items-center justify-center space-x-4">
                            <?php if ($current_page > 1) : ?>
                                <a href="?page=<?= $current_page - 1 ?>"
                                    class="flex items-center justify-center w-10 h-10 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-main hover:text-white hover:border-main transition-all duration-300 shadow-lg hover:shadow-xl">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>

                            <div class="flex items-center space-x-2">
                                <?php
                                $start = max(1, $current_page - 2);
                                $end = min($total_pages, $current_page + 2);

                                for ($i = $start; $i <= $end; $i++) : ?>
                                    <?php if ($i == $current_page) : ?>
                                        <a href="?page=<?= $i ?>" class="flex items-center justify-center w-10 h-10 rounded-lg transition-all duration-300 shadow-lg hover:shadow-xl border bg-main text-white border-main">
                                            <?= $i ?>
                                        </a>
                                    <?php else : ?>
                                        <a href="?page=<?= $i ?>" class="flex items-center justify-center w-10 h-10 rounded-lg transition-all duration-300 shadow-lg hover:shadow-xl border bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-main hover:text-white hover:border-main">
                                            <?= $i ?>
                                        </a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </div>

                            <?php if ($current_page < $total_pages) : ?>
                                <a href="?page=<?= $current_page + 1 ?>"
                                    class="flex items-center justify-center w-10 h-10 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-main hover:text-white hover:border-main transition-all duration-300 shadow-lg hover:shadow-xl">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>

                        <!-- Page Info -->
                        <div class="text-center mt-6">
                            <span class="text-gray-600 dark:text-gray-400">
                                Trang <?= $current_page ?> trong tổng số <?= $total_pages ?> trang
                                (<?= $total_likes ?> bài viết đã thích)
                            </span>
                        </div>
                    <?php endif; ?>

                <?php else : ?>
                    <!-- Empty State -->
                    <div class="text-center py-16">
                        <div class="bg-white dark:bg-gray-800 rounded-2xl p-12 shadow-lg border border-gray-200 dark:border-gray-700">
                            <div class="w-20 h-20 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-6">
                                <i class="fas fa-heart-broken text-4xl text-gray-400"></i>
                            </div>
                            <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">Chưa có bài viết nào</h3>
                            <p class="text-gray-600 dark:text-gray-400 mb-6">
                                Bạn chưa thích bài viết nào. Hãy khám phá và thích những bài viết yêu thích!
                            </p>

                            <div class="space-y-3">
                                <a href="posts.php" class="block w-full btn-primary max-w-sm mx-auto">
                                    <i class="fas fa-list mr-2"></i>
                                    Khám phá bài viết
                                </a>
                                <a href="home.php" class="block w-full btn-secondary max-w-sm mx-auto">
                                    <i class="fas fa-home mr-2"></i>
                                    Về trang chủ
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Action Tips -->
                <?php if ($select_liked_posts->rowCount() > 0) : ?>
                    <div class="mt-8 bg-gradient-to-r from-red-50 to-pink-50 dark:from-red-900/20 dark:to-pink-900/20 rounded-2xl p-6 border border-red-200 dark:border-red-800">
                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0">
                                <i class="fas fa-lightbulb text-red-500 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-900 dark:text-white mb-2">Mẹo sử dụng</h3>
                                <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
                                    <li>• Nhấp vào icon <i class="fas fa-heart text-red-500"></i> để bỏ thích bài viết</li>
                                    <li>• Nhấp vào icon <i class="fas fa-bookmark text-yellow-500"></i> để lưu/bỏ lưu bài viết</li>
                                    <li>• Nhấp vào tên tác giả để xem các bài viết khác của họ</li>
                                    <li>• Nhấp vào danh mục để xem bài viết cùng chủ đề</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<!-- Enhanced JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-hide alerts
        setTimeout(() => {
            const successMessage = document.getElementById('successMessage');
            if (successMessage) {
                successMessage.style.opacity = '0';
                successMessage.style.transform = 'translateY(-10px)';
                setTimeout(() => successMessage.remove(), 300);
            }
        }, 3000);

        // Post card animations
        const postCards = document.querySelectorAll('.post-card');
        postCards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';

            setTimeout(() => {
                card.style.transition = 'all 0.6s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);

            // Hover effects
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
        const statsElements = document.querySelectorAll('.font-bold.text-red-500, .font-bold.text-blue-500, .font-bold.text-yellow-500');
        statsElements.forEach(element => {
            const finalValue = parseInt(element.textContent);
            if (finalValue > 0) {
                const duration = 1500;
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
            }
        });

        // Heart animation for liked posts
        const heartButtons = document.querySelectorAll('button[name="like_post"]');
        heartButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                // Create heart animation
                const heart = document.createElement('i');
                heart.className = 'fas fa-heart text-red-500 absolute';
                heart.style.fontSize = '20px';
                heart.style.pointerEvents = 'none';
                heart.style.left = '50%';
                heart.style.top = '50%';
                heart.style.transform = 'translate(-50%, -50%)';
                heart.style.animation = 'heartFloat 1s ease-out forwards';

                this.style.position = 'relative';
                this.appendChild(heart);

                setTimeout(() => {
                    if (heart.parentNode) {
                        heart.parentNode.removeChild(heart);
                    }
                }, 1000);
            });
        });
    });

    // Add heart float animation
    const style = document.createElement('style');
    style.textContent = `
    @keyframes heartFloat {
        0% {
            opacity: 1;
            transform: translate(-50%, -50%) scale(1);
        }
        100% {
            opacity: 0;
            transform: translate(-50%, -200%) scale(1.5);
        }
    }
`;
    document.head.appendChild(style);

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
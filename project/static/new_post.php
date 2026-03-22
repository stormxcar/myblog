<?php
include '../components/connect.php';
include '../components/seo_helpers.php';

session_start();
$message = [];

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} else {
    $user_id = '';
};

include '../components/like_post.php';
include '../components/save_post.php';

// Xác định trang hiện tại
$items_per_page = 8;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page <= 0) {
    $current_page = 1;
}

// Get the filter days from URL parameter (default 7 days)
$days = isset($_GET['days']) ? (int)$_GET['days'] : 7;
if ($days <= 0) $days = 7;

// Đếm tổng số bài viết mới đăng trong X ngày gần đây
$count_posts = $conn->prepare("SELECT COUNT(*) FROM `posts` WHERE status = ? AND date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)");
$count_posts->execute(['active', $days]);
$total_posts = $count_posts->fetchColumn();

$total_pages = ceil($total_posts / $items_per_page);
$offset = ($current_page - 1) * $items_per_page;

$select_posts = $conn->prepare("SELECT * FROM `posts` WHERE status = ? AND date >= DATE_SUB(CURDATE(), INTERVAL ? DAY) ORDER BY date DESC LIMIT $items_per_page OFFSET $offset");
$select_posts->execute(['active', $days]);

?>

<?php include '../components/layout_header.php'; ?>

<?php
// Include breadcrumb component
include '../components/breadcrumb.php';

// Generate breadcrumb for new post page
$breadcrumb_items = auto_breadcrumb('Viết bài mới');
render_breadcrumb($breadcrumb_items);
?>

<main class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <div class="container-custom py-8">
        <!-- Header Section -->
        <div class="text-center mb-12">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-green-500 to-blue-600 rounded-full mb-6">
                <i class="fas fa-newspaper text-white text-3xl"></i>
            </div>
            <h1 class="text-4xl md:text-5xl font-bold text-gray-900 dark:text-white mb-4">
                Bài viết <span class="gradient-text">mới nhất</span>
            </h1>
            <div class="section-divider mb-6"></div>
            <p class="text-lg text-gray-600 dark:text-gray-300 max-w-2xl mx-auto">
                Cập nhật những bài viết mới nhất từ cộng đồng trong <?= $days ?> ngày qua
            </p>
        </div>

        <!-- Filter Section -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-6 mb-8 border border-gray-200 dark:border-gray-700">
            <div class="flex flex-col md:flex-row items-center justify-between space-y-4 md:space-y-0">
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-calendar-alt text-main"></i>
                        <span class="font-semibold text-gray-900 dark:text-white">Lọc theo thời gian:</span>
                    </div>
                    <div class="flex space-x-2">
                        <?php
                        $filter_options = [
                            1 => 'Hôm nay',
                            3 => '3 ngày',
                            7 => '1 tuần',
                            14 => '2 tuần',
                            30 => '1 tháng'
                        ];
                        foreach ($filter_options as $day_count => $label) :
                        ?>
                            <a href="?days=<?= $day_count ?>"
                                class="px-4 py-2 rounded-lg text-sm font-medium transition-all duration-300 <?= $days == $day_count ? 'bg-main text-white shadow-lg' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-main hover:text-white' ?>">
                                <?= $label ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Stats -->
                <div class="flex items-center space-x-6 text-sm">
                    <div class="flex items-center space-x-2">
                        <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                        <span class="text-gray-600 dark:text-gray-400">
                            <strong class="text-gray-900 dark:text-white"><?= $total_posts ?></strong> bài viết mới
                        </span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="w-3 h-3 bg-blue-500 rounded-full"></div>
                        <span class="text-gray-600 dark:text-gray-400">
                            Trong <strong class="text-gray-900 dark:text-white"><?= $days ?></strong> ngày qua
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($select_posts->rowCount() > 0) : ?>
            <!-- Posts Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8 mb-12">
                <?php while ($fetch_posts = $select_posts->fetch(PDO::FETCH_ASSOC)) {
                    $post_id = $fetch_posts['id'];

                    $count_post_comments = $conn->prepare("SELECT * FROM `comments` WHERE post_id = ?");
                    $count_post_comments->execute([$post_id]);
                    $total_post_comments = $count_post_comments->rowCount();

                    $count_post_likes = $conn->prepare("SELECT * FROM `likes` WHERE post_id = ?");
                    $count_post_likes->execute([$post_id]);
                    $total_post_likes = $count_post_likes->rowCount();

                    $confirm_likes = $conn->prepare("SELECT * FROM `likes` WHERE user_id = ? AND post_id = ?");
                    $confirm_likes->execute([$user_id, $post_id]);

                    $confirm_save = $conn->prepare("SELECT * FROM `favorite_posts` WHERE user_id = ? AND post_id = ?");
                    $confirm_save->execute([$user_id, $post_id]);

                    // Calculate how many days ago
                    $post_date = new DateTime($fetch_posts['date']);
                    $now = new DateTime();
                    $diff = $now->diff($post_date);

                    if ($diff->days == 0) {
                        $time_ago = "Hôm nay";
                    } elseif ($diff->days == 1) {
                        $time_ago = "Hôm qua";
                    } else {
                        $time_ago = $diff->days . " ngày trước";
                    }
                ?>
                    <article class="post-card group relative">
                        <!-- New Badge -->
                        <div class="absolute top-4 left-4 z-10">
                            <span class="bg-green-500 text-white text-xs font-bold px-3 py-1 rounded-full shadow-lg flex items-center space-x-1">
                                <i class="fas fa-star"></i>
                                <span>MỚI</span>
                            </span>
                        </div>

                        <!-- Time Badge -->
                        <div class="absolute top-4 right-4 z-10">
                            <span class="bg-white/90 dark:bg-gray-800/90 text-gray-700 dark:text-gray-300 text-xs font-medium px-2 py-1 rounded-full backdrop-blur-sm">
                                <?= $time_ago ?>
                            </span>
                        </div>

                        <form method="post" class="h-full flex flex-col">
                            <input type="hidden" name="post_id" value="<?= $post_id; ?>">
                            <input type="hidden" name="admin_id" value="<?= $fetch_posts['admin_id']; ?>">

                            <!-- Post Header -->
                            <div class="p-4 flex items-center justify-between border-b border-gray-200 dark:border-gray-600">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-main rounded-full flex items-center justify-center text-white text-sm font-semibold">
                                        <?= strtoupper(substr($fetch_posts['name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <a href="author_posts.php?author=<?= $fetch_posts['name']; ?>"
                                            class="font-semibold text-gray-900 dark:text-white hover:text-main transition-colors text-sm">
                                            <?= $fetch_posts['name']; ?>
                                        </a>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            <?= date('d/m/Y H:i', strtotime($fetch_posts['date'])); ?>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" name="save_post" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                                    <i class="fas fa-bookmark text-sm <?= $confirm_save->rowCount() > 0 ? 'text-yellow-500' : 'text-gray-400' ?>"></i>
                                </button>
                            </div>

                            <!-- Post Image -->
                            <?php if ($fetch_posts['image'] != '') : ?>
                                <div class="relative overflow-hidden h-48">
                                    <img src="<?= htmlspecialchars(blog_post_image_src((string)$fetch_posts['image'], '../uploaded_img/', '../uploaded_img/default_img.jpg'), ENT_QUOTES, 'UTF-8'); ?>"
                                        alt="<?= $fetch_posts['title']; ?>"
                                        class="post-image">
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent"></div>
                                </div>
                            <?php endif; ?>

                            <!-- Post Content -->
                            <div class="p-4 flex-1 flex flex-col">
                                <h3 class="font-bold text-gray-900 dark:text-white mb-3 line-clamp-2 group-hover:text-main transition-colors">
                                    <a href="<?= post_path($post_id); ?>">
                                        <?= $fetch_posts['title']; ?>
                                    </a>
                                </h3>

                                <div class="text-gray-600 dark:text-gray-300 text-sm mb-4 line-clamp-3 flex-1">
                                    <?= strip_tags($fetch_posts['content']); ?>
                                </div>

                                <a href="<?= post_path($post_id); ?>"
                                    class="text-main font-semibold hover:text-blue-700 transition-colors text-sm mb-4 inline-flex items-center group">
                                    Đọc thêm
                                    <i class="fas fa-arrow-right ml-2 transform group-hover:translate-x-1 transition-transform"></i>
                                </a>

                                <!-- Category -->
                                <a href="category.php?category=<?= $fetch_posts['category']; ?>"
                                    class="inline-flex items-center space-x-1 bg-main/10 text-main px-3 py-1 rounded-full text-xs font-medium hover:bg-main/20 transition-colors w-fit">
                                    <i class="fas fa-tag"></i>
                                    <span><?= $fetch_posts['category']; ?></span>
                                </a>
                            </div>

                            <!-- Post Actions -->
                            <div class="p-4 border-t border-gray-200 dark:border-gray-600 flex items-center justify-between">
                                <a href="<?= post_path($post_id); ?>"
                                    class="flex items-center space-x-1 text-gray-500 dark:text-gray-400 hover:text-main transition-colors text-sm">
                                    <i class="fas fa-comment"></i>
                                    <span><?= $total_post_comments; ?></span>
                                </a>

                                <button type="submit" name="like_post"
                                    class="flex items-center space-x-1 text-gray-500 dark:text-gray-400 hover:text-red-500 transition-colors text-sm">
                                    <i class="fas fa-heart <?= $confirm_likes->rowCount() > 0 ? 'text-red-500' : '' ?>"></i>
                                    <span><?= $total_post_likes; ?></span>
                                </button>
                            </div>
                        </form>
                    </article>
                <?php } ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1) : ?>
                <div class="flex items-center justify-center space-x-4">
                    <?php if ($current_page > 1) : ?>
                        <a href="?days=<?= $days ?>&page=<?= $current_page - 1 ?>"
                            class="flex items-center justify-center w-10 h-10 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-main hover:text-white hover:border-main transition-all duration-300 shadow-lg hover:shadow-xl">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>

                    <div class="flex items-center space-x-2">
                        <?php
                        $start = max(1, $current_page - 2);
                        $end = min($total_pages, $current_page + 2);

                        for ($i = $start; $i <= $end; $i++) : ?>
                            <a href="?days=<?= $days ?>&page=<?= $i ?>"
                                class="flex items-center justify-center w-10 h-10 rounded-lg transition-all duration-300 shadow-lg hover:shadow-xl <?= $i == $current_page ? 'bg-main text-white border-main' : 'bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-main hover:text-white hover:border-main' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                    </div>

                    <?php if ($current_page < $total_pages) : ?>
                        <a href="?days=<?= $days ?>&page=<?= $current_page + 1 ?>"
                            class="flex items-center justify-center w-10 h-10 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-main hover:text-white hover:border-main transition-all duration-300 shadow-lg hover:shadow-xl">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Page Info -->
                <div class="text-center mt-6">
                    <span class="text-gray-600 dark:text-gray-400">
                        Trang <?= $current_page ?> trong tổng số <?= $total_pages ?> trang
                        (<?= $total_posts ?> bài viết mới)
                    </span>
                </div>
            <?php endif; ?>

        <?php else : ?>
            <!-- Empty State -->
            <div class="text-center py-16">
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-12 shadow-lg border border-gray-200 dark:border-gray-700 max-w-md mx-auto">
                    <div class="w-20 h-20 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-calendar-times text-4xl text-gray-400"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">Chưa có bài viết mới</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-6">
                        Không có bài viết nào được đăng trong <?= $days ?> ngày qua
                    </p>

                    <div class="space-y-3">
                        <a href="?days=30" class="block w-full btn-primary">
                            <i class="fas fa-search mr-2"></i>
                            Xem bài viết trong 30 ngày
                        </a>
                        <a href="posts.php" class="block w-full btn-secondary">
                            <i class="fas fa-list mr-2"></i>
                            Xem tất cả bài viết
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>


    </div>
</main>

<!-- Enhanced JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
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

        // Enhanced post card interactions
        const postCards = document.querySelectorAll('.post-card');
        postCards.forEach((card, index) => {
            // Stagger animation
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

        // Auto-refresh every 5 minutes
        setInterval(() => {
            const refreshBtn = document.createElement('div');
            refreshBtn.className = 'fixed bottom-6 left-6 bg-blue-500 text-white px-4 py-2 rounded-lg shadow-lg cursor-pointer hover:bg-blue-600 transition-colors';
            refreshBtn.innerHTML = '<i class="fas fa-sync-alt mr-2"></i>Làm mới';
            refreshBtn.onclick = () => window.location.reload();

            document.body.appendChild(refreshBtn);

            setTimeout(() => {
                refreshBtn.remove();
            }, 5000);
        }, 300000); // 5 minutes
    });

    // Notification subscription (placeholder)
    function subscribeToNotifications() {
        if ('Notification' in window) {
            Notification.requestPermission().then(permission => {
                if (permission === 'granted') {
                    new Notification('Đã đăng ký thành công!', {
                        body: 'Bạn sẽ nhận được thông báo khi có bài viết mới.',
                        icon: '/favicon.ico'
                    });
                }
            });
        } else {
            alert('Trình duyệt không hỗ trợ thông báo');
        }
    }

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
<?php

include '../components/connect.php';
include '../components/seo_helpers.php';

if (function_exists('blog_inject_lazy_loading_into_html')) {
    ob_start('blog_inject_lazy_loading_into_html');
}

session_start();

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} else {
    $user_id = '';
};

include '../components/like_post.php';
include '../components/save_post.php';

// Xác định trang hiện tại
$allowed_page_sizes = [8, 12, 16, 24];
$items_per_page = isset($_GET['size']) ? (int)$_GET['size'] : 8;
if (!in_array($items_per_page, $allowed_page_sizes, true)) {
    $items_per_page = 8;
}
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page <= 0) {
    $current_page = 1;
}

// View mode (tabs)
$view = isset($_GET['view']) ? trim((string)$_GET['view']) : 'all';
$allowed_views = ['all', 'featured', 'latest'];
if (!in_array($view, $allowed_views, true)) {
    $view = 'all';
}

// Category filter
$categoryFilter = isset($_GET['category']) ? trim((string)$_GET['category']) : 'all';
if ($categoryFilter === '') {
    $categoryFilter = 'all';
}

try {
    // Đếm tổng số bài viết (cho pagination)
    $countSql = "SELECT COUNT(*) FROM `posts` WHERE status = ?";
    $countParams = ['active'];
    if ($categoryFilter !== 'all') {
        $countSql .= ' AND category = ?';
        $countParams[] = $categoryFilter;
    }
    if ($view === 'latest') {
        $countSql .= ' AND date >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
    }

    $count_posts = $conn->prepare($countSql);
    $count_posts->execute($countParams);
    $total_posts = $count_posts->fetchColumn();

    // Tính tổng số trang
    $total_pages = ceil($total_posts / $items_per_page);
    // Tính toán giới hạn và bù trừ cho truy vấn SQL
    $offset = ($current_page - 1) * $items_per_page;

    // Chuẩn bị query bài viết chính (phụ thuộc tab)
    if ($view === 'featured') {
        $selectSql = "SELECT p.*, COUNT(l.id) AS total_likes
            FROM posts p
            LEFT JOIN likes l ON l.post_id = p.id
            WHERE p.status = ?";
        $selectParams = ['active'];
        if ($categoryFilter !== 'all') {
            $selectSql .= ' AND p.category = ?';
            $selectParams[] = $categoryFilter;
        }
        $selectSql .= " GROUP BY p.id ORDER BY total_likes DESC, p.date DESC LIMIT $items_per_page OFFSET $offset";
        $select_posts = $conn->prepare($selectSql);
        $select_posts->execute($selectParams);
    } else {
        $selectSql = "SELECT * FROM `posts` WHERE status = ?";
        $selectParams = ['active'];
        if ($categoryFilter !== 'all') {
            $selectSql .= ' AND category = ?';
            $selectParams[] = $categoryFilter;
        }
        if ($view === 'latest') {
            $selectSql .= ' AND date >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
        }
        $selectSql .= " ORDER BY date DESC LIMIT $items_per_page OFFSET $offset";
        $select_posts = $conn->prepare($selectSql);
        $select_posts->execute($selectParams);
    }
} catch (Exception $e) {
    $total_posts = 0;
    $total_pages = 0;
    $select_posts = null;
}

?>

<?php
// Only include layout header if not AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    include '../components/layout_header.php';
}
?>

<?php
// Include breadcrumb component
include '../components/breadcrumb.php';

// Generate breadcrumb for posts page
$breadcrumb_items = auto_breadcrumb('Bài viết');

// Only render breadcrumb if not AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    render_breadcrumb($breadcrumb_items);
}
?>

<?php
// Only output HTML if not AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
?>

    <main class="min-h-screen bg-gray-50 dark:bg-gray-900">
        <style>
            #posts-grid {
                transition: all 0.25s ease;
            }

            #posts-grid[data-layout="grid"] .post-card-inner {
                display: block;
            }

            #posts-grid[data-layout="grid"] .post-card-image {
                width: 100%;
                height: 190px;
                margin-bottom: 0.75rem;
            }

            #posts-grid[data-layout="grid"] .post-card-content {
                min-height: 190px;
            }

            #posts-grid[data-layout="grid"] .post-card-content .line-clamp-2 {
                display: -webkit-box;
                -webkit-line-clamp: 2;
                line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }

            #posts-grid[data-layout="list"] {
                grid-template-columns: minmax(0, 1fr);
                gap: 1rem;
            }

            #posts-grid[data-layout="list"] .blog-card-shared {
                transform: none !important;
                border-radius: 1rem;
            }

            #posts-grid[data-layout="list"] .post-card-inner {
                display: grid;
                grid-template-columns: minmax(150px, 100px) minmax(0, 1fr);
                gap: 1rem;
                align-items: start;
            }

            #posts-grid[data-layout="list"] .post-card-image {
                width: 150px;
                max-width: none;
                min-width: 0;
                margin-bottom: 0;
                height: 120px;
            }

            #posts-grid[data-layout="list"] .post-card-content {
                min-width: 0;
            }

            #posts-grid[data-layout="list"] .post-card-content .line-clamp-2 {
                display: -webkit-box;
                -webkit-line-clamp: 2;
                line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }

            @media (max-width: 767px) {
                #posts-grid[data-layout="list"] .post-card-inner {
                    grid-template-columns: 1fr;
                }

                #posts-grid[data-layout="list"] .post-card-image {
                    height: 180px;
                }
            }
        </style>

        <div class="container-custom py-12">
            <!-- Page Header -->
            <div class="text-center mb-12">
                <h1 class="text-4xl md:text-5xl font-bold text-gray-900 dark:text-white mb-4">
                    Tất cả <span class="gradient-text">Bài viết</span>
                </h1>
                <div class="section-divider"></div>
                <p class="text-lg text-gray-600 dark:text-gray-300 mt-6 max-w-2xl mx-auto">
                    Khám phá những bài viết thú vị và chia sẻ kiến thức từ cộng đồng của chúng tôi
                </p>
            </div>

            <?php
            // Category list for filter
            try {
                $categoryStmt = $conn->prepare("SELECT DISTINCT category FROM posts WHERE status = ? AND category <> '' ORDER BY category ASC");
                $categoryStmt->execute(['active']);
                $categories = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);
            } catch (Exception $e) {
                $categories = [];
            }
            ?>

            <div id="posts-ajax-root">
                <!-- View Tabs -->
                <div class="flex flex-wrap items-center gap-2 mb-6">
                    <?php
                    $tabs = [
                        'all' => 'Tất cả bài viết',
                        'featured' => 'Nổi bật',
                        'latest' => 'Mới nhất'
                    ];
                    foreach ($tabs as $tabKey => $tabLabel) :
                        $tabUrl = '?' . http_build_query(array_merge($_GET, ['view' => $tabKey, 'page' => 1]));
                        $isActive = $view === $tabKey;
                    ?>
                        <a href="<?= $tabUrl; ?>" data-posts-nav class="px-4 py-2 rounded-lg text-sm font-semibold transition-colors <?= $isActive ? 'bg-main text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-main/10 dark:hover:bg-main/20'; ?>">
                            <?= $tabLabel; ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Posts Grid -->
                <div class="mb-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                    <form method="get" id="postsFilterForm" class="flex flex-col md:flex-row items-start md:items-center gap-3">
                        <input type="hidden" name="view" value="<?= htmlspecialchars($view, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="page" value="1">

                        <div class="flex items-center gap-2">
                            <label for="size" class="text-sm text-gray-600 dark:text-gray-300">Hiển thị:</label>
                            <select id="size" name="size" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-200">
                                <?php foreach ($allowed_page_sizes as $size_option) : ?>
                                    <option value="<?= $size_option; ?>" <?= $items_per_page === $size_option ? 'selected' : ''; ?>><?= $size_option; ?>/trang</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <?php if (!empty($categories)): ?>
                            <div class="flex items-center gap-2">
                                <label for="category" class="text-sm text-gray-600 dark:text-gray-300">Danh mục:</label>
                                <select id="category" name="category" class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-200">
                                    <option value="all" <?= $categoryFilter === 'all' ? 'selected' : ''; ?>>Tất cả</option>
                                    <?php foreach ($categories as $catOption) : ?>
                                        <option value="<?= htmlspecialchars($catOption, ENT_QUOTES, 'UTF-8'); ?>" <?= $categoryFilter === $catOption ? 'selected' : ''; ?>><?= htmlspecialchars($catOption, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                    </form>

                    <div class="flex items-center gap-2">
                        <span class="text-sm text-gray-600 dark:text-gray-300">Bố cục:</span>
                        <button type="button" class="posts-layout-btn px-3 py-2 rounded-lg bg-main text-white text-sm font-semibold" data-layout="grid" aria-pressed="true" title="Bố cục lưới">
                            <i class="fas fa-th"></i>
                        </button>
                        <button type="button" class="posts-layout-btn px-3 py-2 rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-sm font-semibold" data-layout="list" aria-pressed="false" title="Bố cục danh sách">
                            <i class="fas fa-list"></i>
                        </button>
                    </div>
                </div>

                <div id="posts-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8 mb-12" data-layout="grid">
                    <?php
                    if ($select_posts && $select_posts->rowCount() > 0) {
                        $posts_rows = $select_posts->fetchAll(PDO::FETCH_ASSOC);
                        $tag_map = blog_get_tags_map_for_posts($conn, array_map(function ($row) {
                            return (int)($row['id'] ?? 0);
                        }, $posts_rows));

                        foreach ($posts_rows as $fetch_posts) {
                            $post_id = $fetch_posts['id'];
                            $post_tags = $tag_map[(int)$post_id] ?? [];

                            try {
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
                            } catch (Exception $e) {
                                $total_post_comments = 0;
                                $total_post_likes = 0;
                                $confirm_likes = null;
                                $confirm_save = null;
                            }
                    ?>
                            <article class="card dark:bg-gray-700 group blog-card-shared hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2">
                                <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="h-full flex flex-col">
                                    <input type="hidden" name="post_id" value="<?= $post_id; ?>">
                                    <input type="hidden" name="admin_id" value="<?= $fetch_posts['admin_id']; ?>">

                                    <?php
                                    $displayTitle = blog_decode_html_entities_deep((string)($fetch_posts['title'] ?? ''));
                                    $displayAuthor = blog_decode_html_entities_deep((string)($fetch_posts['name'] ?? ''));
                                    $displayCategory = blog_decode_html_entities_deep((string)($fetch_posts['category'] ?? ''));
                                    $displaySnippet = trim(strip_tags(blog_decode_html_entities_deep((string)($fetch_posts['content'] ?? ''))));
                                    ?>

                                    <!-- Post Header -->
                                    <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-600">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-10 h-10 bg-main rounded-full flex items-center justify-center text-white text-sm font-semibold">
                                                <?= strtoupper(substr((string)$displayAuthor, 0, 1)) ?>
                                            </div>
                                            <div>
                                                <a href="author_posts.php?author=<?= urlencode($displayAuthor); ?>"
                                                    class="font-semibold text-gray-900 dark:text-white hover:text-main transition-colors">
                                                    <?= htmlspecialchars($displayAuthor, ENT_QUOTES, 'UTF-8'); ?>
                                                </a>
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    <?= date('d/m/Y', strtotime($fetch_posts['date'])); ?>
                                                </div>
                                            </div>
                                        </div>

                                        <button type="submit" name="save_post" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                                            <i class="fas fa-bookmark text-lg <?= $confirm_save && $confirm_save->rowCount() > 0 ? 'text-yellow-500' : 'text-gray-400' ?>"></i>
                                        </button>
                                    </div>

                                    <div class="post-card-inner flex flex-col lg:flex-row lg:items-start lg:gap-6 py-4">
                                        <!-- Post Image -->
                                        <?php if ($fetch_posts['image'] != '') : ?>
                                            <div class="post-card-image relative overflow-hidden rounded-lg mb-4 lg:mb-0 lg:w-56 lg:h-40 flex-shrink-0">
                                                <img src="<?= htmlspecialchars(blog_post_image_src((string)$fetch_posts['image'], '../uploaded_img/', '../uploaded_img/default_img.jpg'), ENT_QUOTES, 'UTF-8'); ?>"
                                                    alt="<?= htmlspecialchars($displayTitle, ENT_QUOTES, 'UTF-8'); ?>"
                                                    class="w-full h-full object-cover">
                                                <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent"></div>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Post Content -->
                                        <div class="post-card-content flex-1 flex flex-col">
                                            <a href="<?= post_path($post_id); ?>"
                                                class="text-xl font-bold text-gray-900 dark:text-white hover:text-main transition-colors mb-2 line-clamp-2">
                                                <?= htmlspecialchars($displayTitle, ENT_QUOTES, 'UTF-8'); ?>
                                            </a>

                                            <div class="text-gray-600 dark:text-gray-300 mb-3 line-clamp-2 flex-1">
                                                <?= htmlspecialchars($displaySnippet, ENT_QUOTES, 'UTF-8'); ?>
                                            </div>

                                            <div class="flex flex-wrap items-center justify-between gap-3">
                                                <a href="<?= post_path($post_id); ?>"
                                                    class="text-main font-semibold hover:text-blue-700 transition-colors text-sm">
                                                    Đọc thêm →
                                                </a>

                                                <!-- Category -->
                                                <a href="category.php?category=<?= urlencode($displayCategory); ?>"
                                                    class="inline-flex items-center space-x-1 bg-main/10 text-main px-3 py-1 rounded-full text-xs font-medium hover:bg-main/20 transition-colors">
                                                    <i class="fas fa-tag text-xs"></i>
                                                    <span><?= htmlspecialchars($displayCategory, ENT_QUOTES, 'UTF-8'); ?></span>
                                                </a>
                                            </div>

                                            <?php if (!empty($post_tags)): ?>
                                                <div class="mt-2 flex flex-wrap gap-1">
                                                    <?php foreach ($post_tags as $tag): ?>
                                                        <a href="search.php?tag=<?= urlencode((string)$tag['slug']); ?>&size=12&page=1"
                                                            class="px-2 py-1 rounded-full text-[11px] bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-main hover:text-white transition-colors">
                                                            #<?= htmlspecialchars((string)$tag['name'], ENT_QUOTES, 'UTF-8'); ?>
                                                        </a>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Post Actions -->
                                    <div class="post-actions border-t border-gray-200 dark:border-gray-600 flex items-center justify-between">
                                        <a href="<?= post_path($post_id); ?>"
                                            class="flex items-center space-x-1 text-gray-500 dark:text-gray-400 hover:text-main transition-colors">
                                            <i class="fas fa-comment"></i>
                                            <span><?= $total_post_comments; ?></span>
                                        </a>

                                        <button type="submit" name="like_post"
                                            class="flex items-center space-x-1 text-gray-500 dark:text-gray-400 hover:text-red-500 transition-colors">
                                            <i class="fas fa-heart <?= $confirm_likes && $confirm_likes->rowCount() > 0 ? 'text-red-500' : '' ?>"></i>
                                            <span><?= $total_post_likes; ?></span>
                                        </button>
                                    </div>
                                </form>
                            </article>
                    <?php
                        }
                    } elseif (!$select_posts) {
                        echo '<div class="col-span-full text-center py-16">';
                        echo '<i class="fas fa-exclamation-triangle text-6xl text-red-400 mb-4"></i>';
                        echo '<p class="text-xl text-red-500">Có lỗi khi tải danh sách bài viết!</p>';
                        echo '</div>';
                    } else {
                        echo '<div class="col-span-full text-center py-16">';
                        echo '<i class="fas fa-file-alt text-6xl text-gray-300 dark:text-gray-600 mb-4"></i>';
                        echo '<p class="text-xl text-gray-500 dark:text-gray-400">Chưa có bài viết nào được thêm!</p>';
                        echo '</div>';
                    }
                    ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1) : ?>
                    <div class="flex items-center justify-center space-x-4">
                        <?php if ($current_page > 1) : ?>
                            <a href="?page=<?= $current_page - 1 ?>&size=<?= $items_per_page ?>&view=<?= urlencode($view); ?>&category=<?= urlencode($categoryFilter); ?>" data-posts-nav
                                class="flex items-center justify-center w-10 h-10 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-main hover:text-white hover:border-main transition-all duration-300 shadow-lg hover:shadow-xl">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>

                        <div class="flex items-center space-x-2">
                            <?php
                            $start = max(1, $current_page - 2);
                            $end = min($total_pages, $current_page + 2);

                            for ($i = $start; $i <= $end; $i++) :
                                $isActivePage = ($i == $current_page);
                                $pageClass = 'flex items-center justify-center w-10 h-10 rounded-lg transition-all duration-300 shadow-lg hover:shadow-xl ';

                                if ($isActivePage) {
                                    $pageClass .= 'bg-main text-white border-main';
                                } else {
                                    $pageClass .= 'bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-main hover:text-white hover:border-main';
                                }
                            ?>
                                <a href="?page=<?= $i ?>&size=<?= $items_per_page ?>&view=<?= urlencode($view); ?>&category=<?= urlencode($categoryFilter); ?>" data-posts-nav
                                    class="<?= $pageClass ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                        </div>

                        <?php if ($current_page < $total_pages) : ?>
                            <a href="?page=<?= $current_page + 1 ?>&size=<?= $items_per_page ?>&view=<?= urlencode($view); ?>&category=<?= urlencode($categoryFilter); ?>" data-posts-nav
                                class="flex items-center justify-center w-10 h-10 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-main hover:text-white hover:border-main transition-all duration-300 shadow-lg hover:shadow-xl">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Page Info -->
                    <div class="text-center mt-6">
                        <span class="text-gray-600 dark:text-gray-400">
                            Trang <?= $current_page ?> trong tổng số <?= $total_pages ?> trang
                            (<?= $total_posts ?> bài viết)
                        </span>
                    </div>
                <?php endif; ?>
            </div>
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

            // Smooth scroll for pagination
            async function loadPostsWithoutReload(url, pushState = true) {
                const ajaxRoot = document.getElementById('posts-ajax-root');
                if (!ajaxRoot) {
                    return;
                }

                ajaxRoot.classList.add('opacity-60');
                try {
                    const response = await fetch(url, {
                        credentials: 'same-origin'
                    });
                    const html = await response.text();
                    const parser = new DOMParser();
                    const nextDoc = parser.parseFromString(html, 'text/html');
                    const nextRoot = nextDoc.getElementById('posts-ajax-root');
                    if (!nextRoot) {
                        window.location.href = url;
                        return;
                    }

                    ajaxRoot.replaceWith(nextRoot);
                    if (pushState) {
                        history.pushState({}, '', url);
                    }

                    applyLayout(localStorage.getItem(LAYOUT_KEY) || 'grid');
                    window.scrollTo({
                        top: 180,
                        behavior: 'smooth'
                    });
                } catch (error) {
                    window.location.href = url;
                }
            }

            document.addEventListener('click', function(event) {
                const navLink = event.target.closest('[data-posts-nav]');
                if (!navLink) {
                    return;
                }
                event.preventDefault();
                const href = navLink.getAttribute('href');
                if (!href) {
                    return;
                }
                loadPostsWithoutReload(href, true);
            });

            document.addEventListener('change', function(event) {
                const target = event.target;
                if (!(target instanceof HTMLSelectElement)) {
                    return;
                }
                if (target.id !== 'size' && target.id !== 'category') {
                    return;
                }
                const form = target.closest('form');
                if (!form) {
                    return;
                }
                const params = new URLSearchParams(new FormData(form));
                loadPostsWithoutReload('?' + params.toString(), true);
            });

            // Lazy loading for images
            const images = document.querySelectorAll('img[data-src]');
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('opacity-0');
                        img.classList.add('opacity-100');
                        observer.unobserve(img);
                    }
                });
            });

            images.forEach(img => imageObserver.observe(img));

            // Enhanced post card interactions
            const postCards = document.querySelectorAll('.blog-card-shared');
            postCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    const currentPostsGrid = document.getElementById('posts-grid');
                    if (currentPostsGrid && currentPostsGrid.getAttribute('data-layout') === 'list') {
                        return;
                    }
                    this.style.transform = 'translateY(-8px)';
                    this.style.boxShadow = '0 18px 35px rgba(0, 0, 0, 0.14)';
                });

                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = '';
                });
            });

            // Layout switcher (grid/list)
            const LAYOUT_KEY = 'postsLayout';

            function applyLayout(layout) {
                const postsGrid = document.getElementById('posts-grid');
                const layoutButtons = document.querySelectorAll('.posts-layout-btn');
                if (!postsGrid) return;
                postsGrid.setAttribute('data-layout', layout === 'list' ? 'list' : 'grid');

                layoutButtons.forEach(btn => {
                    const isActive = btn.getAttribute('data-layout') === layout;
                    btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                    if (isActive) {
                        btn.classList.remove('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-200');
                        btn.classList.add('bg-main', 'text-white');
                    } else {
                        btn.classList.remove('bg-main', 'text-white');
                        btn.classList.add('bg-gray-200', 'dark:bg-gray-700', 'text-gray-700', 'dark:text-gray-200');
                    }
                });
            }

            document.addEventListener('click', function(event) {
                const button = event.target.closest('.posts-layout-btn');
                if (!button) {
                    return;
                }
                const layout = button.getAttribute('data-layout');
                if (!layout) {
                    return;
                }
                localStorage.setItem(LAYOUT_KEY, layout);
                applyLayout(layout);
            });

            // Apply saved layout or default
            applyLayout(localStorage.getItem(LAYOUT_KEY) || 'grid');

            window.addEventListener('popstate', function() {
                loadPostsWithoutReload(window.location.href, false);
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

            // Dark mode toggle for post cards
            function updatePostCards() {
                const isDark = document.documentElement.classList.contains('dark');
                postCards.forEach(card => {
                    if (isDark) {
                        card.classList.add('bg-gray-800', 'text-white');
                        card.classList.remove('bg-white', 'text-gray-900');
                    } else {
                        card.classList.add('bg-white', 'text-gray-900');
                        card.classList.remove('bg-gray-800', 'text-white');
                    }
                });
            }

            // Watch for dark mode changes
            const observer = new MutationObserver(updatePostCards);
            observer.observe(document.documentElement, {
                attributes: true,
                attributeFilter: ['class']
            });
        });
    </script>

    <?php
    // Only include layout footer if not AJAX request
    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
        include '../components/layout_footer.php';
    }
    ?>

<?php
} // End of AJAX check
?>
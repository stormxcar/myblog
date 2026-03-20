<?php

include '../components/connect.php';
include '../components/seo_helpers.php';

session_start();

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} else {
    $user_id = '';
};

include '../components/like_post.php';
include '../components/save_post.php';

$allowed_page_sizes = [8, 12, 16, 24];
$page_size = isset($_GET['size']) ? (int)$_GET['size'] : 12;
if (!in_array($page_size, $allowed_page_sizes, true)) {
    $page_size = 12;
}

$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) {
    $current_page = 1;
}

if (isset($_POST['search_btn']) || (isset($_POST['search_box']) && !isset($_POST['save_post']) && !isset($_POST['like_post']))) {
    $redirect_q = trim((string)($_POST['search_box'] ?? ''));
    header('Location: search.php?q=' . urlencode($redirect_q) . '&size=' . $page_size . '&page=1');
    exit;
}

// Get search query
$search_query = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$is_search_requested = isset($_GET['q']);

if (!function_exists('highlight_search_term')) {
    function highlight_search_term($text, $term)
    {
        $safe_text = htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
        $safe_term = trim((string)$term);
        if ($safe_term === '') {
            return $safe_text;
        }
        return preg_replace('/(' . preg_quote($safe_term, '/') . ')/i', '<mark class="bg-yellow-200 dark:bg-yellow-600/70 px-1 rounded">$1</mark>', $safe_text);
    }
}

?>

<?php include '../components/layout_header.php'; ?>

<?php
// Include breadcrumb component
include '../components/breadcrumb.php';

// Generate breadcrumb for search page
$search_title = !empty($search_query) ? "Tìm kiếm: \"$search_query\"" : 'Tìm kiếm';
$breadcrumb_items = auto_breadcrumb($search_title);
render_breadcrumb($breadcrumb_items);
?>

<main class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <div class="container-custom py-8">
        <!-- Search Header -->
        <div class="text-center mb-12">
            <h1 class="text-4xl md:text-5xl font-bold text-gray-900 dark:text-white mb-4">
                <i class="fas fa-search text-main mr-4"></i>
                Tìm kiếm
            </h1>
            <div class="section-divider mb-8"></div>
        </div>

        <!-- Enhanced Search Form -->
        <div class="max-w-4xl mx-auto mb-12">
            <form method="get" class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-6 border border-gray-200 dark:border-gray-700" data-search-autocomplete>
                <div class="flex flex-col md:flex-row gap-4">
                    <div class="flex-1 relative">
                        <input type="text"
                            name="q"
                            data-search-input
                            autocomplete="off"
                            placeholder="Tìm kiếm bài viết, danh mục, nội dung..."
                            value="<?= htmlspecialchars($search_query) ?>"
                            class="w-full px-6 py-4 pl-14 pr-4 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-main focus:border-transparent transition-all duration-300 text-lg">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400 text-xl"></i>
                        </div>
                        <div data-search-dropdown class="hidden absolute left-0 right-0 top-full mt-2 z-50 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-xl overflow-hidden"></div>
                    </div>

                    <div class="flex gap-3">
                        <select name="size" class="px-4 py-4 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                            <?php foreach ($allowed_page_sizes as $size_option) : ?>
                                <option value="<?= $size_option; ?>" <?= $page_size === $size_option ? 'selected' : ''; ?>><?= $size_option; ?>/trang</option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="search_btn"
                            class="px-8 py-4 bg-main text-white rounded-xl hover:bg-blue-700 transition-all duration-300 font-semibold text-lg shadow-lg hover:shadow-xl transform hover:-translate-y-1 flex items-center justify-center space-x-2">
                            <i class="fas fa-search"></i>
                            <span>Tìm kiếm</span>
                        </button>
                    </div>
                </div>

                <!-- Search Filters -->
                <div class="flex flex-wrap gap-3 mt-6 pt-6 border-t border-gray-200 dark:border-gray-600">
                    <span class="text-gray-600 dark:text-gray-400 font-medium">Tìm kiếm nhanh:</span>
                    <?php
                    $quick_searches = ['PHP', 'JavaScript', 'Web Design', 'Tutorial', 'Technology', 'Programming'];
                    foreach ($quick_searches as $tag) :
                    ?>
                        <a href="?q=<?= urlencode($tag); ?>&size=<?= $page_size; ?>&page=1"
                            class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-full hover:bg-main hover:text-white transition-all duration-300 text-sm font-medium">
                            <?= $tag ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </form>
        </div>

        <?php if ($is_search_requested && !empty($search_query)) : ?>
            <!-- Search Results -->
            <div class="mb-8">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                        Kết quả tìm kiếm cho: <span class="text-main">"<?= htmlspecialchars($search_query) ?>"</span>
                    </h2>
                </div>

                <!-- Results Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
                    <?php
                    $search_term = "%{$search_query}%";
                    $count_stmt = $conn->prepare("SELECT COUNT(*) FROM `posts` WHERE (title LIKE ? OR category LIKE ? OR content LIKE ?) AND status = 'active'");
                    $count_stmt->execute([$search_term, $search_term, $search_term]);
                    $result_count = (int)$count_stmt->fetchColumn();

                    if ($result_count > 0) {
                        $total_pages = (int)ceil($result_count / $page_size);
                        if ($total_pages < 1) {
                            $total_pages = 1;
                        }
                        if ($current_page > $total_pages) {
                            $current_page = $total_pages;
                        }
                        $offset = ($current_page - 1) * $page_size;

                        $select_posts = $conn->prepare("SELECT p.*, 
                            (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comment_count,
                            (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) AS like_count,
                            (SELECT COUNT(*) FROM likes ul WHERE ul.post_id = p.id AND ul.user_id = ?) AS user_liked,
                            (SELECT COUNT(*) FROM favorite_posts sf WHERE sf.post_id = p.id AND sf.user_id = ?) AS user_saved
                            FROM posts p
                            WHERE (p.title LIKE ? OR p.category LIKE ? OR p.content LIKE ?) AND p.status = 'active'
                            ORDER BY p.date DESC
                            LIMIT ? OFFSET ?");
                        $select_posts->bindValue(1, (string)$user_id, PDO::PARAM_STR);
                        $select_posts->bindValue(2, (string)$user_id, PDO::PARAM_STR);
                        $select_posts->bindValue(3, $search_term, PDO::PARAM_STR);
                        $select_posts->bindValue(4, $search_term, PDO::PARAM_STR);
                        $select_posts->bindValue(5, $search_term, PDO::PARAM_STR);
                        $select_posts->bindValue(6, $page_size, PDO::PARAM_INT);
                        $select_posts->bindValue(7, $offset, PDO::PARAM_INT);
                        $select_posts->execute();

                        echo "<div class='col-span-full mb-6'>";
                        echo "<div class='bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4'>";
                        echo "<p class='text-blue-800 dark:text-blue-200'><i class='fas fa-info-circle mr-2'></i>Tìm thấy <strong>{$result_count}</strong> kết quả. Hiển thị trang <strong>{$current_page}/{$total_pages}</strong> ({$page_size} bài/trang).</p>";
                        echo "</div>";
                        echo "</div>";

                        while ($fetch_posts = $select_posts->fetch(PDO::FETCH_ASSOC)) {
                            $post_id = $fetch_posts['id'];
                            $highlighted_title = highlight_search_term($fetch_posts['title'], $search_query);
                            $content_plain = strip_tags((string)$fetch_posts['content']);
                            $excerpt_raw = function_exists('mb_substr') ? mb_substr($content_plain, 0, 140) : substr($content_plain, 0, 140);
                            $highlighted_content = highlight_search_term($excerpt_raw, $search_query);
                    ?>
                            <article class="card dark:bg-gray-700 group blog-card-shared hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2">
                                <form method="post" class="h-full flex flex-col">
                                    <input type="hidden" name="post_id" value="<?= $post_id; ?>">
                                    <input type="hidden" name="admin_id" value="<?= $fetch_posts['admin_id']; ?>">
                                    <input type="hidden" name="search_box" value="<?= htmlspecialchars($search_query) ?>">

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
                                                    <?= date('d/m/Y', strtotime($fetch_posts['date'])); ?>
                                                </div>
                                            </div>
                                        </div>

                                        <button type="submit" name="save_post" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                                            <i class="fas fa-bookmark text-sm <?= ((int)$fetch_posts['user_saved'] > 0) ? 'text-yellow-500' : 'text-gray-400' ?>"></i>
                                        </button>
                                    </div>

                                    <!-- Post Image -->
                                    <?php if ($fetch_posts['image'] != '') : ?>
                                        <div class="relative overflow-hidden h-48 rounded-lg mx-4 mt-4">
                                            <img src="../uploaded_img/<?= $fetch_posts['image']; ?>"
                                                alt="<?= $fetch_posts['title']; ?>"
                                                class="blog-card-image">
                                            <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent"></div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Post Content -->
                                    <div class="p-4 flex-1 flex flex-col">
                                        <h3 class="text-xl font-bold text-gray-900 dark:text-white hover:text-main transition-colors mb-3 line-clamp-2">
                                            <a href="<?= post_path($post_id); ?>" class="hover:text-main transition-colors">
                                                <?= $highlighted_title ?>
                                            </a>
                                        </h3>

                                        <div class="text-gray-600 dark:text-gray-300 text-sm mb-4 line-clamp-3 flex-1">
                                            <?= $highlighted_content ?>...
                                        </div>

                                        <a href="<?= post_path($post_id); ?>"
                                            class="text-main font-semibold hover:text-blue-700 transition-colors text-sm mb-3 inline-flex items-center">
                                            Đọc thêm <i class="fas fa-arrow-right ml-1"></i>
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
                                            <span><?= (int)$fetch_posts['comment_count']; ?></span>
                                        </a>

                                        <button type="submit" name="like_post"
                                            class="flex items-center space-x-1 text-gray-500 dark:text-gray-400 hover:text-red-500 transition-colors text-sm">
                                            <i class="fas fa-heart <?= ((int)$fetch_posts['user_liked'] > 0) ? 'text-red-500' : '' ?>"></i>
                                            <span><?= (int)$fetch_posts['like_count']; ?></span>
                                        </button>
                                    </div>
                                </form>
                            </article>
                    <?php
                        }

                        if ($total_pages > 1) {
                            echo '<div class="col-span-full mt-8 flex flex-wrap items-center justify-center gap-2">';
                            for ($p = 1; $p <= $total_pages; $p++) {
                                $isActive = ($p === $current_page);
                                $className = $isActive
                                    ? 'px-4 py-2 rounded-lg bg-main text-white font-semibold'
                                    : 'px-4 py-2 rounded-lg bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 border border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600';
                                echo '<a href="?q=' . urlencode($search_query) . '&size=' . $page_size . '&page=' . $p . '" class="' . $className . '">' . $p . '</a>';
                            }
                            echo '</div>';

                            echo '<div class="col-span-full mt-4 flex md:hidden items-center justify-between gap-3">';
                            if ($current_page > 1) {
                                echo '<a href="?q=' . urlencode($search_query) . '&size=' . $page_size . '&page=' . ($current_page - 1) . '" class="flex-1 text-center px-4 py-2 rounded-lg bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-200">← Prev</a>';
                            } else {
                                echo '<span class="flex-1 text-center px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-400">← Prev</span>';
                            }

                            if ($current_page < $total_pages) {
                                echo '<a href="?q=' . urlencode($search_query) . '&size=' . $page_size . '&page=' . ($current_page + 1) . '" class="flex-1 text-center px-4 py-2 rounded-lg bg-main text-white">Next →</a>';
                            } else {
                                echo '<span class="flex-1 text-center px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-400">Next →</span>';
                            }
                            echo '</div>';
                        }
                    } else {
                        echo '<div class="col-span-full">';
                        echo '<div class="text-center py-16 bg-white dark:bg-gray-800 rounded-2xl shadow-lg">';
                        echo '<i class="fas fa-search-minus text-6xl text-gray-300 dark:text-gray-600 mb-6"></i>';
                        echo '<h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">Không tìm thấy kết quả</h3>';
                        echo '<p class="text-gray-600 dark:text-gray-400 mb-6">Không có bài viết nào khớp với từ khóa "<strong>' . htmlspecialchars($search_query) . '</strong>"</p>';
                        echo '<div class="space-y-4">';
                        echo '<p class="text-gray-500 dark:text-gray-400 text-sm">Gợi ý:</p>';
                        echo '<ul class="text-gray-500 dark:text-gray-400 text-sm space-y-2">';
                        echo '<li>• Kiểm tra lại chính tả</li>';
                        echo '<li>• Thử sử dụng từ khóa khác</li>';
                        echo '<li>• Sử dụng từ khóa ngắn gọn hơn</li>';
                        echo '<li>• Tìm kiếm theo danh mục</li>';
                        echo '</ul>';
                        echo '</div>';
                        echo '</div>';
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>

        <?php elseif ($is_search_requested && empty($search_query)) : ?>
            <!-- Empty Search -->
            <div class="text-center py-16">
                <div class="bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-2xl p-8 max-w-md mx-auto">
                    <i class="fas fa-exclamation-triangle text-4xl text-orange-500 mb-4"></i>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Vui lòng nhập từ khóa</h3>
                    <p class="text-gray-600 dark:text-gray-400">Hãy nhập từ khóa bạn muốn tìm kiếm</p>
                </div>
            </div>

        <?php else : ?>
            <!-- Initial State -->
            <div class="text-center py-16">
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-12 shadow-lg border border-gray-200 dark:border-gray-700 max-w-2xl mx-auto">
                    <div class="w-20 h-20 bg-main/10 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-search text-3xl text-main"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">Khám phá nội dung</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-8">Tìm kiếm bài viết, danh mục và nội dung mà bạn quan tâm</p>

                    <!-- Popular Categories -->
                    <div class="space-y-4">
                        <h4 class="font-semibold text-gray-900 dark:text-white">Danh mục phổ biến:</h4>
                        <div class="flex flex-wrap justify-center gap-3">
                            <?php
                            $popular_categories = $conn->prepare("SELECT category, COUNT(*) as count FROM posts WHERE status = 'active' GROUP BY category ORDER BY count DESC LIMIT 6");
                            $popular_categories->execute();
                            while ($category = $popular_categories->fetch(PDO::FETCH_ASSOC)) :
                            ?>
                                <a href="?q=<?= urlencode($category['category']); ?>&size=<?= $page_size; ?>&page=1"
                                    class="px-4 py-2 bg-main/10 text-main rounded-full hover:bg-main hover:text-white transition-all duration-300 font-medium inline-block">
                                    <?= $category['category'] ?> (<?= $category['count'] ?>)
                                </a>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Search Statistics -->
        <?php if (!empty($search_query) && isset($result_count) && $result_count > 0) : ?>
            <div class="mt-12 bg-gray-100 dark:bg-gray-800 rounded-2xl p-6">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Thống kê tìm kiếm</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-white dark:bg-gray-700 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-main"><?= $result_count ?></div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Kết quả tìm thấy</div>
                    </div>
                    <div class="bg-white dark:bg-gray-700 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-green-500">
                            <?php
                            $unique_authors = $conn->prepare("SELECT COUNT(DISTINCT name) FROM posts WHERE (title LIKE ? OR category LIKE ? OR content LIKE ?) AND status = 'active'");
                            $unique_authors->execute([$search_term, $search_term, $search_term]);
                            echo $unique_authors->fetchColumn();
                            ?>
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Tác giả</div>
                    </div>
                    <div class="bg-white dark:bg-gray-700 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-purple-500">
                            <?php
                            $unique_categories = $conn->prepare("SELECT COUNT(DISTINCT category) FROM posts WHERE (title LIKE ? OR category LIKE ? OR content LIKE ?) AND status = 'active'");
                            $unique_categories->execute([$search_term, $search_term, $search_term]);
                            echo $unique_categories->fetchColumn();
                            ?>
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Danh mục</div>
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

        // Search input enhancements
        const searchInput = document.querySelector('input[name="q"]');
        if (searchInput) {
            // Auto-focus search input
            searchInput.focus();

            // Search on Enter key
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.closest('form').submit();
                }
            });

            // Real-time search suggestions (placeholder for future implementation)
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const query = this.value.trim();

                if (query.length >= 3) {
                    searchTimeout = setTimeout(() => {
                        saveSearchHistory(query);
                    }, 300);
                }
            });
        }

        // Form submission with loading states
        const searchForms = document.querySelectorAll('form');
        searchForms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn && submitBtn.name === 'search_btn') {
                    const originalContent = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Đang tìm...';
                    submitBtn.disabled = true;

                    setTimeout(() => {
                        submitBtn.innerHTML = originalContent;
                        submitBtn.disabled = false;
                    }, 2000);
                }
            });
        });

        // Enhanced post card interactions
        const postCards = document.querySelectorAll('.post-card');
        postCards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-8px) scale(1.02)';
                this.style.boxShadow = '0 25px 50px rgba(0, 0, 0, 0.15)';
            });

            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
                this.style.boxShadow = '';
            });
        });

        // Highlight animation for search results
        const highlights = document.querySelectorAll('mark');
        highlights.forEach((highlight, index) => {
            highlight.style.animationDelay = `${index * 0.1}s`;
            highlight.classList.add('animate-pulse');
        });

        // Scroll to results after search
        if (window.location.hash === '' && document.querySelector('.post-card')) {
            setTimeout(() => {
                document.querySelector('.post-card').scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }, 500);
        }
    });

    // Search history (localStorage)
    function saveSearchHistory(query) {
        if (!query || query.length < 2) return;

        let history = JSON.parse(localStorage.getItem('searchHistory') || '[]');
        history = history.filter(item => item !== query); // Remove if exists
        history.unshift(query); // Add to beginning
        history = history.slice(0, 10); // Keep only last 10

        localStorage.setItem('searchHistory', JSON.stringify(history));
    }

    // Load search history
    function loadSearchHistory() {
        return JSON.parse(localStorage.getItem('searchHistory') || '[]');
    }
</script>

<?php include '../components/layout_footer.php'; ?>
<?php
include '../components/connect.php';

session_start();

$user_id = $_SESSION['user_id'] ?? '';

// Xác định trang hiện tại cho pagination
$items_per_page = 20;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page <= 0) {
    $current_page = 1;
}

// Filter options
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$sort_order = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Build WHERE clause
$where_clause = "WHERE p.status = 'active' AND p.image != ''";
$params = [];

if (!empty($category_filter)) {
    $where_clause .= " AND p.category = ?";
    $params[] = $category_filter;
}

// Build ORDER BY clause
$order_clause = '';
switch ($sort_order) {
    case 'oldest':
        $order_clause = 'ORDER BY p.date ASC';
        break;
    case 'popular':
        $order_clause = 'ORDER BY like_count DESC, p.date DESC';
        break;
    case 'category':
        $order_clause = 'ORDER BY p.category ASC, p.date DESC';
        break;
    default:
        $order_clause = 'ORDER BY p.date DESC';
        break;
}

// Count total photos
$count_query = "SELECT COUNT(*) FROM `posts` p $where_clause";
$count_stmt = $conn->prepare($count_query);
$count_stmt->execute($params);
$total_photos = $count_stmt->fetchColumn();

$total_pages = ceil($total_photos / $items_per_page);
$offset = ($current_page - 1) * $items_per_page;
$has_more = $current_page < $total_pages;
$next_page = $has_more ? ($current_page + 1) : null;

// Get photos with post information
$photo_query = "
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
    $where_clause
    $order_clause
    LIMIT $items_per_page OFFSET $offset
";

$select_photos = $conn->prepare($photo_query);
$select_photos->execute($params);

// Get all categories for filter
$categories_query = $conn->prepare("SELECT DISTINCT category FROM `posts` WHERE status = 'active' AND image != '' ORDER BY category");
$categories_query->execute();
$categories = $categories_query->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include '../components/layout_header.php'; ?>

<?php
// Include breadcrumb component
include '../components/breadcrumb.php';

// Generate breadcrumb for all photos page
$breadcrumb_items = auto_breadcrumb('Bộ sưu tập ảnh');
render_breadcrumb($breadcrumb_items);
?>

<main class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <div class="container-custom py-8">
        <!-- Header Section -->
        <div class="text-center mb-12">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-purple-500 to-pink-600 rounded-full mb-6">
                <i class="fas fa-images text-white text-3xl"></i>
            </div>
            <h1 class="text-4xl md:text-5xl font-bold text-gray-900 dark:text-white mb-4">
                Bộ sưu tập <span class="gradient-text">ảnh</span>
            </h1>
            <div class="section-divider mb-6"></div>
            <p class="text-lg text-gray-600 dark:text-gray-300 max-w-2xl mx-auto">
                Khám phá những hình ảnh đẹp từ các bài viết trên website
            </p>
        </div>

        <!-- Stats Bar -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 text-center border border-gray-200 dark:border-gray-700 shadow-lg">
                <div class="text-3xl font-bold text-purple-500 mb-2"><?= $total_photos ?></div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Tổng số ảnh</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 text-center border border-gray-200 dark:border-gray-700 shadow-lg">
                <div class="text-3xl font-bold text-blue-500 mb-2"><?= count($categories) ?></div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Danh mục</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 text-center border border-gray-200 dark:border-gray-700 shadow-lg">
                <div class="text-3xl font-bold text-green-500 mb-2"><?= $items_per_page ?></div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Ảnh/trang</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 text-center border border-gray-200 dark:border-gray-700 shadow-lg">
                <div class="text-3xl font-bold text-orange-500 mb-2"><?= $total_pages ?></div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Tổng trang</div>
            </div>
        </div>

        <!-- Filter and Sort Section -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-6 mb-8 border border-gray-200 dark:border-gray-700">
            <div class="flex flex-col lg:flex-row items-center justify-between space-y-4 lg:space-y-0">
                <!-- Category Filter -->
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-filter text-main"></i>
                        <span class="font-semibold text-gray-900 dark:text-white">Lọc:</span>
                    </div>
                    <select onchange="updateFilter('category', this.value)"
                        class="appearance-none bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg px-4 py-2 pr-8 text-gray-700 dark:text-gray-300 focus:outline-none focus:ring-2 focus:ring-main focus:border-transparent">
                        <option value="">Tất cả danh mục</option>
                        <?php foreach ($categories as $cat) : ?>
                            <option value="<?= htmlspecialchars($cat['category']) ?>"
                                <?= $category_filter == $cat['category'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['category']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Sort Options -->
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-sort text-main"></i>
                        <span class="font-semibold text-gray-900 dark:text-white">Sắp xếp:</span>
                    </div>
                    <div class="flex space-x-2">
                        <?php
                        $sort_options = [
                            'newest' => ['icon' => 'clock', 'label' => 'Mới nhất'],
                            'oldest' => ['icon' => 'history', 'label' => 'Cũ nhất'],
                            'popular' => ['icon' => 'heart', 'label' => 'Phổ biến'],
                            'category' => ['icon' => 'tags', 'label' => 'Theo danh mục']
                        ];
                        foreach ($sort_options as $sort_key => $sort_info) :
                        ?>
                            <button onclick="updateFilter('sort', '<?= $sort_key ?>')"
                                class="px-4 py-2 rounded-lg text-sm font-medium transition-all duration-300 flex items-center space-x-2 <?= $sort_order == $sort_key ? 'bg-main text-white shadow-lg' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-main hover:text-white' ?>">
                                <i class="fas fa-<?= $sort_info['icon'] ?>"></i>
                                <span><?= $sort_info['label'] ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Active Filters -->
            <?php if (!empty($category_filter) || $sort_order != 'newest') : ?>
                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                    <div class="flex items-center space-x-2">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Bộ lọc đang áp dụng:</span>
                        <?php if (!empty($category_filter)) : ?>
                            <span class="inline-flex items-center space-x-1 bg-main text-white px-3 py-1 rounded-full text-xs">
                                <span>Danh mục: <?= htmlspecialchars($category_filter) ?></span>
                                <button onclick="updateFilter('category', '')" class="ml-1 hover:bg-white/20 rounded-full p-0.5">
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                            </span>
                        <?php endif; ?>
                        <?php if ($sort_order != 'newest') : ?>
                            <span class="inline-flex items-center space-x-1 bg-blue-500 text-white px-3 py-1 rounded-full text-xs">
                                <span>Sắp xếp: <?= $sort_options[$sort_order]['label'] ?></span>
                                <button onclick="updateFilter('sort', 'newest')" class="ml-1 hover:bg-white/20 rounded-full p-0.5">
                                    <i class="fas fa-times text-xs"></i>
                                </button>
                            </span>
                        <?php endif; ?>
                        <button onclick="clearAllFilters()" class="text-sm text-gray-500 hover:text-red-500 transition-colors">
                            <i class="fas fa-times-circle mr-1"></i>
                            Xóa tất cả
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($select_photos->rowCount() > 0) : ?>
            <!-- Photos Grid -->
            <div class="masonry-grid mb-8" id="photosGrid" data-next-page="<?= $next_page === null ? '' : (int)$next_page; ?>" data-has-more="<?= $has_more ? '1' : '0'; ?>" data-limit="<?= (int)$items_per_page; ?>" data-category="<?= htmlspecialchars((string)$category_filter, ENT_QUOTES, 'UTF-8'); ?>" data-sort="<?= htmlspecialchars((string)$sort_order, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="grid-sizer"></div>
                <?php
                $masonry_classes = ['masonry-h-md', 'masonry-h-lg', 'masonry-h-sm', 'masonry-h-xl'];
                $photo_index = 0;
                while ($photo = $select_photos->fetch(PDO::FETCH_ASSOC)) :
                    $height_class = $masonry_classes[$photo_index % count($masonry_classes)];
                ?>
                    <div class="photo-card masonry-item <?= $height_class; ?> group cursor-pointer" data-photo-id="<?= $photo['id'] ?>" data-post-url="<?= htmlspecialchars(post_path((int)$photo['id'], (string)$photo['title']), ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="relative overflow-hidden rounded-xl bg-white dark:bg-gray-800 shadow-lg border border-gray-200 dark:border-gray-700 transition-all duration-300 hover:shadow-2xl">
                            <!-- Image -->
                            <div class="h-full overflow-hidden">
                                <img src="../uploaded_img/<?= $photo['image'] ?>"
                                    alt="<?= htmlspecialchars($photo['title']) ?>"
                                    class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-110"
                                    loading="lazy">
                            </div>

                            <!-- Overlay -->
                            <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-50 transition-all duration-300 flex items-center justify-center">
                                <div class="opacity-0 group-hover:opacity-100 transition-opacity duration-300 text-white text-center">
                                    <a href="<?= post_path((int)$photo['id'], (string)$photo['title']); ?>" class="bg-white text-gray-900 px-4 py-2 rounded-lg font-medium hover:bg-gray-100 transition-colors inline-flex items-center">
                                        <i class="fas fa-eye mr-2"></i>
                                        Đọc bài viết
                                    </a>
                                </div>
                            </div>

                            <!-- Info Bar -->
                            <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/80 to-transparent p-4 text-white transform translate-y-full group-hover:translate-y-0 transition-transform duration-300">
                                <h3 class="font-semibold text-sm line-clamp-2 mb-2"><?= htmlspecialchars($photo['title']) ?></h3>
                                <div class="flex items-center justify-between text-xs">
                                    <div class="flex items-center space-x-2">
                                        <span class="bg-white/20 px-2 py-1 rounded"><?= htmlspecialchars($photo['category']) ?></span>
                                        <span>by <?= htmlspecialchars($photo['author']) ?></span>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span class="flex items-center space-x-1">
                                            <i class="fas fa-heart text-red-400"></i>
                                            <span><?= $photo['like_count'] ?></span>
                                        </span>
                                        <span class="flex items-center space-x-1">
                                            <i class="fas fa-comment text-blue-400"></i>
                                            <span><?= $photo['comment_count'] ?></span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php $photo_index++; ?>
                <?php endwhile; ?>
            </div>

            <div id="photosLoading" class="hidden mb-4 text-center text-sm text-gray-500 dark:text-gray-400">
                <i class="fas fa-spinner fa-spin mr-2"></i>Đang tải thêm ảnh...
            </div>
            <div id="photosEnd" class="hidden mb-4 text-center text-xs text-gray-500 dark:text-gray-400">Bạn đã xem hết ảnh.</div>
            <div id="photosSentinel" class="h-6"></div>

            <div class="text-center mt-6">
                <span class="text-gray-600 dark:text-gray-400">
                    Trang <?= $current_page ?> / <?= $total_pages ?> (<?= $total_photos ?> ảnh)
                </span>
            </div>

        <?php else : ?>
            <!-- Empty State -->
            <div class="text-center py-16">
                <div class="bg-white dark:bg-gray-800 rounded-2xl p-12 shadow-lg border border-gray-200 dark:border-gray-700 max-w-md mx-auto">
                    <div class="w-20 h-20 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-image text-4xl text-gray-400"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">Không tìm thấy ảnh</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-6">
                        <?php if (!empty($category_filter)) : ?>
                            Không có ảnh nào trong danh mục "<?= htmlspecialchars($category_filter) ?>"
                        <?php else : ?>
                            Chưa có ảnh nào được đăng tải
                        <?php endif; ?>
                    </p>

                    <div class="space-y-3">
                        <button onclick="clearAllFilters()" class="block w-full btn-primary">
                            <i class="fas fa-refresh mr-2"></i>
                            Xem tất cả ảnh
                        </button>
                        <a href="posts.php" class="block w-full btn-secondary">
                            <i class="fas fa-list mr-2"></i>
                            Xem bài viết
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Enhanced JavaScript -->
<script>
    let photosMasonry = null;
    let photosInfiniteObserver = null;
    let isLoadingMorePhotos = false;

    function getPhotosApiEndpoint() {
        if (window.BLOG_ENDPOINTS && window.BLOG_ENDPOINTS.photosList) {
            return window.BLOG_ENDPOINTS.photosList;
        }
        return 'all_photos_list_api.php';
    }

    function getPhotosGridState() {
        const grid = document.getElementById('photosGrid');
        if (!grid) {
            return null;
        }
        return {
            grid,
            loadingEl: document.getElementById('photosLoading'),
            endEl: document.getElementById('photosEnd'),
            sentinel: document.getElementById('photosSentinel')
        };
    }

    function initPhotoCardsAnimation() {
        const photoCards = document.querySelectorAll('.photo-card');
        photoCards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';

            setTimeout(() => {
                card.style.transition = 'all 0.6s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 40);
        });
    }

    function initStatsCounterAnimation() {
        const statsElements = document.querySelectorAll('.text-3xl.font-bold');
        statsElements.forEach(element => {
            const finalValue = parseInt(element.textContent, 10);
            if (Number.isNaN(finalValue)) {
                return;
            }

            const duration = 800;
            const increment = Math.max(1, Math.ceil(finalValue / (duration / 16)));
            let current = 0;

            const timer = setInterval(() => {
                current += increment;
                if (current >= finalValue) {
                    element.textContent = finalValue;
                    clearInterval(timer);
                } else {
                    element.textContent = current;
                }
            }, 16);
        });
    }

    function initPhotosMasonry() {
        if (typeof Masonry === 'undefined') {
            return;
        }

        const photosGrid = document.getElementById('photosGrid');
        if (!photosGrid) {
            return;
        }

        if (photosMasonry) {
            photosMasonry.destroy();
            photosMasonry = null;
        }

        photosMasonry = new Masonry(photosGrid, {
            itemSelector: '.masonry-item',
            columnWidth: '.grid-sizer',
            percentPosition: true,
            gutter: 16,
            transitionDuration: '0.25s'
        });
    }

    function initPhotosPage() {
        initPhotoCardsAnimation();
        initStatsCounterAnimation();
        initPhotosMasonry();

        if (photosInfiniteObserver) {
            photosInfiniteObserver.disconnect();
            photosInfiniteObserver = null;
        }

        const state = getPhotosGridState();
        if (!state || !window.IntersectionObserver || !state.sentinel) {
            return;
        }

        photosInfiniteObserver = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    loadMorePhotos();
                }
            });
        }, {
            root: null,
            rootMargin: '220px 0px',
            threshold: 0.01
        });

        photosInfiniteObserver.observe(state.sentinel);
    }

    async function loadMorePhotos() {
        const state = getPhotosGridState();
        if (!state || isLoadingMorePhotos) {
            return;
        }

        const hasMore = state.grid.getAttribute('data-has-more') === '1';
        const nextPage = Number(state.grid.getAttribute('data-next-page') || '0');
        const limit = Number(state.grid.getAttribute('data-limit') || '20');
        const category = state.grid.getAttribute('data-category') || '';
        const sort = state.grid.getAttribute('data-sort') || 'newest';

        if (!hasMore || !nextPage) {
            if (state.endEl) {
                state.endEl.classList.remove('hidden');
            }
            return;
        }

        isLoadingMorePhotos = true;
        if (state.loadingEl) {
            state.loadingEl.classList.remove('hidden');
        }

        try {
            const params = new URLSearchParams();
            params.set('page', String(nextPage));
            params.set('limit', String(limit));
            if (category) {
                params.set('category', category);
            }
            if (sort) {
                params.set('sort', sort);
            }

            const response = await fetch(getPhotosApiEndpoint() + '?' + params.toString(), {
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error('Fetch failed');
            }

            const payload = await response.json();
            if (!payload || payload.ok !== true) {
                throw new Error('Invalid payload');
            }

            if (payload.html) {
                const wrapper = document.createElement('div');
                wrapper.innerHTML = String(payload.html);
                const newItems = Array.from(wrapper.children);

                newItems.forEach(item => state.grid.appendChild(item));

                if (photosMasonry && newItems.length > 0) {
                    photosMasonry.appended(newItems);
                    photosMasonry.layout();
                } else if (photosMasonry) {
                    photosMasonry.layout();
                }
            }

            state.grid.setAttribute('data-next-page', payload.next_page ? String(payload.next_page) : '');
            state.grid.setAttribute('data-has-more', payload.has_more ? '1' : '0');

            if (!payload.has_more && state.endEl) {
                state.endEl.classList.remove('hidden');
            }
        } catch (error) {
            state.grid.setAttribute('data-has-more', '0');
            if (state.endEl) {
                state.endEl.classList.remove('hidden');
            }
        } finally {
            isLoadingMorePhotos = false;
            if (state.loadingEl) {
                state.loadingEl.classList.add('hidden');
            }
        }
    }

    async function replaceContentFromUrl(url, pushHistory = true) {
        const main = document.querySelector('main');
        if (!main) {
            window.location.href = url.toString();
            return;
        }

        main.classList.add('opacity-60');

        try {
            const response = await fetch(url.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error('Fetch failed');
            }

            const html = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');

            const newMain = doc.querySelector('main');
            if (!newMain) {
                throw new Error('Main content not found');
            }

            main.replaceWith(newMain);

            if (pushHistory) {
                window.history.pushState({}, '', url.toString());
            }

            initPhotosPage();
        } catch (error) {
            window.location.href = url.toString();
        } finally {
            const currentMain = document.querySelector('main');
            if (currentMain) {
                currentMain.classList.remove('opacity-60');
            }
        }
    }

    // Filter functions
    function updateFilter(type, value) {
        const url = new URL(window.location.href);

        if (value === '') {
            url.searchParams.delete(type);
        } else {
            url.searchParams.set(type, value);
        }

        url.searchParams.set('page', '1');
        replaceContentFromUrl(url);
    }

    function clearAllFilters() {
        const url = new URL(window.location.href);
        url.searchParams.delete('category');
        url.searchParams.delete('sort');
        url.searchParams.delete('page');
        replaceContentFromUrl(url);
    }

    document.addEventListener('click', function(e) {
        const card = e.target.closest('.photo-card[data-post-url]');
        if (!card) {
            return;
        }

        if (e.target.closest('a, button, select, input, textarea, label')) {
            return;
        }

        const targetUrl = card.getAttribute('data-post-url');
        if (targetUrl) {
            window.location.href = targetUrl;
        }
    });

    document.addEventListener('click', function(e) {
        const link = e.target.closest('a[href]');
        if (!link) {
            return;
        }

        const url = new URL(link.href, window.location.origin);
        const samePage = url.pathname === window.location.pathname;
        const isFilterOrPageNav = url.searchParams.has('page') || url.searchParams.has('category') || url.searchParams.has('sort');

        if (!samePage || !isFilterOrPageNav) {
            return;
        }

        e.preventDefault();
        replaceContentFromUrl(url);
    });

    window.addEventListener('popstate', function() {
        replaceContentFromUrl(new URL(window.location.href), false);
    });

    document.addEventListener('DOMContentLoaded', function() {
        initPhotosPage();
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
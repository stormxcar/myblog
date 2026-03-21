<?php
include '../components/connect.php';
include '../components/seo_helpers.php';
include '../components/community_engine.php';
include '../components/community_feed_renderer.php';

session_start();
community_ensure_tables($conn);

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$activeTopicSlug = trim((string)($_GET['topic'] ?? ''));

$page_title = 'Cộng đồng - My Blog';
$page_description = 'Bảng tin cộng đồng nơi người dùng chia sẻ bài viết, hình ảnh và liên kết.';
$page_canonical = site_url('static/community_feed.php');

$firstBundle = community_fetch_feed_posts_page($conn, $user_id, 1, 6, $activeTopicSlug);
$firstMaps = community_load_post_maps($conn, $firstBundle['posts'], $user_id);
$firstPostsHtml = community_render_feed_posts_html($firstBundle['posts'], $firstMaps, $user_id);
$trendingTopics = community_get_trending_topics($conn, $user_id, 10);

$activeTopicName = '';
if ($activeTopicSlug !== '') {
    $topicNameStmt = $conn->prepare('SELECT name FROM community_topics WHERE slug = ? LIMIT 1');
    $topicNameStmt->execute([$activeTopicSlug]);
    $activeTopicName = (string)($topicNameStmt->fetchColumn() ?: '');
}

?>

<?php include '../components/layout_header.php'; ?>

<?php
include '../components/breadcrumb.php';
$breadcrumb_items = auto_breadcrumb('Cộng đồng');
render_breadcrumb($breadcrumb_items);
?>

<main class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <style>
        .community-media-preview .media-item {
            overflow: hidden;
        }

        .community-carousel {
            position: relative;
        }

        .community-carousel-track {
            display: flex;
            width: 100%;
            transition: transform 0.35s ease;
        }

        .community-carousel-slide {
            position: relative;
            min-width: 100%;
        }

        .community-carousel-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 36px;
            height: 36px;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.55);
            background: rgba(15, 23, 42, 0.55);
            color: #fff;
            z-index: 4;
            transition: background-color 0.2s ease;
        }

        .community-carousel-nav:hover {
            background: rgba(15, 23, 42, 0.85);
        }

        .community-carousel-nav.is-prev {
            left: 10px;
        }

        .community-carousel-nav.is-next {
            right: 10px;
        }

        .community-carousel-dots {
            position: absolute;
            left: 50%;
            bottom: 10px;
            transform: translateX(-50%);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            z-index: 4;
            padding: 4px 8px;
            border-radius: 999px;
            background: rgba(2, 6, 23, 0.45);
        }

        .community-carousel-dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.6);
            transition: transform 0.2s ease, background-color 0.2s ease;
        }

        .community-carousel-dot.is-active {
            background: #ffffff;
            transform: scale(1.18);
        }

        #community-gallery-modal.open {
            display: flex;
        }

        #community-gallery-modal {
            z-index: 10020;
            pointer-events: auto;
        }

        #community-gallery-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(2, 6, 23, 0.82);
            backdrop-filter: blur(2px);
            z-index: 10;
        }

        .community-gallery-panel {
            position: relative;
            z-index: 20;
        }

        .community-lightbox-image {
            max-height: calc(90vh - 220px);
            width: auto;
            max-width: 100%;
            object-fit: cover;
            border-radius: 0.75rem;
            border: 1px solid rgba(148, 163, 184, 0.28);
            transition: transform 0.22s ease;
            transform-origin: center center;
        }

        .community-gallery-tools {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .community-gallery-tools button,
        .community-gallery-tools a {
            min-width: 2.8rem;
            height: 2.8rem;
            border-radius: 0.6rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            border: 1px solid rgba(148, 163, 184, 0.35);
            background: rgba(15, 23, 42, 0.72);
            color: #fff;
            transition: background-color 0.2s ease;
        }

        .community-gallery-tools button:hover,
        .community-gallery-tools a:hover {
            background: rgba(15, 23, 42, 0.95);
        }

        #community-gallery-image-spinner {
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
    <div class="container-custom py-6 sm:py-8">
        <section class="mb-6 sm:mb-8">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-5 sm:p-6 md:p-8 border border-gray-200 dark:border-gray-700">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 dark:text-white">Bảng tin cộng đồng</h1>
                        <p class="text-gray-600 dark:text-gray-300 mt-2 text-sm sm:text-base">Theo dõi chủ đề bạn quan tâm, thao tác nhanh, và cập nhật theo thời gian thực.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <?php if ($user_id > 0): ?>
                            <a href="community_create.php" class="btn-primary"><i class="fas fa-plus mr-2"></i>Tạo bài đăng</a>
                        <?php else: ?>
                            <a href="login.php" class="btn-primary"><i class="fas fa-sign-in-alt mr-2"></i>Đăng nhập để đăng bài</a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($activeTopicSlug !== '' && $activeTopicName !== ''): ?>
                    <div class="mt-4 inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-main/10 text-main text-xs font-semibold">
                        <span>Lọc theo chủ đề:</span>
                        <span>#<?= htmlspecialchars($activeTopicName, ENT_QUOTES, 'UTF-8'); ?></span>
                        <a href="community_feed.php" class="text-gray-600 hover:text-main">Bo loc</a>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            <div class="w-full lg:col-span-7 xl:col-span-8">
                <div id="community-feed-list" class="space-y-6 mx-auto w-full" style="max-width:850px;" data-next-page="<?= $firstBundle['next_page'] === null ? '' : (int)$firstBundle['next_page']; ?>" data-has-more="<?= $firstBundle['has_more'] ? '1' : '0'; ?>" data-topic="<?= htmlspecialchars($activeTopicSlug, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php if (!empty($firstBundle['posts'])): ?>
                        <?= $firstPostsHtml; ?>
                    <?php else: ?>
                        <section class="bg-white dark:bg-gray-800 rounded-2xl mt-6 shadow-lg border border-gray-200 dark:border-gray-700 p-8 sm:p-10 text-center">
                            <div class="w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-700 mx-auto flex items-center justify-center mb-4">
                                <i class="fas fa-users text-gray-400 text-2xl"></i>
                            </div>
                            <h2 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white">Chua co bai dang phu hop</h2>
                            <p class="text-gray-600 dark:text-gray-300 mt-2 text-sm sm:text-base">Hãy tạo bài đầu tiên hoặc lọc chủ đề để xem thêm nội dung.</p>
                        </section>
                    <?php endif; ?>
                </div>

                <div id="community-feed-skeleton" class="hidden mt-4 space-y-4 mx-auto w-full" style="max-width:850px;" aria-hidden="true">
                    <section class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-200 dark:border-gray-700 p-4 sm:p-5 animate-pulse">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-gray-200 dark:bg-gray-700"></div>
                            <div class="flex-1 space-y-2">
                                <div class="h-3 w-32 bg-gray-200 dark:bg-gray-700 rounded"></div>
                                <div class="h-2.5 w-24 bg-gray-200 dark:bg-gray-700 rounded"></div>
                            </div>
                        </div>
                        <div class="mt-4 space-y-2">
                            <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded"></div>
                            <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded"></div>
                            <div class="h-3 w-3/4 bg-gray-200 dark:bg-gray-700 rounded"></div>
                        </div>
                    </section>
                    <section class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-200 dark:border-gray-700 p-4 sm:p-5 animate-pulse">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-gray-200 dark:bg-gray-700"></div>
                            <div class="flex-1 space-y-2">
                                <div class="h-3 w-36 bg-gray-200 dark:bg-gray-700 rounded"></div>
                                <div class="h-2.5 w-20 bg-gray-200 dark:bg-gray-700 rounded"></div>
                            </div>
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <div class="h-24 bg-gray-200 dark:bg-gray-700 rounded-lg"></div>
                            <div class="h-24 bg-gray-200 dark:bg-gray-700 rounded-lg"></div>
                        </div>
                    </section>
                </div>

                <div id="community-feed-loading" class="hidden mt-4 text-center text-sm text-gray-500 dark:text-gray-400 mx-auto w-full" style="max-width:850px;">
                    <i class="fas fa-spinner fa-spin mr-2"></i>Đang tải thêm bài viết...
                </div>
                <div id="community-feed-end" class="hidden mt-4 text-center text-xs text-gray-500 dark:text-gray-400 mx-auto w-full" style="max-width:850px;">Bạn đã xem hết bài viết.</div>
                <div id="community-feed-sentinel" class="h-8 mx-auto w-full" style="max-width:850px;"></div>
            </div>

            <aside class="space-y-4 lg:sticky lg:top-24 lg:col-span-5 xl:col-span-4">
                <section class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow p-4">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Chủ đề nội bật</h3>
                        <?php if ($activeTopicSlug !== ''): ?>
                            <a href="community_feed.php" class="text-xs text-main hover:underline">Xem tất cả</a>
                        <?php endif; ?>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <?php if (!empty($trendingTopics)): ?>
                            <?php foreach ($trendingTopics as $topic): ?>
                                <a href="?topic=<?= urlencode((string)$topic['slug']); ?>" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold <?= $activeTopicSlug === (string)$topic['slug'] ? 'bg-main text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-main/10 hover:text-main'; ?>">
                                    <span>#<?= htmlspecialchars((string)$topic['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="opacity-70">(<?= (int)$topic['post_count']; ?>)</span>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Chu de se xuat hien khi co bai viet moi.</p>
                        <?php endif; ?>
                    </div>
                </section>
            </aside>
        </section>
    </div>
</main>

<div id="community-delete-modal" class="hidden fixed inset-0 z-50 bg-black/50 p-4">
    <div class="min-h-full w-full flex items-center justify-center">
        <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-2xl p-5 sm:p-6">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Xác nhận xóa bài viết</h3>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Hành động này không thể hoàn tác. Bạn có chắc chắn muốn xóa bài viết này không?</p>
            <div class="mt-5 flex items-center justify-end gap-2">
                <button type="button" id="community-delete-cancel" class="px-4 py-2 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">Huy</button>
                <button type="button" id="community-delete-confirm" class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700">Xoa bai viet</button>
            </div>
        </div>
    </div>
</div>

<div id="community-gallery-modal" class="hidden fixed inset-0 z-[10020] p-4 items-center justify-center">
    <div id="community-gallery-backdrop"></div>
    <div class="community-gallery-panel w-full max-w-5xl max-h-[90vh] overflow-hidden rounded-2xl border border-gray-700 bg-gray-900 text-white shadow-2xl">
        <div class="px-4 py-3 border-b border-gray-700 flex items-center justify-between gap-3">
            <h3 class="text-sm sm:text-base font-semibold">Tất cả ảnh bài viết</h3>
            <div class="community-gallery-tools">
                <button type="button" id="community-gallery-zoom-out" aria-label="Thu nhỏ">-</button>
                <button type="button" id="community-gallery-zoom-reset" aria-label="Đặt về 100%">100%</button>
                <button type="button" id="community-gallery-zoom-in" aria-label="Phóng to">+</button>
                <a href="#" id="community-gallery-download" aria-label="Tải ảnh" download>
                    <i class="fas fa-download"></i>
                </a>
                <button type="button" id="community-gallery-close" aria-label="Đóng gallery">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="p-4 max-h-[calc(90vh-56px)]">
            <div class="relative flex items-center justify-center">
                <button type="button" id="community-gallery-prev" class="absolute left-0 sm:left-2 w-9 h-9 rounded-full bg-black/50 hover:bg-black/70 text-white transition-colors" aria-label="Anh truoc">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <div class="community-image-spinner absolute inset-0 bg-black/20 rounded-xl hidden" id="community-gallery-image-spinner">
                    <i class="fas fa-spinner fa-spin text-gray-200"></i>
                </div>
                <img id="community-gallery-image" class="community-lightbox-image" src="" alt="gallery image">
                <button type="button" id="community-gallery-next" class="absolute right-0 sm:right-2 w-9 h-9 rounded-full bg-black/50 hover:bg-black/70 text-white transition-colors" aria-label="Anh tiep theo">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <div id="community-gallery-counter" class="mt-3 text-center text-xs text-gray-300"></div>
        </div>
    </div>
</div>

<script src="../js/community-feed-shared.js"></script>
<script>
    (function() {
        const feedList = document.getElementById('community-feed-list');
        const loadingEl = document.getElementById('community-feed-loading');
        const skeletonEl = document.getElementById('community-feed-skeleton');
        const endEl = document.getElementById('community-feed-end');
        const sentinel = document.getElementById('community-feed-sentinel');
        const deleteModal = document.getElementById('community-delete-modal');
        const deleteConfirmBtn = document.getElementById('community-delete-confirm');
        const deleteCancelBtn = document.getElementById('community-delete-cancel');
        const galleryModal = document.getElementById('community-gallery-modal');
        const galleryCloseBtn = document.getElementById('community-gallery-close');
        const galleryImage = document.getElementById('community-gallery-image');
        const galleryCounter = document.getElementById('community-gallery-counter');
        const galleryPrevBtn = document.getElementById('community-gallery-prev');
        const galleryNextBtn = document.getElementById('community-gallery-next');
        const galleryImageSpinner = document.getElementById('community-gallery-image-spinner');
        const galleryBackdrop = document.getElementById('community-gallery-backdrop');
        const galleryZoomInBtn = document.getElementById('community-gallery-zoom-in');
        const galleryZoomOutBtn = document.getElementById('community-gallery-zoom-out');
        const galleryZoomResetBtn = document.getElementById('community-gallery-zoom-reset');
        const galleryDownloadBtn = document.getElementById('community-gallery-download');
        let pendingDeletePostId = 0;
        let galleryImages = [];
        let currentGalleryIndex = 0;
        let currentZoom = 1;
        const shared = window.CommunityFeedShared ? window.CommunityFeedShared.create({}) : null;

        if (!feedList) {
            return;
        }

        let isLoadingMore = false;

        const getFeedEndpoint = () => {
            if (window.BLOG_ENDPOINTS && window.BLOG_ENDPOINTS.communityFeedList) {
                return window.BLOG_ENDPOINTS.communityFeedList;
            }
            return 'community_feed_list_api.php';
        };

        const getManageEndpoint = () => {
            if (window.BLOG_ENDPOINTS && window.BLOG_ENDPOINTS.communityManage) {
                return window.BLOG_ENDPOINTS.communityManage;
            }
            return 'community_post_manage_api.php';
        };

        const getActionEndpoint = () => {
            if (window.BLOG_ENDPOINTS && window.BLOG_ENDPOINTS.communityAction) {
                return window.BLOG_ENDPOINTS.communityAction;
            }
            return 'community_action_api.php';
        };

        const setLoading = (value) => {
            if (!loadingEl) {
                if (skeletonEl) {
                    skeletonEl.classList.toggle('hidden', !value);
                }
                return;
            }
            loadingEl.classList.toggle('hidden', !value);
            if (skeletonEl) {
                skeletonEl.classList.toggle('hidden', !value);
            }
        };

        const closeDeleteModal = () => {
            pendingDeletePostId = 0;
            if (!deleteModal) {
                return;
            }
            deleteModal.classList.add('hidden');
        };

        const parseGalleryImages = (raw) => {
            if (!raw) {
                return [];
            }
            try {
                const parsed = JSON.parse(raw);
                return Array.isArray(parsed) ? parsed.map(String).filter(Boolean) : [];
            } catch (err) {
                return [];
            }
        };

        const closeGalleryModal = () => {
            if (!galleryModal) {
                return;
            }
            galleryModal.classList.remove('open');
            galleryModal.classList.add('hidden');
            if (galleryImage) {
                galleryImage.src = '';
                galleryImage.style.transform = 'scale(1)';
            }
            galleryImages = [];
            currentGalleryIndex = 0;
            currentZoom = 1;
        };

        const syncDownloadLink = () => {
            if (!galleryDownloadBtn || !galleryImages.length) {
                return;
            }
            const url = galleryImages[currentGalleryIndex] || '';
            galleryDownloadBtn.href = url;
            galleryDownloadBtn.setAttribute('download', 'community-image-' + (currentGalleryIndex + 1) + '.jpg');
        };

        const renderGalleryImage = () => {
            if (!galleryImage || galleryImages.length === 0) {
                return;
            }
            const url = galleryImages[currentGalleryIndex] || '';
            if (galleryImageSpinner) {
                galleryImageSpinner.classList.remove('hidden');
            }
            galleryImage.onload = function() {
                if (galleryImageSpinner) {
                    galleryImageSpinner.classList.add('hidden');
                }
                galleryImage.style.transform = 'scale(' + currentZoom + ')';
            };
            galleryImage.src = url;
            syncDownloadLink();

            if (galleryCounter) {
                galleryCounter.textContent = 'Anh ' + (currentGalleryIndex + 1) + ' / ' + galleryImages.length;
            }
        };

        const openGalleryModal = (images, startIndex) => {
            if (!galleryModal || !galleryImage || !Array.isArray(images) || images.length === 0) {
                return;
            }

            galleryImages = images;
            currentGalleryIndex = Math.max(0, Math.min(Number(startIndex) || 0, galleryImages.length - 1));
            currentZoom = 1;
            renderGalleryImage();

            galleryModal.classList.remove('hidden');
            galleryModal.classList.add('open');
        };

        const showNextGalleryImage = () => {
            if (!galleryImages.length) {
                return;
            }
            currentGalleryIndex = (currentGalleryIndex + 1) % galleryImages.length;
            renderGalleryImage();
        };

        const showPrevGalleryImage = () => {
            if (!galleryImages.length) {
                return;
            }
            currentGalleryIndex = (currentGalleryIndex - 1 + galleryImages.length) % galleryImages.length;
            renderGalleryImage();
        };

        const openDeleteModal = (postId) => {
            if (!deleteModal) {
                return;
            }
            pendingDeletePostId = postId;
            deleteModal.classList.remove('hidden');
        };

        const getNextPage = () => Number(feedList.getAttribute('data-next-page') || '0');
        const hasMore = () => feedList.getAttribute('data-has-more') === '1';
        const setHasMore = (value) => {
            feedList.setAttribute('data-has-more', value ? '1' : '0');
            if (!value && endEl) {
                endEl.classList.remove('hidden');
            }
        };

        async function loadMoreFeed() {
            if (isLoadingMore || !hasMore()) {
                return;
            }

            const nextPage = getNextPage();
            if (!nextPage) {
                setHasMore(false);
                return;
            }

            isLoadingMore = true;
            setLoading(true);

            try {
                const params = new URLSearchParams();
                params.set('page', String(nextPage));
                params.set('limit', '6');
                const topic = feedList.getAttribute('data-topic') || '';
                if (topic) {
                    params.set('topic', topic);
                }

                const res = await fetch(getFeedEndpoint() + '?' + params.toString(), {
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                if (!res.ok) {
                    return;
                }
                const payload = await res.json();
                if (!payload || payload.ok !== true) {
                    return;
                }

                if (payload.html) {
                    feedList.insertAdjacentHTML('beforeend', String(payload.html));
                    if (shared) {
                        shared.initCarousels(feedList);
                    } else {
                        initCarousels(feedList);
                    }
                }
                feedList.setAttribute('data-next-page', payload.next_page ? String(payload.next_page) : '');
                setHasMore(!!payload.has_more);
            } catch (err) {
                // Keep silent to avoid disrupting reading flow.
            } finally {
                setLoading(false);
                isLoadingMore = false;
            }
        }

        async function submitVote(button) {
            const postId = Number(button.getAttribute('data-post-id') || '0');
            const voteType = String(button.getAttribute('data-vote') || 'up');
            if (!postId) {
                return;
            }

            button.disabled = true;
            try {
                const endpoint = (window.BLOG_ENDPOINTS && window.BLOG_ENDPOINTS.communityReact) ?
                    window.BLOG_ENDPOINTS.communityReact :
                    'community_react.php';
                const fd = new FormData();
                fd.set('post_id', String(postId));
                fd.set('vote', voteType);

                const res = await fetch(endpoint, {
                    method: 'POST',
                    body: fd,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                });
                const payload = await res.json();
                if (!payload || payload.ok !== true) {
                    if (payload && payload.login_required && payload.login_url) {
                        showNotification(payload.message || 'Ban can dang nhap.', 'warning');
                        setTimeout(function() {
                            window.location.href = payload.login_url;
                        }, 500);
                        return;
                    }
                    showNotification((payload && payload.message) || 'Khong the vote bai viet.', 'error');
                    return;
                }

                const upBtn = document.querySelector('[data-community-vote-btn][data-vote="up"][data-post-id="' + postId + '"]');
                const downBtn = document.querySelector('[data-community-vote-btn][data-vote="down"][data-post-id="' + postId + '"]');
                const upCountEl = document.getElementById('community-upvote-count-' + postId);
                const downCountEl = document.getElementById('community-downvote-count-' + postId);
                const scoreEl = document.getElementById('community-score-count-' + postId);

                if (upCountEl) {
                    upCountEl.textContent = String(payload.total_upvotes || 0);
                }
                if (downCountEl) {
                    downCountEl.textContent = String(payload.total_downvotes || 0);
                }
                if (scoreEl) {
                    scoreEl.textContent = String(payload.vote_score || 0);
                }

                const reaction = Number(payload.reaction || 0);
                if (upBtn) {
                    const activeUp = reaction === 1;
                    upBtn.classList.toggle('text-emerald-600', activeUp);
                    upBtn.setAttribute('aria-pressed', activeUp ? 'true' : 'false');
                }
                if (downBtn) {
                    const activeDown = reaction === -1;
                    downBtn.classList.toggle('text-rose-600', activeDown);
                    downBtn.setAttribute('aria-pressed', activeDown ? 'true' : 'false');
                }
            } catch (err) {
                showNotification('Loi ket noi khi vote bai viet.', 'error');
            } finally {
                button.disabled = false;
            }
        }

        function initCarousels(root) {
            const scope = root || document;
            scope.querySelectorAll('[data-community-carousel]').forEach(function(carousel) {
                if (carousel.dataset.carouselBound === '1') {
                    return;
                }
                carousel.dataset.carouselBound = '1';

                const track = carousel.querySelector('[data-community-carousel-track]');
                if (!track) {
                    return;
                }

                const slides = Array.from(track.children);
                if (!slides.length) {
                    return;
                }

                let index = 0;
                const dots = Array.from(carousel.querySelectorAll('[data-community-carousel-dot]'));
                const update = function() {
                    track.style.transform = 'translateX(-' + (index * 100) + '%)';
                    dots.forEach(function(dot, dotIndex) {
                        dot.classList.toggle('is-active', dotIndex === index);
                    });
                };

                const prevBtn = carousel.querySelector('[data-community-carousel-prev]');
                const nextBtn = carousel.querySelector('[data-community-carousel-next]');

                if (prevBtn) {
                    prevBtn.addEventListener('click', function(event) {
                        event.preventDefault();
                        event.stopPropagation();
                        index = (index - 1 + slides.length) % slides.length;
                        update();
                    });
                }

                if (nextBtn) {
                    nextBtn.addEventListener('click', function(event) {
                        event.preventDefault();
                        event.stopPropagation();
                        index = (index + 1) % slides.length;
                        update();
                    });
                }

                dots.forEach(function(dot) {
                    dot.addEventListener('click', function(event) {
                        event.preventDefault();
                        event.stopPropagation();
                        const nextIndex = Number(dot.getAttribute('data-dot-index') || '0');
                        index = Math.max(0, Math.min(nextIndex, slides.length - 1));
                        update();
                    });
                });

                update();
            });
        }

        async function handleCardAction(action, postId, actionButton) {
            if (!action || !postId) {
                return;
            }

            if (action === 'hide') {
                const postEl = document.getElementById('community-post-' + postId);
                if (postEl) {
                    postEl.remove();
                }
                showNotification('Da an bai viet khoi bang tin.', 'info');
                return;
            }

            if (action === 'save') {
                try {
                    const fd = new FormData();
                    fd.set('action', 'save');
                    fd.set('post_id', String(postId));
                    const res = await fetch(getActionEndpoint(), {
                        method: 'POST',
                        body: fd,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin'
                    });
                    const payload = await res.json();
                    if (!payload || payload.ok !== true) {
                        if (payload && payload.login_required && payload.login_url) {
                            showNotification(payload.message || 'Ban can dang nhap.', 'warning');
                            setTimeout(function() {
                                window.location.href = payload.login_url;
                            }, 500);
                            return;
                        }
                        showNotification((payload && payload.message) || 'Khong the luu bai viet.', 'error');
                        return;
                    }

                    if (actionButton) {
                        const isSaved = Number(payload.saved || 0) === 1;
                        actionButton.setAttribute('data-saved', isSaved ? '1' : '0');
                        actionButton.textContent = isSaved ? 'Bo luu bai viet' : 'Luu bai viet';
                    }
                    showNotification(payload.message || 'Da cap nhat bai viet da luu.', 'success');
                } catch (err) {
                    showNotification('Loi ket noi khi luu bai viet.', 'error');
                }
                return;
            }

            if (action === 'report') {
                try {
                    const fd = new FormData();
                    fd.set('action', 'report');
                    fd.set('post_id', String(postId));
                    const res = await fetch(getActionEndpoint(), {
                        method: 'POST',
                        body: fd,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin'
                    });
                    const payload = await res.json();
                    if (!payload || payload.ok !== true) {
                        if (payload && payload.login_required && payload.login_url) {
                            showNotification(payload.message || 'Ban can dang nhap.', 'warning');
                            setTimeout(function() {
                                window.location.href = payload.login_url;
                            }, 500);
                            return;
                        }
                        showNotification((payload && payload.message) || 'Khong the bao cao bai viet.', 'error');
                        return;
                    }
                    showNotification(payload.message || 'Da gui bao cao.', 'warning');
                } catch (err) {
                    showNotification('Loi ket noi khi bao cao bai viet.', 'error');
                }
            }
        }

        async function submitComment(form) {
            const postId = Number(form.getAttribute('data-post-id') || '0');
            const endpoint = (window.BLOG_ENDPOINTS && window.BLOG_ENDPOINTS.communityCommentAdd) ?
                window.BLOG_ENDPOINTS.communityCommentAdd :
                'community_comment_add.php';
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalBtn = submitBtn ? submitBtn.innerHTML : '';

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = 'Dang gui...';
            }

            try {
                const fd = new FormData(form);
                const res = await fetch(endpoint, {
                    method: 'POST',
                    body: fd,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                });
                const payload = await res.json();
                if (!payload || payload.ok !== true) {
                    if (payload && payload.login_required && payload.login_url) {
                        showNotification(payload.message || 'Ban can dang nhap.', 'warning');
                        setTimeout(function() {
                            window.location.href = payload.login_url;
                        }, 500);
                        return;
                    }
                    showNotification((payload && payload.message) || 'Khong the gui binh luan.', 'error');
                    return;
                }

                const list = document.getElementById('community-comments-list-' + postId);
                if (list && payload.comment_html) {
                    const empty = document.getElementById('community-empty-comments-' + postId);
                    if (empty) {
                        empty.remove();
                    }

                    const parentId = Number(payload.parent_comment_id || 0);
                    if (parentId > 0) {
                        const parentEl = list.querySelector('[data-community-comment-id="' + parentId + '"]');
                        if (parentEl) {
                            let repliesWrap = parentEl.querySelector('[data-community-replies-parent="' + parentId + '"]');
                            if (!repliesWrap) {
                                repliesWrap = document.createElement('div');
                                repliesWrap.className = 'mt-3 space-y-2';
                                repliesWrap.setAttribute('data-community-replies-parent', String(parentId));
                                parentEl.appendChild(repliesWrap);
                            }
                            repliesWrap.insertAdjacentHTML('beforeend', String(payload.comment_html));
                        } else {
                            list.insertAdjacentHTML('afterbegin', String(payload.comment_html));
                        }
                    } else {
                        list.insertAdjacentHTML('afterbegin', String(payload.comment_html));
                    }
                }

                const countEl = document.getElementById('community-comment-count-' + postId);
                if (countEl) {
                    countEl.textContent = String(payload.total_comments || 0);
                }

                form.reset();
                cancelCommunityReply(postId);
                showNotification(payload.message || 'Da gui binh luan.', 'success');
            } catch (err) {
                showNotification('Loi ket noi khi gui binh luan.', 'error');
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtn;
                }
            }
        }

        async function deletePostFromFeed(postId) {
            const endpoint = getManageEndpoint();
            const fd = new FormData();
            fd.set('action', 'delete');
            fd.set('post_id', String(postId));

            if (deleteConfirmBtn) {
                deleteConfirmBtn.disabled = true;
                deleteConfirmBtn.textContent = 'Dang xoa...';
            }

            try {
                const res = await fetch(endpoint, {
                    method: 'POST',
                    body: fd,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                });
                const payload = await res.json();
                if (!payload || payload.ok !== true) {
                    showNotification((payload && payload.message) || 'Khong the xoa bai viet.', 'error');
                    return;
                }

                const postEl = document.getElementById('community-post-' + postId);
                if (postEl) {
                    postEl.classList.add('opacity-0', 'scale-[0.98]', 'transition-all', 'duration-200');
                    setTimeout(function() {
                        postEl.remove();
                    }, 220);
                }
                showNotification(payload.message || 'Da xoa bai viet.', 'success');
                closeDeleteModal();
            } catch (err) {
                showNotification('Loi ket noi khi xoa bai viet.', 'error');
            } finally {
                if (deleteConfirmBtn) {
                    deleteConfirmBtn.disabled = false;
                    deleteConfirmBtn.textContent = 'Xoa bai viet';
                }
            }
        }

        document.addEventListener('click', function(event) {
            if (shared && shared.handleClick(event)) {
                return;
            }

            const ownerTrigger = event.target.closest('[data-community-owner-trigger]');
            if (ownerTrigger) {
                event.preventDefault();
                const wrap = ownerTrigger.closest('[data-community-owner-wrap]');
                if (!wrap) {
                    return;
                }
                const menu = wrap.querySelector('[data-community-owner-menu]');
                if (!menu) {
                    return;
                }
                document.querySelectorAll('[data-community-owner-menu]').forEach(function(otherMenu) {
                    if (otherMenu !== menu) {
                        otherMenu.classList.add('hidden');
                    }
                });
                menu.classList.toggle('hidden');
                return;
            }

            if (!event.target.closest('[data-community-owner-wrap]')) {
                document.querySelectorAll('[data-community-owner-menu]').forEach(function(menu) {
                    menu.classList.add('hidden');
                });
            }

            const actionTrigger = event.target.closest('[data-community-action-trigger]');
            if (actionTrigger) {
                event.preventDefault();
                const wrap = actionTrigger.closest('[data-community-action-wrap]');
                const menu = wrap ? wrap.querySelector('[data-community-action-menu]') : null;
                if (!menu) {
                    return;
                }
                document.querySelectorAll('[data-community-action-menu]').forEach(function(otherMenu) {
                    if (otherMenu !== menu) {
                        otherMenu.classList.add('hidden');
                    }
                });
                menu.classList.toggle('hidden');
                return;
            }

            if (!event.target.closest('[data-community-action-wrap]')) {
                document.querySelectorAll('[data-community-action-menu]').forEach(function(menu) {
                    menu.classList.add('hidden');
                });
            }

            const actionButton = event.target.closest('[data-community-action]');
            if (actionButton) {
                event.preventDefault();
                const action = String(actionButton.getAttribute('data-community-action') || '');
                const postId = Number(actionButton.getAttribute('data-post-id') || '0');
                handleCardAction(action, postId, actionButton);
                return;
            }

            const voteButton = event.target.closest('[data-community-vote-btn]');
            if (voteButton) {
                event.preventDefault();
                submitVote(voteButton);
                return;
            }

            const mediaImage = event.target.closest('[data-community-lazy-image]');
            if (mediaImage) {
                const galleryRoot = mediaImage.closest('[data-community-carousel]');
                const images = parseGalleryImages(galleryRoot ? galleryRoot.getAttribute('data-gallery-images') : '');
                const imageWrap = mediaImage.closest('[data-gallery-index]');
                const startIndex = imageWrap ? Number(imageWrap.getAttribute('data-gallery-index') || '0') : 0;
                openGalleryModal(images, startIndex);
                return;
            }

            const deleteBtn = event.target.closest('[data-community-delete-btn]');
            if (deleteBtn) {
                event.preventDefault();
                const postId = Number(deleteBtn.getAttribute('data-post-id') || '0');
                if (!postId) {
                    return;
                }
                document.querySelectorAll('[data-community-owner-menu]').forEach(function(menu) {
                    menu.classList.add('hidden');
                });
                openDeleteModal(postId);
            }

            if (event.target === deleteModal || event.target.closest('#community-delete-cancel')) {
                closeDeleteModal();
            }

            if (event.target === galleryModal || event.target === galleryBackdrop || event.target.closest('#community-gallery-close')) {
                closeGalleryModal();
            }
        });

        if (deleteConfirmBtn) {
            deleteConfirmBtn.addEventListener('click', function() {
                if (!pendingDeletePostId) {
                    return;
                }
                deletePostFromFeed(pendingDeletePostId);
            });
        }

        if (deleteCancelBtn) {
            deleteCancelBtn.addEventListener('click', closeDeleteModal);
        }

        if (galleryCloseBtn) {
            galleryCloseBtn.addEventListener('click', closeGalleryModal);
        }

        if (galleryNextBtn) {
            galleryNextBtn.addEventListener('click', function(event) {
                event.stopPropagation();
                showNextGalleryImage();
            });
        }

        if (galleryPrevBtn) {
            galleryPrevBtn.addEventListener('click', function(event) {
                event.stopPropagation();
                showPrevGalleryImage();
            });
        }

        const applyZoom = () => {
            currentZoom = Math.max(0.5, Math.min(3, currentZoom));
            if (galleryImage) {
                galleryImage.style.transform = 'scale(' + currentZoom + ')';
            }
        };

        if (galleryZoomInBtn) {
            galleryZoomInBtn.addEventListener('click', function(event) {
                event.preventDefault();
                event.stopPropagation();
                currentZoom += 0.2;
                applyZoom();
            });
        }

        if (galleryZoomOutBtn) {
            galleryZoomOutBtn.addEventListener('click', function(event) {
                event.preventDefault();
                event.stopPropagation();
                currentZoom -= 0.2;
                applyZoom();
            });
        }

        if (galleryZoomResetBtn) {
            galleryZoomResetBtn.addEventListener('click', function(event) {
                event.preventDefault();
                event.stopPropagation();
                currentZoom = 1;
                applyZoom();
            });
        }

        if (galleryDownloadBtn) {
            galleryDownloadBtn.addEventListener('click', async function(event) {
                event.preventDefault();
                if (!galleryImages.length) {
                    return;
                }

                const url = galleryImages[currentGalleryIndex] || '';
                if (!url) {
                    return;
                }

                try {
                    const response = await fetch(url, {
                        credentials: 'same-origin'
                    });
                    if (!response.ok) {
                        throw new Error('Download failed');
                    }

                    const blob = await response.blob();
                    const blobUrl = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = blobUrl;
                    link.download = 'community-image-' + (currentGalleryIndex + 1) + '.jpg';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(blobUrl);
                } catch (err) {
                    showNotification('Không thể tải ảnh xuống. Vui lòng thử lại.', 'error');
                }
            });
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeGalleryModal();
                return;
            }
            if (!galleryModal || !galleryModal.classList.contains('open')) {
                return;
            }
            if (event.key === 'ArrowRight') {
                showNextGalleryImage();
            }
            if (event.key === 'ArrowLeft') {
                showPrevGalleryImage();
            }
        });

        document.addEventListener('load', function(event) {
            const imageEl = event.target;
            if (!(imageEl instanceof HTMLImageElement) || !imageEl.matches('[data-community-lazy-image]')) {
                return;
            }
            imageEl.classList.remove('opacity-0');
            const spinner = imageEl.closest('.media-item')?.querySelector('.community-image-spinner');
            if (spinner) {
                spinner.remove();
            }
        }, true);

        document.addEventListener('submit', function(event) {
            const form = event.target.closest('[data-community-comment-form]');
            if (!form) {
                return;
            }
            event.preventDefault();
            submitComment(form);
        });

        if (window.IntersectionObserver && sentinel) {
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        loadMoreFeed();
                    }
                });
            }, {
                root: null,
                rootMargin: '180px 0px',
                threshold: 0.01
            });
            observer.observe(sentinel);
        }

        if (shared) {
            shared.initCarousels(feedList);
        } else {
            initCarousels(feedList);
        }
    })();

    function toggleCommunityComments(postId) {
        const panel = document.getElementById('community-comments-panel-' + postId);
        if (!panel) {
            return;
        }
        panel.classList.toggle('hidden');
    }

    function shareCommunityPost(postId) {
        const url = window.location.origin + window.location.pathname + '#community-post-' + postId;
        if (navigator.share) {
            navigator.share({
                title: 'Bai viet cong dong',
                text: 'Xem bai viet cong dong tren My Blog',
                url: url
            }).catch(function() {});
            return;
        }

        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            navigator.clipboard.writeText(url).then(function() {
                showNotification('Da sao chep link chia se.', 'success');
            }).catch(function() {
                showNotification('Khong the sao chep lien ket.', 'error');
            });
            return;
        }

        showNotification(url, 'info');
    }

    function communityReplyToComment(commentId) {
        const commentEl = document.querySelector('[data-community-comment-id="' + commentId + '"]');
        const panel = commentEl ? commentEl.closest('[id^="community-comments-panel-"]') : null;
        if (!panel) {
            return;
        }
        const postId = Number((panel.id || '').replace('community-comments-panel-', ''));
        if (!postId) {
            return;
        }

        const parentInput = document.getElementById('community-parent-comment-' + postId);
        const indicator = document.getElementById('community-reply-indicator-' + postId);
        const textarea = panel.querySelector('textarea[name="comment"]');
        if (!parentInput || !indicator || !textarea) {
            return;
        }

        parentInput.value = String(commentId);
        indicator.textContent = 'Dang tra loi binh luan #' + commentId;
        indicator.classList.remove('hidden');
        textarea.focus();
    }

    function cancelCommunityReply(postId) {
        const parentInput = document.getElementById('community-parent-comment-' + postId);
        const indicator = document.getElementById('community-reply-indicator-' + postId);
        if (parentInput) {
            parentInput.value = '0';
        }
        if (indicator) {
            indicator.classList.add('hidden');
            indicator.textContent = '';
        }
    }

    function toggleCommunityReplies(commentId, triggerBtn) {
        const repliesWrap = document.getElementById('community-replies-' + commentId);
        if (!repliesWrap || !triggerBtn) {
            return;
        }

        const isHidden = repliesWrap.classList.toggle('hidden');
        const countMatch = String(triggerBtn.textContent || '').match(/\((\d+)\)/);
        const countText = countMatch ? countMatch[1] : '0';

        triggerBtn.textContent = (isHidden ? 'Hiện phản hồi' : 'Ẩn phản hồi') + ' (' + countText + ')';
    }
</script>

<?php include '../components/layout_footer.php'; ?>
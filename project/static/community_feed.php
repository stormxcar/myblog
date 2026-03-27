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
$featured24h = community_get_featured_posts_24h($conn, 5);
$interestPosts = community_get_posts_near_user_interests($conn, $user_id, 5);
$viewerBadges = $user_id > 0 ? (community_build_user_badges_map($conn, [$user_id])[$user_id] ?? []) : [];
$viewerFollowers = [];
$viewerFollowing = [];
$followSuggestions = [];
$viewerNotifyPref = ['follow_events_enabled' => 1, 'new_post_events_enabled' => 1];

if ($user_id > 0) {
    $followersStmt = $conn->prepare('SELECT u.id, u.name
        FROM community_user_follows f
        INNER JOIN users u ON u.id = f.follower_user_id
        WHERE f.following_user_id = ?
        ORDER BY f.created_at DESC
        LIMIT 8');
    $followersStmt->execute([$user_id]);
    $viewerFollowers = $followersStmt->fetchAll(PDO::FETCH_ASSOC);

    $followingStmt = $conn->prepare('SELECT u.id, u.name
        FROM community_user_follows f
        INNER JOIN users u ON u.id = f.following_user_id
        WHERE f.follower_user_id = ?
        ORDER BY f.created_at DESC
        LIMIT 8');
    $followingStmt->execute([$user_id]);
    $viewerFollowing = $followingStmt->fetchAll(PDO::FETCH_ASSOC);

    if (function_exists('community_get_follow_suggestions')) {
        $followSuggestions = community_get_follow_suggestions($conn, $user_id, 6);
    }
    if (function_exists('community_get_notification_preference')) {
        $viewerNotifyPref = community_get_notification_preference($conn, $user_id);
    }
}

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

        .community-post-card[data-read="1"] {
            position: relative;
            outline: 1px dashed rgba(107, 114, 128, 0.45);
            opacity: 0.9;
        }

        .community-post-card[data-read="1"]::after {
            content: 'DA DOC';
            position: absolute;
            top: 0.6rem;
            right: 0.75rem;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.04em;
            color: #4b5563;
            background: #e5e7eb;
            border-radius: 999px;
            padding: 0.15rem 0.45rem;
            z-index: 8;
        }

        .community-read-hidden {
            display: none !important;
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
                            <a href="community_create.php?quick=1" class="btn-secondary"><i class="fas fa-bolt mr-2"></i>Đăng nhanh 15 giây</a>
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
                <div class="mx-auto w-full mb-3" style="max-width:850px;">
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <button type="button" id="community-toggle-hide-read" class="px-3 py-1.5 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-main/10 hover:text-main">An bai da doc</button>
                        <button type="button" id="community-reset-read" class="px-3 py-1.5 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-main/10 hover:text-main">Hien lai bai da doc</button>
                        <span id="community-read-stats" class="text-gray-500 dark:text-gray-400">0 bai da doc</span>
                    </div>
                </div>

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
                <div id="community-feed-error" class="hidden mt-3 text-center text-xs text-red-600 dark:text-red-300 mx-auto w-full" style="max-width:850px;"></div>
                <div id="community-feed-end" class="hidden mt-4 text-center text-xs text-gray-500 dark:text-gray-400 mx-auto w-full" style="max-width:850px;">Bạn đã xem hết bài viết.</div>
                <div id="community-feed-sentinel" class="h-8 mx-auto w-full" style="max-width:850px;"></div>
            </div>

            <aside class="space-y-4 lg:sticky lg:top-24 lg:col-span-5 xl:col-span-4">
                <?php if ($user_id > 0): ?>
                    <section class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow p-4">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Mang theo doi</h3>
                        </div>

                        <div class="grid grid-cols-2 gap-3 mt-3">
                            <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-3">
                                <p class="text-[11px] text-gray-500 dark:text-gray-400 uppercase tracking-wide">Nguoi theo doi</p>
                                <p class="text-xl font-bold text-gray-900 dark:text-white mt-1"><?= count($viewerFollowers); ?></p>
                            </div>
                            <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-3">
                                <p class="text-[11px] text-gray-500 dark:text-gray-400 uppercase tracking-wide">Dang theo doi</p>
                                <p class="text-xl font-bold text-gray-900 dark:text-white mt-1"><?= count($viewerFollowing); ?></p>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-1 gap-3">
                            <div>
                                <p class="text-xs font-semibold text-gray-600 dark:text-gray-300 mb-2">Ai dang theo doi ban</p>
                                <?php if (!empty($viewerFollowers)): ?>
                                    <div class="space-y-1.5">
                                        <?php foreach ($viewerFollowers as $follower): ?>
                                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200 mr-1 mb-1">
                                                <i class="fas fa-user-check text-[10px]"></i>
                                                <?= htmlspecialchars((string)($follower['name'] ?? 'User'), ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Chua co nguoi theo doi.</p>
                                <?php endif; ?>
                            </div>

                            <div>
                                <p class="text-xs font-semibold text-gray-600 dark:text-gray-300 mb-2">Ban dang theo doi</p>
                                <?php if (!empty($viewerFollowing)): ?>
                                    <div class="space-y-1.5">
                                        <?php foreach ($viewerFollowing as $following): ?>
                                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-200 mr-1 mb-1">
                                                <i class="fas fa-user-plus text-[10px]"></i>
                                                <?= htmlspecialchars((string)($following['name'] ?? 'User'), ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Ban chua theo doi ai.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>

                    <section class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow p-4">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Hồ sơ cộng đồng</h3>
                            <a href="update.php" class="text-xs text-main hover:underline">Cập nhật hồ sơ</a>
                        </div>
                        <?php if (!empty($viewerBadges)): ?>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <?php foreach ($viewerBadges as $badge): ?>
                                    <span class="text-[11px] px-2.5 py-1 rounded-full font-semibold <?= htmlspecialchars((string)($badge['class'] ?? 'bg-main/10 text-main'), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string)($badge['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Đăng thêm bài và nhận upvote để mở khóa badge mốc 10 bài, 100 upvote, Top tuần.</p>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

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

                <section class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow p-4">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Bài nổi bật 24h</h3>
                    </div>
                    <div class="mt-3 space-y-3">
                        <?php if (!empty($featured24h)): ?>
                            <?php foreach ($featured24h as $item): ?>
                                <?php $itemTitle = trim((string)($item['post_title'] ?? '')) !== '' ? (string)$item['post_title'] : community_extract_title((string)$item['content']); ?>
                                <a href="<?= htmlspecialchars(community_post_path((int)$item['id'], (string)$itemTitle), ENT_QUOTES, 'UTF-8'); ?>" class="block rounded-lg border border-gray-200 dark:border-gray-700 p-3 hover:border-main transition-colors">
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white line-clamp-2"><?= htmlspecialchars((string)$itemTitle, ENT_QUOTES, 'UTF-8'); ?></h4>
                                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                                        <span class="mr-2"><i class="fas fa-arrow-up mr-1"></i><?= (int)($item['total_upvotes'] ?? 0); ?></span>
                                        <span><i class="fas fa-comments mr-1"></i><?= (int)($item['total_comments'] ?? 0); ?></span>
                                    </p>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Chưa có bài đủ tín hiệu trong 24 giờ gần đây.</p>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow p-4">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Bài gần bạn quan tâm</h3>
                    </div>
                    <div class="mt-3 space-y-3">
                        <?php if (!empty($interestPosts)): ?>
                            <?php foreach ($interestPosts as $item): ?>
                                <?php $itemTitle = trim((string)($item['post_title'] ?? '')) !== '' ? (string)$item['post_title'] : community_extract_title((string)$item['content']); ?>
                                <a href="<?= htmlspecialchars(community_post_path((int)$item['id'], (string)$itemTitle), ENT_QUOTES, 'UTF-8'); ?>" class="block rounded-lg border border-gray-200 dark:border-gray-700 p-3 hover:border-main transition-colors">
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white line-clamp-2"><?= htmlspecialchars((string)$itemTitle, ENT_QUOTES, 'UTF-8'); ?></h4>
                                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400"><?= htmlspecialchars(community_time_ago((string)$item['created_at']), ENT_QUOTES, 'UTF-8'); ?></p>
                                </a>
                            <?php endforeach; ?>
                        <?php elseif ($user_id > 0): ?>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Hãy tương tác vài bài đầu tiên để hệ thống cá nhân hóa tốt hơn.</p>
                        <?php else: ?>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Đăng nhập để xem gợi ý theo chủ đề bạn quan tâm.</p>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow p-4">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Digest thông báo</h3>
                    </div>
                    <p class="mt-3 text-xs text-gray-600 dark:text-gray-300">Nhận tổng hợp theo ngày hoặc tuần khi bài viết của bạn có react và comment mới.</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" data-community-digest="daily" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-main text-white hover:opacity-90">Bật digest ngày</button>
                        <button type="button" data-community-digest="weekly" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-main/10">Bật digest tuần</button>
                    </div>
                </section>

                <?php if ($user_id > 0): ?>
                    <section class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow p-4">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Thong bao theo doi</h3>
                        </div>
                        <p class="mt-3 text-xs text-gray-600 dark:text-gray-300">Bat/tat rieng thong bao khi co follower moi va khi nguoi ban theo doi dang bai moi.</p>
                        <div class="mt-3 space-y-2">
                            <?php
                            $followPrefOn = (int)($viewerNotifyPref['follow_events_enabled'] ?? 1) === 1;
                            $newPostPrefOn = (int)($viewerNotifyPref['new_post_events_enabled'] ?? 1) === 1;
                            ?>
                            <button
                                type="button"
                                data-community-notify-pref="follow_events_enabled"
                                data-enabled="<?= $followPrefOn ? '1' : '0'; ?>"
                                class="w-full text-left px-3 py-2 rounded-lg text-xs font-semibold <?= $followPrefOn ? 'bg-main text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200'; ?>">
                                Follower moi: <?= $followPrefOn ? 'Dang bat' : 'Dang tat'; ?>
                            </button>
                            <button
                                type="button"
                                data-community-notify-pref="new_post_events_enabled"
                                data-enabled="<?= $newPostPrefOn ? '1' : '0'; ?>"
                                class="w-full text-left px-3 py-2 rounded-lg text-xs font-semibold <?= $newPostPrefOn ? 'bg-main text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200'; ?>">
                                Bai moi tu nguoi dang theo doi: <?= $newPostPrefOn ? 'Dang bat' : 'Dang tat'; ?>
                            </button>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ($user_id > 0): ?>
                    <section class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow p-4">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Goi y nen theo doi</h3>
                        </div>
                        <div class="mt-3 space-y-2">
                            <?php if (!empty($followSuggestions)): ?>
                                <?php foreach ($followSuggestions as $suggest): ?>
                                    <?php
                                    $suggestUserId = (int)($suggest['user_id'] ?? 0);
                                    $suggestName = (string)($suggest['user_name'] ?? 'User');
                                    $suggestProfileUrl = function_exists('community_profile_path') ? community_profile_path($suggestUserId, $suggestName) : ('community_profile.php?user=' . $suggestUserId);
                                    ?>
                                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                                        <div class="flex items-center justify-between gap-2">
                                            <a href="<?= htmlspecialchars($suggestProfileUrl, ENT_QUOTES, 'UTF-8'); ?>" class="text-sm font-semibold text-gray-900 dark:text-white hover:underline"><?= htmlspecialchars($suggestName, ENT_QUOTES, 'UTF-8'); ?></a>
                                            <button
                                                type="button"
                                                class="px-2.5 py-1 rounded-md text-xs font-semibold bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-main/10 hover:text-main"
                                                data-community-follow-btn
                                                data-target-user-id="<?= $suggestUserId; ?>"
                                                data-following="0">Theo doi</button>
                                        </div>
                                        <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">Hop chu de: <?= (int)($suggest['matched_posts'] ?? 0); ?> bai</p>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Tuong tac them de nhan goi y theo doi phu hop hon.</p>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <section class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow p-4">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Template post nhanh</h3>
                    </div>
                    <div class="mt-3 grid grid-cols-1 gap-2">
                        <a href="community_create.php?template=experience" class="px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-200 hover:bg-main/10">Chia sẻ kinh nghiệm</a>
                        <a href="community_create.php?template=review" class="px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-200 hover:bg-main/10">Review địa điểm</a>
                        <a href="community_create.php?template=qa" class="px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-200 hover:bg-main/10">Hỏi đáp nhanh</a>
                        <a href="community_create.php?template=poll" class="px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-200 hover:bg-main/10">Khảo sát nhanh (poll)</a>
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
        const errorEl = document.getElementById('community-feed-error');
        const endEl = document.getElementById('community-feed-end');
        const sentinel = document.getElementById('community-feed-sentinel');
        const toggleHideReadBtn = document.getElementById('community-toggle-hide-read');
        const resetReadBtn = document.getElementById('community-reset-read');
        const readStatsEl = document.getElementById('community-read-stats');
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
        let consecutiveLoadFailures = 0;

        const storageScope = String(<?= (int)$user_id; ?> || 'guest');
        const readStorageKey = 'community_read_posts_v1_' + storageScope;
        const hideReadStorageKey = 'community_hide_read_v1_' + storageScope;

        const readPostIds = new Set((() => {
            try {
                const raw = localStorage.getItem(readStorageKey);
                const parsed = raw ? JSON.parse(raw) : [];
                return Array.isArray(parsed) ? parsed.map(function(id) { return Number(id) || 0; }).filter(function(id) { return id > 0; }) : [];
            } catch (err) {
                return [];
            }
        })());
        let hideReadPosts = (function() {
            try {
                return localStorage.getItem(hideReadStorageKey) === '1';
            } catch (err) {
                return false;
            }
        })();

        const persistReadState = function() {
            try {
                localStorage.setItem(readStorageKey, JSON.stringify(Array.from(readPostIds)));
                localStorage.setItem(hideReadStorageKey, hideReadPosts ? '1' : '0');
            } catch (err) {
                // Ignore storage failures.
            }
        };

        const updateReadStats = function() {
            if (readStatsEl) {
                readStatsEl.textContent = String(readPostIds.size) + ' bai da doc';
            }
            if (toggleHideReadBtn) {
                toggleHideReadBtn.textContent = hideReadPosts ? 'Dang an bai da doc' : 'An bai da doc';
                toggleHideReadBtn.classList.toggle('bg-main', hideReadPosts);
                toggleHideReadBtn.classList.toggle('text-white', hideReadPosts);
            }
        };

        const getPostIdFromCard = function(card) {
            if (!card || !card.id) {
                return 0;
            }
            const match = String(card.id).match(/^community-post-(\d+)$/);
            return match ? Number(match[1]) : 0;
        };

        const applyReadStateToCard = function(card) {
            const postId = getPostIdFromCard(card);
            if (!postId) {
                return;
            }
            const isRead = readPostIds.has(postId);
            card.setAttribute('data-read', isRead ? '1' : '0');
            card.classList.toggle('community-read-hidden', hideReadPosts && isRead);
        };

        const applyReadStateToFeed = function() {
            feedList.querySelectorAll('.community-post-card').forEach(function(card) {
                applyReadStateToCard(card);
            });
            updateReadStats();
        };

        const markPostRead = function(postId) {
            postId = Number(postId || 0);
            if (!postId || readPostIds.has(postId)) {
                return;
            }
            readPostIds.add(postId);
            persistReadState();
            applyReadStateToFeed();
        };

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

        const getPollVoteEndpoint = () => {
            if (window.BLOG_ENDPOINTS && window.BLOG_ENDPOINTS.communityPollVote) {
                return window.BLOG_ENDPOINTS.communityPollVote;
            }
            return 'community_poll_vote.php';
        };

        const getDigestPreferenceEndpoint = () => {
            if (window.BLOG_ENDPOINTS && window.BLOG_ENDPOINTS.communityDigestPreference) {
                return window.BLOG_ENDPOINTS.communityDigestPreference;
            }
            return 'community_digest_preference.php';
        };

        const getNotificationPreferenceEndpoint = () => {
            if (window.BLOG_ENDPOINTS && window.BLOG_ENDPOINTS.communityNotificationPreference) {
                return window.BLOG_ENDPOINTS.communityNotificationPreference;
            }
            return 'community_notification_preference.php';
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
            if (value && errorEl) {
                errorEl.classList.add('hidden');
                errorEl.textContent = '';
            }
        };

        const showLoadError = (message) => {
            if (errorEl) {
                errorEl.textContent = message;
                errorEl.classList.remove('hidden');
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
                    throw new Error('HTTP ' + res.status);
                }
                const rawText = await res.text();
                let payload = null;
                try {
                    payload = JSON.parse(rawText);
                } catch (err) {
                    throw new Error('INVALID_JSON');
                }
                if (!payload || payload.ok !== true) {
                    throw new Error('API_FAIL');
                }

                if (payload.html) {
                    feedList.insertAdjacentHTML('beforeend', String(payload.html));
                    applyReadStateToFeed();
                    if (shared) {
                        shared.initCarousels(feedList);
                    } else {
                        initCarousels(feedList);
                    }
                }
                feedList.setAttribute('data-next-page', payload.next_page ? String(payload.next_page) : '');
                setHasMore(!!payload.has_more);
                consecutiveLoadFailures = 0;
            } catch (err) {
                consecutiveLoadFailures += 1;
                showLoadError('Khong tai duoc bai tiep theo. Thu keo xuong lai sau it giay.');
                if (consecutiveLoadFailures >= 3) {
                    setHasMore(false);
                }
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

            if (action === 'pin') {
                try {
                    const fd = new FormData();
                    fd.set('action', 'pin');
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
                        showNotification((payload && payload.message) || 'Khong the cap nhat ghim bai viet.', 'error');
                        return;
                    }

                    if (actionButton) {
                        const isPinned = Number(payload.pinned || 0) === 1;
                        actionButton.setAttribute('data-pinned', isPinned ? '1' : '0');
                        actionButton.textContent = isPinned ? 'Bo ghim tren dau feed' : 'Ghim len dau feed';
                    }
                    showNotification(payload.message || 'Da cap nhat ghim bai viet.', 'success');
                } catch (err) {
                    showNotification('Loi ket noi khi cap nhat ghim bai viet.', 'error');
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

        function syncFollowButtons(targetUserId, following, followersCount, followedByTarget) {
            const selector = '[data-community-follow-btn][data-target-user-id="' + String(targetUserId) + '"]';
            document.querySelectorAll(selector).forEach(function(btn) {
                btn.setAttribute('data-following', following ? '1' : '0');
                if (following) {
                    btn.textContent = 'Dang theo doi';
                    btn.classList.add('bg-main', 'text-white');
                    btn.classList.remove('bg-gray-100', 'text-gray-700', 'dark:text-gray-200');
                } else {
                    btn.textContent = followedByTarget ? 'Theo doi lai' : 'Theo doi';
                    btn.classList.remove('bg-main', 'text-white');
                    btn.classList.add('bg-gray-100', 'text-gray-700', 'dark:text-gray-200');
                }
            });

            const countSelector = '[data-community-followers-count][data-user-id="' + String(targetUserId) + '"]';
            document.querySelectorAll(countSelector).forEach(function(el) {
                el.textContent = String(Number(followersCount || 0));
            });
        }

        async function submitFollow(button) {
            const targetUserId = Number(button.getAttribute('data-target-user-id') || '0');
            if (!targetUserId) {
                return;
            }

            button.disabled = true;
            try {
                const fd = new FormData();
                fd.set('action', 'follow');
                fd.set('target_user_id', String(targetUserId));

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
                    showNotification((payload && payload.message) || 'Khong the cap nhat theo doi.', 'error');
                    return;
                }

                syncFollowButtons(
                    Number(payload.target_user_id || targetUserId),
                    Number(payload.following || 0) === 1,
                    Number(payload.followers_count || 0),
                    Boolean(payload.followed_by_target)
                );
                showNotification(payload.message || 'Da cap nhat theo doi.', 'success');
            } catch (err) {
                showNotification('Loi ket noi khi theo doi tac gia.', 'error');
            } finally {
                button.disabled = false;
            }
        }

        async function toggleNotificationPreference(button) {
            const key = String(button.getAttribute('data-community-notify-pref') || '');
            const enabledNow = String(button.getAttribute('data-enabled') || '1') === '1';
            if (!key) {
                return;
            }

            try {
                const fd = new FormData();
                fd.set('key', key);
                fd.set('enabled', enabledNow ? '0' : '1');
                const res = await fetch(getNotificationPreferenceEndpoint(), {
                    method: 'POST',
                    body: fd,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                });
                const payload = await res.json();
                if (!payload || payload.ok !== true) {
                    showNotification((payload && payload.message) || 'Khong the cap nhat cai dat thong bao.', 'error');
                    return;
                }

                const enabled = (payload.preference && Number(payload.preference[key] || 0) === 1);
                button.setAttribute('data-enabled', enabled ? '1' : '0');
                button.classList.toggle('bg-main', enabled);
                button.classList.toggle('text-white', enabled);
                button.classList.toggle('bg-gray-100', !enabled);
                button.classList.toggle('text-gray-700', !enabled);
                if (key === 'follow_events_enabled') {
                    button.textContent = 'Follower moi: ' + (enabled ? 'Dang bat' : 'Dang tat');
                } else if (key === 'new_post_events_enabled') {
                    button.textContent = 'Bai moi tu nguoi dang theo doi: ' + (enabled ? 'Dang bat' : 'Dang tat');
                }
                showNotification(payload.message || 'Da cap nhat cai dat thong bao.', 'success');
            } catch (err) {
                showNotification('Loi ket noi khi cap nhat cai dat thong bao.', 'error');
            }
        }

        function updatePollOptionState(postId, userOptionId, totalVotes, options) {
            const wrap = document.querySelector('[data-community-poll-wrap][data-post-id="' + postId + '"]');
            if (!wrap) {
                return;
            }

            const optionsById = {};
            (options || []).forEach(function(item) {
                const optionId = Number(item.option_id || 0);
                if (optionId > 0) {
                    optionsById[optionId] = Number(item.vote_count || 0);
                }
            });

            const effectiveTotal = Math.max(0, Number(totalVotes || 0));
            wrap.querySelectorAll('[data-community-poll-option][data-post-id="' + postId + '"]').forEach(function(btn) {
                const optionId = Number(btn.getAttribute('data-option-id') || '0');
                const voteCount = Number(optionsById[optionId] || 0);
                const percent = effectiveTotal > 0 ? Math.round((voteCount / effectiveTotal) * 100) : 0;
                const isActive = optionId > 0 && optionId === Number(userOptionId || 0);
                btn.classList.toggle('border-main', isActive);
                btn.classList.toggle('bg-main/10', isActive);
                btn.classList.toggle('font-semibold', isActive);
                btn.setAttribute('data-selected', isActive ? '1' : '0');

                const meta = btn.querySelector('[data-community-poll-option-meta]');
                if (meta) {
                    meta.setAttribute('data-vote-count', String(voteCount));
                    meta.textContent = voteCount + ' vote(s) • ' + percent + '%';
                }
            });

            const totalEl = wrap.querySelector('[data-community-poll-total]');
            if (totalEl) {
                totalEl.textContent = String(effectiveTotal);
            }
        }

        async function votePollOption(button) {
            const postId = Number(button.getAttribute('data-post-id') || '0');
            const optionId = Number(button.getAttribute('data-option-id') || '0');
            if (!postId) {
                return;
            }

            button.disabled = true;
            try {
                const fd = new FormData();
                fd.set('post_id', String(postId));
                fd.set('option_id', String(optionId));

                const res = await fetch(getPollVoteEndpoint(), {
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
                    showNotification((payload && payload.message) || 'Khong the cap nhat poll.', 'error');
                    return;
                }

                updatePollOptionState(postId, payload.user_option_id || 0, payload.total_votes || 0, payload.options || []);
                showNotification(payload.message || 'Đã ghi nhận bình chọn poll của bạn.', 'success');
            } catch (err) {
                showNotification('Loi ket noi khi binh chon poll.', 'error');
            } finally {
                button.disabled = false;
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

        document.addEventListener('click', async function(event) {
            if (shared && shared.handleClick(event)) {
                return;
            }

            const postLink = event.target.closest('a[href]');
            if (postLink) {
                const href = String(postLink.getAttribute('href') || '');
                const isPostDetailLink = href.indexOf('/community/p/') !== -1 || href.indexOf('community_post.php') !== -1;
                if (isPostDetailLink) {
                    const postCard = postLink.closest('.community-post-card');
                    if (postCard) {
                        const postId = getPostIdFromCard(postCard);
                        if (postId > 0) {
                            markPostRead(postId);
                        }
                    }
                }
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

            const followButton = event.target.closest('[data-community-follow-btn]');
            if (followButton) {
                event.preventDefault();
                submitFollow(followButton);
                return;
            }

            const pollButton = event.target.closest('[data-community-poll-option]');
            if (pollButton) {
                event.preventDefault();
                votePollOption(pollButton);
                return;
            }

            const digestBtn = event.target.closest('[data-community-digest]');
            if (digestBtn) {
                event.preventDefault();
                const digestMode = String(digestBtn.getAttribute('data-community-digest') || 'daily');
                try {
                    const fd = new FormData();
                    fd.set('frequency', digestMode);
                    const res = await fetch(getDigestPreferenceEndpoint(), {
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
                        showNotification((payload && payload.message) || 'Khong the cap nhat digest.', 'error');
                        return;
                    }
                    showNotification(payload.message || 'Da cap nhat digest.', 'success');
                } catch (err) {
                    showNotification('Loi ket noi khi cap nhat digest.', 'error');
                }
                return;
            }

            const notifyPrefBtn = event.target.closest('[data-community-notify-pref]');
            if (notifyPrefBtn) {
                event.preventDefault();
                toggleNotificationPreference(notifyPrefBtn);
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

        if (toggleHideReadBtn) {
            toggleHideReadBtn.addEventListener('click', function() {
                hideReadPosts = !hideReadPosts;
                persistReadState();
                applyReadStateToFeed();
            });
        }

        if (resetReadBtn) {
            resetReadBtn.addEventListener('click', function() {
                readPostIds.clear();
                hideReadPosts = false;
                persistReadState();
                applyReadStateToFeed();
            });
        }

        if (shared) {
            shared.initCarousels(feedList);
        } else {
            initCarousels(feedList);
        }

        applyReadStateToFeed();

        // Try one proactive load when sentinel is already visible on tall screens.
        setTimeout(function() {
            if (hasMore()) {
                loadMoreFeed();
            }
        }, 250);

        document.querySelectorAll('[data-community-poll-wrap]').forEach(function(wrap) {
            const pid = Number(wrap.getAttribute('data-post-id') || '0');
            if (pid <= 0) {
                return;
            }
            let selected = 0;
            const options = [];
            wrap.querySelectorAll('[data-community-poll-option][data-post-id="' + pid + '"]').forEach(function(btn) {
                const optionId = Number(btn.getAttribute('data-option-id') || '0');
                const voteCount = Number(btn.querySelector('[data-community-poll-option-meta]')?.getAttribute('data-vote-count') || '0');
                if (btn.getAttribute('data-selected') === '1') {
                    selected = optionId;
                }
                options.push({
                    option_id: optionId,
                    vote_count: voteCount
                });
            });
            const totalVotes = Number(wrap.querySelector('[data-community-poll-total]')?.textContent || '0');
            updatePollOptionState(pid, selected, totalVotes, options);
        });

        document.querySelectorAll('[data-community-poll-option]').forEach(function(btn) {
            const pid = Number(btn.getAttribute('data-post-id') || '0');
            if (pid <= 0) {
                btn.disabled = true;
            }
        });
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
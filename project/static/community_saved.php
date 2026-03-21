<?php
include '../components/connect.php';
include '../components/seo_helpers.php';
include '../components/community_engine.php';
include '../components/community_feed_renderer.php';

session_start();
community_ensure_tables($conn);

$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
    $_SESSION['flash_message'] = 'Vui long dang nhap de xem bai cong dong da luu.';
    $_SESSION['flash_type'] = 'warning';
    header('Location: login.php');
    exit;
}

$page_title = 'Bai cong dong da luu - My Blog';
$page_description = 'Danh sach bai viet cong dong ma ban da luu.';
$page_canonical = site_url('static/community_saved.php');

$activeTopicSlug = trim((string)($_GET['topic'] ?? ''));
$firstBundle = community_fetch_saved_posts_page($conn, $user_id, 1, 6, $activeTopicSlug);
$firstMaps = community_load_post_maps($conn, $firstBundle['posts'], $user_id);
$firstPostsHtml = community_render_feed_posts_html($firstBundle['posts'], $firstMaps, $user_id);

$savedTopicsStmt = $conn->prepare("SELECT ct.slug, ct.name, COUNT(*) AS post_count
    FROM community_saved_posts sp
    INNER JOIN community_posts p ON p.id = sp.post_id
    INNER JOIN community_post_topics cpt ON cpt.post_id = p.id
    INNER JOIN community_topics ct ON ct.id = cpt.topic_id
    WHERE sp.user_id = ?
    AND (p.status = 'published' OR p.user_id = ?)
    GROUP BY ct.id, ct.slug, ct.name
    ORDER BY post_count DESC, ct.name ASC
    LIMIT 16");
$savedTopicsStmt->execute([$user_id, $user_id]);
$savedTopics = $savedTopicsStmt->fetchAll(PDO::FETCH_ASSOC);

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
$breadcrumb_items = auto_breadcrumb('Bài cộng đồng đã lưu');
render_breadcrumb($breadcrumb_items);
?>

<main class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <div class="container-custom py-8">
        <section class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-5 sm:p-6 border border-gray-200 dark:border-gray-700 mb-6">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Bài cộng đồng đã lưu</h1>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mt-2">Danh sách bài đã lưu với trải nghiệm giống bảng tin cộng đồng.</p>
                </div>
                <a href="community_feed.php" class="btn-primary">Quay lại bảng tin</a>
            </div>

            <?php if ($activeTopicSlug !== '' && $activeTopicName !== ''): ?>
                <div class="mt-4 inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-main/10 text-main text-xs font-semibold">
                    <span>Lọc theo chủ đề:</span>
                    <span>#<?= htmlspecialchars($activeTopicName, ENT_QUOTES, 'UTF-8'); ?></span>
                    <a href="community_saved.php" class="text-gray-600 hover:text-main">Bỏ lọc</a>
                </div>
            <?php endif; ?>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            <div class="w-full lg:col-span-8 xl:col-span-8">
                <div id="community-saved-list" class="space-y-6 mx-auto w-full" style="max-width:850px;" data-next-page="<?= $firstBundle['next_page'] === null ? '' : (int)$firstBundle['next_page']; ?>" data-has-more="<?= $firstBundle['has_more'] ? '1' : '0'; ?>" data-topic="<?= htmlspecialchars($activeTopicSlug, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php if (!empty($firstBundle['posts'])): ?>
                        <?= $firstPostsHtml; ?>
                    <?php else: ?>
                        <section class="bg-white dark:bg-gray-800 rounded-2xl mt-6 shadow-lg border border-gray-200 dark:border-gray-700 p-8 sm:p-10 text-center">
                            <h2 class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-white">Chưa có bài đã lưu phù hợp</h2>
                            <p class="text-gray-600 dark:text-gray-300 mt-2 text-sm sm:text-base">Hãy vào bảng tin và chọn lưu bài viết.</p>
                            <a href="community_feed.php" class="btn-primary mt-4 inline-flex">Mở bảng tin cộng đồng</a>
                        </section>
                    <?php endif; ?>
                </div>

                <div id="community-saved-skeleton" class="hidden mt-4 space-y-4 mx-auto w-full" style="max-width:850px;" aria-hidden="true">
                    <section class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-200 dark:border-gray-700 p-4 sm:p-5 animate-pulse">
                        <div class="h-5 w-40 bg-gray-200 dark:bg-gray-700 rounded"></div>
                        <div class="mt-4 h-24 bg-gray-200 dark:bg-gray-700 rounded"></div>
                    </section>
                </div>

                <div id="community-saved-loading" class="hidden mt-4 text-center text-sm text-gray-500 dark:text-gray-400 mx-auto w-full" style="max-width:850px;">
                    <i class="fas fa-spinner fa-spin mr-2"></i>Đang tải thêm bài đã lưu...
                </div>
                <div id="community-saved-end" class="hidden mt-4 text-center text-xs text-gray-500 dark:text-gray-400 mx-auto w-full" style="max-width:850px;">Bạn đã xem hết bài đã lưu.</div>
                <div id="community-saved-sentinel" class="h-8 mx-auto w-full" style="max-width:850px;"></div>
            </div>

            <aside class="space-y-4 lg:sticky lg:top-24 lg:col-span-4 xl:col-span-4">
                <section class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow p-4">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Chủ đề đã lưu</h3>
                        <?php if ($activeTopicSlug !== ''): ?>
                            <a href="community_saved.php" class="text-xs text-main hover:underline">Xem tất cả</a>
                        <?php endif; ?>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <?php if (!empty($savedTopics)): ?>
                            <?php foreach ($savedTopics as $topic): ?>
                                <a href="?topic=<?= urlencode((string)$topic['slug']); ?>" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold <?= $activeTopicSlug === (string)$topic['slug'] ? 'bg-main text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-main/10 hover:text-main'; ?>">
                                    <span>#<?= htmlspecialchars((string)$topic['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="opacity-70">(<?= (int)$topic['post_count']; ?>)</span>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Chủ đề sẽ hiển thị khi bạn lưu bài viết.</p>
                        <?php endif; ?>
                    </div>
                </section>
            </aside>
        </section>
    </div>
</main>

<script src="../js/community-feed-shared.js"></script>
<script>
    (function() {
        const listEl = document.getElementById('community-saved-list');
        const loadingEl = document.getElementById('community-saved-loading');
        const skeletonEl = document.getElementById('community-saved-skeleton');
        const endEl = document.getElementById('community-saved-end');
        const sentinel = document.getElementById('community-saved-sentinel');

        if (!listEl || !sentinel) {
            return;
        }

        let isLoading = false;
        const shared = window.CommunityFeedShared ? window.CommunityFeedShared.create({
            onPostRemoved: function() {
                // no-op
            }
        }) : null;

        const getEndpoint = () => {
            if (window.BLOG_ENDPOINTS && window.BLOG_ENDPOINTS.communitySavedList) {
                return window.BLOG_ENDPOINTS.communitySavedList;
            }
            return 'community_saved_list_api.php';
        };

        const setLoading = (next) => {
            if (loadingEl) {
                loadingEl.classList.toggle('hidden', !next);
            }
            if (skeletonEl) {
                skeletonEl.classList.toggle('hidden', !next);
            }
        };

        const hasMore = () => listEl.getAttribute('data-has-more') === '1';
        const getNextPage = () => Number(listEl.getAttribute('data-next-page') || '0');

        const setHasMore = (next) => {
            listEl.setAttribute('data-has-more', next ? '1' : '0');
            if (!next && endEl) {
                endEl.classList.remove('hidden');
            }
        };

        async function loadMore() {
            if (isLoading || !hasMore()) {
                return;
            }

            const page = getNextPage();
            if (!page) {
                setHasMore(false);
                return;
            }

            isLoading = true;
            setLoading(true);

            try {
                const params = new URLSearchParams();
                params.set('page', String(page));
                params.set('limit', '6');
                const topic = listEl.getAttribute('data-topic') || '';
                if (topic) {
                    params.set('topic', topic);
                }

                const response = await fetch(getEndpoint() + '?' + params.toString(), {
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!response.ok) {
                    return;
                }

                const payload = await response.json();
                if (!payload || payload.ok !== true) {
                    return;
                }

                if (payload.html) {
                    listEl.insertAdjacentHTML('beforeend', String(payload.html));
                    if (shared) {
                        shared.initCarousels(listEl);
                    }
                }
                listEl.setAttribute('data-next-page', payload.next_page ? String(payload.next_page) : '');
                setHasMore(!!payload.has_more);
            } catch (e) {
                // Silent fail to keep reading flow.
            } finally {
                isLoading = false;
                setLoading(false);
            }
        }

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    loadMore();
                }
            });
        }, {
            rootMargin: '220px 0px'
        });

        observer.observe(sentinel);

        if (shared) {
            shared.bindDelegation(document);
            shared.initCarousels(listEl);
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
        const targetUrl = window.location.origin + '/project/static/community_feed.php#community-post-' + String(postId || '');
        if (navigator.share) {
            navigator.share({
                title: 'Chia se bai viet cong dong',
                url: targetUrl,
            }).catch(function() {});
            return;
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(targetUrl).then(function() {
                showNotification('Da sao chep lien ket bai viet.', 'success');
            }).catch(function() {});
        }
    }
</script>

<?php include '../components/layout_footer.php'; ?>
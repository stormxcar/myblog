<?php
include '../components/connect.php';
include '../components/seo_helpers.php';
include '../components/community_engine.php';
include '../components/community_feed_renderer.php';

session_start();
community_ensure_tables($conn);

$viewerId = (int)($_SESSION['user_id'] ?? 0);
$userSlug = trim((string)($_GET['user'] ?? ''));
$profileUserId = community_extract_profile_user_id($userSlug);
if ($profileUserId <= 0) {
    $profileUserId = (int)($_GET['user_id'] ?? 0);
}
if ($profileUserId <= 0) {
    http_response_code(404);
    include '404.php';
    exit;
}

$userStmt = $conn->prepare('SELECT id, name FROM users WHERE id = ? LIMIT 1');
$userStmt->execute([$profileUserId]);
$profileUser = $userStmt->fetch(PDO::FETCH_ASSOC);
if (!$profileUser) {
    http_response_code(404);
    include '404.php';
    exit;
}

$profileName = (string)($profileUser['name'] ?? 'User');
$isOwner = $viewerId > 0 && $viewerId === $profileUserId;

$tab = trim((string)($_GET['tab'] ?? 'posts'));
if (!in_array($tab, ['posts', 'followers', 'following'], true)) {
    $tab = 'posts';
}

$profileUrl = community_profile_path($profileUserId, $profileName);
$requestPath = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
$isCanonicalPath = strpos($requestPath, '/community/u/') !== false;

if (!$isCanonicalPath || isset($_GET['user_id'])) {
    $redirectParams = [];
    if ($tab !== 'posts') {
        $redirectParams['tab'] = $tab;
    }

    $requestedPage = max(1, (int)($_GET['page'] ?? 1));
    if ($requestedPage > 1) {
        $redirectParams['page'] = $requestedPage;
    }

    $redirectUrl = $profileUrl;
    if (!empty($redirectParams)) {
        $redirectUrl .= '?' . http_build_query($redirectParams);
    }

    header('Location: ' . $redirectUrl, true, 301);
    exit;
}

$isFollowing = false;
$followedByProfile = false;
if ($viewerId > 0 && !$isOwner) {
    $checkFollowStmt = $conn->prepare('SELECT 1 FROM community_user_follows WHERE follower_user_id = ? AND following_user_id = ? LIMIT 1');
    $checkFollowStmt->execute([$viewerId, $profileUserId]);
    $isFollowing = (bool)$checkFollowStmt->fetchColumn();

    $checkBackStmt = $conn->prepare('SELECT 1 FROM community_user_follows WHERE follower_user_id = ? AND following_user_id = ? LIMIT 1');
    $checkBackStmt->execute([$profileUserId, $viewerId]);
    $followedByProfile = (bool)$checkBackStmt->fetchColumn();
}

$countFollowersStmt = $conn->prepare('SELECT COUNT(*) FROM community_user_follows WHERE following_user_id = ?');
$countFollowersStmt->execute([$profileUserId]);
$totalFollowers = (int)$countFollowersStmt->fetchColumn();

$countFollowingStmt = $conn->prepare('SELECT COUNT(*) FROM community_user_follows WHERE follower_user_id = ?');
$countFollowingStmt->execute([$profileUserId]);
$totalFollowing = (int)$countFollowingStmt->fetchColumn();

$countPostsStmt = $conn->prepare("SELECT COUNT(*) FROM community_posts WHERE user_id = ? AND status = 'published'");
$countPostsStmt->execute([$profileUserId]);
$totalPosts = (int)$countPostsStmt->fetchColumn();

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 8;
$offset = ($page - 1) * $limit;
$listRows = [];
$listTotal = 0;
$postsHtml = '';

if ($tab === 'posts') {
    $where = "p.user_id = :profile_user_id AND p.status = 'published'";
    if ($viewerId <= 0 || !$isFollowing) {
        $where .= " AND p.privacy = 'public'";
    } elseif ($viewerId > 0 && $isFollowing) {
        $where .= " AND p.privacy IN ('public','followers')";
    }
    if ($isOwner) {
        $where = "p.user_id = :profile_user_id AND p.status IN ('published','draft')";
    }

    $countStmt = $conn->prepare("SELECT COUNT(*) FROM community_posts p WHERE {$where}");
    $countStmt->bindValue(':profile_user_id', $profileUserId, PDO::PARAM_INT);
    $countStmt->execute();
    $listTotal = (int)$countStmt->fetchColumn();

    $pinJoin = '';
    $pinSelect = '';
    $orderBy = 'p.created_at DESC';
    if ($viewerId > 0) {
        $pinJoin = ' LEFT JOIN community_user_pins cup ON cup.post_id = p.id AND cup.user_id = :viewer_pin_user_id ';
        $pinSelect = ', CASE WHEN cup.id IS NULL THEN 0 ELSE 1 END AS is_pinned';
        $orderBy = 'is_pinned DESC, p.created_at DESC';
    }

    $listStmt = $conn->prepare("SELECT p.*{$pinSelect}
        FROM community_posts p
        {$pinJoin}
        WHERE {$where}
        ORDER BY {$orderBy}
        LIMIT :limit OFFSET :offset");
    $listStmt->bindValue(':profile_user_id', $profileUserId, PDO::PARAM_INT);
    if ($viewerId > 0) {
        $listStmt->bindValue(':viewer_pin_user_id', $viewerId, PDO::PARAM_INT);
    }
    $listStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $listStmt->execute();
    $rows = $listStmt->fetchAll(PDO::FETCH_ASSOC);

    $maps = community_load_post_maps($conn, $rows, $viewerId);
    $postsHtml = community_render_feed_posts_html($rows, $maps, $viewerId, ['compact' => true]);
} elseif ($tab === 'followers') {
    $countStmt = $conn->prepare('SELECT COUNT(*) FROM community_user_follows WHERE following_user_id = ?');
    $countStmt->execute([$profileUserId]);
    $listTotal = (int)$countStmt->fetchColumn();

    $listStmt = $conn->prepare('SELECT u.id, u.name, u.avatar, f.created_at
        FROM community_user_follows f
        INNER JOIN users u ON u.id = f.follower_user_id
        WHERE f.following_user_id = ?
        ORDER BY f.created_at DESC
        LIMIT ? OFFSET ?');
    $listStmt->execute([$profileUserId, $limit, $offset]);
    $listRows = $listStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $countStmt = $conn->prepare('SELECT COUNT(*) FROM community_user_follows WHERE follower_user_id = ?');
    $countStmt->execute([$profileUserId]);
    $listTotal = (int)$countStmt->fetchColumn();

    $listStmt = $conn->prepare('SELECT u.id, u.name, u.avatar, f.created_at
        FROM community_user_follows f
        INNER JOIN users u ON u.id = f.following_user_id
        WHERE f.follower_user_id = ?
        ORDER BY f.created_at DESC
        LIMIT ? OFFSET ?');
    $listStmt->execute([$profileUserId, $limit, $offset]);
    $listRows = $listStmt->fetchAll(PDO::FETCH_ASSOC);
}

$totalPages = max(1, (int)ceil($listTotal / $limit));
if ($page > $totalPages) {
    $page = $totalPages;
}

$page_title = htmlspecialchars($profileName, ENT_QUOTES, 'UTF-8') . ' - Community Profile';
$page_description = 'Trang profile cong dong cua ' . $profileName . ' voi bai viet, followers va following.';
$page_canonical = $profileUrl . '?tab=' . urlencode($tab);

function community_profile_page_url($profileUrl, $tab, $targetPage)
{
    return $profileUrl . '?tab=' . urlencode((string)$tab) . '&page=' . max(1, (int)$targetPage);
}

include '../components/layout_header.php';
?>

<?php
include '../components/breadcrumb.php';
$breadcrumb_items = [
    ['title' => 'Trang chủ', 'url' => site_url('static/home.php')],
    ['title' => 'Cộng đồng', 'url' => site_url('static/community_feed.php')],
    ['title' => $profileName, 'url' => ''],
];
render_breadcrumb($breadcrumb_items);
?>

<main class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <div class="container-custom py-8">
        <section class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow p-6 mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white"><?= htmlspecialchars($profileName, ENT_QUOTES, 'UTF-8'); ?></h1>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">Community profile public</p>
                    <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                        <span class="px-3 py-1 rounded-full bg-gray-100 dark:bg-gray-700"><?php echo $totalPosts; ?> bài đăng</span>
                        <span class="px-3 py-1 rounded-full bg-gray-100 dark:bg-gray-700"><?php echo $totalFollowers; ?> người theo dõi</span>
                        <span class="px-3 py-1 rounded-full bg-gray-100 dark:bg-gray-700"><?php echo $totalFollowing; ?> người đang theo dõi</span>
                        <?php if ($followedByProfile && !$isOwner): ?>
                            <span class="px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200">Theo doi ban</span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($viewerId > 0 && !$isOwner): ?>
                    <?php
                    $followClass = $isFollowing
                        ? 'px-4 py-2 rounded-lg text-sm font-semibold bg-main text-white hover:bg-main/90'
                        : 'px-4 py-2 rounded-lg text-sm font-semibold bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-main/10 hover:text-main';
                    ?>
                    <button type="button" class="<?= $followClass; ?>" data-community-follow-btn data-target-user-id="<?= $profileUserId; ?>" data-following="<?= $isFollowing ? '1' : '0'; ?>">
                        <?= $isFollowing ? 'Đang theo dõi' : ($followedByProfile ? 'Theo dõi lại' : 'Theo dõi'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </section>

        <section id="community-profile-ajax-root">
            <section class="mb-5 flex flex-wrap items-center gap-2">
                <a data-community-profile-ajax="1" href="<?= htmlspecialchars($profileUrl . '?tab=posts', ENT_QUOTES, 'UTF-8'); ?>" class="px-3 py-2 rounded-lg text-sm font-semibold <?= $tab === 'posts' ? 'bg-main text-white' : 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200'; ?>">Bài đăng</a>
                <a data-community-profile-ajax="1" href="<?= htmlspecialchars($profileUrl . '?tab=followers', ENT_QUOTES, 'UTF-8'); ?>" class="px-3 py-2 rounded-lg text-sm font-semibold <?= $tab === 'followers' ? 'bg-main text-white' : 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200'; ?>">Người theo dõi</a>
                <a data-community-profile-ajax="1" href="<?= htmlspecialchars($profileUrl . '?tab=following', ENT_QUOTES, 'UTF-8'); ?>" class="px-3 py-2 rounded-lg text-sm font-semibold <?= $tab === 'following' ? 'bg-main text-white' : 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200'; ?>">Người đang theo dõi</a>
            </section>

            <?php if ($tab === 'posts'): ?>
                <section class="space-y-6 mx-auto w-full" style="max-width:850px;" data-community-feed-list>
                    <?php if ($postsHtml !== ''): ?>
                        <?= $postsHtml; ?>
                    <?php else: ?>
                        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-8 text-center text-gray-600 dark:text-gray-300">Chua co bai viet hien thi.</div>
                    <?php endif; ?>
                </section>
            <?php else: ?>
                <section class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-4 sm:p-5">
                    <div class="space-y-3">
                        <?php if (!empty($listRows)): ?>
                            <?php foreach ($listRows as $row): ?>
                                <?php $rowUrl = community_profile_path((int)$row['id'], (string)($row['name'] ?? 'User')); ?>
                                <a href="<?= htmlspecialchars($rowUrl, ENT_QUOTES, 'UTF-8'); ?>" class="flex items-center justify-between rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 hover:border-main transition-colors">
                                    <div class="flex items-center gap-3">
                                        <?php
                                        $avatarFallback = blog_user_avatar_src(null);
                                        $avatarSrc = blog_user_avatar_src((string)($row['avatar'] ?? ''), $avatarFallback);
                                        ?>
                                        <img
                                            src="<?= htmlspecialchars($avatarSrc, ENT_QUOTES, 'UTF-8'); ?>"
                                            alt="<?= htmlspecialchars((string)($row['name'] ?? 'User'), ENT_QUOTES, 'UTF-8'); ?>"
                                            class="h-10 w-10 rounded-full object-cover border border-gray-200 dark:border-gray-700"
                                            onerror="this.onerror=null;this.src='<?= htmlspecialchars($avatarFallback, ENT_QUOTES, 'UTF-8'); ?>';" />
                                        <div>
                                            <p class="text-sm font-semibold text-gray-900 dark:text-white"><?= htmlspecialchars((string)($row['name'] ?? 'User'), ENT_QUOTES, 'UTF-8'); ?></p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400"><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)($row['created_at'] ?? 'now'))), ENT_QUOTES, 'UTF-8'); ?></p>
                                        </div>
                                    </div>
                                    <span class="text-xs text-main">Xem profile</span>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center text-sm text-gray-600 dark:text-gray-300 py-6">Khong co du lieu trong tab nay.</div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($totalPages > 1): ?>
                <div class="mt-6 flex flex-wrap items-center justify-center gap-2">
                    <?php if ($page > 1): ?>
                        <a data-community-profile-ajax="1" href="<?= htmlspecialchars(community_profile_page_url($profileUrl, $tab, $page - 1), ENT_QUOTES, 'UTF-8'); ?>" class="px-3 py-2 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-sm">Trang truoc</a>
                    <?php endif; ?>
                    <span class="px-3 py-2 rounded-lg bg-main/10 text-main text-sm font-semibold">Trang <?= $page; ?>/<?= $totalPages; ?></span>
                    <?php if ($page < $totalPages): ?>
                        <a data-community-profile-ajax="1" href="<?= htmlspecialchars(community_profile_page_url($profileUrl, $tab, $page + 1), ENT_QUOTES, 'UTF-8'); ?>" class="px-3 py-2 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-sm">Trang sau</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>

<script src="<?= htmlspecialchars(site_url('js/community-feed-shared.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script>
    (function() {
        const shared = window.CommunityFeedShared ? window.CommunityFeedShared.create({}) : null;
        if (shared) {
            shared.bindDelegation(document);
            shared.initCarousels(document);
        }

        async function loadCommunityProfileFragment(url, pushState) {
            const root = document.getElementById('community-profile-ajax-root');
            if (!root) {
                window.location.href = url;
                return;
            }

            root.style.opacity = '0.45';
            try {
                const response = await fetch(url, {
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const html = await response.text();
                const parsed = new DOMParser().parseFromString(html, 'text/html');
                const nextRoot = parsed.getElementById('community-profile-ajax-root');
                if (!nextRoot) {
                    window.location.href = url;
                    return;
                }

                root.innerHTML = nextRoot.innerHTML;
                if (pushState) {
                    window.history.pushState({}, '', url);
                }

                if (shared) {
                    shared.initCarousels(root);
                }
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            } catch (error) {
                window.location.href = url;
            } finally {
                root.style.opacity = '';
            }
        }

        document.addEventListener('click', function(event) {
            const ajaxLink = event.target.closest('a[data-community-profile-ajax="1"]');
            if (!ajaxLink) {
                return;
            }

            const href = ajaxLink.getAttribute('href');
            if (!href) {
                return;
            }

            event.preventDefault();
            loadCommunityProfileFragment(ajaxLink.href, true);
        });

        window.addEventListener('popstate', function() {
            loadCommunityProfileFragment(window.location.href, false);
        });

        window.toggleCommunityComments = function(postId) {
            const panel = document.getElementById('community-comments-panel-' + String(postId || ''));
            if (panel) {
                panel.classList.toggle('hidden');
            }
        };

        window.shareCommunityPost = function(postId) {
            const url = window.location.origin + window.location.pathname + '#community-post-' + String(postId || '');
            navigator.clipboard.writeText(url).then(function() {
                if (typeof showNotification === 'function') {
                    showNotification('Da sao chep lien ket bai viet.', 'success');
                }
            }).catch(function() {});
        };

        window.communityReplyToComment = function(commentId) {
            const input = document.querySelector('[data-community-comment-input]');
            const parentInput = document.querySelector('[data-community-parent-comment-id]');
            const cancelBtn = document.querySelector('[data-community-cancel-reply]');
            if (!input || !parentInput || !cancelBtn) {
                return;
            }
            parentInput.value = String(commentId || 0);
            cancelBtn.classList.remove('hidden');
            input.focus();
        };

        window.cancelCommunityReply = function() {
            const parentInput = document.querySelector('[data-community-parent-comment-id]');
            const cancelBtn = document.querySelector('[data-community-cancel-reply]');
            if (parentInput) {
                parentInput.value = '0';
            }
            if (cancelBtn) {
                cancelBtn.classList.add('hidden');
            }
        };

        window.toggleCommunityReplies = function(commentId, triggerBtn) {
            const target = document.getElementById('community-replies-' + String(commentId || ''));
            if (!target) {
                return;
            }
            const hidden = target.classList.toggle('hidden');
            if (triggerBtn) {
                triggerBtn.textContent = hidden ?
                    String(triggerBtn.textContent || '').replace('Ẩn', 'Hiện') :
                    String(triggerBtn.textContent || '').replace('Hiện', 'Ẩn');
            }
        };

        window.communitySubmitComment = async function(postId) {
            const card = document.getElementById('community-post-' + String(postId || ''));
            if (!card) {
                return;
            }

            const input = card.querySelector('[data-community-comment-input]');
            const parentInput = card.querySelector('[data-community-parent-comment-id]');
            const honeypotInput = card.querySelector('[name="comment_hp"]');
            if (!input) {
                return;
            }

            const comment = String(input.value || '').trim();
            if (!comment) {
                if (typeof showNotification === 'function') {
                    showNotification('Vui long nhap noi dung binh luan.', 'warning');
                }
                return;
            }

            const endpoint = (window.BLOG_ENDPOINTS && window.BLOG_ENDPOINTS.communityCommentAdd) ?
                window.BLOG_ENDPOINTS.communityCommentAdd :
                'community_comment_add.php';

            const fd = new FormData();
            fd.set('post_id', String(postId));
            fd.set('comment', comment);
            fd.set('parent_comment_id', parentInput ? String(parentInput.value || '0') : '0');
            fd.set('comment_hp', honeypotInput ? String(honeypotInput.value || '') : '');

            try {
                const res = await fetch(endpoint, {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const payload = await res.json();
                if (!payload || payload.ok !== true) {
                    if (typeof showNotification === 'function') {
                        showNotification((payload && payload.message) || 'Khong the gui binh luan.', 'error');
                    }
                    return;
                }

                window.location.reload();
            } catch (err) {
                if (typeof showNotification === 'function') {
                    showNotification('Loi ket noi khi gui binh luan.', 'error');
                }
            }
        };
    })();
</script>

<?php include '../components/layout_footer.php'; ?>
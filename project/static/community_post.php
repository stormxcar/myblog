<?php
include '../components/connect.php';
include '../components/seo_helpers.php';
include '../components/community_engine.php';
include '../components/community_feed_renderer.php';

session_start();
community_ensure_tables($conn);

$userId = (int)($_SESSION['user_id'] ?? 0);
$slug = trim((string)($_GET['slug'] ?? ''));
$postId = community_extract_post_id_from_slug($slug);
if ($postId <= 0) {
    $postId = (int)($_GET['post_id'] ?? $_GET['id'] ?? 0);
}

if ($postId <= 0) {
    http_response_code(404);
    include '404.php';
    exit;
}

$postStmt = $conn->prepare('SELECT * FROM community_posts WHERE id = ? LIMIT 1');
$postStmt->execute([$postId]);
$post = $postStmt->fetch(PDO::FETCH_ASSOC);
if (!$post) {
    http_response_code(404);
    include '404.php';
    exit;
}

$authorId = (int)($post['user_id'] ?? 0);
$isOwner = $userId > 0 && $authorId === $userId;
$canView = false;
if ((string)$post['status'] === 'published') {
    $privacy = (string)($post['privacy'] ?? 'public');
    if ($privacy === 'public' || $isOwner) {
        $canView = true;
    } elseif ($privacy === 'followers' && $userId > 0) {
        $followStmt = $conn->prepare('SELECT 1 FROM community_user_follows WHERE follower_user_id = ? AND following_user_id = ? LIMIT 1');
        $followStmt->execute([$userId, $authorId]);
        $canView = (bool)$followStmt->fetchColumn();
    }
} elseif ((string)$post['status'] === 'draft' && $isOwner) {
    $canView = true;
}

if (!$canView) {
    http_response_code(403);
    include '404.php';
    exit;
}

$titleText = trim((string)($post['post_title'] ?? ''));
if ($titleText === '') {
    $titleText = community_extract_title((string)($post['content'] ?? ''));
}
$bodyText = community_extract_body((string)($post['content'] ?? ''));
$bodyText = trim(preg_replace('/\s+/u', ' ', strip_tags($bodyText)));
if ($bodyText === '') {
    $bodyText = $titleText;
}

$page_title = htmlspecialchars($titleText, ENT_QUOTES, 'UTF-8') . ' - Cộng đồng My Blog';
$page_description = mb_substr($bodyText, 0, 160, 'UTF-8');
$page_canonical = community_post_path($postId, $titleText);
$page_robots = ((string)$post['status'] === 'published' && (string)$post['privacy'] === 'public')
    ? 'index,follow,max-image-preview:large'
    : 'noindex,follow,max-image-preview:large';

$expectedSlug = community_post_slug($titleText, $postId);
$requestPath = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
$isCanonicalPath = strpos($requestPath, '/community/p/') !== false;
$isSameSlug = ($slug !== '' && $slug === $expectedSlug);

if (!$isCanonicalPath || !$isSameSlug || isset($_GET['post_id']) || isset($_GET['id'])) {
    $allowedQuery = $_GET;
    unset($allowedQuery['slug'], $allowedQuery['post_id'], $allowedQuery['id']);
    $redirectUrl = $page_canonical;
    if (!empty($allowedQuery)) {
        $redirectUrl .= '?' . http_build_query($allowedQuery);
    }
    header('Location: ' . $redirectUrl, true, 301);
    exit;
}

$authorName = trim((string)($post['user_name'] ?? 'Thanh vien'));
$createdAt = (string)($post['created_at'] ?? '');
$publishedIso = '';
if ($createdAt !== '') {
    $createdTs = strtotime($createdAt);
    if ($createdTs !== false) {
        $publishedIso = gmdate('c', $createdTs);
    }
}

$page_structured_data = [
    '@context' => 'https://schema.org',
    '@type' => 'DiscussionForumPosting',
    'mainEntityOfPage' => [
        '@type' => 'WebPage',
        '@id' => $page_canonical,
    ],
    'headline' => $titleText,
    'description' => $page_description,
    'url' => $page_canonical,
    'author' => [
        '@type' => 'Person',
        'name' => $authorName,
        'url' => community_profile_path($authorId, $authorName),
    ],
    'interactionStatistic' => [
        [
            '@type' => 'InteractionCounter',
            'interactionType' => 'https://schema.org/LikeAction',
            'userInteractionCount' => (int)($post['total_upvotes'] ?? 0),
        ],
        [
            '@type' => 'InteractionCounter',
            'interactionType' => 'https://schema.org/CommentAction',
            'userInteractionCount' => (int)($post['total_comments'] ?? 0),
        ],
    ],
];

if ($publishedIso !== '') {
    $page_structured_data['datePublished'] = $publishedIso;
    $page_structured_data['dateModified'] = $publishedIso;
}

$maps = community_load_post_maps($conn, [$post], $userId);
$postHtml = community_render_feed_posts_html([$post], $maps, $userId);

include '../components/layout_header.php';
?>

<?php
include '../components/breadcrumb.php';
$breadcrumb_items = [
    ['title' => 'Trang chủ', 'url' => site_url('static/home.php')],
    ['title' => 'Cộng đồng', 'url' => site_url('static/community_feed.php')],
    ['title' => $titleText, 'url' => ''],
];
render_breadcrumb($breadcrumb_items);
?>

<main class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <div class="container-custom py-8">
        <article class="mx-auto w-full" style="max-width:900px;">
            <?= $postHtml; ?>
        </article>
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

        window.toggleCommunityComments = function(postId) {
            const panel = document.getElementById('community-comments-panel-' + String(postId || ''));
            if (panel) {
                panel.classList.toggle('hidden');
            }
        };

        window.shareCommunityPost = function(postId) {
            const card = document.getElementById('community-post-' + String(postId || ''));
            const titleEl = card ? card.querySelector('h3') : null;
            const shareUrl = window.location.href;
            const shareTitle = titleEl ? String(titleEl.textContent || '').trim() : 'Bai dang cong dong';

            if (navigator.share) {
                navigator.share({
                    title: shareTitle,
                    url: shareUrl
                }).catch(function() {});
                return;
            }

            navigator.clipboard.writeText(shareUrl).then(function() {
                if (typeof showNotification === 'function') {
                    showNotification('Da sao chep lien ket bai viet.', 'success');
                }
            }).catch(function() {
                if (typeof showNotification === 'function') {
                    showNotification('Khong the sao chep lien ket.', 'error');
                }
            });
        };

        window.communityReplyToComment = function(commentId) {
            const commentInput = document.querySelector('[data-community-comment-input]');
            const parentInput = document.querySelector('[data-community-parent-comment-id]');
            const cancelBtn = document.querySelector('[data-community-cancel-reply]');
            if (!commentInput || !parentInput || !cancelBtn) {
                return;
            }
            parentInput.value = String(commentId || 0);
            cancelBtn.classList.remove('hidden');
            commentInput.focus();
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

            const fd = new FormData();
            fd.set('post_id', String(postId));
            fd.set('comment', comment);
            fd.set('parent_comment_id', parentInput ? String(parentInput.value || '0') : '0');
            fd.set('comment_hp', honeypotInput ? String(honeypotInput.value || '') : '');

            const endpoint = (window.BLOG_ENDPOINTS && window.BLOG_ENDPOINTS.communityCommentAdd) ?
                window.BLOG_ENDPOINTS.communityCommentAdd :
                'community_comment_add.php';

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
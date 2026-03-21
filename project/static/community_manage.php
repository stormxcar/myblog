<?php
include '../components/connect.php';
include '../components/seo_helpers.php';
include '../components/community_engine.php';

session_start();
community_ensure_tables($conn);

$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
    $_SESSION['flash_message'] = 'Vui lòng đăng nhập để quản lý bài cộng đồng.';
    $_SESSION['flash_type'] = 'warning';
    header('Location: login.php');
    exit;
}

$page_title = 'Quản lý bài cộng đồng - My Blog';
$page_description = 'Tìm kiếm, lọc, sắp xếp và chỉnh sửa bài cộng đồng của bạn.';
$page_canonical = site_url('static/community_manage.php');

$q = trim((string)($_GET['q'] ?? ''));
$privacy = trim((string)($_GET['privacy'] ?? 'all'));
$status = trim((string)($_GET['status'] ?? 'all'));
$sort = trim((string)($_GET['sort'] ?? 'newest'));
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 8;
$offset = ($page - 1) * $limit;

$where = ['user_id = :user_id'];
$params = [':user_id' => $user_id];

if ($q !== '') {
    $where[] = '(post_title LIKE :q OR content LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}

if (in_array($privacy, ['public', 'followers', 'private'], true)) {
    $where[] = 'privacy = :privacy';
    $params[':privacy'] = $privacy;
}

if (in_array($status, ['published', 'draft', 'hidden', 'deleted'], true)) {
    $where[] = 'status = :status';
    $params[':status'] = $status;
}

$orderBy = 'created_at DESC';
if ($sort === 'oldest') {
    $orderBy = 'created_at ASC';
} elseif ($sort === 'comments') {
    $orderBy = 'total_comments DESC, created_at DESC';
} elseif ($sort === 'score') {
    $orderBy = 'vote_score DESC, created_at DESC';
}

$whereSql = implode(' AND ', $where);

$countSql = 'SELECT COUNT(*) FROM community_posts WHERE ' . $whereSql;
$countStmt = $conn->prepare($countSql);
foreach ($params as $k => $v) {
    $countStmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$countStmt->execute();
$totalItems = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalItems / $limit));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $limit;
}

$listSql = 'SELECT * FROM community_posts WHERE ' . $whereSql . ' ORDER BY ' . $orderBy . ' LIMIT :limit OFFSET :offset';
$listStmt = $conn->prepare($listSql);
foreach ($params as $k => $v) {
    $listStmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$listStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStmt->execute();
$posts = $listStmt->fetchAll(PDO::FETCH_ASSOC);

$postIds = array_map(function ($row) {
    return (int)$row['id'];
}, $posts);

$linksByPost = [];
if (!empty($postIds)) {
    $placeholders = implode(',', array_fill(0, count($postIds), '?'));
    $linkStmt = $conn->prepare("SELECT post_id, url FROM community_post_links WHERE post_id IN ({$placeholders}) ORDER BY id ASC");
    $linkStmt->execute($postIds);
    foreach ($linkStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $pid = (int)$row['post_id'];
        if (!isset($linksByPost[$pid])) {
            $linksByPost[$pid] = [];
        }
        $linksByPost[$pid][] = (string)$row['url'];
    }
}

function manage_query(array $overrides = [])
{
    $base = $_GET;
    foreach ($overrides as $k => $v) {
        $base[$k] = $v;
    }
    return http_build_query($base);
}
?>

<?php include '../components/layout_header.php'; ?>

<?php
include '../components/breadcrumb.php';
$breadcrumb_items = auto_breadcrumb('Quản lý bài cộng đồng');
render_breadcrumb($breadcrumb_items);
?>

<main class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <div class="container-custom py-8">
        <section class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-5 border border-gray-200 dark:border-gray-700 mb-5">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Quản lý bài cộng đồng</h1>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">Tìm nhanh bài cần chỉnh sửa, không cần mở toàn bộ danh sách cùng lúc.</p>
                </div>
                <a href="community_create.php" class="btn-primary text-sm"><i class="fas fa-plus mr-2"></i>Tạo bài mới</a>
            </div>

            <form method="get" class="mt-4 grid grid-cols-1 md:grid-cols-5 gap-3">
                <input type="text" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>" class="form-input md:col-span-2" placeholder="Tìm theo tiêu đề hoặc nội dung...">
                <select name="privacy" class="form-input">
                    <option value="all" <?= $privacy === 'all' ? 'selected' : ''; ?>>Mọi phạm vi</option>
                    <option value="public" <?= $privacy === 'public' ? 'selected' : ''; ?>>Công khai</option>
                    <option value="followers" <?= $privacy === 'followers' ? 'selected' : ''; ?>>Follower</option>
                    <option value="private" <?= $privacy === 'private' ? 'selected' : ''; ?>>Bản nháp riêng tư</option>
                </select>
                <select name="sort" class="form-input">
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : ''; ?>>Mới nhất</option>
                    <option value="oldest" <?= $sort === 'oldest' ? 'selected' : ''; ?>>Cũ nhất</option>
                    <option value="comments" <?= $sort === 'comments' ? 'selected' : ''; ?>>Nhiều bình luận</option>
                    <option value="score" <?= $sort === 'score' ? 'selected' : ''; ?>>Điểm vote cao</option>
                </select>
                <button type="submit" class="btn-secondary text-sm">Áp dụng</button>
            </form>
        </section>

        <?php if (!empty($posts)): ?>
            <section class="space-y-3">
                <?php foreach ($posts as $post): ?>
                    <?php
                    $postId = (int)$post['id'];
                    $privacyValue = (string)$post['privacy'];
                    $linkLines = isset($linksByPost[$postId]) ? implode("\n", $linksByPost[$postId]) : '';
                    $title = (string)($post['post_title'] ?: community_extract_title((string)$post['content']));
                    $excerpt = trim((string)community_extract_body((string)$post['content']));
                    $excerpt = mb_substr($excerpt, 0, 170, 'UTF-8');
                    ?>
                    <article class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white break-words"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h2>
                                <?php if ($excerpt !== ''): ?>
                                    <p class="text-sm text-gray-600 dark:text-gray-300 mt-1 line-clamp-2"><?= htmlspecialchars($excerpt, ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php endif; ?>
                                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                    <span><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$post['created_at'])), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span>•</span>
                                    <span><?= htmlspecialchars(community_visibility_badge((string)$post['privacy'], (string)$post['status']), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span>•</span>
                                    <span><?= (int)($post['total_comments'] ?? 0); ?> bình luận</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <button type="button" class="px-3 py-1.5 rounded-lg bg-gray-100 dark:bg-gray-700 text-sm" data-manage-toggle="<?= $postId; ?>">Chỉnh sửa</button>
                                <button type="button" class="px-3 py-1.5 rounded-lg bg-red-100 text-red-600 text-sm hover:bg-red-200" onclick="deleteCommunityPost(<?= $postId; ?>)">Xóa</button>
                            </div>
                        </div>

                        <form class="mt-3 space-y-3 hidden" data-manage-editor="<?= $postId; ?>" data-community-manage-form data-post-id="<?= $postId; ?>">
                            <input type="text" name="title" maxlength="300" class="form-input" required value="<?= htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8'); ?>">
                            <textarea name="content" rows="4" maxlength="5000" class="form-textarea" required><?= htmlspecialchars(html_entity_decode((string)$post['content'], ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?></textarea>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <select name="privacy" class="form-input">
                                    <option value="public" <?= $privacyValue === 'public' ? 'selected' : ''; ?>>Công khai</option>
                                    <option value="followers" <?= $privacyValue === 'followers' ? 'selected' : ''; ?>>Chỉ người theo dõi</option>
                                    <option value="private" <?= $privacyValue === 'private' ? 'selected' : ''; ?>>Chỉ mình tôi (Bản nháp)</option>
                                </select>
                                <textarea name="links" rows="3" class="form-textarea" placeholder="Mỗi dòng một liên kết"><?= htmlspecialchars($linkLines, ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>
                            <button type="submit" class="btn-primary text-sm"><i class="fas fa-save mr-2"></i>Lưu thay đổi</button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </section>

            <?php if ($totalPages > 1): ?>
                <div class="mt-6 flex items-center justify-center gap-2 flex-wrap">
                    <?php if ($page > 1): ?>
                        <a href="?<?= htmlspecialchars(manage_query(['page' => $page - 1]), ENT_QUOTES, 'UTF-8'); ?>" class="px-3 py-2 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-sm">Trang trước</a>
                    <?php endif; ?>
                    <span class="px-3 py-2 rounded-lg bg-main/10 text-main text-sm font-semibold">Trang <?= $page; ?>/<?= $totalPages; ?></span>
                    <?php if ($page < $totalPages): ?>
                        <a href="?<?= htmlspecialchars(manage_query(['page' => $page + 1]), ENT_QUOTES, 'UTF-8'); ?>" class="px-3 py-2 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-sm">Trang sau</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <section class="bg-white dark:bg-gray-800 rounded-2xl shadow-md border border-gray-200 dark:border-gray-700 p-10 text-center">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Không có bài phù hợp bộ lọc</h2>
                <p class="text-gray-600 dark:text-gray-300 mt-2">Thử thay đổi từ khóa hoặc bộ lọc để tìm bài cần chỉnh sửa.</p>
                <a href="community_manage.php" class="btn-secondary mt-4 inline-flex">Xóa bộ lọc</a>
            </section>
        <?php endif; ?>
    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[data-manage-toggle]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const postId = String(btn.getAttribute('data-manage-toggle') || '');
                const editor = document.querySelector('[data-manage-editor="' + postId + '"]');
                if (!editor) {
                    return;
                }
                editor.classList.toggle('hidden');
                btn.textContent = editor.classList.contains('hidden') ? 'Chỉnh sửa' : 'Đóng';
            });
        });

        const forms = document.querySelectorAll('[data-community-manage-form]');
        forms.forEach(function(form) {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                const postId = Number(form.getAttribute('data-post-id') || '0');
                if (!postId) {
                    showNotification('Không xác định được bài viết.', 'error');
                    return;
                }

                const submitBtn = form.querySelector('button[type="submit"]');
                const original = submitBtn ? submitBtn.innerHTML : '';
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Đang lưu...';
                }

                try {
                    const fd = new FormData(form);
                    fd.set('action', 'update');
                    fd.set('post_id', String(postId));

                    const endpoint = (window.BLOG_ENDPOINTS && window.BLOG_ENDPOINTS.communityManage) ?
                        window.BLOG_ENDPOINTS.communityManage :
                        'community_post_manage_api.php';

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
                        showNotification((payload && payload.message) || 'Không thể cập nhật bài viết.', 'error');
                        return;
                    }
                    showNotification(payload.message || 'Đã cập nhật bài viết.', 'success');
                } catch (err) {
                    showNotification('Lỗi kết nối khi cập nhật bài viết.', 'error');
                } finally {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = original;
                    }
                }
            });
        });
    });

    async function deleteCommunityPost(postId) {
        if (!confirm('Bạn chắc chắn muốn xóa bài viết này?')) {
            return;
        }

        try {
            const endpoint = (window.BLOG_ENDPOINTS && window.BLOG_ENDPOINTS.communityManage) ?
                window.BLOG_ENDPOINTS.communityManage :
                'community_post_manage_api.php';

            const fd = new FormData();
            fd.set('action', 'delete');
            fd.set('post_id', String(postId));

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
                showNotification((payload && payload.message) || 'Không thể xóa bài viết.', 'error');
                return;
            }
            showNotification(payload.message || 'Đã xóa bài viết.', 'success');
            setTimeout(function() {
                window.location.reload();
            }, 350);
        } catch (err) {
            showNotification('Lỗi kết nối khi xóa bài viết.', 'error');
        }
    }
</script>

<?php include '../components/layout_footer.php'; ?>
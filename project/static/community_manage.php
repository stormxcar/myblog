<?php
include '../components/connect.php';
include '../components/seo_helpers.php';
include '../components/community_engine.php';

session_start();
community_ensure_tables($conn);

$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
    $_SESSION['flash_message'] = 'Vui long dang nhap de quan ly bai cong dong.';
    $_SESSION['flash_type'] = 'warning';
    header('Location: login.php');
    exit;
}

$page_title = 'Quan ly bai cong dong - My Blog';
$page_description = 'Quan ly bai dang cong dong do ban tao.';
$page_canonical = site_url('static/community_manage.php');

$postStmt = $conn->prepare('SELECT * FROM community_posts WHERE user_id = ? ORDER BY created_at DESC');
$postStmt->execute([$user_id]);
$posts = $postStmt->fetchAll(PDO::FETCH_ASSOC);

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
?>

<?php include '../components/layout_header.php'; ?>

<?php
include '../components/breadcrumb.php';
$breadcrumb_items = auto_breadcrumb('Quan ly bai cong dong');
render_breadcrumb($breadcrumb_items);
?>

<main class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <div class="container-custom py-8">
        <section class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-6 border border-gray-200 dark:border-gray-700 mb-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Quan ly bai cong dong</h1>
                    <p class="text-gray-600 dark:text-gray-300 mt-1">Cap nhat hoac xoa bai viet ban da dang.</p>
                </div>
                <a href="community_create.php" class="btn-primary"><i class="fas fa-plus mr-2"></i>Tao bai moi</a>
            </div>
        </section>

        <?php if (!empty($posts)): ?>
            <section class="space-y-5">
                <?php foreach ($posts as $post):
                    $postId = (int)$post['id'];
                    $privacy = (string)$post['privacy'];
                    $linkLines = isset($linksByPost[$postId]) ? implode("\n", $linksByPost[$postId]) : '';
                ?>
                    <article class="bg-white dark:bg-gray-800 rounded-2xl shadow-md border border-gray-200 dark:border-gray-700 p-5">
                        <div class="flex items-center justify-between gap-3 mb-3">
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400"><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$post['created_at'])), ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="text-sm font-semibold text-main"><?= htmlspecialchars(community_visibility_badge((string)$post['privacy'], (string)$post['status']), ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                            <button type="button" class="px-3 py-1.5 rounded-lg bg-red-100 text-red-600 text-sm hover:bg-red-200" onclick="deleteCommunityPost(<?= $postId; ?>)">
                                <i class="fas fa-trash mr-1"></i>Xoa
                            </button>
                        </div>

                        <form class="space-y-3" data-community-manage-form data-post-id="<?= $postId; ?>">
                            <textarea name="content" rows="4" maxlength="5000" class="form-textarea" required><?= htmlspecialchars(html_entity_decode((string)$post['content'], ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?></textarea>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <select name="privacy" class="form-input">
                                    <option value="public" <?= $privacy === 'public' ? 'selected' : ''; ?>>Cong khai</option>
                                    <option value="followers" <?= $privacy === 'followers' ? 'selected' : ''; ?>>Chi nguoi theo doi</option>
                                    <option value="private" <?= $privacy === 'private' ? 'selected' : ''; ?>>Chi minh toi (Ban nhap)</option>
                                </select>
                                <textarea name="links" rows="3" class="form-textarea" placeholder="Moi dong mot link"><?= htmlspecialchars($linkLines, ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>
                            <button type="submit" class="btn-primary"><i class="fas fa-save mr-2"></i>Luu thay doi</button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php else: ?>
            <section class="bg-white dark:bg-gray-800 rounded-2xl shadow-md border border-gray-200 dark:border-gray-700 p-10 text-center">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Ban chua co bai cong dong nao</h2>
                <p class="text-gray-600 dark:text-gray-300 mt-2">Hay tao bai dau tien de bat dau.</p>
                <a href="community_create.php" class="btn-primary mt-4 inline-flex">Tao bai cong dong</a>
            </section>
        <?php endif; ?>
    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const forms = document.querySelectorAll('[data-community-manage-form]');

        forms.forEach(function(form) {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                const postId = Number(form.getAttribute('data-post-id') || '0');
                if (!postId) {
                    showNotification('Khong xac dinh duoc bai viet.', 'error');
                    return;
                }

                const submitBtn = form.querySelector('button[type="submit"]');
                const original = submitBtn ? submitBtn.innerHTML : '';
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Dang luu...';
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
                        showNotification((payload && payload.message) || 'Khong the cap nhat bai viet.', 'error');
                        return;
                    }
                    showNotification(payload.message || 'Da cap nhat bai viet.', 'success');
                } catch (err) {
                    showNotification('Loi ket noi khi cap nhat bai viet.', 'error');
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
        if (!confirm('Ban chac chan muon xoa bai viet nay?')) {
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
                showNotification((payload && payload.message) || 'Khong the xoa bai viet.', 'error');
                return;
            }
            showNotification(payload.message || 'Da xoa bai viet.', 'success');
            setTimeout(function() {
                window.location.reload();
            }, 400);
        } catch (err) {
            showNotification('Loi ket noi khi xoa bai viet.', 'error');
        }
    }
</script>

<?php include '../components/layout_footer.php'; ?>
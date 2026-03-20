<?php
include '../components/connect.php';
include '../components/seo_helpers.php';

session_start();
$message = [];

if (!isset($_SERVER['HTTP_REFERER'])) {
    header('location: home.php');
    exit;
}

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} else {
    $user_id = '';
    header('location: home.php');
    exit;
}

// Edit comment
if (isset($_POST['edit_comment'])) {
    $edit_comment_id = (int)($_POST['edit_comment_id'] ?? 0);
    $comment_edit_box = trim((string)($_POST['comment_edit_box'] ?? ''));

    if (empty(trim($comment_edit_box))) {
        $_SESSION['error_message'] = 'Bình luận không được để trống!';
    } else {
        $verify_comment = $conn->prepare("SELECT * FROM `comments` WHERE comment = ? AND id = ? AND user_id = ?");
        $verify_comment->execute([$comment_edit_box, $edit_comment_id, $user_id]);

        if ($verify_comment->rowCount() > 0) {
            $_SESSION['info_message'] = 'Bình luận này giống với nội dung hiện tại!';
        } else {
            $update_comment = $conn->prepare("UPDATE `comments` SET comment = ? WHERE id = ? AND user_id = ?");
            $update_comment->execute([$comment_edit_box, $edit_comment_id, $user_id]);
            $_SESSION['success_message'] = 'Bình luận của bạn đã được cập nhật!';
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Delete comment
if (isset($_POST['delete_comment'])) {
    $delete_comment_id = (int)($_POST['comment_id'] ?? 0);

    $verify_owner = $conn->prepare("SELECT * FROM `comments` WHERE id = ? AND user_id = ?");
    $verify_owner->execute([$delete_comment_id, $user_id]);

    if ($verify_owner->rowCount() > 0) {
        $delete_comment = $conn->prepare("DELETE FROM `comments` WHERE id = ? AND user_id = ?");
        $delete_comment->execute([$delete_comment_id, $user_id]);
        $_SESSION['success_message'] = 'Xóa bình luận thành công!';
    } else {
        $_SESSION['error_message'] = 'Bạn không có quyền xóa bình luận này!';
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Pagination
$items_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page <= 0) {
    $current_page = 1;
}

// Count total comments
$count_comments = $conn->prepare("SELECT COUNT(*) FROM `comments` WHERE user_id = ?");
$count_comments->execute([$user_id]);
$total_comments = $count_comments->fetchColumn();

$total_pages = ceil($total_comments / $items_per_page);
$offset = ($current_page - 1) * $items_per_page;

// Get user comments with post information
$select_comments = $conn->prepare("
    SELECT c.*, p.title as post_title, p.id as post_id 
    FROM `comments` c 
    JOIN `posts` p ON c.post_id = p.id 
    WHERE c.user_id = ? 
    ORDER BY c.date DESC 
    LIMIT $items_per_page OFFSET $offset
");
$select_comments->execute([$user_id]);

// Get user info
$select_user = $conn->prepare("SELECT name, email FROM `users` WHERE id = ?");
$select_user->execute([$user_id]);
$user_info = $select_user->fetch(PDO::FETCH_ASSOC);

// Check if editing
$editing_comment_id = isset($_POST['open_edit_box']) ? $_POST['comment_id'] : null;
?>

<?php include '../components/layout_header.php'; ?>

<?php
// Include breadcrumb component
include '../components/breadcrumb.php';

// Generate breadcrumb for user comments page
$breadcrumb_items = auto_breadcrumb('Bình luận của tôi');
render_breadcrumb($breadcrumb_items);
?>

<main class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <div class="container-custom py-8">
        <!-- Header Section -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full mb-4">
                <i class="fas fa-comments text-white text-2xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">Bình luận của tôi</h1>
            <p class="text-gray-600 dark:text-gray-400">
                Quản lý tất cả bình luận mà bạn đã đăng
            </p>
        </div>

        <!-- Alert Messages -->
        <?php if (isset($_SESSION['success_message'])) : ?>
            <div id="successMessage" class="mb-6 p-4 rounded-lg border bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800 text-green-800 dark:text-green-200">
                <div class="flex items-start space-x-3">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-green-500"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium"><?= $_SESSION['success_message'] ?></p>
                    </div>
                    <button onclick="this.parentElement.parentElement.style.display='none'" class="flex-shrink-0 text-green-400 hover:text-green-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])) : ?>
            <div id="errorMessage" class="mb-6 p-4 rounded-lg border bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800 text-red-800 dark:text-red-200">
                <div class="flex items-start space-x-3">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-red-500"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium"><?= $_SESSION['error_message'] ?></p>
                    </div>
                    <button onclick="this.parentElement.parentElement.style.display='none'" class="flex-shrink-0 text-red-400 hover:text-red-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['info_message'])) : ?>
            <div id="infoMessage" class="mb-6 p-4 rounded-lg border bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800 text-blue-800 dark:text-blue-200">
                <div class="flex items-start space-x-3">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-blue-500"></i>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium"><?= $_SESSION['info_message'] ?></p>
                    </div>
                    <button onclick="this.parentElement.parentElement.style.display='none'" class="flex-shrink-0 text-blue-400 hover:text-blue-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <?php unset($_SESSION['info_message']); ?>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8 profile-shell">
            <!-- Sidebar -->
            <div class="lg:col-span-1 order-2 lg:order-1 profile-sidebar">
                <!-- User Info -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-200 dark:border-gray-700 p-6 mb-6 profile-card">
                    <div class="text-center">
                        <div class="w-16 h-16 bg-main rounded-full flex items-center justify-center text-white text-xl font-bold mx-auto mb-4">
                            <?= strtoupper(substr($user_info['name'], 0, 1)) ?>
                        </div>
                        <h3 class="font-bold text-gray-900 dark:text-white mb-1"><?= htmlspecialchars($user_info['name']) ?></h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400"><?= htmlspecialchars($user_info['email']) ?></p>
                    </div>
                </div>

                <!-- Stats -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-200 dark:border-gray-700 p-6 mb-6 profile-card">
                    <h3 class="font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                        <i class="fas fa-chart-bar text-main mr-2"></i>
                        Thống kê
                    </h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Tổng bình luận</span>
                            <span class="font-bold text-gray-900 dark:text-white"><?= $total_comments ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Trang hiện tại</span>
                            <span class="font-bold text-main"><?= $current_page ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Tổng trang</span>
                            <span class="font-bold text-gray-900 dark:text-white"><?= $total_pages ?></span>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-200 dark:border-gray-700 p-6 profile-card">
                    <h3 class="font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                        <i class="fas fa-bolt text-main mr-2"></i>
                        Truy cập nhanh
                    </h3>
                    <div class="space-y-2">
                        <a href="user_likes.php" class="flex items-center space-x-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-main/10 transition-colors group">
                            <i class="fas fa-heart text-red-500 group-hover:text-red-600"></i>
                            <span class="text-sm text-gray-700 dark:text-gray-300 group-hover:text-main">Bài viết đã thích</span>
                        </a>
                        <a href="update.php" class="flex items-center space-x-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-main/10 transition-colors group">
                            <i class="fas fa-user-edit text-main group-hover:text-blue-600"></i>
                            <span class="text-sm text-gray-700 dark:text-gray-300 group-hover:text-main">Cập nhật hồ sơ</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="lg:col-span-3 order-1 lg:order-2 profile-main">
                <?php if ($select_comments->rowCount() > 0) : ?>
                    <!-- Comments List -->
                    <div class="space-y-6">
                        <?php while ($fetch_comment = $select_comments->fetch(PDO::FETCH_ASSOC)) {
                            $comment_id = $fetch_comment['id'];
                            $is_editing = ($editing_comment_id == $comment_id);
                        ?>
                            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden comment-card" data-comment-id="<?= $comment_id ?>">
                                <!-- Comment Header -->
                                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700">
                                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-10 h-10 bg-main rounded-full flex items-center justify-center text-white text-sm font-bold">
                                                <?= strtoupper(substr($user_info['name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="font-semibold text-gray-900 dark:text-white"><?= htmlspecialchars($user_info['name']) ?></div>
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    <?= date('d/m/Y H:i', strtotime($fetch_comment['date'])) ?>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Actions -->
                                        <div class="flex items-center space-x-2 self-end sm:self-auto">
                                            <?php if (!$is_editing) : ?>
                                                <form method="post" class="inline">
                                                    <input type="hidden" name="comment_id" value="<?= $comment_id ?>">
                                                    <button type="submit" name="open_edit_box" class="p-2 text-gray-400 hover:text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-colors">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </form>

                                                <button onclick="confirmDelete(<?= $comment_id ?>)" class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Post Reference -->
                                <div class="px-6 py-3 bg-blue-50 dark:bg-blue-900/20 border-b border-gray-200 dark:border-gray-600">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                                        <i class="fas fa-newspaper text-blue-500"></i>
                                        <span class="text-gray-600 dark:text-gray-400">Bình luận trên bài viết:</span>
                                        <a href="<?= post_path($fetch_comment['post_id']); ?>"
                                            class="font-medium text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors">
                                            "<?= htmlspecialchars($fetch_comment['post_title']) ?>"
                                        </a>
                                    </div>
                                </div>

                                <!-- Comment Content -->
                                <div class="px-6 py-4">
                                    <?php if ($is_editing) : ?>
                                        <!-- Edit Form -->
                                        <form method="post" class="space-y-4" id="editForm<?= $comment_id ?>">
                                            <input type="hidden" name="edit_comment_id" value="<?= $comment_id ?>">
                                            <div>
                                                <label for="comment_edit_box<?= $comment_id ?>" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                    Chỉnh sửa bình luận
                                                </label>
                                                <textarea id="comment_edit_box<?= $comment_id ?>"
                                                    name="comment_edit_box"
                                                    rows="4"
                                                    required
                                                    placeholder="Nhập nội dung bình luận..."
                                                    class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-main focus:border-transparent dark:bg-gray-700 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 transition-all duration-300 resize-none"><?= htmlspecialchars($fetch_comment['comment']) ?></textarea>
                                                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                    Tối thiểu 10 ký tự, tối đa 1000 ký tự
                                                </div>
                                            </div>

                                            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                                                <button type="submit"
                                                    name="edit_comment"
                                                    class="btn-primary text-sm px-4 py-2">
                                                    <i class="fas fa-save mr-2"></i>
                                                    Lưu thay đổi
                                                </button>
                                                <a href="<?= $_SERVER['PHP_SELF'] ?>"
                                                    class="btn-secondary text-sm px-4 py-2">
                                                    <i class="fas fa-times mr-2"></i>
                                                    Hủy
                                                </a>
                                            </div>
                                        </form>
                                    <?php else : ?>
                                        <!-- Display Comment -->
                                        <div class="prose prose-gray dark:prose-invert max-w-none">
                                            <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                                                <?= nl2br(htmlspecialchars($fetch_comment['comment'])) ?>
                                            </p>
                                        </div>

                                        <!-- Comment Footer -->
                                        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                                            <div class="flex items-center justify-between">
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    <?= strlen($fetch_comment['comment']) ?> ký tự
                                                </div>
                                                <a href="<?= post_path($fetch_comment['post_id']); ?>"
                                                    class="inline-flex items-center text-sm text-main hover:text-blue-700 transition-colors">
                                                    Xem bài viết
                                                    <i class="fas fa-external-link-alt ml-1"></i>
                                                </a>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php } ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1) : ?>
                        <div class="mt-8 flex items-center justify-center space-x-4">
                            <?php if ($current_page > 1) : ?>
                                <a href="?page=<?= $current_page - 1 ?>"
                                    class="flex items-center justify-center w-10 h-10 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-main hover:text-white hover:border-main transition-all duration-300 shadow-lg hover:shadow-xl">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>

                            <div class="flex items-center space-x-2">
                                <?php
                                $start = max(1, $current_page - 2);
                                $end = min($total_pages, $current_page + 2);

                                for ($i = $start; $i <= $end; $i++) : ?>
                                    <?php if ($i == $current_page) : ?>
                                        <a href="?page=<?= $i ?>"
                                            class="flex items-center justify-center w-10 h-10 rounded-lg transition-all duration-300 shadow-lg hover:shadow-xl bg-main text-white border border-main">
                                            <?= $i ?>
                                        </a>
                                    <?php else : ?>
                                        <a href="?page=<?= $i ?>"
                                            class="flex items-center justify-center w-10 h-10 rounded-lg transition-all duration-300 shadow-lg hover:shadow-xl bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-main hover:text-white hover:border-main">
                                            <?= $i ?>
                                        </a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </div>

                            <?php if ($current_page < $total_pages) : ?>
                                <a href="?page=<?= $current_page + 1 ?>"
                                    class="flex items-center justify-center w-10 h-10 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-main hover:text-white hover:border-main transition-all duration-300 shadow-lg hover:shadow-xl">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>

                        <!-- Page Info -->
                        <div class="text-center mt-4">
                            <span class="text-gray-600 dark:text-gray-400">
                                Trang <?= $current_page ?> trong tổng số <?= $total_pages ?> trang
                                (<?= $total_comments ?> bình luận)
                            </span>
                        </div>
                    <?php endif; ?>

                <?php else : ?>
                    <!-- Empty State -->
                    <div class="text-center py-16">
                        <div class="bg-white dark:bg-gray-800 rounded-2xl p-12 shadow-lg border border-gray-200 dark:border-gray-700">
                            <div class="w-20 h-20 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-6">
                                <i class="fas fa-comment-slash text-4xl text-gray-400"></i>
                            </div>
                            <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">Chưa có bình luận</h3>
                            <p class="text-gray-600 dark:text-gray-400 mb-6">
                                Bạn chưa đăng bình luận nào. Hãy tham gia thảo luận trên các bài viết!
                            </p>

                            <div class="space-y-3">
                                <a href="posts.php" class="block w-full btn-primary max-w-sm mx-auto">
                                    <i class="fas fa-list mr-2"></i>
                                    Xem bài viết
                                </a>
                                <a href="home.php" class="block w-full btn-secondary max-w-sm mx-auto">
                                    <i class="fas fa-home mr-2"></i>
                                    Về trang chủ
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" onclick="closeDeleteModal()"></div>

        <!-- Modal Content -->
        <div class="relative inline-block w-full max-w-md p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white dark:bg-gray-800 shadow-xl rounded-2xl">
            <div class="text-center">
                <div class="w-16 h-16 bg-red-100 dark:bg-red-900/20 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-trash text-2xl text-red-500"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Xác nhận xóa</h3>
                <p class="text-gray-600 dark:text-gray-400 mb-6">
                    Bạn có chắc chắn muốn xóa bình luận này? Hành động này không thể hoàn tác.
                </p>

                <div class="flex space-x-3">
                    <button onclick="closeDeleteModal()" class="flex-1 btn-secondary">
                        <i class="fas fa-times mr-2"></i>
                        Hủy
                    </button>
                    <form id="deleteForm" method="post" class="flex-1">
                        <input type="hidden" name="comment_id" id="deleteCommentId">
                        <button type="submit" name="delete_comment" class="w-full btn-danger">
                            <i class="fas fa-trash mr-2"></i>
                            Xóa
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Enhanced JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-hide alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('#successMessage, #errorMessage, #infoMessage');
            alerts.forEach(alert => {
                if (alert) {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(() => alert.remove(), 300);
                }
            });
        }, 5000);

        // Comment card animations
        const commentCards = document.querySelectorAll('.comment-card');
        commentCards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';

            setTimeout(() => {
                card.style.transition = 'all 0.6s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });

        // Form validation for editing
        const editForms = document.querySelectorAll('[id^="editForm"]');
        editForms.forEach(form => {
            const textarea = form.querySelector('textarea');

            textarea.addEventListener('input', function() {
                const length = this.value.length;
                const isValid = length >= 10 && length <= 1000;

                if (!isValid && length > 0) {
                    this.classList.add('border-red-500');
                    this.classList.remove('border-green-500');
                } else if (isValid) {
                    this.classList.add('border-green-500');
                    this.classList.remove('border-red-500');
                } else {
                    this.classList.remove('border-red-500', 'border-green-500');
                }
            });

            form.addEventListener('submit', function(e) {
                const comment = textarea.value.trim();
                if (comment.length < 10 || comment.length > 1000) {
                    e.preventDefault();
                    textarea.focus();

                    // Shake animation
                    textarea.style.animation = 'shake 0.5s ease-in-out';
                    setTimeout(() => {
                        textarea.style.animation = '';
                    }, 500);
                }
            });
        });
    });

    // Delete modal functions
    function confirmDelete(commentId) {
        document.getElementById('deleteCommentId').value = commentId;
        document.getElementById('deleteModal').classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDeleteModal();
        }
    });

    // Add shake animation
    const style = document.createElement('style');
    style.textContent = `
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }
`;
    document.head.appendChild(style);
</script>

<?php include '../components/layout_footer.php'; ?>
<?php
include '../components/connect.php';
include '../components/seo_helpers.php';
include '../components/admin_pagination.php';
session_start();

$admin_id = $_SESSION['admin_id'] ?? null;
if (!$admin_id) {
    header('location:admin_login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bulkAction = $_POST['bulk_action'] ?? '';
    $selected = $_POST['selected_comment_ids'] ?? [];
    $selected = is_array($selected) ? array_map('intval', $selected) : [];
    $selected = array_values(array_filter(array_unique($selected), function ($v) {
        return $v > 0;
    }));

    if ($bulkAction === 'delete' && !empty($selected)) {
        $inSql = implode(',', array_fill(0, count($selected), '?'));
        $sql = "DELETE FROM comments WHERE id IN ({$inSql})";
        $stmt = $conn->prepare($sql);
        $stmt->execute($selected);
        $message[] = 'Đã xóa bình luận đã chọn.';
    }
}

$q = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'date';
$order = strtolower($_GET['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$perPage = (int)($_GET['per_page'] ?? 10);
$perPage = in_array($perPage, [10, 20, 50, 100], true) ? $perPage : 10;
$page = max(1, (int)($_GET['page'] ?? 1));

$allowedSort = [
    'id' => 'cm.id',
    'date' => 'cm.date',
    'user' => 'cm.user_name',
    'post' => 'post_title'
];
$sortSql = $allowedSort[$sort] ?? 'cm.date';

$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(cm.user_name LIKE ? OR cm.comment LIKE ? OR p.title LIKE ?)';
    $qLike = '%' . $q . '%';
    $params[] = $qLike;
    $params[] = $qLike;
    $params[] = $qLike;
}

$whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

$countSql = "
    SELECT COUNT(*)
    FROM comments cm
    LEFT JOIN posts p ON p.id = cm.post_id
    {$whereSql}
";
$countStmt = $conn->prepare($countSql);
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$sql = "
    SELECT
        cm.id,
        cm.post_id,
        cm.user_name,
        cm.comment,
        cm.date,
        p.title AS post_title
    FROM comments cm
    LEFT JOIN posts p ON p.id = cm.post_id
    {$whereSql}
    ORDER BY {$sortSql} {$order}
    LIMIT {$perPage} OFFSET {$offset}
";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

function commentsPageUrl($targetPage)
{
    $query = $_GET;
    $query['page'] = $targetPage;
    return 'comments.php?' . http_build_query($query);
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý bình luận</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
</head>

<body class="ui-page">
    <?php include '../components/admin_header.php' ?>

    <section class="comments ui-container">
        <h1 class="heading ui-title">Quản lý bình luận</h1>

        <article class="admin-panel-card" style="margin-bottom:1.2rem;">
            <form method="get" data-admin-ajax-form="1" style="display:flex;gap:.8rem;flex-wrap:wrap;align-items:center;">
                <input type="text" name="q" class="box" placeholder="Tìm theo người dùng, nội dung, bài viết" value="<?= htmlspecialchars($q); ?>">

                <select name="sort" class="box ui-select">
                    <option value="date" <?= $sort === 'date' ? 'selected' : ''; ?>>Sắp xếp theo ngày</option>
                    <option value="id" <?= $sort === 'id' ? 'selected' : ''; ?>>Sắp xếp theo ID</option>
                    <option value="user" <?= $sort === 'user' ? 'selected' : ''; ?>>Sắp xếp theo người dùng</option>
                    <option value="post" <?= $sort === 'post' ? 'selected' : ''; ?>>Sắp xếp theo bài viết</option>
                </select>

                <select name="order" class="box ui-select">
                    <option value="desc" <?= $order === 'DESC' ? 'selected' : ''; ?>>Giảm dần</option>
                    <option value="asc" <?= $order === 'ASC' ? 'selected' : ''; ?>>Tăng dần</option>
                </select>

                <select name="per_page" class="box ui-select">
                    <option value="10" <?= $perPage === 10 ? 'selected' : ''; ?>>10 / trang</option>
                    <option value="20" <?= $perPage === 20 ? 'selected' : ''; ?>>20 / trang</option>
                    <option value="50" <?= $perPage === 50 ? 'selected' : ''; ?>>50 / trang</option>
                    <option value="100" <?= $perPage === 100 ? 'selected' : ''; ?>>100 / trang</option>
                </select>

                <input type="hidden" name="page" value="1">
                <button type="submit" class="btn ui-btn">Lọc / Sắp xếp</button>
                <a href="comments.php" data-admin-ajax-link="1" class="delete-btn ui-btn-danger" style="text-decoration:none;display:inline-flex;align-items:center;">Reset</a>
                <button type="button" data-admin-refresh="1" class="option-btn ui-btn-warning">Làm mới</button>
            </form>
        </article>

        <div class="box-container ui-card">
            <form method="post" data-admin-ajax-post-form="1" onsubmit="return confirm('Bạn chắc chắn xóa các bình luận đã chọn?');">
                <div style="display:flex;gap:.8rem;flex-wrap:wrap;align-items:center;margin-bottom:1rem;">
                    <label style="display:flex;align-items:center;gap:.4rem;">
                        <input type="checkbox" id="checkAllComments"> Chọn tất cả
                    </label>
                    <select name="bulk_action" class="box ui-select" required>
                        <option value="">-- Chọn thao tác --</option>
                        <option value="delete">Xóa bình luận</option>
                    </select>
                    <button type="submit" class="btn ui-btn">Thực hiện</button>
                </div>

                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th></th>
                                <th>ID</th>
                                <th>Người dùng</th>
                                <th>Bài viết</th>
                                <th>Nội dung</th>
                                <th>Ngày</th>
                                <th>Xem bài viết</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($comments)): ?>
                                <?php foreach ($comments as $c): ?>
                                    <?php $decodedPostTitle = blog_decode_html_entities_deep((string)($c['post_title'] ?? '')); ?>
                                    <tr>
                                        <td><input type="checkbox" class="row-check-comment" name="selected_comment_ids[]" value="<?= (int)$c['id']; ?>"></td>
                                        <td><?= (int)$c['id']; ?></td>
                                        <td><?= htmlspecialchars((string)$c['user_name']); ?></td>
                                        <td><?= htmlspecialchars($decodedPostTitle, ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?= htmlspecialchars((string)$c['comment']); ?></td>
                                        <td><?= htmlspecialchars((string)$c['date']); ?></td>
                                        <td>
                                            <a href="read_post.php?post_id=<?= (int)$c['post_id']; ?>" class="btn ui-btn">Xem</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7">Không có bình luận phù hợp bộ lọc.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin-top:1rem;">
                    <div>Trang <?= (int)$page; ?>/<?= (int)$totalPages; ?> - Tổng: <?= (int)$totalRows; ?> bình luận</div>
                    <?= admin_render_numeric_pagination((int)$page, (int)$totalPages, static function (int $targetPage): string {
                        return commentsPageUrl($targetPage);
                    }, 'data-admin-ajax-link="1"'); ?>
                </div>
            </form>
        </div>
    </section>

    <script src="../js/admin_script.js"></script>
    <script>
        const checkAllComments = document.getElementById('checkAllComments');
        if (checkAllComments) {
            checkAllComments.setAttribute('data-bulk-check-all', '1');
            checkAllComments.setAttribute('data-target-selector', '.row-check-comment');
        }
    </script>
</body>

</html>
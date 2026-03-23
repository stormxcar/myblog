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
    $selected = $_POST['selected_post_ids'] ?? [];
    $selected = is_array($selected) ? array_map('intval', $selected) : [];
    $selected = array_values(array_filter(array_unique($selected), function ($v) {
        return $v > 0;
    }));

    if (!empty($selected) && in_array($bulkAction, ['delete', 'active', 'inactive'], true)) {
        $inSql = implode(',', array_fill(0, count($selected), '?'));

        if ($bulkAction === 'delete') {
            $imgSql = "SELECT image FROM posts WHERE admin_id = ? AND id IN ({$inSql})";
            $imgStmt = $conn->prepare($imgSql);
            $imgStmt->execute(array_merge([(int)$admin_id], $selected));

            while ($img = $imgStmt->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($img['image'])) {
                    blog_delete_image_resource((string)$img['image']);
                }
            }

            $delSql = "DELETE FROM posts WHERE admin_id = ? AND id IN ({$inSql})";
            $delStmt = $conn->prepare($delSql);
            $delStmt->execute(array_merge([(int)$admin_id], $selected));
            $message[] = 'Đã xóa các bài viết đã chọn.';
        }

        if ($bulkAction === 'active' || $bulkAction === 'inactive') {
            $newStatus = $bulkAction === 'active' ? 'active' : 'deactive';
            $updSql = "UPDATE posts SET status = ? WHERE admin_id = ? AND id IN ({$inSql})";
            $updStmt = $conn->prepare($updSql);
            $updStmt->execute(array_merge([$newStatus, (int)$admin_id], $selected));
            $message[] = 'Đã cập nhật trạng thái bài viết đã chọn.';
        }
    }
}

$q = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? 'all';
$category = trim($_GET['category'] ?? 'all');
$sort = $_GET['sort'] ?? 'date';
$order = strtolower($_GET['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$perPage = (int)($_GET['per_page'] ?? 10);
$perPage = in_array($perPage, [10, 20, 50, 100], true) ? $perPage : 10;
$page = max(1, (int)($_GET['page'] ?? 1));

$allowedSort = [
    'id' => 'p.id',
    'date' => 'p.date',
    'title' => 'p.title',
    'status' => 'p.status',
    'comments' => 'comments_count',
    'likes' => 'likes_count'
];
$sortSql = $allowedSort[$sort] ?? 'p.date';

$where = [];
$params = [];

$where[] = 'p.admin_id = ?';
$params[] = (int)$admin_id;

if ($q !== '') {
    $where[] = '(p.title LIKE ? OR p.content LIKE ? OR p.name LIKE ?)';
    $qLike = '%' . $q . '%';
    $params[] = $qLike;
    $params[] = $qLike;
    $params[] = $qLike;
}

if ($status !== 'all') {
    $where[] = 'p.status = ?';
    $params[] = $status;
}

if ($category !== 'all') {
    $where[] = 'c.name = ?';
    $params[] = $category;
}

$whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

$categoryStmt = $conn->prepare('SELECT DISTINCT name FROM cart WHERE admin_id = ? AND name IS NOT NULL AND name <> "" ORDER BY name ASC');
$categoryStmt->execute([(int)$admin_id]);
$categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

$countSql = "
    SELECT COUNT(*)
    FROM posts p
    LEFT JOIN cart c ON c.category_id = p.tag_id
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
        p.id,
        p.image,
        p.status,
        p.date,
        p.title,
        p.content,
        p.name,
        c.name AS category_name,
        COALESCE((SELECT COUNT(*) FROM comments cm WHERE cm.post_id = p.id), 0) AS comments_count,
        COALESCE((SELECT COUNT(*) FROM likes lk WHERE lk.post_id = p.id), 0) AS likes_count
    FROM posts p
    LEFT JOIN cart c ON c.category_id = p.tag_id
    {$whereSql}
    ORDER BY {$sortSql} {$order}
    LIMIT {$perPage} OFFSET {$offset}
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

function postsPageUrl($targetPage)
{
    $query = $_GET;
    $query['page'] = $targetPage;
    return 'view_posts.php?' . http_build_query($query);
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý bài viết</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
</head>

<body class="ui-page">
    <?php include '../components/admin_header.php' ?>

    <section class="show-posts ui-container" style="margin-bottom: 8rem;">
        <h1 class="heading ui-title">Quản lý bài viết</h1>

        <article class="admin-panel-card" style="margin-bottom:1.2rem;">
            <form method="get" data-admin-ajax-form="1" style="display:flex;gap:.8rem;flex-wrap:wrap;align-items:center;">
                <input type="text" name="q" class="box" placeholder="Tìm theo tiêu đề, nội dung" value="<?= htmlspecialchars($q); ?>">

                <select name="status" class="box ui-select">
                    <option value="all" <?= $status === 'all' ? 'selected' : ''; ?>>Tất cả trạng thái</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="deactive" <?= $status === 'deactive' ? 'selected' : ''; ?>>Deactive</option>
                </select>

                <select name="category" class="box ui-select">
                    <option value="all" <?= $category === 'all' ? 'selected' : ''; ?>>Tất cả danh mục</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['name']); ?>" <?= $category === $cat['name'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="sort" class="box ui-select">
                    <option value="date" <?= $sort === 'date' ? 'selected' : ''; ?>>Sắp xếp theo ngày</option>
                    <option value="id" <?= $sort === 'id' ? 'selected' : ''; ?>>Sắp xếp theo ID</option>
                    <option value="title" <?= $sort === 'title' ? 'selected' : ''; ?>>Sắp xếp theo tiêu đề</option>
                    <option value="status" <?= $sort === 'status' ? 'selected' : ''; ?>>Sắp xếp theo trạng thái</option>
                    <option value="comments" <?= $sort === 'comments' ? 'selected' : ''; ?>>Sắp xếp theo bình luận</option>
                    <option value="likes" <?= $sort === 'likes' ? 'selected' : ''; ?>>Sắp xếp theo lượt thích</option>
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
                <a href="view_posts.php" data-admin-ajax-link="1" class="delete-btn ui-btn-danger" style="text-decoration:none;display:inline-flex;align-items:center;">Reset</a>
                <button type="button" data-admin-refresh="1" class="option-btn ui-btn-warning">Làm mới</button>
            </form>
        </article>

        <div class="box-container ui-card">
            <form method="post" data-admin-ajax-post-form="1" onsubmit="return confirm('Bạn chắc chắn thực hiện thao tác hàng loạt?');">
                <div style="display:flex;gap:.8rem;flex-wrap:wrap;align-items:center;margin-bottom:1rem;">
                    <label style="display:flex;align-items:center;gap:.4rem;">
                        <input type="checkbox" id="checkAllPosts"> Chọn tất cả
                    </label>
                    <select name="bulk_action" class="box ui-select" required>
                        <option value="">-- Chọn thao tác --</option>
                        <option value="active">Đặt Active</option>
                        <option value="inactive">Đặt Deactive</option>
                        <option value="delete">Xóa bài viết</option>
                    </select>
                    <button type="submit" class="btn ui-btn">Thực hiện</button>
                </div>

                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th></th>
                                <th>ID</th>
                                <th>Ảnh</th>
                                <th>Trạng thái</th>
                                <th>Ngày</th>
                                <th>Danh mục</th>
                                <th>Tiêu đề</th>
                                <th>Bình luận</th>
                                <th>Lượt thích</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($posts)): ?>
                                <?php foreach ($posts as $p): ?>
                                    <?php
                                    $decodedTitle = blog_decode_html_entities_deep((string)($p['title'] ?? ''));
                                    $decodedCategory = blog_decode_html_entities_deep((string)($p['category_name'] ?? ''));
                                    ?>
                                    <tr>
                                        <td><input type="checkbox" class="row-check-post" name="selected_post_ids[]" value="<?= (int)$p['id']; ?>"></td>
                                        <td><?= (int)$p['id']; ?></td>
                                        <td>
                                            <?php if (!empty($p['image'])): ?>
                                                <img src="<?= htmlspecialchars(blog_post_image_src((string)$p['image'], '../uploaded_img/', '../uploaded_img/default_img.jpg'), ENT_QUOTES, 'UTF-8'); ?>" alt="post" style="max-width:70px;border-radius:8px;">
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($p['status']); ?></td>
                                        <td><?= htmlspecialchars((string)$p['date']); ?></td>
                                        <td><?= htmlspecialchars($decodedCategory, ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?= htmlspecialchars($decodedTitle, ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?= (int)$p['comments_count']; ?></td>
                                        <td><?= (int)$p['likes_count']; ?></td>
                                        <td style="display:flex;gap:.4rem;flex-wrap:wrap;">
                                            <a href="edit_post.php?id=<?= (int)$p['id']; ?>" class="option-btn ui-btn-warning">Sửa</a>
                                            <a href="read_post.php?post_id=<?= (int)$p['id']; ?>" class="btn ui-btn">Xem</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10">Không có bài viết phù hợp bộ lọc.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin-top:1rem;">
                    <div>Trang <?= (int)$page; ?>/<?= (int)$totalPages; ?> - Tổng: <?= (int)$totalRows; ?> bài viết</div>
                    <?= admin_render_numeric_pagination((int)$page, (int)$totalPages, static function (int $targetPage): string {
                        return postsPageUrl($targetPage);
                    }, 'data-admin-ajax-link="1"'); ?>
                </div>
            </form>
        </div>
    </section>

    <script src="../js/admin_script.js"></script>
    <script>
        const checkAllPosts = document.getElementById('checkAllPosts');
        if (checkAllPosts) {
            checkAllPosts.setAttribute('data-bulk-check-all', '1');
            checkAllPosts.setAttribute('data-target-selector', '.row-check-post');
        }
    </script>
</body>

</html>
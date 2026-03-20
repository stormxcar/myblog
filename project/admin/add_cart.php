<?php
include '../components/connect.php';
session_start();

$admin_id = $_SESSION['admin_id'] ?? null;
if (!$admin_id) {
    header('location:admin_login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_category'])) {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $message[] = 'Tên danh mục không được để trống.';
        } else {
            try {
                $exists = $conn->prepare('SELECT category_id FROM cart WHERE name = ? LIMIT 1');
                $exists->execute([$name]);
                if ($exists->fetch(PDO::FETCH_ASSOC)) {
                    $message[] = 'Danh mục đã tồn tại.';
                } else {
                    $cartOwnerId = blog_resolve_cart_admin_fk_value($conn, $admin_id);
                    $stmt = $conn->prepare('INSERT INTO cart(admin_id, name) VALUES(?, ?)');
                    $stmt->execute([(int)$cartOwnerId, $name]);
                    if ($stmt->rowCount() > 0) {
                        $_SESSION['message'] = 'Đã thêm danh mục.';
                        header('location:add_cart.php');
                        exit;
                    }
                    $message[] = 'Không thể thêm danh mục mới.';
                }
            } catch (PDOException $e) {
                $message[] = 'Không thể thêm danh mục do ràng buộc dữ liệu admin. Vui lòng kiểm tra mapping tài khoản admin.';
            }
        }
    }

    if (isset($_POST['bulk_action'])) {
        $bulkAction = $_POST['bulk_action'] ?? '';
        $selected = $_POST['selected_category_ids'] ?? [];
        $selected = is_array($selected) ? array_map('intval', $selected) : [];
        $selected = array_values(array_filter(array_unique($selected), function ($v) {
            return $v > 0;
        }));

        if ($bulkAction === 'delete' && !empty($selected)) {
            $idsInSql = implode(',', array_fill(0, count($selected), '?'));
            $sql = 'DELETE FROM cart WHERE category_id IN (' . $idsInSql . ')';
            $stmt = $conn->prepare($sql);
            $stmt->execute($selected);
            $message[] = 'Đã xóa các danh mục đã chọn.';
        }
    }
}

$q = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'id';
$order = strtolower($_GET['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$perPage = (int)($_GET['per_page'] ?? 10);
$perPage = in_array($perPage, [10, 20, 50, 100], true) ? $perPage : 10;
$page = max(1, (int)($_GET['page'] ?? 1));

$allowedSort = [
    'id' => 'c.category_id',
    'name' => 'c.name',
    'posts' => 'posts_count'
];
$sortSql = $allowedSort[$sort] ?? 'c.category_id';

$where = [];
$params = [];

if ($q !== '') {
    $where[] = 'c.name LIKE ?';
    $params[] = '%' . $q . '%';
}

$whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

$countSql = "SELECT COUNT(*) FROM cart c {$whereSql}";
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
        c.category_id,
        c.name,
        COALESCE((SELECT COUNT(*) FROM posts p WHERE p.tag_id = c.category_id), 0) AS posts_count
    FROM cart c
    {$whereSql}
    ORDER BY {$sortSql} {$order}
    LIMIT {$perPage} OFFSET {$offset}
";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

function categoriesPageUrl($targetPage)
{
    $query = $_GET;
    $query['page'] = $targetPage;
    return 'add_cart.php?' . http_build_query($query);
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý danh mục</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
</head>

<body class="ui-page">
    <?php include '../components/admin_header.php' ?>

    <section class="category_edit ui-container">
        <h1 class="heading ui-title">Quản lý danh mục</h1>

        <article class="admin-panel-card" style="margin-bottom:1.2rem;">
            <form method="post" style="display:flex;gap:.8rem;flex-wrap:wrap;align-items:center;">
                <input type="text" name="name" class="box" placeholder="Nhập tên danh mục mới" required>
                <button type="submit" name="add_category" class="btn ui-btn">Thêm danh mục</button>
            </form>
        </article>

        <article class="admin-panel-card" style="margin-bottom:1.2rem;">
            <form method="get" data-admin-ajax-form="1" style="display:flex;gap:.8rem;flex-wrap:wrap;align-items:center;">
                <input type="text" name="q" class="box" placeholder="Tìm theo tên danh mục" value="<?= htmlspecialchars($q); ?>">

                <select name="sort" class="box ui-select">
                    <option value="id" <?= $sort === 'id' ? 'selected' : ''; ?>>Sắp xếp theo ID</option>
                    <option value="name" <?= $sort === 'name' ? 'selected' : ''; ?>>Sắp xếp theo tên</option>
                    <option value="posts" <?= $sort === 'posts' ? 'selected' : ''; ?>>Sắp xếp theo số bài viết</option>
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
                <a href="add_cart.php" data-admin-ajax-link="1" class="delete-btn ui-btn-danger" style="text-decoration:none;display:inline-flex;align-items:center;">Reset</a>
                <button type="button" data-admin-refresh="1" class="option-btn ui-btn-warning">Làm mới</button>
            </form>
        </article>

        <div class="ui-card">
            <form method="post" data-admin-ajax-post-form="1" onsubmit="return confirm('Bạn chắc chắn thực hiện thao tác hàng loạt?');">
                <div style="display:flex;gap:.8rem;flex-wrap:wrap;align-items:center;margin-bottom:1rem;">
                    <label style="display:flex;align-items:center;gap:.4rem;">
                        <input type="checkbox" id="checkAllCategory"> Chọn tất cả
                    </label>

                    <select name="bulk_action" class="box ui-select" required>
                        <option value="">-- Chọn thao tác --</option>
                        <option value="delete">Xóa danh mục</option>
                    </select>

                    <button type="submit" class="btn ui-btn">Thực hiện</button>
                </div>

                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th></th>
                                <th>ID</th>
                                <th>Tên danh mục</th>
                                <th>Số bài viết</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($categories)): ?>
                                <?php foreach ($categories as $cat): ?>
                                    <tr>
                                        <td><input type="checkbox" class="row-check-category" name="selected_category_ids[]" value="<?= (int)$cat['category_id']; ?>"></td>
                                        <td><?= (int)$cat['category_id']; ?></td>
                                        <td><?= htmlspecialchars((string)$cat['name']); ?></td>
                                        <td><?= (int)$cat['posts_count']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4">Không có danh mục phù hợp bộ lọc.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin-top:1rem;">
                    <div>Trang <?= (int)$page; ?>/<?= (int)$totalPages; ?> - Tổng: <?= (int)$totalRows; ?> danh mục</div>
                    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                        <?php if ($page > 1): ?>
                            <a data-admin-ajax-link="1" class="option-btn ui-btn-warning" href="<?= htmlspecialchars(categoriesPageUrl(1)); ?>">Đầu</a>
                            <a data-admin-ajax-link="1" class="option-btn ui-btn-warning" href="<?= htmlspecialchars(categoriesPageUrl($page - 1)); ?>">Trước</a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a data-admin-ajax-link="1" class="btn ui-btn" href="<?= htmlspecialchars(categoriesPageUrl($page + 1)); ?>">Sau</a>
                            <a data-admin-ajax-link="1" class="btn ui-btn" href="<?= htmlspecialchars(categoriesPageUrl($totalPages)); ?>">Cuối</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <script src="../js/admin_script.js"></script>
    <script>
        const checkAllCategory = document.getElementById('checkAllCategory');
        if (checkAllCategory) {
            checkAllCategory.setAttribute('data-bulk-check-all', '1');
            checkAllCategory.setAttribute('data-target-selector', '.row-check-category');
        }
    </script>
</body>

</html>
<?php
include '../components/connect.php';
include '../components/admin_pagination.php';
session_start();

$admin_id = $_SESSION['admin_id'] ?? null;
if (!$admin_id) {
    header('location:admin_login.php');
    exit;
}

function blog_get_interaction_labels($conn)
{
    $stmt = $conn->query("SHOW COLUMNS FROM users LIKE 'level_of_interaction'");
    $col = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    $type = $col['Type'] ?? '';

    if (preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $type, $m) && !empty($m[1])) {
        return array_map('stripslashes', $m[1]);
    }

    return ['Cao', 'Ổn định', 'Thấp'];
}

function updateUserRatings($conn, $selectedUserIds = null)
{
    $labels = blog_get_interaction_labels($conn);
    $high = $labels[0] ?? 'Cao';
    $mid = $labels[1] ?? ($labels[0] ?? 'Ổn định');
    $low = $labels[2] ?? ($labels[1] ?? 'Thấp');

    $where = '';
    $params = [];

    if (is_array($selectedUserIds) && !empty($selectedUserIds)) {
        $ids = array_values(array_unique(array_map('intval', $selectedUserIds)));
        $ids = array_filter($ids, function ($v) {
            return $v > 0;
        });
        if (!empty($ids)) {
            $where = ' WHERE u.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            $params = $ids;
        }
    }

    $sql = "
        UPDATE users u
        SET u.level_of_interaction = (
            CASE
                WHEN (SELECT COUNT(*) FROM comments c WHERE c.user_id = u.id) > 5
                 AND (SELECT COUNT(*) FROM likes l WHERE l.user_id = u.id) > 5 THEN ?
                WHEN (SELECT COUNT(*) FROM comments c WHERE c.user_id = u.id) >= 2
                 AND (SELECT COUNT(*) FROM likes l WHERE l.user_id = u.id) >= 2 THEN ?
                ELSE ?
            END
        )
        {$where}
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute(array_merge([$high, $mid, $low], $params));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bulkAction = $_POST['bulk_action'] ?? '';
    $selectedIds = $_POST['selected_ids'] ?? [];
    $selectedIds = is_array($selectedIds) ? array_map('intval', $selectedIds) : [];
    $selectedIds = array_values(array_filter(array_unique($selectedIds), function ($v) {
        return $v > 0;
    }));

    if ($bulkAction === 'recalc_interaction') {
        updateUserRatings($conn, $selectedIds ?: null);
        $message[] = 'Đã cập nhật mức độ tương tác.';
    } elseif (!empty($selectedIds) && in_array($bulkAction, ['ban', 'unban', 'delete'], true)) {
        if ($bulkAction === 'ban' || $bulkAction === 'unban') {
            $banVal = $bulkAction === 'ban' ? 1 : 0;
            $sql = 'UPDATE users SET banned = ? WHERE id IN (' . implode(',', array_fill(0, count($selectedIds), '?')) . ')';
            $stmt = $conn->prepare($sql);
            $stmt->execute(array_merge([$banVal], $selectedIds));
            $message[] = $bulkAction === 'ban' ? 'Đã khóa tài khoản đã chọn.' : 'Đã mở khóa tài khoản đã chọn.';
        }

        if ($bulkAction === 'delete') {
            $safeIds = array_values(array_filter($selectedIds, function ($id) use ($admin_id) {
                return (int)$id !== (int)$admin_id;
            }));

            if (!empty($safeIds)) {
                $sql = 'DELETE FROM users WHERE id IN (' . implode(',', array_fill(0, count($safeIds), '?')) . ')';
                $stmt = $conn->prepare($sql);
                $stmt->execute($safeIds);
                $message[] = 'Đã xóa tài khoản đã chọn.';
            } else {
                $message[] = 'Không thể xóa tài khoản đang đăng nhập.';
            }
        }
    }
}

$search = trim($_GET['q'] ?? '');
$roleFilter = $_GET['role'] ?? 'all';
$bannedFilter = $_GET['banned'] ?? 'all';
$sort = $_GET['sort'] ?? 'id';
$order = strtolower($_GET['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$perPage = (int)($_GET['per_page'] ?? 10);
$perPage = in_array($perPage, [10, 20, 50, 100], true) ? $perPage : 10;
$page = max(1, (int)($_GET['page'] ?? 1));

$allowedSort = [
    'id' => 'u.id',
    'name' => 'u.name',
    'role' => 'role_label',
    'interaction' => 'u.level_of_interaction',
    'comments' => 'comments_count',
    'likes' => 'likes_count',
    'posts' => 'posts_count',
    'banned' => 'u.banned'
];
$sortSql = $allowedSort[$sort] ?? 'u.id';

$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(u.name LIKE ? OR u.email LIKE ? OR CAST(u.id AS CHAR) LIKE ?)';
    $searchLike = '%' . $search . '%';
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}

if ($roleFilter !== 'all') {
    $where[] = "COALESCE(u.role, 'user') = ?";
    $params[] = $roleFilter;
}

if ($bannedFilter === '0' || $bannedFilter === '1') {
    $where[] = 'u.banned = ?';
    $params[] = (int)$bannedFilter;
}

$whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

$postsCountExpr = 'COALESCE((SELECT COUNT(*) FROM posts p WHERE p.admin_id = u.id), 0)';
if (blog_has_legacy_admin_id_column($conn)) {
    $postsCountExpr = 'COALESCE((SELECT COUNT(*) FROM posts p WHERE p.admin_id = u.id OR (u.legacy_admin_id IS NOT NULL AND p.admin_id = u.legacy_admin_id)), 0)';
}

$countSql = "SELECT COUNT(*) FROM users u {$whereSql}";
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
        u.id,
        u.name,
        u.email,
        u.banned,
        u.level_of_interaction,
        COALESCE(u.role, 'user') AS role_label,
        COALESCE((SELECT COUNT(*) FROM comments c WHERE c.user_id = u.id), 0) AS comments_count,
        COALESCE((SELECT COUNT(*) FROM likes l WHERE l.user_id = u.id), 0) AS likes_count,
        {$postsCountExpr} AS posts_count
    FROM users u
    {$whereSql}
    ORDER BY {$sortSql} {$order}
    LIMIT {$perPage} OFFSET {$offset}
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

function usersPageUrl($targetPage)
{
    $query = $_GET;
    $query['page'] = $targetPage;
    return 'users_accounts.php?' . http_build_query($query);
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tài khoản User/Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
</head>

<body class="ui-page">
    <?php include '../components/admin_header.php' ?>

    <section class="accounts ui-container">
        <h1 class="heading ui-title">Tài khoản hệ thống (User/Admin)</h1>

        <article class="admin-panel-card" style="margin-bottom:1.2rem;">
            <form method="get" data-admin-ajax-form="1" class="admin-inline-form" style="display:flex;gap:.8rem;flex-wrap:wrap;align-items:center;">
                <input type="text" name="q" class="box" placeholder="Tìm theo tên, email, ID" value="<?= htmlspecialchars($search); ?>">

                <select name="role" class="box ui-select">
                    <option value="all" <?= $roleFilter === 'all' ? 'selected' : ''; ?>>Tất cả role</option>
                    <option value="user" <?= $roleFilter === 'user' ? 'selected' : ''; ?>>User</option>
                    <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                </select>

                <select name="banned" class="box ui-select">
                    <option value="all" <?= $bannedFilter === 'all' ? 'selected' : ''; ?>>Tất cả trạng thái</option>
                    <option value="0" <?= $bannedFilter === '0' ? 'selected' : ''; ?>>Đang hoạt động</option>
                    <option value="1" <?= $bannedFilter === '1' ? 'selected' : ''; ?>>Đang bị khóa</option>
                </select>

                <select name="sort" class="box ui-select">
                    <option value="id" <?= $sort === 'id' ? 'selected' : ''; ?>>Sắp xếp theo ID</option>
                    <option value="name" <?= $sort === 'name' ? 'selected' : ''; ?>>Sắp xếp theo tên</option>
                    <option value="role" <?= $sort === 'role' ? 'selected' : ''; ?>>Sắp xếp theo role</option>
                    <option value="interaction" <?= $sort === 'interaction' ? 'selected' : ''; ?>>Sắp xếp theo tương tác</option>
                    <option value="comments" <?= $sort === 'comments' ? 'selected' : ''; ?>>Sắp xếp theo bình luận</option>
                    <option value="likes" <?= $sort === 'likes' ? 'selected' : ''; ?>>Sắp xếp theo thích</option>
                    <option value="posts" <?= $sort === 'posts' ? 'selected' : ''; ?>>Sắp xếp theo bài viết</option>
                    <option value="banned" <?= $sort === 'banned' ? 'selected' : ''; ?>>Sắp xếp theo trạng thái khóa</option>
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
                <a href="users_accounts.php" data-admin-ajax-link="1" class="delete-btn ui-btn-danger" style="text-decoration:none;display:inline-flex;align-items:center;">Reset</a>
                <button type="button" data-admin-refresh="1" class="option-btn ui-btn-warning">Làm mới</button>
                <a href="register_admin.php" class="option-btn ui-btn-warning" style="text-decoration:none;display:inline-flex;align-items:center;">Thêm admin</a>
            </form>
        </article>

        <div class="box-container ui-card">
            <form method="post" data-admin-ajax-post-form="1" onsubmit="return confirm('Bạn chắc chắn thực hiện thao tác cho các dòng đã chọn?');">
                <div style="display:flex;gap:.8rem;flex-wrap:wrap;align-items:center;margin-bottom:1rem;">
                    <label style="display:flex;align-items:center;gap:.4rem;">
                        <input type="checkbox" id="checkAllUsers"> Chọn tất cả
                    </label>

                    <select name="bulk_action" class="box ui-select" required>
                        <option value="">-- Chọn thao tác hàng loạt --</option>
                        <option value="ban">Khóa tài khoản</option>
                        <option value="unban">Mở khóa tài khoản</option>
                        <option value="delete">Xóa tài khoản</option>
                        <option value="recalc_interaction">Cập nhật mức tương tác</option>
                    </select>

                    <button type="submit" class="btn ui-btn">Thực hiện</button>
                </div>

                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th></th>
                                <th>ID</th>
                                <th>Tên</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Bình luận</th>
                                <th>Thích</th>
                                <th>Bài viết</th>
                                <th>Tương tác</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($accounts)): ?>
                                <?php foreach ($accounts as $row): ?>
                                    <tr>
                                        <td><input type="checkbox" class="row-check-user" name="selected_ids[]" value="<?= (int)$row['id']; ?>"></td>
                                        <td><?= (int)$row['id']; ?></td>
                                        <td><?= htmlspecialchars($row['name'] ?? ''); ?></td>
                                        <td><?= htmlspecialchars($row['email'] ?? ''); ?></td>
                                        <td>
                                            <span class="admin-kpi-chip <?= ($row['role_label'] === 'admin') ? 'admin-kpi-chip--warn' : 'admin-kpi-chip--ok'; ?>">
                                                <?= htmlspecialchars($row['role_label']); ?>
                                            </span>
                                        </td>
                                        <td><?= (int)$row['comments_count']; ?></td>
                                        <td><?= (int)$row['likes_count']; ?></td>
                                        <td><?= (int)$row['posts_count']; ?></td>
                                        <td><?= htmlspecialchars((string)($row['level_of_interaction'] ?? '')); ?></td>
                                        <td><?= ((int)$row['banned'] === 1) ? 'Khóa' : 'Hoạt động'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10">Không có dữ liệu phù hợp bộ lọc.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin-top:1rem;">
                    <div>Trang <?= (int)$page; ?>/<?= (int)$totalPages; ?> - Tổng: <?= (int)$totalRows; ?> tài khoản</div>
                    <?= admin_render_numeric_pagination((int)$page, (int)$totalPages, static function (int $targetPage): string {
                        return usersPageUrl($targetPage);
                    }, 'data-admin-ajax-link="1"'); ?>
                </div>
            </form>
        </div>
    </section>

    <script src="../js/admin_script.js"></script>
    <script>
        const checkAllUsers = document.getElementById('checkAllUsers');
        if (checkAllUsers) {
            checkAllUsers.setAttribute('data-bulk-check-all', '1');
            checkAllUsers.setAttribute('data-target-selector', '.row-check-user');
        }
    </script>
</body>

</html>
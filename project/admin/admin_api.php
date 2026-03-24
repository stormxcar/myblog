<?php

include '../components/connect.php';

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Phiên đăng nhập đã hết hạn.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$admin_id = (int)$_SESSION['admin_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

$hasBannedColumn = blog_db_has_column($conn, 'users', 'banned');
$hasAvatarColumn = blog_db_has_column($conn, 'users', 'avatar');
$hasLevelColumn = blog_db_has_column($conn, 'users', 'level_of_interaction');

function json_success(array $payload = [])
{
    echo json_encode(array_merge(['ok' => true], $payload), JSON_UNESCAPED_UNICODE);
    exit;
}

function json_error(string $message, int $code = 400)
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function decode_deep(string $input, int $depth = 3): string
{
    $result = $input;
    for ($i = 0; $i < $depth; $i++) {
        $next = html_entity_decode($result, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($next === $result) {
            break;
        }
        $result = $next;
    }
    return $result;
}

function build_pagination_html(int $page, int $totalPages): string
{
    if ($totalPages <= 1) {
        return '';
    }

    $html = '<div class="admin-pagination">';
    $prevDisabled = $page <= 1 ? 'disabled' : '';
    $nextDisabled = $page >= $totalPages ? 'disabled' : '';
    $html .= '<button type="button" class="admin-page-btn" data-page="' . max(1, $page - 1) . '" ' . $prevDisabled . '>Truoc</button>';

    for ($i = 1; $i <= $totalPages; $i++) {
        $active = $i === $page ? 'is-active' : '';
        $html .= '<button type="button" class="admin-page-btn ' . $active . '" data-page="' . $i . '">' . $i . '</button>';
    }

    $html .= '<button type="button" class="admin-page-btn" data-page="' . min($totalPages, $page + 1) . '" ' . $nextDisabled . '>Sau</button>';
    $html .= '</div>';

    return $html;
}

if ($action === 'posts_list') {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = (int)($_GET['limit'] ?? 10);
    if (!in_array($limit, [10, 20, 30, 50], true)) {
        $limit = 10;
    }

    $search = trim((string)($_GET['search'] ?? ''));
    $status = trim((string)($_GET['status'] ?? 'all'));
    $category = trim((string)($_GET['category'] ?? 'all'));
    $sort = trim((string)($_GET['sort'] ?? 'newest'));

    $where = ['1=1'];
    $params = [];

    if ($search !== '') {
        $where[] = '(p.title LIKE ? OR p.content LIKE ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    if (in_array($status, ['active', 'deactive'], true)) {
        $where[] = 'p.status = ?';
        $params[] = $status;
    }

    if ($category !== '' && $category !== 'all') {
        $where[] = 'p.category = ?';
        $params[] = $category;
    }

    $orderBy = 'p.id DESC';
    if ($sort === 'oldest') {
        $orderBy = 'p.id ASC';
    } elseif ($sort === 'date_desc') {
        $orderBy = 'p.date DESC, p.id DESC';
    } elseif ($sort === 'date_asc') {
        $orderBy = 'p.date ASC, p.id ASC';
    } elseif ($sort === 'engagement_desc') {
        $orderBy = 'engagement_score DESC, p.id DESC';
    } elseif ($sort === 'likes_desc') {
        $orderBy = 'total_likes DESC, p.id DESC';
    } elseif ($sort === 'comments_desc') {
        $orderBy = 'total_comments DESC, p.id DESC';
    } elseif ($sort === 'status_active') {
        $orderBy = "CASE WHEN p.status = 'active' THEN 0 ELSE 1 END, p.date DESC, p.id DESC";
    } elseif ($sort === 'status_draft') {
        $orderBy = "CASE WHEN p.status = 'deactive' THEN 0 ELSE 1 END, p.date DESC, p.id DESC";
    }

    $whereSql = implode(' AND ', $where);

    $countSql = 'SELECT COUNT(*) FROM `posts` p WHERE ' . $whereSql;
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $totalPages = max(1, (int)ceil($total / $limit));
    if ($page > $totalPages) {
        $page = $totalPages;
    }

    $offset = ($page - 1) * $limit;

    $listSql = "SELECT
            p.id,
            p.title,
            p.content,
            p.image,
            p.status,
            p.date,
            p.category,
            COALESCE((SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id), 0) AS total_comments,
            COALESCE((SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id), 0) AS total_likes,
            (
                COALESCE((SELECT COUNT(*) FROM comments c2 WHERE c2.post_id = p.id), 0)
                + COALESCE((SELECT COUNT(*) FROM likes l2 WHERE l2.post_id = p.id), 0)
            ) AS engagement_score
        FROM `posts` p
        WHERE {$whereSql}
        ORDER BY {$orderBy}
        LIMIT {$limit} OFFSET {$offset}";

    $stmt = $conn->prepare($listSql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $html = '';
    if (!$rows) {
        $html = '<tr><td colspan="12"><p class="empty">Không có bài viết phù hợp bộ lọc.</p></td></tr>';
    } else {
        $index = $offset + 1;
        foreach ($rows as $row) {
            $statusClass = $row['status'] === 'active' ? 'status-active' : 'status-draft';
            $decodedCategory = decode_deep((string)($row['category'] ?? ''));
            $decodedTitle = decode_deep((string)($row['title'] ?? ''));
            $imageHtml = $row['image'] !== ''
                ? '<img src="../uploaded_img/' . htmlspecialchars($row['image'], ENT_QUOTES, 'UTF-8') . '" class="image" alt="post">'
                : '<span class="admin-muted">-</span>';

            $html .= '<tr>';
            $html .= '<td><input type="checkbox" class="admin-bulk-post-check" value="' . (int)$row['id'] . '"></td>';
            $html .= '<td>' . $index . '</td>';
            $html .= '<td>' . $imageHtml . '</td>';
            $html .= '<td><span class="admin-status-pill ' . $statusClass . '">' . htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8') . '</span></td>';
            $html .= '<td>' . htmlspecialchars($row['date'], ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '<td>' . htmlspecialchars($decodedCategory !== '' ? $decodedCategory : 'chua co', ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '<td><div class="title">' . htmlspecialchars($decodedTitle, ENT_QUOTES, 'UTF-8') . '</div></td>';
            $html .= '<td><div class="posts-content">' . htmlspecialchars(mb_substr(strip_tags((string)$row['content']), 0, 120, 'UTF-8'), ENT_QUOTES, 'UTF-8') . '</div></td>';
            $html .= '<td><span class="admin-kpi-chip"><i class="fas fa-heart"></i>' . (int)$row['total_likes'] . '</span></td>';
            $html .= '<td><span class="admin-kpi-chip"><i class="fas fa-comment"></i>' . (int)$row['total_comments'] . '</span></td>';
            $html .= '<td><a href="edit_post.php?id=' . (int)$row['id'] . '" class="option-btn">Sua</a></td>';
            $html .= '<td><button type="button" class="delete-btn admin-delete-post" data-post-id="' . (int)$row['id'] . '">Xoa</button></td>';
            $html .= '</tr>';
            $index++;
        }
    }

    json_success([
        'html' => $html,
        'pagination' => build_pagination_html($page, $totalPages),
        'summary' => [
            'total' => $total,
            'page' => $page,
            'total_pages' => $totalPages,
            'limit' => $limit,
        ],
    ]);
}

if ($action === 'comments_list') {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = (int)($_GET['limit'] ?? 10);
    if (!in_array($limit, [10, 20, 30, 50], true)) {
        $limit = 10;
    }

    $search = trim((string)($_GET['search'] ?? ''));
    $sort = trim((string)($_GET['sort'] ?? 'newest'));

    $where = ['1=1'];
    $params = [];

    if ($search !== '') {
        $where[] = '(c.user_name LIKE ? OR c.comment LIKE ? OR p.title LIKE ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    $orderBy = 'c.id DESC';
    if ($sort === 'oldest') {
        $orderBy = 'c.id ASC';
    } elseif ($sort === 'date_desc') {
        $orderBy = 'c.date DESC, c.id DESC';
    } elseif ($sort === 'date_asc') {
        $orderBy = 'c.date ASC, c.id ASC';
    } elseif ($sort === 'engagement_desc') {
        $orderBy = 'post_engagement_score DESC, c.id DESC';
    } elseif ($sort === 'status_active') {
        $orderBy = "CASE WHEN p.status = 'active' THEN 0 ELSE 1 END, c.id DESC";
    } elseif ($sort === 'status_draft') {
        $orderBy = "CASE WHEN p.status = 'deactive' THEN 0 ELSE 1 END, c.id DESC";
    }

    $whereSql = implode(' AND ', $where);

    $countSql = 'SELECT COUNT(*) FROM comments c LEFT JOIN posts p ON p.id = c.post_id WHERE ' . $whereSql;
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $totalPages = max(1, (int)ceil($total / $limit));
    if ($page > $totalPages) {
        $page = $totalPages;
    }

    $offset = ($page - 1) * $limit;

    $listSql = "SELECT
            c.id,
            c.post_id,
            c.user_name,
            c.comment,
            c.date,
            COALESCE(p.title, 'Bai viet da xoa') AS post_title,
            COALESCE(p.status, 'deactive') AS post_status,
            (
                COALESCE((SELECT COUNT(*) FROM comments c2 WHERE c2.post_id = c.post_id), 0)
                + COALESCE((SELECT COUNT(*) FROM likes l2 WHERE l2.post_id = c.post_id), 0)
            ) AS post_engagement_score
        FROM comments c
        LEFT JOIN posts p ON p.id = c.post_id
        WHERE {$whereSql}
        ORDER BY {$orderBy}
        LIMIT {$limit} OFFSET {$offset}";

    $stmt = $conn->prepare($listSql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $html = '';
    if (!$rows) {
        $html = '<div class="empty">Không có bình luận phù hợp.</div>';
    } else {
        foreach ($rows as $row) {
            $statusClass = $row['post_status'] === 'active' ? 'status-active' : 'status-draft';
            $html .= '<article class="admin-comment-card">';
            $html .= '<div class="admin-comment-meta">';
            $html .= '<strong>' . htmlspecialchars($row['user_name'], ENT_QUOTES, 'UTF-8') . '</strong>';
            $html .= '<span>' . htmlspecialchars($row['date'], ENT_QUOTES, 'UTF-8') . '</span>';
            $html .= '</div>';
            $html .= '<p class="admin-comment-post">Bai viet: <a href="read_post.php?post_id=' . (int)$row['post_id'] . '">' . htmlspecialchars(decode_deep((string)$row['post_title']), ENT_QUOTES, 'UTF-8') . '</a></p>';
            $html .= '<p class="admin-comment-post"><span class="admin-status-pill ' . $statusClass . '">' . htmlspecialchars($row['post_status'], ENT_QUOTES, 'UTF-8') . '</span> <span class="admin-kpi-chip"><i class="fas fa-chart-line"></i>' . (int)$row['post_engagement_score'] . '</span></p>';
            $html .= '<p class="admin-comment-body">' . nl2br(htmlspecialchars($row['comment'], ENT_QUOTES, 'UTF-8')) . '</p>';
            $html .= '<label class="admin-inline-check"><input type="checkbox" class="admin-bulk-comment-check" value="' . (int)$row['id'] . '"> Chon xoa nhanh</label>';
            $html .= '<button type="button" class="inline-delete-btn admin-delete-comment" data-comment-id="' . (int)$row['id'] . '">Xoa binh luan</button>';
            $html .= '</article>';
        }
    }

    json_success([
        'html' => $html,
        'pagination' => build_pagination_html($page, $totalPages),
        'summary' => [
            'total' => $total,
            'page' => $page,
            'total_pages' => $totalPages,
            'limit' => $limit,
        ],
    ]);
}

if ($action === 'users_list') {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = (int)($_GET['limit'] ?? 10);
    if (!in_array($limit, [10, 20, 30, 50], true)) {
        $limit = 10;
    }

    $search = trim((string)($_GET['search'] ?? ''));
    $banFilter = trim((string)($_GET['ban_filter'] ?? 'all'));
    $sort = trim((string)($_GET['sort'] ?? 'newest'));

    $where = ['1=1'];
    $params = [];

    if ($search !== '') {
        $where[] = '(u.name LIKE ? OR u.email LIKE ? OR CAST(u.id AS CHAR) LIKE ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    if ($hasBannedColumn && in_array($banFilter, ['banned', 'active'], true)) {
        $where[] = $banFilter === 'banned' ? 'u.banned = 1' : 'u.banned = 0';
    }

    $orderBy = 'u.id DESC';
    if ($sort === 'oldest') {
        $orderBy = 'u.id ASC';
    } elseif ($sort === 'name_asc') {
        $orderBy = 'u.name ASC';
    } elseif ($sort === 'name_desc') {
        $orderBy = 'u.name DESC';
    } elseif ($sort === 'engagement_desc') {
        $orderBy = 'total_interactions DESC, u.id DESC';
    } elseif ($sort === 'comments_desc') {
        $orderBy = 'total_comments DESC, u.id DESC';
    } elseif ($sort === 'likes_desc') {
        $orderBy = 'total_likes DESC, u.id DESC';
    }

    $whereSql = implode(' AND ', $where);

    $countStmt = $conn->prepare('SELECT COUNT(*) FROM users u WHERE ' . $whereSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $totalPages = max(1, (int)ceil($total / $limit));
    if ($page > $totalPages) {
        $page = $totalPages;
    }

    $offset = ($page - 1) * $limit;

    $bannedSelect = $hasBannedColumn ? 'COALESCE(u.banned, 0) AS banned' : '0 AS banned';
    $avatarSelect = $hasAvatarColumn ? 'u.avatar' : "'' AS avatar";
    $levelSelect = $hasLevelColumn ? 'u.level_of_interaction' : "'' AS level_of_interaction";

    $sql = "SELECT
            u.id,
            u.name,
            u.email,
            {$bannedSelect},
            {$avatarSelect},
            {$levelSelect},
            COALESCE((SELECT COUNT(*) FROM comments c WHERE c.user_id = u.id), 0) AS total_comments,
            COALESCE((SELECT COUNT(*) FROM likes l WHERE l.user_id = u.id), 0) AS total_likes,
            (
                COALESCE((SELECT COUNT(*) FROM comments c2 WHERE c2.user_id = u.id), 0)
                + COALESCE((SELECT COUNT(*) FROM likes l2 WHERE l2.user_id = u.id), 0)
            ) AS total_interactions
        FROM users u
        WHERE {$whereSql}
        ORDER BY {$orderBy}
        LIMIT {$limit} OFFSET {$offset}";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $html = '';
    if (!$rows) {
        $html = '<tr><td colspan="11"><p class="empty">Không có người dùng phù hợp.</p></td></tr>';
    } else {
        $index = $offset + 1;
        foreach ($rows as $row) {
            $hasAvatar = !empty($row['avatar']) ? 'Có' : 'Không';
            $rating = $row['level_of_interaction'];
            if ($rating === '' || $rating === null) {
                if ((int)$row['total_comments'] > 5 && (int)$row['total_likes'] > 5) {
                    $rating = 'Cao';
                } elseif ((int)$row['total_comments'] >= 2 && (int)$row['total_likes'] >= 2) {
                    $rating = 'On dinh';
                } else {
                    $rating = 'Thap';
                }
            }

            $html .= '<tr>';
            $html .= '<td><input type="checkbox" class="admin-bulk-user-check" value="' . (int)$row['id'] . '"></td>';
            $html .= '<td>' . $index . '</td>';
            $html .= '<td>' . (int)$row['id'] . '</td>';
            $html .= '<td>' . htmlspecialchars($row['name'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '<td>' . htmlspecialchars($row['email'] ?? '-', ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '<td>' . (int)$row['total_comments'] . '</td>';
            $html .= '<td>' . (int)$row['total_likes'] . '</td>';
            $html .= '<td>' . htmlspecialchars($hasAvatar, ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '<td>' . htmlspecialchars($rating, ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '<td><button type="button" class="delete-btn admin-delete-user" data-user-id="' . (int)$row['id'] . '">Xoa</button></td>';
            if ($hasBannedColumn) {
                $checked = ((int)$row['banned']) === 1 ? 'checked' : '';
                $html .= '<td><label class="admin-switch"><input type="checkbox" class="admin-toggle-ban" data-user-id="' . (int)$row['id'] . '" ' . $checked . '><span></span></label></td>';
            } else {
                $html .= '<td><span class="admin-muted">N/A</span></td>';
            }
            $html .= '</tr>';
            $index++;
        }
    }

    json_success([
        'html' => $html,
        'pagination' => build_pagination_html($page, $totalPages),
        'summary' => [
            'total' => $total,
            'page' => $page,
            'total_pages' => $totalPages,
            'limit' => $limit,
        ],
    ]);
}

if ($action === 'toggle_user_ban') {
    if (!$hasBannedColumn) {
        json_error('He thong hien khong co cot banned.', 422);
    }

    $user_id = (int)($_POST['user_id'] ?? 0);
    $banned = (int)($_POST['banned'] ?? 0) === 1 ? 1 : 0;

    if ($user_id <= 0) {
        json_error('User ID khong hop le.');
    }

    $stmt = $conn->prepare('UPDATE users SET banned = ? WHERE id = ?');
    $stmt->execute([$banned, $user_id]);

    json_success(['message' => $banned ? 'Da khoa tai khoan.' : 'Da mo khoa tai khoan.']);
}

if ($action === 'delete_user') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    if ($user_id <= 0) {
        json_error('User ID khong hop le.');
    }

    $roleWhere = '';
    if (blog_has_admin_role_column($conn)) {
        $roleWhere = " AND role = 'admin'";
    }
    $adminCheck = $conn->prepare('SELECT id FROM users WHERE id = ?' . $roleWhere . ' LIMIT 1');
    $adminCheck->execute([$user_id]);
    if ($adminCheck->fetch(PDO::FETCH_ASSOC)) {
        json_error('Khong the xoa tai khoan admin trong man hinh nguoi dung.', 422);
    }

    $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$user_id]);

    if ($stmt->rowCount() === 0) {
        json_error('Khong tim thay nguoi dung de xoa.', 404);
    }

    json_success(['message' => 'Da xoa nguoi dung.']);
}

if ($action === 'bulk_delete_users') {
    $idsRaw = trim((string)($_POST['ids'] ?? ''));
    if ($idsRaw === '') {
        json_error('Chua chon nguoi dung de xoa.');
    }

    $ids = array_values(array_filter(array_map('intval', explode(',', $idsRaw)), fn($v) => $v > 0));
    if (!$ids) {
        json_error('Danh sach user khong hop le.');
    }

    if (blog_has_admin_role_column($conn)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $check = $conn->prepare("SELECT COUNT(*) FROM users WHERE id IN ({$placeholders}) AND role = 'admin'");
        $check->execute($ids);
        if ((int)$check->fetchColumn() > 0) {
            json_error('Danh sach chon co tai khoan admin, khong the xoa.', 422);
        }
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare("DELETE FROM users WHERE id IN ({$placeholders})");
    $stmt->execute($ids);

    json_success(['message' => 'Da xoa hang loat nguoi dung: ' . $stmt->rowCount()]);
}

if ($action === 'admins_list') {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = (int)($_GET['limit'] ?? 10);
    if (!in_array($limit, [10, 20, 30, 50], true)) {
        $limit = 10;
    }

    $search = trim((string)($_GET['search'] ?? ''));
    $sort = trim((string)($_GET['sort'] ?? 'newest'));

    if (!blog_has_admin_role_column($conn)) {
        json_success([
            'html' => '<tr><td colspan="7"><p class="empty">Schema hiện tại chưa có cột role để phân biệt admin.</p></td></tr>',
            'pagination' => '',
            'summary' => [
                'total' => 0,
                'page' => 1,
                'total_pages' => 1,
                'limit' => $limit,
            ],
        ]);
    }

    $where = ["u.role = 'admin'"];
    $params = [];
    if ($search !== '') {
        $where[] = '(u.name LIKE ? OR u.email LIKE ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }

    $orderBy = 'u.id DESC';
    if ($sort === 'oldest') {
        $orderBy = 'u.id ASC';
    } elseif ($sort === 'name_asc') {
        $orderBy = 'u.name ASC';
    } elseif ($sort === 'name_desc') {
        $orderBy = 'u.name DESC';
    }

    $whereSql = implode(' AND ', $where);
    $countStmt = $conn->prepare('SELECT COUNT(*) FROM users u WHERE ' . $whereSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $totalPages = max(1, (int)ceil($total / $limit));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $limit;

    $legacySelect = blog_has_legacy_admin_id_column($conn) ? 'u.legacy_admin_id' : 'u.id AS legacy_admin_id';
    $sql = "SELECT u.id, u.name, u.email, {$legacySelect} FROM users u WHERE {$whereSql} ORDER BY {$orderBy} LIMIT {$limit} OFFSET {$offset}";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $html = '';
    if (!$rows) {
        $html = '<tr><td colspan="7"><p class="empty">Khong co tai khoan admin.</p></td></tr>';
    } else {
        $index = $offset + 1;
        foreach ($rows as $row) {
            $adminIdentity = (int)($row['legacy_admin_id'] ?: $row['id']);
            $postCount = 0;
            $cStmt = $conn->prepare('SELECT COUNT(*) FROM posts WHERE admin_id = ?');
            $cStmt->execute([$adminIdentity]);
            $postCount = (int)$cStmt->fetchColumn();

            $html .= '<tr>';
            $html .= '<td><input type="checkbox" class="admin-bulk-admin-check" value="' . (int)$row['id'] . '"></td>';
            $html .= '<td>' . $index . '</td>';
            $html .= '<td>' . (int)$adminIdentity . '</td>';
            $html .= '<td>' . htmlspecialchars($row['name'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '<td>' . htmlspecialchars($row['email'] ?? '-', ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '<td>' . $postCount . '</td>';
            $html .= '<td class="admin-action-row">';
            $html .= '<button type="button" class="option-btn admin-edit-admin" data-admin-id="' . (int)$row['id'] . '" data-admin-name="' . htmlspecialchars($row['name'] ?? '', ENT_QUOTES, 'UTF-8') . '">Sua</button>';
            if ((int)$row['id'] !== (int)$admin_id) {
                $html .= '<button type="button" class="delete-btn admin-delete-admin" data-admin-id="' . (int)$row['id'] . '">Xoa</button>';
            } else {
                $html .= '<span class="admin-muted">Tai khoan hien tai</span>';
            }
            $html .= '</td>';
            $html .= '</tr>';
            $index++;
        }
    }

    json_success([
        'html' => $html,
        'pagination' => build_pagination_html($page, $totalPages),
        'summary' => [
            'total' => $total,
            'page' => $page,
            'total_pages' => $totalPages,
            'limit' => $limit,
        ],
    ]);
}

if ($action === 'create_admin') {
    $name = trim((string)($_POST['name'] ?? ''));
    $password = trim((string)($_POST['password'] ?? ''));

    if ($name === '' || $password === '') {
        json_error('Vui long nhap day du ten va mat khau.');
    }

    $existsSql = 'SELECT id FROM users WHERE name = ?';
    if (blog_has_admin_role_column($conn)) {
        $existsSql .= " AND role = 'admin'";
    }
    $existsSql .= ' LIMIT 1';
    $existsStmt = $conn->prepare($existsSql);
    $existsStmt->execute([$name]);
    if ($existsStmt->fetch(PDO::FETCH_ASSOC)) {
        json_error('Ten admin da ton tai.', 422);
    }

    $emailBase = strtolower(preg_replace('/[^a-z0-9]+/i', '', $name));
    if ($emailBase === '') {
        $emailBase = 'admin';
    }
    $email = $emailBase . '+admin@local.blog';
    $eStmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $suffix = 1;
    while (true) {
        $eStmt->execute([$email]);
        if ($eStmt->rowCount() === 0) {
            break;
        }
        $suffix++;
        $email = $emailBase . '+admin' . $suffix . '@local.blog';
    }

    $cols = ['name', 'email', 'password'];
    $vals = ['?', '?', '?'];
    $params = [$name, $email, password_hash($password, PASSWORD_DEFAULT)];

    if (blog_has_admin_role_column($conn)) {
        $cols[] = 'role';
        $vals[] = '?';
        $params[] = 'admin';
    }
    if (blog_has_legacy_admin_id_column($conn)) {
        $nextSql = 'SELECT COALESCE(MAX(legacy_admin_id),0) + 1 FROM users';
        if (blog_has_admin_role_column($conn)) {
            $nextSql .= " WHERE role = 'admin'";
        }
        $nextId = (int)$conn->query($nextSql)->fetchColumn();
        $cols[] = 'legacy_admin_id';
        $vals[] = '?';
        $params[] = $nextId;
    }

    if (blog_db_has_column($conn, 'users', 'is_verified')) {
        $cols[] = 'is_verified';
        $vals[] = '?';
        $params[] = 1;
    }

    if (blog_db_has_column($conn, 'users', 'verified_at')) {
        $cols[] = 'verified_at';
        $vals[] = '?';
        $params[] = gmdate('Y-m-d H:i:s');
    }

    if (blog_db_has_column($conn, 'users', 'verified_by_admin_id')) {
        $cols[] = 'verified_by_admin_id';
        $vals[] = '?';
        $params[] = (int)$admin_id;
    }

    $insertSql = 'INSERT INTO users(' . implode(',', $cols) . ') VALUES(' . implode(',', $vals) . ')';
    $ins = $conn->prepare($insertSql);
    $ins->execute($params);

    json_success(['message' => 'Da tao tai khoan admin moi.']);
}

if ($action === 'update_admin') {
    $targetId = (int)($_POST['admin_id'] ?? 0);
    $newName = trim((string)($_POST['name'] ?? ''));
    $newPassword = trim((string)($_POST['password'] ?? ''));

    if ($targetId <= 0 || $newName === '') {
        json_error('Thong tin cap nhat khong hop le.');
    }

    $checkSql = 'SELECT id FROM users WHERE id = ?';
    if (blog_has_admin_role_column($conn)) {
        $checkSql .= " AND role = 'admin'";
    }
    $checkSql .= ' LIMIT 1';
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->execute([$targetId]);
    if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
        json_error('Khong tim thay tai khoan admin.', 404);
    }

    $dupSql = 'SELECT id FROM users WHERE name = ? AND id != ?';
    if (blog_has_admin_role_column($conn)) {
        $dupSql .= " AND role = 'admin'";
    }
    $dupSql .= ' LIMIT 1';
    $dup = $conn->prepare($dupSql);
    $dup->execute([$newName, $targetId]);
    if ($dup->fetch(PDO::FETCH_ASSOC)) {
        json_error('Ten admin da ton tai.', 422);
    }

    if ($newPassword !== '') {
        $update = $conn->prepare('UPDATE users SET name = ?, password = ? WHERE id = ?');
        $update->execute([$newName, password_hash($newPassword, PASSWORD_DEFAULT), $targetId]);
    } else {
        $update = $conn->prepare('UPDATE users SET name = ? WHERE id = ?');
        $update->execute([$newName, $targetId]);
    }

    json_success(['message' => 'Da cap nhat tai khoan admin.']);
}

if ($action === 'delete_admin') {
    $targetId = (int)($_POST['admin_id'] ?? 0);
    if ($targetId <= 0) {
        json_error('Admin ID khong hop le.');
    }
    if ($targetId === $admin_id) {
        json_error('Khong the xoa tai khoan dang dang nhap.', 422);
    }

    $sql = 'DELETE FROM users WHERE id = ?';
    if (blog_has_admin_role_column($conn)) {
        $sql .= " AND role = 'admin'";
    }
    $stmt = $conn->prepare($sql);
    $stmt->execute([$targetId]);

    if ($stmt->rowCount() === 0) {
        json_error('Khong tim thay admin de xoa.', 404);
    }

    json_success(['message' => 'Da xoa admin.']);
}

if ($action === 'bulk_delete_admins') {
    $idsRaw = trim((string)($_POST['ids'] ?? ''));
    if ($idsRaw === '') {
        json_error('Chua chon admin de xoa.');
    }
    $ids = array_values(array_filter(array_map('intval', explode(',', $idsRaw)), fn($v) => $v > 0 && $v !== (int)$admin_id));
    if (!$ids) {
        json_error('Danh sach admin khong hop le.');
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "DELETE FROM users WHERE id IN ({$placeholders})";
    if (blog_has_admin_role_column($conn)) {
        $sql .= " AND role = 'admin'";
    }
    $stmt = $conn->prepare($sql);
    $stmt->execute($ids);
    json_success(['message' => 'Da xoa hang loat admin: ' . $stmt->rowCount()]);
}

if ($action === 'carts_list') {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = (int)($_GET['limit'] ?? 10);
    if (!in_array($limit, [10, 20, 30, 50], true)) {
        $limit = 10;
    }

    $search = trim((string)($_GET['search'] ?? ''));
    $sort = trim((string)($_GET['sort'] ?? 'newest'));

    $where = ['1=1'];
    $params = [];

    if ($search !== '') {
        $where[] = 'c.name LIKE ?';
        $params[] = '%' . $search . '%';
    }

    $orderBy = 'c.category_id DESC';
    if ($sort === 'oldest') {
        $orderBy = 'c.category_id ASC';
    } elseif ($sort === 'name_asc') {
        $orderBy = 'c.name ASC';
    } elseif ($sort === 'name_desc') {
        $orderBy = 'c.name DESC';
    } elseif ($sort === 'posts_desc') {
        $orderBy = 'num_posts DESC, c.category_id DESC';
    }

    $whereSql = implode(' AND ', $where);

    $countStmt = $conn->prepare('SELECT COUNT(*) FROM cart c WHERE ' . $whereSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $totalPages = max(1, (int)ceil($total / $limit));
    if ($page > $totalPages) {
        $page = $totalPages;
    }

    $offset = ($page - 1) * $limit;

    $sql = "SELECT
            c.category_id,
            c.name,
            COALESCE((SELECT COUNT(*) FROM posts p WHERE p.tag_id = c.category_id), 0) AS num_posts
        FROM cart c
        WHERE {$whereSql}
        ORDER BY {$orderBy}
        LIMIT {$limit} OFFSET {$offset}";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $html = '';
    if (!$rows) {
        $html = '<tr><td colspan="5"><p class="empty">Không có danh mục phù hợp.</p></td></tr>';
    } else {
        $index = $offset + 1;
        foreach ($rows as $row) {
            $html .= '<tr>';
            $html .= '<td>' . $index . '</td>';
            $html .= '<td>' . (int)$row['category_id'] . '</td>';
            $html .= '<td>' . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '<td><a href="view_posts.php" class="admin-link-plain">' . (int)$row['num_posts'] . '</a></td>';
            $html .= '<td class="admin-action-row">';
            $html .= '<button type="button" class="option-btn admin-edit-cart" data-cart-id="' . (int)$row['category_id'] . '" data-cart-name="' . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . '">Sua</button>';
            $html .= '<button type="button" class="delete-btn admin-delete-cart" data-cart-id="' . (int)$row['category_id'] . '">Xoa</button>';
            $html .= '</td>';
            $html .= '</tr>';
            $index++;
        }
    }

    json_success([
        'html' => $html,
        'pagination' => build_pagination_html($page, $totalPages),
        'summary' => [
            'total' => $total,
            'page' => $page,
            'total_pages' => $totalPages,
            'limit' => $limit,
        ],
    ]);
}

if ($action === 'add_cart') {
    $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '') {
        json_error('Vui long nhap ten danh muc.');
    }

    $exists = $conn->prepare('SELECT category_id FROM cart WHERE name = ? LIMIT 1');
    $exists->execute([$name]);
    if ($exists->fetch(PDO::FETCH_ASSOC)) {
        json_error('Danh muc da ton tai.', 422);
    }

    $stmt = $conn->prepare('INSERT INTO cart (admin_id, name) VALUES (?, ?)');
    $stmt->execute([$admin_id, $name]);

    json_success(['message' => 'Da them danh muc moi.']);
}

if ($action === 'edit_cart') {
    $cart_id = (int)($_POST['cart_id'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));

    if ($cart_id <= 0 || $name === '') {
        json_error('Thong tin cap nhat khong hop le.');
    }

    $exists = $conn->prepare('SELECT category_id FROM cart WHERE name = ? AND category_id != ? LIMIT 1');
    $exists->execute([$name, $cart_id]);
    if ($exists->fetch(PDO::FETCH_ASSOC)) {
        json_error('Ten danh muc da ton tai.', 422);
    }

    $stmt = $conn->prepare('UPDATE cart SET name = ? WHERE category_id = ?');
    $stmt->execute([$name, $cart_id]);

    if ($stmt->rowCount() === 0) {
        json_error('Khong tim thay danh muc de cap nhat.', 404);
    }

    json_success(['message' => 'Da cap nhat danh muc.']);
}

if ($action === 'delete_cart') {
    $cart_id = (int)($_POST['cart_id'] ?? 0);
    if ($cart_id <= 0) {
        json_error('ID danh muc khong hop le.');
    }

    $stmt = $conn->prepare('DELETE FROM cart WHERE category_id = ?');
    $stmt->execute([$cart_id]);

    if ($stmt->rowCount() === 0) {
        json_error('Khong tim thay danh muc de xoa.', 404);
    }

    json_success(['message' => 'Da xoa danh muc.']);
}

if ($action === 'bulk_delete_posts') {
    $idsRaw = trim((string)($_POST['ids'] ?? ''));
    if ($idsRaw === '') {
        json_error('Chua chon bai viet de xoa.');
    }
    $ids = array_values(array_filter(array_map('intval', explode(',', $idsRaw)), fn($v) => $v > 0));
    if (!$ids) {
        json_error('Danh sach bai viet khong hop le.');
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $params = $ids;

    $imgStmt = $conn->prepare("SELECT image FROM posts WHERE id IN ({$placeholders})");
    $imgStmt->execute($params);
    $images = $imgStmt->fetchAll(PDO::FETCH_COLUMN);

    $deletePosts = $conn->prepare("DELETE FROM posts WHERE id IN ({$placeholders})");
    $deletePosts->execute($params);

    foreach ($ids as $id) {
        $conn->prepare('DELETE FROM comments WHERE post_id = ?')->execute([(int)$id]);
        $conn->prepare('DELETE FROM likes WHERE post_id = ?')->execute([(int)$id]);
    }

    foreach ($images as $img) {
        if ($img) {
            $path = '../uploaded_img/' . $img;
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    json_success(['message' => 'Da xoa hang loat bai viet: ' . $deletePosts->rowCount()]);
}

if ($action === 'bulk_delete_comments') {
    $idsRaw = trim((string)($_POST['ids'] ?? ''));
    if ($idsRaw === '') {
        json_error('Chua chon binh luan de xoa.');
    }
    $ids = array_values(array_filter(array_map('intval', explode(',', $idsRaw)), fn($v) => $v > 0));
    if (!$ids) {
        json_error('Danh sach binh luan khong hop le.');
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $params = $ids;
    $stmt = $conn->prepare("DELETE FROM comments WHERE id IN ({$placeholders})");
    $stmt->execute($params);
    json_success(['message' => 'Da xoa hang loat binh luan: ' . $stmt->rowCount()]);
}

if ($action === 'delete_post') {
    $post_id = (int)($_POST['post_id'] ?? 0);
    if ($post_id <= 0) {
        json_error('ID bai viet khong hop le.');
    }

    $selectPost = $conn->prepare('SELECT image FROM posts WHERE id = ? LIMIT 1');
    $selectPost->execute([$post_id]);
    $post = $selectPost->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        json_error('Bai viet khong ton tai hoac ban khong co quyen.', 404);
    }

    $conn->prepare('DELETE FROM posts WHERE id = ?')->execute([$post_id]);
    $conn->prepare('DELETE FROM comments WHERE post_id = ?')->execute([$post_id]);
    $conn->prepare('DELETE FROM likes WHERE post_id = ?')->execute([$post_id]);

    if (!empty($post['image'])) {
        $filePath = '../uploaded_img/' . $post['image'];
        if (is_file($filePath)) {
            @unlink($filePath);
        }
    }

    json_success(['message' => 'Da xoa bai viet thanh cong.']);
}

if ($action === 'delete_comment') {
    $comment_id = (int)($_POST['comment_id'] ?? 0);
    if ($comment_id <= 0) {
        json_error('ID binh luan khong hop le.');
    }

    $stmt = $conn->prepare('DELETE FROM comments WHERE id = ?');
    $stmt->execute([$comment_id]);

    if ($stmt->rowCount() === 0) {
        json_error('Khong tim thay binh luan de xoa.', 404);
    }

    json_success(['message' => 'Da xoa binh luan.']);
}

json_error('Action khong hop le.', 404);

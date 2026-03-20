<?php
include '../components/connect.php';
include '../components/community_engine.php';
session_start();

$admin_id = $_SESSION['admin_id'] ?? null;
if (!$admin_id) {
    header('location:admin_login.php');
    exit;
}

community_ensure_tables($conn);
$message = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $singleAction = trim((string)($_POST['single_action'] ?? ''));
    $singlePostId = (int)($_POST['single_post_id'] ?? 0);

    if ($singlePostId > 0 && in_array($singleAction, ['publish', 'hide', 'delete'], true)) {
        if ($singleAction === 'delete') {
            $stmt = $conn->prepare('DELETE FROM community_posts WHERE id = ?');
            $stmt->execute([$singlePostId]);
            $message[] = 'Đã xóa bài viết cộng đồng.';
        } else {
            $statusValue = $singleAction === 'publish' ? 'published' : 'hidden';
            $stmt = $conn->prepare('UPDATE community_posts SET status = ? WHERE id = ?');
            $stmt->execute([$statusValue, $singlePostId]);
            $message[] = $singleAction === 'publish' ? 'Đã hiển thị lại bài viết.' : 'Đã ẩn bài viết.';
        }
    }

    $bulkAction = trim((string)($_POST['bulk_action'] ?? ''));
    $selected = $_POST['selected_post_ids'] ?? [];
    $selected = is_array($selected) ? array_map('intval', $selected) : [];
    $selected = array_values(array_filter(array_unique($selected), function ($id) {
        return $id > 0;
    }));

    if (!empty($selected) && in_array($bulkAction, ['publish', 'hide', 'delete'], true)) {
        $placeholders = implode(',', array_fill(0, count($selected), '?'));
        if ($bulkAction === 'delete') {
            $stmt = $conn->prepare("DELETE FROM community_posts WHERE id IN ({$placeholders})");
            $stmt->execute($selected);
            $message[] = 'Đã xóa các bài community đã chọn.';
        } else {
            $bulkStatus = $bulkAction === 'publish' ? 'published' : 'hidden';
            $params = array_merge([$bulkStatus], $selected);
            $stmt = $conn->prepare("UPDATE community_posts SET status = ? WHERE id IN ({$placeholders})");
            $stmt->execute($params);
            $message[] = $bulkAction === 'publish' ? 'Đã hiển thị lại bài đã chọn.' : 'Đã ẩn bài đã chọn.';
        }
    }
}

$q = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? 'all'));
$topicSlug = trim((string)($_GET['topic'] ?? 'all'));
$sort = trim((string)($_GET['sort'] ?? 'newest'));
$perPage = (int)($_GET['per_page'] ?? 10);
$perPage = in_array($perPage, [10, 20, 50, 100], true) ? $perPage : 10;
$page = max(1, (int)($_GET['page'] ?? 1));

$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(cp.content LIKE ? OR cp.user_name LIKE ? OR CAST(cp.id AS CHAR) LIKE ?)';
    $qLike = '%' . $q . '%';
    $params[] = $qLike;
    $params[] = $qLike;
    $params[] = $qLike;
}

if (in_array($status, ['published', 'hidden', 'draft', 'deleted'], true)) {
    $where[] = 'cp.status = ?';
    $params[] = $status;
}

$allTopicsStmt = $conn->query('SELECT slug, name FROM community_topics ORDER BY name ASC');
$allTopics = $allTopicsStmt ? $allTopicsStmt->fetchAll(PDO::FETCH_ASSOC) : [];
$allowedTopicSlugs = array_map(function ($row) {
    return (string)($row['slug'] ?? '');
}, $allTopics);

if ($topicSlug !== 'all' && in_array($topicSlug, $allowedTopicSlugs, true)) {
    $where[] = 'EXISTS (
        SELECT 1
        FROM community_post_topics cpt
        INNER JOIN community_topics ct ON ct.id = cpt.topic_id
        WHERE cpt.post_id = cp.id AND ct.slug = ?
    )';
    $params[] = $topicSlug;
}

$whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

$sortSql = 'cp.created_at DESC';
if ($sort === 'oldest') {
    $sortSql = 'cp.created_at ASC';
} elseif ($sort === 'most_liked') {
    $sortSql = 'cp.total_reactions DESC, cp.created_at DESC';
} elseif ($sort === 'most_commented') {
    $sortSql = 'cp.total_comments DESC, cp.created_at DESC';
}

$countStmt = $conn->prepare("SELECT COUNT(*) FROM community_posts cp {$whereSql}");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$listSql = "
    SELECT
        cp.id,
        cp.user_id,
        cp.user_name,
        cp.content,
        cp.status,
        cp.privacy,
        cp.total_reactions,
        cp.total_comments,
        cp.created_at,
        COALESCE(pm.media_count, 0) AS media_count
    FROM community_posts cp
    LEFT JOIN (
        SELECT post_id, COUNT(*) AS media_count
        FROM community_post_media
        GROUP BY post_id
    ) pm ON pm.post_id = cp.id
    {$whereSql}
    ORDER BY {$sortSql}
    LIMIT {$perPage} OFFSET {$offset}
";
$listStmt = $conn->prepare($listSql);
$listStmt->execute($params);
$posts = $listStmt->fetchAll(PDO::FETCH_ASSOC);

$postIds = array_map(function ($row) {
    return (int)$row['id'];
}, $posts);
$postIds = array_values(array_filter($postIds, function ($id) {
    return $id > 0;
}));

$topicsByPost = [];
if (!empty($postIds)) {
    $postPlaceholders = implode(',', array_fill(0, count($postIds), '?'));
    $topicStmt = $conn->prepare("SELECT cpt.post_id, ct.name
        FROM community_post_topics cpt
        INNER JOIN community_topics ct ON ct.id = cpt.topic_id
        WHERE cpt.post_id IN ({$postPlaceholders})
        ORDER BY ct.name ASC");
    $topicStmt->execute($postIds);
    while ($row = $topicStmt->fetch(PDO::FETCH_ASSOC)) {
        $pid = (int)$row['post_id'];
        if (!isset($topicsByPost[$pid])) {
            $topicsByPost[$pid] = [];
        }
        $topicsByPost[$pid][] = (string)$row['name'];
    }
}

$statsStmt = $conn->query("SELECT
    COUNT(*) AS total_posts,
    SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) AS published_posts,
    SUM(CASE WHEN status = 'hidden' THEN 1 ELSE 0 END) AS hidden_posts,
    SUM(total_reactions) AS total_reactions,
    SUM(total_comments) AS total_comments
FROM community_posts");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

function communityAdminPageUrl($targetPage)
{
    $query = $_GET;
    $query['page'] = $targetPage;
    return 'community_posts.php?' . http_build_query($query);
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Community</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
        .community-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.4rem 0.85rem;
            border-radius: 999px;
            font-size: 1.15rem;
            font-weight: 700;
            border: 1px solid transparent;
        }

        .community-status-badge::before {
            content: '';
            width: 0.7rem;
            height: 0.7rem;
            border-radius: 999px;
            background: currentColor;
            opacity: 0.85;
        }

        .community-status-published {
            color: #166534;
            background: #dcfce7;
            border-color: #86efac;
        }

        .community-status-hidden {
            color: #92400e;
            background: #fef3c7;
            border-color: #fcd34d;
        }

        .community-status-draft {
            color: #1e3a8a;
            background: #dbeafe;
            border-color: #93c5fd;
        }

        .community-status-deleted {
            color: #991b1b;
            background: #fee2e2;
            border-color: #fca5a5;
        }

        .community-content-preview {
            max-width: 44rem;
            display: -webkit-box;
            line-clamp: 2;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.5;
            word-break: break-word;
        }

        .community-topic-chip {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 0.25rem 0.7rem;
            font-size: 1.12rem;
            font-weight: 600;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            color: #334155;
        }

        .community-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .community-row-btn {
            min-width: 8.6rem !important;
            padding: 0.7rem 0.85rem !important;
            font-size: 1.2rem !important;
            border-radius: 9px !important;
        }

        .community-detail-modal {
            position: fixed;
            inset: 0;
            z-index: 1600;
            background: rgba(2, 6, 23, 0.56);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1.4rem;
        }

        .community-detail-modal.open {
            display: flex;
        }

        .community-detail-card {
            width: min(76rem, 100%);
            max-height: 88vh;
            overflow: auto;
            background: #ffffff;
            border: 1px solid #d7e1ec;
            border-radius: 16px;
            box-shadow: 0 28px 60px rgba(15, 23, 42, 0.28);
        }

        .community-detail-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.8rem;
        }

        @media (max-width: 900px) {
            .community-content-preview {
                max-width: 28rem;
                line-clamp: 3;
                -webkit-line-clamp: 3;
            }
        }

        @media (max-width: 768px) {
            .community-detail-grid {
                grid-template-columns: 1fr;
            }

            .community-actions {
                flex-direction: column;
            }

            .community-row-btn {
                width: 100%;
            }

            .community-content-preview {
                max-width: 100%;
            }
        }
    </style>
</head>

<body class="ui-page">
    <?php include '../components/admin_header.php'; ?>

    <section class="ui-container">
        <h1 class="heading">Quản lý bài viết từ cộng đồng</h1>

        <?php if (!empty($message)): ?>
            <article class="admin-panel-card" style="margin-bottom:1rem;">
                <?php foreach ($message as $msg): ?>
                    <p class="ui-muted" style="margin:.2rem 0;"><?= htmlspecialchars((string)$msg, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endforeach; ?>
            </article>
        <?php endif; ?>

        <div class="admin-kpi-grid" style="margin-bottom:1rem;">
            <article class="admin-kpi-card">
                <h3><?= (int)($stats['total_posts'] ?? 0); ?></h3>
                <p>Tổng bài community</p>
            </article>
            <article class="admin-kpi-card">
                <h3><?= (int)($stats['published_posts'] ?? 0); ?></h3>
                <p>Đang hiển thị</p>
            </article>
            <article class="admin-kpi-card">
                <h3><?= (int)($stats['hidden_posts'] ?? 0); ?></h3>
                <p>Đang ẩn</p>
            </article>
            <article class="admin-kpi-card">
                <h3><?= (int)($stats['total_reactions'] ?? 0); ?></h3>
                <p>Tổng lượt thích</p>
            </article>
        </div>

        <article class="admin-panel-card" style="margin-bottom:1.2rem;">
            <form method="get" style="display:flex;gap:.8rem;flex-wrap:wrap;align-items:center;">
                <input type="text" name="q" class="box" placeholder="Tìm theo ID, người đăng, nội dung" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">

                <select name="status" class="box ui-select">
                    <option value="all" <?= $status === 'all' ? 'selected' : ''; ?>>Tất cả trạng thái</option>
                    <option value="published" <?= $status === 'published' ? 'selected' : ''; ?>>Đang hiển thị</option>
                    <option value="hidden" <?= $status === 'hidden' ? 'selected' : ''; ?>>Đang ẩn</option>
                    <option value="draft" <?= $status === 'draft' ? 'selected' : ''; ?>>Bản nháp</option>
                    <option value="deleted" <?= $status === 'deleted' ? 'selected' : ''; ?>>Đã xóa</option>
                </select>

                <select name="topic" class="box ui-select">
                    <option value="all" <?= $topicSlug === 'all' ? 'selected' : ''; ?>>Tất cả chủ đề</option>
                    <?php foreach ($allTopics as $topicRow): ?>
                        <?php
                        $slug = (string)($topicRow['slug'] ?? '');
                        $name = (string)($topicRow['name'] ?? '');
                        if ($slug === '' || $name === '') {
                            continue;
                        }
                        ?>
                        <option value="<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'); ?>" <?= $topicSlug === $slug ? 'selected' : ''; ?>><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="sort" class="box ui-select">
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : ''; ?>>Mới nhất</option>
                    <option value="oldest" <?= $sort === 'oldest' ? 'selected' : ''; ?>>Cũ nhất</option>
                    <option value="most_liked" <?= $sort === 'most_liked' ? 'selected' : ''; ?>>Nhiều like nhất</option>
                    <option value="most_commented" <?= $sort === 'most_commented' ? 'selected' : ''; ?>>Nhiều bình luận nhất</option>
                </select>

                <select name="per_page" class="box ui-select">
                    <option value="10" <?= $perPage === 10 ? 'selected' : ''; ?>>10 / trang</option>
                    <option value="20" <?= $perPage === 20 ? 'selected' : ''; ?>>20 / trang</option>
                    <option value="50" <?= $perPage === 50 ? 'selected' : ''; ?>>50 / trang</option>
                    <option value="100" <?= $perPage === 100 ? 'selected' : ''; ?>>100 / trang</option>
                </select>

                <input type="hidden" name="page" value="1">
                <button type="submit" class="btn ui-btn">Lọc</button>
                <a href="community_posts.php" class="delete-btn ui-btn-danger" style="text-decoration:none;display:inline-flex;align-items:center;">Reset</a>
            </form>
        </article>

        <div class="box-container ui-card">
            <form method="post" action="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'community_posts.php', ENT_QUOTES, 'UTF-8'); ?>" onsubmit="return confirm('Bạn chắc chắn thực hiện thao tác này?');">
                <div style="display:flex;gap:.8rem;flex-wrap:wrap;align-items:center;margin-bottom:1rem;">
                    <label style="display:flex;align-items:center;gap:.4rem;">
                        <input type="checkbox" id="checkAllCommunityPosts"> Chọn tất cả
                    </label>
                    <select name="bulk_action" class="box ui-select" required>
                        <option value="">-- Chọn thao tác --</option>
                        <option value="publish">Hiển thị bài đã chọn</option>
                        <option value="hide">Ẩn bài đã chọn</option>
                        <option value="delete">Xóa bài đã chọn</option>
                    </select>
                    <button type="submit" class="btn ui-btn">Thực hiện</button>
                </div>

                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th></th>
                                <th>ID</th>
                                <th>Nguoi dang</th>
                                <th>Noi dung</th>
                                <th>Chu de</th>
                                <th>Anh</th>
                                <th>Like</th>
                                <th>Comment</th>
                                <th>Trang thai</th>
                                <th>Ngay dang</th>
                                <th>Thao tac</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($posts)): ?>
                                <?php foreach ($posts as $post): ?>
                                    <?php
                                    $postId = (int)$post['id'];
                                    $topicNames = $topicsByPost[$postId] ?? [];
                                    $statusLabel = (string)$post['status'];

                                    $statusClass = 'community-status-draft';
                                    $statusText = 'Bản nháp';
                                    if ($statusLabel === 'published') {
                                        $statusClass = 'community-status-published';
                                        $statusText = 'Đang hiển thị';
                                    } elseif ($statusLabel === 'hidden') {
                                        $statusClass = 'community-status-hidden';
                                        $statusText = 'Đang ẩn';
                                    } elseif ($statusLabel === 'deleted') {
                                        $statusClass = 'community-status-deleted';
                                        $statusText = 'Đã xóa';
                                    }

                                    $detailPayload = [
                                        'id' => $postId,
                                        'user_name' => (string)$post['user_name'],
                                        'created_at' => (string)$post['created_at'],
                                        'status' => $statusText,
                                        'privacy' => (string)$post['privacy'],
                                        'content' => (string)$post['content'],
                                        'topics' => $topicNames,
                                        'media_count' => (int)$post['media_count'],
                                        'total_reactions' => (int)$post['total_reactions'],
                                        'total_comments' => (int)$post['total_comments'],
                                    ];
                                    $detailJson = htmlspecialchars(json_encode($detailPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <tr>
                                        <td><input type="checkbox" class="row-check-community-post" name="selected_post_ids[]" value="<?= $postId; ?>"></td>
                                        <td>#<?= $postId; ?></td>
                                        <td><?= htmlspecialchars((string)$post['user_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <div class="community-content-preview" title="<?= htmlspecialchars((string)$post['content'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <?= htmlspecialchars((string)$post['content'], ENT_QUOTES, 'UTF-8'); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($topicNames)): ?>
                                                <?php foreach ($topicNames as $topicName): ?>
                                                    <span class="community-topic-chip">#<?= htmlspecialchars((string)$topicName, ENT_QUOTES, 'UTF-8'); ?></span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="ui-muted">Không có</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= (int)$post['media_count']; ?></td>
                                        <td><?= (int)$post['total_reactions']; ?></td>
                                        <td><?= (int)$post['total_comments']; ?></td>
                                        <td><span class="community-status-badge <?= $statusClass; ?>"><?= htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <td><?= htmlspecialchars((string)$post['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <div class="community-actions">
                                                <button type="button" class="option-btn ui-btn-warning community-row-btn" data-community-open-detail data-community-detail='<?= $detailJson; ?>'>Chi tiết</button>
                                                <?php if ($statusLabel === 'published'): ?>
                                                    <button type="submit" class="btn ui-btn community-row-btn" name="single_action" value="hide" formaction="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'community_posts.php', ENT_QUOTES, 'UTF-8'); ?>" onclick="document.getElementById('single-post-id').value='<?= $postId; ?>';">Ẩn</button>
                                                <?php else: ?>
                                                    <button type="submit" class="btn ui-btn community-row-btn" name="single_action" value="publish" formaction="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'community_posts.php', ENT_QUOTES, 'UTF-8'); ?>" onclick="document.getElementById('single-post-id').value='<?= $postId; ?>';">Hiện</button>
                                                <?php endif; ?>
                                                <button type="submit" class="delete-btn ui-btn-danger community-row-btn" name="single_action" value="delete" formaction="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'community_posts.php', ENT_QUOTES, 'UTF-8'); ?>" onclick="if(!confirm('Bạn có chắc muốn xóa bài này?')){return false;} document.getElementById('single-post-id').value='<?= $postId; ?>';">Xóa</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="11">Không tìm thấy bài community nào.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <input type="hidden" id="single-post-id" name="single_post_id" value="0">

                <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin-top:1rem;">
                    <div>Trang <?= (int)$page; ?>/<?= (int)$totalPages; ?> - Tổng: <?= (int)$totalRows; ?> bài community</div>
                    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                        <?php if ($page > 1): ?>
                            <a class="option-btn ui-btn-warning" href="<?= htmlspecialchars(communityAdminPageUrl(1), ENT_QUOTES, 'UTF-8'); ?>">Đầu</a>
                            <a class="option-btn ui-btn-warning" href="<?= htmlspecialchars(communityAdminPageUrl($page - 1), ENT_QUOTES, 'UTF-8'); ?>">Trước</a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a class="btn ui-btn" href="<?= htmlspecialchars(communityAdminPageUrl($page + 1), ENT_QUOTES, 'UTF-8'); ?>">Sau</a>
                            <a class="btn ui-btn" href="<?= htmlspecialchars(communityAdminPageUrl($totalPages), ENT_QUOTES, 'UTF-8'); ?>">Cuối</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <div id="community-detail-modal" class="community-detail-modal" aria-hidden="true">
        <div class="community-detail-card">
            <div style="padding:1.2rem 1.4rem;border-bottom:1px solid #d7e1ec;display:flex;align-items:center;justify-content:space-between;gap:1rem;">
                <h3 style="margin:0;font-size:1.8rem;color:#0f172a;">Chi tiết bài viết community</h3>
                <button type="button" id="community-detail-close" class="option-btn ui-btn-warning community-row-btn" style="min-width:4.6rem!important;">Đóng</button>
            </div>
            <div style="padding:1.4rem;">
                <div class="community-detail-grid" style="margin-bottom:1.1rem;">
                    <div class="admin-kpi-chip"><strong>ID:</strong> <span id="detail-id">-</span></div>
                    <div class="admin-kpi-chip"><strong>Người đăng:</strong> <span id="detail-user">-</span></div>
                    <div class="admin-kpi-chip"><strong>Ngày đăng:</strong> <span id="detail-date">-</span></div>
                    <div class="admin-kpi-chip"><strong>Trạng thái:</strong> <span id="detail-status">-</span></div>
                    <div class="admin-kpi-chip"><strong>Quyền riêng tư:</strong> <span id="detail-privacy">-</span></div>
                    <div class="admin-kpi-chip"><strong>Ảnh đính kèm:</strong> <span id="detail-media">0</span></div>
                    <div class="admin-kpi-chip"><strong>Lượt thích:</strong> <span id="detail-like">0</span></div>
                    <div class="admin-kpi-chip"><strong>Bình luận:</strong> <span id="detail-comment">0</span></div>
                </div>

                <div style="margin-bottom:1rem;">
                    <div style="font-size:1.3rem;font-weight:700;color:#0f172a;margin-bottom:.5rem;">Chủ đề</div>
                    <div id="detail-topics" style="display:flex;gap:.45rem;flex-wrap:wrap;"></div>
                </div>

                <div>
                    <div style="font-size:1.3rem;font-weight:700;color:#0f172a;margin-bottom:.5rem;">Nội dung đầy đủ</div>
                    <div id="detail-content" style="border:1px solid #d7e1ec;border-radius:12px;padding:1rem;line-height:1.6;white-space:pre-line;word-break:break-word;background:#f8fafc;"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const checkAllCommunityPosts = document.getElementById('checkAllCommunityPosts');
        if (checkAllCommunityPosts) {
            checkAllCommunityPosts.addEventListener('change', function() {
                const checked = !!checkAllCommunityPosts.checked;
                document.querySelectorAll('.row-check-community-post').forEach(function(item) {
                    item.checked = checked;
                });
            });
        }

        const detailModal = document.getElementById('community-detail-modal');
        const detailCloseBtn = document.getElementById('community-detail-close');

        function openCommunityDetail(payload) {
            if (!detailModal || !payload) {
                return;
            }

            document.getElementById('detail-id').textContent = '#' + (payload.id || '-');
            document.getElementById('detail-user').textContent = payload.user_name || '-';
            document.getElementById('detail-date').textContent = payload.created_at || '-';
            document.getElementById('detail-status').textContent = payload.status || '-';
            document.getElementById('detail-privacy').textContent = payload.privacy || '-';
            document.getElementById('detail-media').textContent = String(payload.media_count || 0);
            document.getElementById('detail-like').textContent = String(payload.total_reactions || 0);
            document.getElementById('detail-comment').textContent = String(payload.total_comments || 0);
            document.getElementById('detail-content').textContent = payload.content || '';

            const topicsWrap = document.getElementById('detail-topics');
            topicsWrap.innerHTML = '';
            if (Array.isArray(payload.topics) && payload.topics.length > 0) {
                payload.topics.forEach(function(topic) {
                    const chip = document.createElement('span');
                    chip.className = 'community-topic-chip';
                    chip.textContent = '#' + String(topic || '').trim();
                    topicsWrap.appendChild(chip);
                });
            } else {
                const muted = document.createElement('span');
                muted.className = 'ui-muted';
                muted.textContent = 'Không có chủ đề';
                topicsWrap.appendChild(muted);
            }

            detailModal.classList.add('open');
            detailModal.setAttribute('aria-hidden', 'false');
        }

        function closeCommunityDetail() {
            if (!detailModal) {
                return;
            }
            detailModal.classList.remove('open');
            detailModal.setAttribute('aria-hidden', 'true');
        }

        document.addEventListener('click', function(event) {
            const openBtn = event.target.closest('[data-community-open-detail]');
            if (openBtn) {
                const raw = openBtn.getAttribute('data-community-detail') || '';
                try {
                    const payload = JSON.parse(raw);
                    openCommunityDetail(payload);
                } catch (err) {
                    // Ignore invalid payload silently.
                }
                return;
            }

            if (event.target === detailModal) {
                closeCommunityDetail();
            }
        });

        if (detailCloseBtn) {
            detailCloseBtn.addEventListener('click', closeCommunityDetail);
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeCommunityDetail();
            }
        });
    </script>

    <script src="../js/admin_script.js"></script>
</body>

</html>
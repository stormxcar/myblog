<?php
include '../components/connect.php';
include '../components/community_engine.php';

session_start();

$admin_id = $_SESSION['admin_id'] ?? null;
if (!isset($admin_id)) {
    header('location:../static/login.php?next=admin');
    exit;
}

community_ensure_tables($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reportId = (int)($_POST['report_id'] ?? 0);
    $nextStatus = trim((string)($_POST['status'] ?? 'pending'));
    $allowedStatuses = ['pending', 'reviewed', 'dismissed'];

    if ($reportId > 0 && in_array($nextStatus, $allowedStatuses, true)) {
        $updateStmt = $conn->prepare('UPDATE community_post_reports SET status = ? WHERE id = ?');
        $updateStmt->execute([$nextStatus, $reportId]);
        $_SESSION['message'] = 'Da cap nhat trang thai bao cao.';
    } else {
        $_SESSION['message'] = 'Du lieu cap nhat khong hop le.';
    }

    header('Location: community_reports.php');
    exit;
}

$filterStatus = trim((string)($_GET['status'] ?? 'all'));
$where = '';
$params = [];
if (in_array($filterStatus, ['pending', 'reviewed', 'dismissed'], true)) {
    $where = ' WHERE r.status = ? ';
    $params[] = $filterStatus;
}

$listStmt = $conn->prepare("SELECT r.id, r.post_id, r.user_id, r.reason, r.status, r.created_at, p.post_title, p.user_name
    FROM community_post_reports r
    INNER JOIN community_posts p ON p.id = r.post_id
    {$where}
    ORDER BY r.created_at DESC
    LIMIT 300");
$listStmt->execute($params);
$reports = $listStmt->fetchAll(PDO::FETCH_ASSOC);

$countStmt = $conn->query("SELECT status, COUNT(*) AS total FROM community_post_reports GROUP BY status");
$statusMap = ['pending' => 0, 'reviewed' => 0, 'dismissed' => 0];
foreach ($countStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $key = (string)$row['status'];
    if (isset($statusMap[$key])) {
        $statusMap[$key] = (int)$row['total'];
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Bao cao cong dong</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
</head>

<body class="ui-page">
    <?php include '../components/admin_header.php'; ?>

    <section class="dashboard ui-container">
        <div class="admin-page-topbar">
            <div>
                <h1 class="heading">Báo cáo cộng đồng</h1>
                <p class="admin-subheading">Duyệt và đổi trạng thái báo cáo: pending, reviewed, dismissed.</p>
            </div>
        </div>

        <div class="admin-kpi-grid" style="margin-bottom:1.6rem;">
            <article class="admin-kpi-card">
                <h3><?= (int)$statusMap['pending']; ?></h3>
                <p>Pending</p>
            </article>
            <article class="admin-kpi-card">
                <h3><?= (int)$statusMap['reviewed']; ?></h3>
                <p>Reviewed</p>
            </article>
            <article class="admin-kpi-card">
                <h3><?= (int)$statusMap['dismissed']; ?></h3>
                <p>Dismissed</p>
            </article>
        </div>

        <div class="admin-card" style="padding:1.2rem 1.4rem; margin-bottom:1rem;">
            <form method="get" class="flex" style="gap:.6rem; align-items:center; flex-wrap:wrap;">
                <label for="status" style="font-weight:600;">Lọc trạng thái</label>
                <select id="status" name="status" class="box" style="max-width:240px;">
                    <option value="all" <?= $filterStatus === 'all' ? 'selected' : ''; ?>>Tất cả</option>
                    <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="reviewed" <?= $filterStatus === 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                    <option value="dismissed" <?= $filterStatus === 'dismissed' ? 'selected' : ''; ?>>Dismissed</option>
                </select>
                <button type="submit" class="btn">Áp dụng</button>
            </form>
        </div>

        <div class="admin-card" style="padding:1rem; overflow:auto;">
            <table style="width:100%; min-width:920px; border-collapse:collapse;">
                <thead>
                    <tr style="text-align:left; border-bottom:1px solid #e5e7eb;">
                        <th style="padding:.7rem;">Bài viết</th>
                        <th style="padding:.7rem;">Người báo cáo</th>
                        <th style="padding:.7rem;">Lý do</th>
                        <th style="padding:.7rem;">Thời gian</th>
                        <th style="padding:.7rem;">Trạng thái</th>
                        <th style="padding:.7rem;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($reports)): ?>
                        <?php foreach ($reports as $report): ?>
                            <tr style="border-bottom:1px solid #f1f5f9; vertical-align:top;">
                                <td style="padding:.7rem; max-width:260px;">
                                    <strong><?= htmlspecialchars((string)($report['post_title'] ?: 'Bai dang cong dong'), ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                    <span style="color:#64748b; font-size:.85rem;">Tac gia: <?= htmlspecialchars((string)$report['user_name'], ENT_QUOTES, 'UTF-8'); ?></span><br>
                                    <a href="../static/community_feed.php#community-post-<?= (int)$report['post_id']; ?>" target="_blank" style="font-size:.85rem; color:#2563eb;">Xem bai dang</a>
                                </td>
                                <td style="padding:.7rem;"><?= (int)$report['user_id']; ?></td>
                                <td style="padding:.7rem; max-width:280px;"><?= nl2br(htmlspecialchars((string)$report['reason'], ENT_QUOTES, 'UTF-8')); ?></td>
                                <td style="padding:.7rem;"><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$report['created_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="padding:.7rem;"><?= htmlspecialchars((string)$report['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td style="padding:.7rem;">
                                    <form method="post" class="flex" style="gap:.4rem; align-items:center;">
                                        <input type="hidden" name="report_id" value="<?= (int)$report['id']; ?>">
                                        <select name="status" class="box" style="max-width:140px;">
                                            <option value="pending" <?= (string)$report['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="reviewed" <?= (string)$report['status'] === 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                            <option value="dismissed" <?= (string)$report['status'] === 'dismissed' ? 'selected' : ''; ?>>Dismissed</option>
                                        </select>
                                        <button type="submit" class="option-btn">Lưu</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="padding:1rem; color:#64748b;">Chưa có báo cáo nào.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</body>

</html>
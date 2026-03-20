<?php

include '../components/connect.php';

session_start();

$admin_id = $_SESSION['admin_id'] ?? null;

if (!isset($admin_id)) {
    header('location:../static/login.php?next=admin');
    exit;
}

$fetch_profile = blog_fetch_admin_profile($conn, $admin_id);

$stats = [
    'posts_total' => 0,
    'posts_active' => 0,
    'posts_draft' => 0,
    'comments_total' => 0,
    'likes_total' => 0,
    'users_total' => 0,
    'admins_total' => 0,
    'categories_total' => 0,
];

$stmt = $conn->prepare('SELECT COUNT(*) FROM posts WHERE admin_id = ?');
$stmt->execute([(int)$admin_id]);
$stats['posts_total'] = (int)$stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) FROM posts WHERE admin_id = ? AND status = 'active'");
$stmt->execute([(int)$admin_id]);
$stats['posts_active'] = (int)$stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) FROM posts WHERE admin_id = ? AND status = 'deactive'");
$stmt->execute([(int)$admin_id]);
$stats['posts_draft'] = (int)$stmt->fetchColumn();

$stmt = $conn->prepare('SELECT COUNT(*) FROM comments WHERE admin_id = ?');
$stmt->execute([(int)$admin_id]);
$stats['comments_total'] = (int)$stmt->fetchColumn();

$stmt = $conn->prepare('SELECT COUNT(*) FROM likes WHERE admin_id = ?');
$stmt->execute([(int)$admin_id]);
$stats['likes_total'] = (int)$stmt->fetchColumn();

$stmt = $conn->prepare('SELECT COUNT(*) FROM users');
$stmt->execute();
$stats['users_total'] = (int)$stmt->fetchColumn();

$stats['admins_total'] = blog_count_admin_users($conn);

$stmt = $conn->prepare('SELECT COUNT(*) FROM cart WHERE admin_id = ?');
$stmt->execute([(int)$admin_id]);
$stats['categories_total'] = (int)$stmt->fetchColumn();

$stmt = $conn->prepare('SELECT COUNT(*) FROM posts WHERE admin_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)');
$stmt->execute([(int)$admin_id]);
$posts7Total = (int)$stmt->fetchColumn();

$stmt = $conn->prepare('SELECT COUNT(*) FROM posts WHERE admin_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)');
$stmt->execute([(int)$admin_id]);
$posts30Total = (int)$stmt->fetchColumn();

$stmt = $conn->prepare('SELECT COUNT(*) FROM comments WHERE admin_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)');
$stmt->execute([(int)$admin_id]);
$comments7Total = (int)$stmt->fetchColumn();

$stmt = $conn->prepare('SELECT COUNT(*) FROM comments WHERE admin_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)');
$stmt->execute([(int)$admin_id]);
$comments30Total = (int)$stmt->fetchColumn();

$engagementRate = $stats['posts_total'] > 0
    ? round((($stats['likes_total'] + $stats['comments_total']) / $stats['posts_total']), 2)
    : 0;

$topPostsStmt = $conn->prepare("SELECT
        p.id,
        p.title,
        p.status,
        p.date,
        COALESCE((SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id), 0) AS likes_count,
        COALESCE((SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id), 0) AS comments_count,
        (COALESCE((SELECT COUNT(*) FROM likes l2 WHERE l2.post_id = p.id), 0) + COALESCE((SELECT COUNT(*) FROM comments c2 WHERE c2.post_id = p.id), 0)) AS engagement_score
    FROM posts p
    WHERE p.admin_id = ?
    ORDER BY engagement_score DESC, p.id DESC
    LIMIT 5");
$topPostsStmt->execute([(int)$admin_id]);
$topPosts = $topPostsStmt->fetchAll(PDO::FETCH_ASSOC);

$dailyPostsRaw = [];
$dailyCommentsRaw = [];

$dailyPostsStmt = $conn->prepare("SELECT DATE(date) AS d, COUNT(*) AS c
    FROM posts
    WHERE admin_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(date)
    ORDER BY d ASC");
$dailyPostsStmt->execute([(int)$admin_id]);
foreach ($dailyPostsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $dailyPostsRaw[$row['d']] = (int)$row['c'];
}

$dailyCommentsStmt = $conn->prepare("SELECT DATE(date) AS d, COUNT(*) AS c
    FROM comments
    WHERE admin_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(date)
    ORDER BY d ASC");
$dailyCommentsStmt->execute([(int)$admin_id]);
foreach ($dailyCommentsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $dailyCommentsRaw[$row['d']] = (int)$row['c'];
}

$chartLabels = [];
$chartPosts = [];
$chartComments = [];
for ($i = 6; $i >= 0; $i--) {
    $dateKey = date('Y-m-d', strtotime("-{$i} day"));
    $chartLabels[] = date('d/m', strtotime($dateKey));
    $chartPosts[] = $dailyPostsRaw[$dateKey] ?? 0;
    $chartComments[] = $dailyCommentsRaw[$dateKey] ?? 0;
}

$statusChartLabels = ['Đã xuất bản', 'Bản nháp'];
$statusChartValues = [$stats['posts_active'], $stats['posts_draft']];

$engagementChartLabels = ['Lượt thích', 'Bình luận'];
$engagementChartValues = [$stats['likes_total'], $stats['comments_total']];

$monthlyPostsRaw = [];
$monthlyCommentsRaw = [];

$monthlyPostsStmt = $conn->prepare("SELECT DATE_FORMAT(date, '%Y-%m') AS ym, COUNT(*) AS c
    FROM posts
    WHERE admin_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
    GROUP BY DATE_FORMAT(date, '%Y-%m')
    ORDER BY ym ASC");
$monthlyPostsStmt->execute([(int)$admin_id]);
foreach ($monthlyPostsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $monthlyPostsRaw[$row['ym']] = (int)$row['c'];
}

$monthlyCommentsStmt = $conn->prepare("SELECT DATE_FORMAT(date, '%Y-%m') AS ym, COUNT(*) AS c
    FROM comments
    WHERE admin_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
    GROUP BY DATE_FORMAT(date, '%Y-%m')
    ORDER BY ym ASC");
$monthlyCommentsStmt->execute([(int)$admin_id]);
foreach ($monthlyCommentsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $monthlyCommentsRaw[$row['ym']] = (int)$row['c'];
}

$monthlyLabels = [];
$monthlyPosts = [];
$monthlyComments = [];
for ($i = 5; $i >= 0; $i--) {
    $monthKey = date('Y-m', strtotime(date('Y-m-01') . " -{$i} month"));
    $monthlyLabels[] = date('m/Y', strtotime($monthKey . '-01'));
    $monthlyPosts[] = $monthlyPostsRaw[$monthKey] ?? 0;
    $monthlyComments[] = $monthlyCommentsRaw[$monthKey] ?? 0;
}

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Dashboard Admin</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="ui-page">

    <?php include '../components/admin_header.php'; ?>

    <section class="dashboard admin-dashboard-surface ui-container">
        <div class="admin-page-topbar">
            <div>
                <h1 class="heading">Dashboard</h1>
                <p class="admin-subheading">Xin chào <?= htmlspecialchars($fetch_profile['name'], ENT_QUOTES, 'UTF-8'); ?>, đây là tổng quan 7 ngày và 30 ngày.</p>
            </div>
            <a href="add_posts.php" class="btn admin-top-action"><i class="fas fa-plus"></i> Đăng bài mới</a>
        </div>

        <div class="admin-kpi-grid">
            <article class="admin-kpi-card">
                <h3><?= $stats['posts_total']; ?></h3>
                <p>Tổng bài viết</p>
            </article>
            <article class="admin-kpi-card">
                <h3><?= $stats['posts_active']; ?></h3>
                <p>Đã xuất bản</p>
            </article>
            <article class="admin-kpi-card">
                <h3><?= $stats['posts_draft']; ?></h3>
                <p>Bản nháp</p>
            </article>
            <article class="admin-kpi-card">
                <h3><?= $stats['comments_total']; ?></h3>
                <p>Bình luận</p>
            </article>
            <article class="admin-kpi-card">
                <h3><?= $stats['likes_total']; ?></h3>
                <p>Lượt thích</p>
            </article>
            <article class="admin-kpi-card">
                <h3><?= $engagementRate; ?></h3>
                <p>Tỉ lệ tương tác / bài</p>
            </article>
        </div>

        <div class="admin-analytics-grid">
            <article class="admin-panel-card">
                <h3>Analytics 7 ngày</h3>
                <p class="admin-muted">Bài viết: <?= $posts7Total; ?> | Bình luận: <?= $comments7Total; ?></p>
                <div class="admin-chart-wrap admin-chart-wrap--lg">
                    <canvas id="adminActivity7Chart"></canvas>
                </div>
            </article>
            <article class="admin-panel-card">
                <h3>Analytics 30 ngày</h3>
                <div class="admin-kpi-inline">
                    <span class="admin-kpi-chip"><i class="fas fa-file-lines"></i><?= $posts30Total; ?> bài viết</span>
                    <span class="admin-kpi-chip"><i class="fas fa-comments"></i><?= $comments30Total; ?> bình luận</span>
                    <span class="admin-kpi-chip"><i class="fas fa-users"></i><?= $stats['users_total']; ?> users</span>
                    <span class="admin-kpi-chip"><i class="fas fa-user-shield"></i><?= $stats['admins_total']; ?> admins</span>
                    <span class="admin-kpi-chip"><i class="fas fa-tags"></i><?= $stats['categories_total']; ?> danh mục</span>
                </div>
                <p class="admin-muted">Cập nhật theo dữ liệu thực trong DB.</p>
            </article>
            <article class="admin-panel-card">
                <h3>Tỉ lệ trạng thái bài viết</h3>
                <p class="admin-muted">Theo trạng thái active và deactive.</p>
                <div class="admin-chart-wrap admin-chart-wrap--sm">
                    <canvas id="adminStatusChart"></canvas>
                </div>
            </article>
            <article class="admin-panel-card">
                <h3>So sánh tương tác</h3>
                <p class="admin-muted">Tổng lượt thích so với bình luận.</p>
                <div class="admin-chart-wrap admin-chart-wrap--sm">
                    <canvas id="adminEngagementChart"></canvas>
                </div>
            </article>
        </div>

        <article class="admin-panel-card" style="margin-top:1.2rem;">
            <h3>Xu hướng 6 tháng gần nhất</h3>
            <p class="admin-muted">Thống kê số bài viết và bình luận theo tháng.</p>
            <div class="admin-chart-wrap admin-chart-wrap--lg">
                <canvas id="adminMonthlyChart"></canvas>
            </div>
        </article>

        <article class="admin-panel-card">
            <h3>Top bài viết theo tương tác</h3>
            <div class="admin-table-wrap">
                <table class="admin-table ui-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Tiêu đề</th>
                            <th>Trạng thái</th>
                            <th>Ngày đăng</th>
                            <th>Like</th>
                            <th>Binh luan</th>
                            <th>Điểm tương tác</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$topPosts): ?>
                            <tr>
                                <td colspan="8">
                                    <p class="empty">Chưa có bài viết để thống kê.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($topPosts as $index => $post): ?>
                                <tr>
                                    <td><?= $index + 1; ?></td>
                                    <td><strong><?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                    <td><?= htmlspecialchars($post['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlspecialchars($post['date'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= (int)$post['likes_count']; ?></td>
                                    <td><?= (int)$post['comments_count']; ?></td>
                                    <td><?= (int)$post['engagement_score']; ?></td>
                                    <td>
                                        <a href="read_post.php?post_id=<?= (int)$post['id']; ?>" class="btn ui-btn">Xem</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </article>
    </section>

    <script>
        const labels = <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE); ?>;
        const postsData = <?= json_encode($chartPosts); ?>;
        const commentsData = <?= json_encode($chartComments); ?>;

        const statusLabels = <?= json_encode($statusChartLabels, JSON_UNESCAPED_UNICODE); ?>;
        const statusValues = <?= json_encode($statusChartValues, JSON_UNESCAPED_UNICODE); ?>;

        const engagementLabels = <?= json_encode($engagementChartLabels, JSON_UNESCAPED_UNICODE); ?>;
        const engagementValues = <?= json_encode($engagementChartValues, JSON_UNESCAPED_UNICODE); ?>;

        const monthlyLabels = <?= json_encode($monthlyLabels, JSON_UNESCAPED_UNICODE); ?>;
        const monthlyPosts = <?= json_encode($monthlyPosts); ?>;
        const monthlyComments = <?= json_encode($monthlyComments); ?>;

        const chartStore = window.__adminCharts || (window.__adminCharts = {});

        function renderAdminChart(chartId, config) {
            const canvas = document.getElementById(chartId);
            if (!canvas) {
                return;
            }

            const context = canvas.getContext('2d');
            if (chartStore[chartId]) {
                chartStore[chartId].destroy();
            }
            chartStore[chartId] = new Chart(context, config);
        }

        const chartCommonOptions = {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            resizeDelay: 150,
        };

        renderAdminChart('adminActivity7Chart', {
            type: 'line',
            data: {
                labels,
                datasets: [{
                        label: 'Bài viết',
                        data: postsData,
                        borderColor: '#0f766e',
                        backgroundColor: 'rgba(15, 118, 110, 0.15)',
                        tension: 0.35,
                        fill: true
                    },
                    {
                        label: 'Bình luận',
                        data: commentsData,
                        borderColor: '#1d4ed8',
                        backgroundColor: 'rgba(29, 78, 216, 0.12)',
                        tension: 0.35,
                        fill: true
                    }
                ]
            },
            options: {
                ...chartCommonOptions,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });

        renderAdminChart('adminStatusChart', {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusValues,
                    backgroundColor: ['#16a34a', '#f59e0b'],
                    borderWidth: 0
                }]
            },
            options: {
                ...chartCommonOptions,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        renderAdminChart('adminEngagementChart', {
            type: 'bar',
            data: {
                labels: engagementLabels,
                datasets: [{
                    label: 'Tổng',
                    data: engagementValues,
                    backgroundColor: ['#eab308', '#2563eb'],
                    borderRadius: 10
                }]
            },
            options: {
                ...chartCommonOptions,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });

        renderAdminChart('adminMonthlyChart', {
            type: 'bar',
            data: {
                labels: monthlyLabels,
                datasets: [{
                        label: 'Bài viết',
                        data: monthlyPosts,
                        backgroundColor: 'rgba(15, 118, 110, 0.75)',
                        borderRadius: 8
                    },
                    {
                        label: 'Bình luận',
                        data: monthlyComments,
                        backgroundColor: 'rgba(37, 99, 235, 0.75)',
                        borderRadius: 8
                    }
                ]
            },
            options: {
                ...chartCommonOptions,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    </script>

    <script src="../js/admin_script.js"></script>
</body>

</html>
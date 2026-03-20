<?php
include '../components/connect.php';
include '../components/feature_engine.php';

session_start();
header('Content-Type: application/json; charset=utf-8');

blog_ensure_feature_tables($conn);

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if ($user_id <= 0) {
    echo json_encode([
        'ok' => true,
        'unread' => 0,
        'items' => []
    ]);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

if ($action === 'mark_read') {
    $update = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
    $update->execute([$user_id]);
    echo json_encode(['ok' => true]);
    exit;
}

$unreadStmt = $conn->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
$unreadStmt->execute([$user_id]);
$unread = (int)$unreadStmt->fetchColumn();

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 8;
$limit = max(1, min(200, $limit));
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$offset = ($page - 1) * $limit;

$whereClauses = ['user_id = ?'];
$params = [$user_id];

if (isset($_GET['unread']) && $_GET['unread'] === '1') {
    $whereClauses[] = 'is_read = 0';
}

$status = trim((string)($_GET['status'] ?? 'all'));
if ($status === 'unread') {
    $whereClauses[] = 'is_read = 0';
} elseif ($status === 'read') {
    $whereClauses[] = 'is_read = 1';
}

$startDate = trim((string)($_GET['start_date'] ?? ''));
$endDate = trim((string)($_GET['end_date'] ?? ''));

if ($startDate !== '') {
    // If only a date is provided, treat it as the start of the day
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
        $startDate .= ' 00:00:00';
    }
    $whereClauses[] = 'created_at >= ?';
    $params[] = $startDate;
}

if ($endDate !== '') {
    // If only a date is provided, treat it as the end of the day
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        $endDate .= ' 23:59:59';
    }
    $whereClauses[] = 'created_at <= ?';
    $params[] = $endDate;
}

$whereSql = implode(' AND ', $whereClauses);

$countStmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE $whereSql");
$countStmt->execute($params);
$totalItems = (int)$countStmt->fetchColumn();

$listStmt = $conn->prepare("SELECT id, type, title, message, link, is_read, created_at FROM notifications WHERE $whereSql ORDER BY id DESC LIMIT ? OFFSET ?");
$params[] = $limit;
$params[] = $offset;
$listStmt->execute($params);
$items = $listStmt->fetchAll(PDO::FETCH_ASSOC);

$hasMore = ($offset + count($items)) < $totalItems;

echo json_encode([
    'ok' => true,
    'unread' => $unread,
    'items' => $items,
    'page' => $page,
    'limit' => $limit,
    'total_items' => $totalItems,
    'has_more' => $hasMore,
    'next_page' => $hasMore ? ($page + 1) : null
]);

<?php
include '../components/connect.php';
include '../components/community_engine.php';

session_start();
header('Content-Type: application/json; charset=utf-8');

community_ensure_tables($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Phuong thuc khong hop le.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if ($user_id <= 0) {
    echo json_encode([
        'ok' => false,
        'message' => 'Vui long dang nhap de binh chon poll.',
        'login_required' => true,
        'login_url' => site_url('static/login.php')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$post_id = (int)($_POST['post_id'] ?? 0);
$option_id = (int)($_POST['option_id'] ?? 0);
if ($post_id <= 0 || $option_id <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Du lieu poll khong hop le.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$postStmt = $conn->prepare("SELECT id FROM community_posts WHERE id = ? AND post_type = 'poll' AND status IN ('published', 'draft') LIMIT 1");
$postStmt->execute([$post_id]);
if (!$postStmt->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode(['ok' => false, 'message' => 'Poll khong ton tai hoac da bi an.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$optionStmt = $conn->prepare('SELECT id FROM community_poll_options WHERE id = ? AND post_id = ? LIMIT 1');
$optionStmt->execute([$option_id, $post_id]);
if (!$optionStmt->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode(['ok' => false, 'message' => 'Lua chon poll khong hop le.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $conn->beginTransaction();

    $upsert = $conn->prepare('INSERT INTO community_poll_votes (post_id, option_id, user_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE option_id = VALUES(option_id), created_at = CURRENT_TIMESTAMP');
    $upsert->execute([$post_id, $option_id, $user_id]);

    $totalsStmt = $conn->prepare('SELECT option_id, COUNT(*) AS vote_count FROM community_poll_votes WHERE post_id = ? GROUP BY option_id');
    $totalsStmt->execute([$post_id]);
    $counts = [];
    $total_votes = 0;
    foreach ($totalsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $oid = (int)($row['option_id'] ?? 0);
        $vc = (int)($row['vote_count'] ?? 0);
        $counts[$oid] = $vc;
        $total_votes += $vc;
    }

    $optionsListStmt = $conn->prepare('SELECT id, option_text FROM community_poll_options WHERE post_id = ? ORDER BY sort_order ASC, id ASC');
    $optionsListStmt->execute([$post_id]);
    $options = [];
    foreach ($optionsListStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $oid = (int)($row['id'] ?? 0);
        $options[] = [
            'option_id' => $oid,
            'option_text' => (string)($row['option_text'] ?? ''),
            'vote_count' => (int)($counts[$oid] ?? 0)
        ];
    }

    $conn->commit();

    echo json_encode([
        'ok' => true,
        'message' => 'Da ghi nhan binh chon poll.',
        'post_id' => $post_id,
        'user_option_id' => $option_id,
        'total_votes' => $total_votes,
        'options' => $options
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Khong the cap nhat poll luc nay.'], JSON_UNESCAPED_UNICODE);
}

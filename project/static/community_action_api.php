<?php
ob_start();

include '../components/connect.php';
include '../components/community_engine.php';
include '../components/seo_helpers.php';

session_start();
header('Content-Type: application/json; charset=utf-8');

community_ensure_tables($conn);

function community_action_fail($message, $code = 400, array $extra = [])
{
    http_response_code($code);
    if (ob_get_length() > 0) {
        ob_clean();
    }

    echo json_encode(array_merge([
        'ok' => false,
        'message' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    community_action_fail('Phuong thuc khong hop le.', 405);
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    community_action_fail('Vui long dang nhap de thao tac.', 401, [
        'login_required' => true,
        'login_url' => site_url('static/login.php'),
    ]);
}

$action = trim((string)($_POST['action'] ?? ''));
$postId = (int)($_POST['post_id'] ?? 0);
if ($postId <= 0 || !in_array($action, ['save', 'report'], true)) {
    community_action_fail('Du lieu yeu cau khong hop le.');
}

$postStmt = $conn->prepare('SELECT id, user_id, status FROM community_posts WHERE id = ? LIMIT 1');
$postStmt->execute([$postId]);
$post = $postStmt->fetch(PDO::FETCH_ASSOC);
if (!$post) {
    community_action_fail('Bai viet khong ton tai.', 404);
}

$status = (string)($post['status'] ?? 'published');
$ownerId = (int)($post['user_id'] ?? 0);
if ($status !== 'published' && $ownerId !== $userId) {
    community_action_fail('Ban khong co quyen thao tac voi bai viet nay.', 403);
}

if ($action === 'save') {
    $existsStmt = $conn->prepare('SELECT id FROM community_saved_posts WHERE post_id = ? AND user_id = ? LIMIT 1');
    $existsStmt->execute([$postId, $userId]);
    $savedId = (int)($existsStmt->fetchColumn() ?: 0);

    if ($savedId > 0) {
        $deleteStmt = $conn->prepare('DELETE FROM community_saved_posts WHERE id = ? LIMIT 1');
        $deleteStmt->execute([$savedId]);

        echo json_encode([
            'ok' => true,
            'saved' => 0,
            'post_id' => $postId,
            'message' => 'Da bo luu bai viet.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $insertStmt = $conn->prepare('INSERT INTO community_saved_posts (post_id, user_id) VALUES (?, ?)');
    $insertStmt->execute([$postId, $userId]);

    echo json_encode([
        'ok' => true,
        'saved' => 1,
        'post_id' => $postId,
        'message' => 'Da luu bai viet.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$reason = trim((string)($_POST['reason'] ?? 'Bao cao tu bang tin cong dong'));
if ($reason !== '') {
    $reason = mb_substr($reason, 0, 1000, 'UTF-8');
}

$reportStmt = $conn->prepare('INSERT INTO community_post_reports (post_id, user_id, reason, status) VALUES (?, ?, ?, "pending") ON DUPLICATE KEY UPDATE reason = VALUES(reason), status = "pending", created_at = CURRENT_TIMESTAMP');
$reportStmt->execute([$postId, $userId, $reason]);

echo json_encode([
    'ok' => true,
    'post_id' => $postId,
    'message' => 'Da gui bao cao. Chung toi se kiem tra trong thoi gian som nhat.',
], JSON_UNESCAPED_UNICODE);

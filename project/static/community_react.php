<?php
include '../components/connect.php';
include '../components/community_engine.php';
include '../components/feature_engine.php';
include '../components/seo_helpers.php';

session_start();
header('Content-Type: application/json; charset=utf-8');

community_ensure_tables($conn);
blog_ensure_feature_tables($conn);

function react_fail($message, $code = 400, array $extra = [])
{
    http_response_code($code);
    echo json_encode(array_merge(['ok' => false, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    react_fail('Phuong thuc khong hop le.', 405);
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    react_fail('Vui long dang nhap de thich bai viet.', 401, [
        'login_required' => true,
        'login_url' => 'login.php'
    ]);
}

$postId = (int)($_POST['post_id'] ?? 0);
if ($postId <= 0) {
    react_fail('Du lieu bai viet khong hop le.');
}

$postStmt = $conn->prepare('SELECT id, user_id, status FROM community_posts WHERE id = ? LIMIT 1');
$postStmt->execute([$postId]);
$post = $postStmt->fetch(PDO::FETCH_ASSOC);
if (!$post) {
    react_fail('Bai viet khong ton tai.', 404);
}

if ((string)$post['status'] !== 'published' && (int)$post['user_id'] !== $userId) {
    react_fail('Ban khong co quyen tuong tac bai viet nay.', 403);
}

$existsStmt = $conn->prepare('SELECT id FROM community_post_reactions WHERE post_id = ? AND user_id = ? LIMIT 1');
$existsStmt->execute([$postId, $userId]);
$existing = $existsStmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    $deleteStmt = $conn->prepare('DELETE FROM community_post_reactions WHERE post_id = ? AND user_id = ?');
    $deleteStmt->execute([$postId, $userId]);
    $liked = false;
} else {
    $insertStmt = $conn->prepare('INSERT INTO community_post_reactions (post_id, user_id, reaction) VALUES (?, ?, 1)');
    $insertStmt->execute([$postId, $userId]);
    $liked = true;

    $actorStmt = $conn->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
    $actorStmt->execute([$userId]);
    $actorName = (string)($actorStmt->fetchColumn() ?: 'Mot nguoi dung');

    $ownerId = (int)$post['user_id'];
    if ($ownerId > 0 && $ownerId !== $userId) {
        blog_push_notification(
            $conn,
            $ownerId,
            'community_like',
            'Bai cong dong vua duoc thich',
            $actorName . ' vua thich bai dang cong dong cua ban.',
            site_url('static/community_feed.php#community-post-' . $postId)
        );
    }
}

community_sync_post_counters($conn, $postId);

$countStmt = $conn->prepare('SELECT total_reactions FROM community_posts WHERE id = ? LIMIT 1');
$countStmt->execute([$postId]);
$totalReactions = (int)$countStmt->fetchColumn();

echo json_encode([
    'ok' => true,
    'liked' => $liked,
    'post_id' => $postId,
    'total_reactions' => $totalReactions,
    'message' => $liked ? 'Da thich bai viet.' : 'Da bo thich bai viet.'
], JSON_UNESCAPED_UNICODE);

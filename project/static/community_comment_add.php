<?php
include '../components/connect.php';
include '../components/community_engine.php';
include '../components/feature_engine.php';
include '../components/seo_helpers.php';

session_start();
header('Content-Type: application/json; charset=utf-8');

community_ensure_tables($conn);
blog_ensure_feature_tables($conn);

function comment_fail($message, $code = 400, array $extra = [])
{
    http_response_code($code);
    echo json_encode(array_merge(['ok' => false, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    comment_fail('Phuong thuc khong hop le.', 405);
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    comment_fail('Vui long dang nhap de binh luan.', 401, [
        'login_required' => true,
        'login_url' => 'login.php'
    ]);
}

$postId = (int)($_POST['post_id'] ?? 0);
$parentCommentId = (int)($_POST['parent_comment_id'] ?? 0);
$commentRaw = trim((string)($_POST['comment'] ?? ''));

if ($postId <= 0 || $commentRaw === '') {
    comment_fail('Noi dung binh luan khong hop le.');
}

if (mb_strlen($commentRaw, 'UTF-8') > 1200) {
    comment_fail('Binh luan toi da 1200 ky tu.');
}

$postStmt = $conn->prepare('SELECT id, user_id, status FROM community_posts WHERE id = ? LIMIT 1');
$postStmt->execute([$postId]);
$post = $postStmt->fetch(PDO::FETCH_ASSOC);
if (!$post) {
    comment_fail('Bai viet khong ton tai.', 404);
}

if ((string)$post['status'] !== 'published' && (int)$post['user_id'] !== $userId) {
    comment_fail('Ban khong co quyen binh luan bai viet nay.', 403);
}

if ($parentCommentId > 0) {
    $parentStmt = $conn->prepare("SELECT id FROM community_post_comments WHERE id = ? AND post_id = ? AND status = 'active' LIMIT 1");
    $parentStmt->execute([$parentCommentId, $postId]);
    if (!$parentStmt->fetch(PDO::FETCH_ASSOC)) {
        comment_fail('Khong tim thay binh luan cha de tra loi.', 404);
    }
}

$userStmt = $conn->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
$userStmt->execute([$userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    comment_fail('Khong tim thay thong tin nguoi dung.', 404);
}

$insertStmt = $conn->prepare('INSERT INTO community_post_comments (post_id, parent_comment_id, user_id, user_name, comment, status) VALUES (?, ?, ?, ?, ?, ?)');
$insertStmt->execute([
    $postId,
    $parentCommentId > 0 ? $parentCommentId : null,
    $userId,
    (string)$user['name'],
    $commentRaw,
    'active'
]);
$commentId = (int)$conn->lastInsertId();

$actorName = (string)$user['name'];
$ownerId = (int)$post['user_id'];
if ($ownerId > 0 && $ownerId !== $userId) {
    blog_push_notification(
        $conn,
        $ownerId,
        'community_comment',
        'Bai cong dong co binh luan moi',
        $actorName . ' vua binh luan bai dang cong dong cua ban.',
        site_url('static/community_feed.php#community-post-' . $postId)
    );
}

if ($parentCommentId > 0) {
    $parentOwnerStmt = $conn->prepare('SELECT user_id FROM community_post_comments WHERE id = ? LIMIT 1');
    $parentOwnerStmt->execute([$parentCommentId]);
    $parentOwnerId = (int)$parentOwnerStmt->fetchColumn();
    if ($parentOwnerId > 0 && $parentOwnerId !== $userId) {
        blog_push_notification(
            $conn,
            $parentOwnerId,
            'community_reply',
            'Binh luan cong dong duoc tra loi',
            $actorName . ' vua tra loi binh luan cua ban.',
            site_url('static/community_feed.php#community-post-' . $postId)
        );
    }
}

community_sync_post_counters($conn, $postId);

$countStmt = $conn->prepare('SELECT total_comments FROM community_posts WHERE id = ? LIMIT 1');
$countStmt->execute([$postId]);
$totalComments = (int)$countStmt->fetchColumn();

$commentRow = [
    'id' => $commentId,
    'user_name' => (string)$user['name'],
    'comment' => $commentRaw,
    'created_at' => date('Y-m-d H:i:s')
];

echo json_encode([
    'ok' => true,
    'message' => 'Da gui binh luan.',
    'post_id' => $postId,
    'parent_comment_id' => $parentCommentId,
    'total_comments' => $totalComments,
    'comment_html' => community_render_comment_item($commentRow, true, $parentCommentId > 0)
], JSON_UNESCAPED_UNICODE);

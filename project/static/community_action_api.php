<?php
ob_start();

include '../components/connect.php';
include '../components/community_engine.php';
include '../components/seo_helpers.php';
include_once '../components/feature_engine.php';

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

if ($action === 'follow') {
    $targetUserId = (int)($_POST['target_user_id'] ?? 0);
    if ($targetUserId <= 0 || $targetUserId === $userId) {
        community_action_fail('Khong the theo doi tai khoan nay.');
    }

    $targetStmt = $conn->prepare('SELECT id, name FROM users WHERE id = ? LIMIT 1');
    $targetStmt->execute([$targetUserId]);
    $targetUser = $targetStmt->fetch(PDO::FETCH_ASSOC);
    if (!$targetUser) {
        community_action_fail('Nguoi dung khong ton tai.', 404);
    }

    $existsStmt = $conn->prepare('SELECT id FROM community_user_follows WHERE follower_user_id = ? AND following_user_id = ? LIMIT 1');
    $existsStmt->execute([$userId, $targetUserId]);
    $followId = (int)($existsStmt->fetchColumn() ?: 0);

    $following = 0;
    if ($followId > 0) {
        $deleteStmt = $conn->prepare('DELETE FROM community_user_follows WHERE id = ? LIMIT 1');
        $deleteStmt->execute([$followId]);
    } else {
        $insertStmt = $conn->prepare('INSERT INTO community_user_follows (follower_user_id, following_user_id) VALUES (?, ?)');
        $insertStmt->execute([$userId, $targetUserId]);
        $following = 1;

        if (function_exists('blog_push_notification') && function_exists('community_get_notification_preference')) {
            $targetPref = community_get_notification_preference($conn, $targetUserId);
            if ((int)($targetPref['follow_events_enabled'] ?? 1) === 1) {
                blog_push_notification(
                    $conn,
                    $targetUserId,
                    'community_follow',
                    'Ban co nguoi theo doi moi',
                    'Mot nguoi dung vua theo doi ban trong cong dong.',
                    site_url('static/community_feed.php')
                );
            }
        }
    }

    $countStmt = $conn->prepare('SELECT COUNT(*) FROM community_user_follows WHERE following_user_id = ?');
    $countStmt->execute([$targetUserId]);
    $followersCount = (int)$countStmt->fetchColumn();

    $reciprocalStmt = $conn->prepare('SELECT 1 FROM community_user_follows WHERE follower_user_id = ? AND following_user_id = ? LIMIT 1');
    $reciprocalStmt->execute([$targetUserId, $userId]);
    $followedByTarget = (bool)$reciprocalStmt->fetchColumn();

    echo json_encode([
        'ok' => true,
        'action' => 'follow',
        'target_user_id' => $targetUserId,
        'following' => $following,
        'followers_count' => $followersCount,
        'followed_by_target' => $followedByTarget,
        'message' => $following ? 'Da theo doi tac gia.' : 'Da bo theo doi tac gia.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($postId <= 0 || !in_array($action, ['save', 'report', 'pin'], true)) {
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

if ($action === 'pin') {
    $existsStmt = $conn->prepare('SELECT id FROM community_user_pins WHERE user_id = ? AND post_id = ? LIMIT 1');
    $existsStmt->execute([$userId, $postId]);
    $pinId = (int)($existsStmt->fetchColumn() ?: 0);

    $pinned = 0;
    if ($pinId > 0) {
        $deleteStmt = $conn->prepare('DELETE FROM community_user_pins WHERE id = ? LIMIT 1');
        $deleteStmt->execute([$pinId]);
    } else {
        $insertStmt = $conn->prepare('INSERT INTO community_user_pins (user_id, post_id) VALUES (?, ?)');
        $insertStmt->execute([$userId, $postId]);
        $pinned = 1;
    }

    echo json_encode([
        'ok' => true,
        'post_id' => $postId,
        'pinned' => $pinned,
        'message' => $pinned ? 'Da ghim bai viet len dau feed cua ban.' : 'Da bo ghim bai viet.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
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

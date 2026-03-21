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
    react_fail('Vui long dang nhap de vote bai viet.', 401, [
        'login_required' => true,
        'login_url' => 'login.php'
    ]);
}

$postId = (int)($_POST['post_id'] ?? 0);
$voteType = trim((string)($_POST['vote'] ?? 'up'));
$voteValue = $voteType === 'down' ? -1 : 1;
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

$existsStmt = $conn->prepare('SELECT id, reaction FROM community_post_reactions WHERE post_id = ? AND user_id = ? LIMIT 1');
$existsStmt->execute([$postId, $userId]);
$existing = $existsStmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    $currentReaction = (int)($existing['reaction'] ?? 0);
    if ($currentReaction === $voteValue) {
        $deleteStmt = $conn->prepare('DELETE FROM community_post_reactions WHERE post_id = ? AND user_id = ?');
        $deleteStmt->execute([$postId, $userId]);
        $currentReaction = 0;
    } else {
        $updateStmt = $conn->prepare('UPDATE community_post_reactions SET reaction = ? WHERE post_id = ? AND user_id = ?');
        $updateStmt->execute([$voteValue, $postId, $userId]);
        $currentReaction = $voteValue;
    }
} else {
    $insertStmt = $conn->prepare('INSERT INTO community_post_reactions (post_id, user_id, reaction) VALUES (?, ?, ?)');
    $insertStmt->execute([$postId, $userId, $voteValue]);
    $currentReaction = $voteValue;

    $actorStmt = $conn->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
    $actorStmt->execute([$userId]);
    $actorName = (string)($actorStmt->fetchColumn() ?: 'Mot nguoi dung');

    $ownerId = (int)$post['user_id'];
    if ($ownerId > 0 && $ownerId !== $userId) {
        blog_push_notification(
            $conn,
            $ownerId,
            'community_vote',
            'Bai cong dong vua duoc vote',
            $actorName . ' vua vote bai dang cong dong cua ban.',
            site_url('static/community_feed.php#community-post-' . $postId)
        );
    }
}

community_sync_post_counters($conn, $postId);

$stats = [];
try {
    $countStmt = $conn->prepare('SELECT total_reactions, total_upvotes, total_downvotes, vote_score FROM community_posts WHERE id = ? LIMIT 1');
    $countStmt->execute([$postId]);
    $stats = $countStmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    $countStmt = $conn->prepare('SELECT total_reactions FROM community_posts WHERE id = ? LIMIT 1');
    $countStmt->execute([$postId]);
    $stats = ['total_reactions' => (int)$countStmt->fetchColumn()];
}
$totalReactions = (int)($stats['total_reactions'] ?? 0);
$totalUpvotes = (int)($stats['total_upvotes'] ?? 0);
$totalDownvotes = (int)($stats['total_downvotes'] ?? 0);
$voteScore = (int)($stats['vote_score'] ?? 0);

echo json_encode([
    'ok' => true,
    'reaction' => $currentReaction,
    'post_id' => $postId,
    'total_reactions' => $totalReactions,
    'total_upvotes' => $totalUpvotes,
    'total_downvotes' => $totalDownvotes,
    'vote_score' => $voteScore,
    'message' => $currentReaction === 1 ? 'Da upvote bai viet.' : ($currentReaction === -1 ? 'Da downvote bai viet.' : 'Da bo vote bai viet.')
], JSON_UNESCAPED_UNICODE);

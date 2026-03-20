<?php
include '../components/connect.php';
include '../components/feature_engine.php';

session_start();
header('Content-Type: application/json; charset=utf-8');

blog_ensure_feature_tables($conn);

$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
    echo json_encode([
        'ok' => false,
        'login_required' => true,
        'login_url' => 'login.php',
        'message' => 'Ban can dang nhap de vote binh luan.'
    ]);
    exit;
}

$comment_id = (int)($_POST['comment_id'] ?? 0);
$vote_type = (string)($_POST['vote_type'] ?? '');
$vote_value = $vote_type === 'up' ? 1 : ($vote_type === 'down' ? -1 : 0);

if ($comment_id <= 0 || $vote_value === 0) {
    echo json_encode(['ok' => false, 'message' => 'Du lieu vote khong hop le.']);
    exit;
}

$exists = $conn->prepare('SELECT id FROM comments WHERE id = ? LIMIT 1');
$exists->execute([$comment_id]);
if (!$exists->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode(['ok' => false, 'message' => 'Binh luan khong ton tai.']);
    exit;
}

$upsert = $conn->prepare('INSERT INTO comment_votes (comment_id, user_id, vote) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE vote = VALUES(vote), created_at = CURRENT_TIMESTAMP');
$upsert->execute([$comment_id, $user_id, $vote_value]);

$scoreStmt = $conn->prepare('SELECT COALESCE(SUM(vote), 0) FROM comment_votes WHERE comment_id = ?');
$scoreStmt->execute([$comment_id]);
$score = (int)$scoreStmt->fetchColumn();

$countStmt = $conn->prepare('SELECT
    COALESCE(SUM(CASE WHEN vote = 1 THEN 1 ELSE 0 END), 0) AS up_count,
    COALESCE(SUM(CASE WHEN vote = -1 THEN 1 ELSE 0 END), 0) AS down_count
    FROM comment_votes
    WHERE comment_id = ?');
$countStmt->execute([$comment_id]);
$counts = $countStmt->fetch(PDO::FETCH_ASSOC) ?: ['up_count' => 0, 'down_count' => 0];

echo json_encode([
    'ok' => true,
    'comment_id' => $comment_id,
    'score' => $score,
    'up_count' => (int)$counts['up_count'],
    'down_count' => (int)$counts['down_count'],
    'message' => 'Da cap nhat vote binh luan.'
]);

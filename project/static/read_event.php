<?php
include '../components/connect.php';
include '../components/feature_engine.php';

session_start();
header('Content-Type: application/json; charset=utf-8');

blog_ensure_feature_tables($conn);

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if ($user_id <= 0) {
    echo json_encode(['ok' => true]);
    exit;
}

$post_id = (int)($_POST['post_id'] ?? 0);
$seconds_read = (int)($_POST['seconds_read'] ?? 0);
$seconds_read = max(0, min($seconds_read, 3600));

if ($post_id <= 0 || $seconds_read <= 0) {
    echo json_encode(['ok' => false]);
    exit;
}

$insert = $conn->prepare('INSERT INTO user_read_events (user_id, post_id, seconds_read) VALUES (?, ?, ?)');
$insert->execute([$user_id, $post_id, $seconds_read]);

echo json_encode(['ok' => true]);

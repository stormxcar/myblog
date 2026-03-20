<?php
include '../components/connect.php';
include '../components/feature_engine.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

$socketId = (string)($_POST['socket_id'] ?? '');
$channelName = (string)($_POST['channel_name'] ?? '');

if ($socketId === '' || $channelName === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid auth payload']);
    exit;
}

$expectedChannel = 'private-user-notifications-' . $userId;
if ($channelName !== $expectedChannel) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Forbidden channel']);
    exit;
}

$client = blog_get_pusher_client();
if (!$client) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'message' => 'Realtime service unavailable']);
    exit;
}

try {
    echo $client->authorizeChannel($channelName, $socketId);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Realtime auth failed']);
}

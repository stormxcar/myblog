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

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    echo json_encode([
        'ok' => false,
        'message' => 'Vui long dang nhap de cai dat thong bao.',
        'login_required' => true,
        'login_url' => site_url('static/login.php')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$key = trim((string)($_POST['key'] ?? ''));
$enabled = isset($_POST['enabled']) ? (int)$_POST['enabled'] : -1;
if (!in_array($key, ['follow_events_enabled', 'new_post_events_enabled'], true) || !in_array($enabled, [0, 1], true)) {
    echo json_encode(['ok' => false, 'message' => 'Du lieu cai dat khong hop le.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ok = community_set_notification_preference($conn, $userId, [$key => $enabled]);
if (!$ok) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Khong the cap nhat cai dat thong bao.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pref = community_get_notification_preference($conn, $userId);
echo json_encode([
    'ok' => true,
    'message' => 'Da cap nhat cai dat thong bao.',
    'preference' => $pref,
], JSON_UNESCAPED_UNICODE);

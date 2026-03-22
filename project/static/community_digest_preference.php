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
        'message' => 'Vui long dang nhap de cai dat digest.',
        'login_required' => true,
        'login_url' => site_url('static/login.php')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$frequency = trim((string)($_POST['frequency'] ?? 'daily'));
if (!in_array($frequency, ['daily', 'weekly', 'off'], true)) {
    echo json_encode(['ok' => false, 'message' => 'Gia tri tan suat khong hop le.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ok = community_set_digest_preference($conn, $user_id, $frequency);
if (!$ok) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Khong the luu cai dat digest.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pref = community_get_digest_preference($conn, $user_id);
echo json_encode([
    'ok' => true,
    'message' => $frequency === 'off' ? 'Da tat digest.' : ('Da cap nhat digest ' . $frequency . '.'),
    'preference' => $pref
], JSON_UNESCAPED_UNICODE);

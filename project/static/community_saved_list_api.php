<?php
include '../components/connect.php';
include '../components/community_engine.php';
include '../components/community_feed_renderer.php';

session_start();
header('Content-Type: application/json; charset=utf-8');

community_ensure_tables($conn);

function saved_list_fail($message, $code = 400)
{
    http_response_code($code);
    echo json_encode([
        'ok' => false,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    saved_list_fail('Phuong thuc khong hop le.', 405);
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    saved_list_fail('Vui long dang nhap de xem bai da luu.', 401);
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 6;
$topic = trim((string)($_GET['topic'] ?? ''));

$bundle = community_fetch_saved_posts_page($conn, $userId, $page, $limit, $topic);
$maps = community_load_post_maps($conn, $bundle['posts'], $userId);
$html = community_render_feed_posts_html($bundle['posts'], $maps, $userId);

echo json_encode([
    'ok' => true,
    'html' => $html,
    'items_count' => count($bundle['posts']),
    'has_more' => (bool)$bundle['has_more'],
    'next_page' => $bundle['next_page'],
    'current_page' => $bundle['current_page'],
], JSON_UNESCAPED_UNICODE);

<?php
ob_start();

include '../components/connect.php';
include '../components/community_engine.php';
include '../components/seo_helpers.php';

session_start();
header('Content-Type: application/json; charset=utf-8');

community_ensure_tables($conn);

function manage_fail($message, $code = 400, array $extra = [])
{
    http_response_code($code);
    if (ob_get_length() > 0) {
        ob_clean();
    }
    echo json_encode(array_merge(['ok' => false, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    manage_fail('Phuong thuc khong hop le.', 405);
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    manage_fail('Vui long dang nhap.', 401, [
        'login_required' => true,
        'login_url' => site_url('static/login.php')
    ]);
}

$action = (string)($_POST['action'] ?? '');
$postId = (int)($_POST['post_id'] ?? 0);
if ($postId <= 0 || !in_array($action, ['delete', 'update'], true)) {
    manage_fail('Du lieu yeu cau khong hop le.');
}

$postStmt = $conn->prepare('SELECT * FROM community_posts WHERE id = ? LIMIT 1');
$postStmt->execute([$postId]);
$post = $postStmt->fetch(PDO::FETCH_ASSOC);
if (!$post) {
    manage_fail('Bai viet khong ton tai.', 404);
}
if ((int)$post['user_id'] !== $userId) {
    manage_fail('Ban khong co quyen chinh sua bai viet nay.', 403);
}

if ($action === 'delete') {
    $deleteStmt = $conn->prepare('DELETE FROM community_posts WHERE id = ?');
    $deleteStmt->execute([$postId]);

    echo json_encode([
        'ok' => true,
        'message' => 'Da xoa bai viet.',
        'post_id' => $postId
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$content = trim((string)($_POST['content'] ?? ''));
$privacy = (string)($_POST['privacy'] ?? 'public');
$privacy = in_array($privacy, ['public', 'followers', 'private'], true) ? $privacy : 'public';
$status = $privacy === 'private' ? 'draft' : 'published';

if ($content === '') {
    manage_fail('Noi dung bai viet khong duoc de trong.');
}
if (mb_strlen($content, 'UTF-8') > 5000) {
    manage_fail('Noi dung toi da 5000 ky tu.');
}

$linksRaw = (string)($_POST['links'] ?? '');
$links = community_extract_links_from_textarea($linksRaw);
if (count($links) > 5) {
    $links = array_slice($links, 0, 5);
}

$updateStmt = $conn->prepare('UPDATE community_posts SET content = ?, privacy = ?, status = ? WHERE id = ?');
$updateStmt->execute([$content, $privacy, $status, $postId]);

$deleteLinks = $conn->prepare('DELETE FROM community_post_links WHERE post_id = ?');
$deleteLinks->execute([$postId]);

if (!empty($links)) {
    $insertLink = $conn->prepare('INSERT INTO community_post_links (post_id, url, host, title, description, preview_image) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($links as $url) {
        $meta = community_fetch_link_metadata($url);
        $insertLink->execute([
            $postId,
            $meta['url'],
            $meta['host'],
            $meta['title'],
            $meta['description'],
            $meta['preview_image']
        ]);
    }
}

community_attach_topics_to_post($conn, $postId, $content);

echo json_encode([
    'ok' => true,
    'message' => 'Da cap nhat bai viet.',
    'post_id' => $postId,
    'redirect_url' => site_url('static/community_manage.php')
], JSON_UNESCAPED_UNICODE);

<?php
ob_start();

include_once __DIR__ . '/../components/connect.php';
include_once __DIR__ . '/../components/seo_helpers.php';
include_once __DIR__ . '/../components/community_engine.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
community_ensure_tables($conn);

function community_json_fail($message, $statusCode = 400, array $extra = [])
{
    http_response_code($statusCode);
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
    community_json_fail('Phuong thuc khong hop le.', 405);
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    community_json_fail('Vui long dang nhap de dang bai cong dong.', 401, [
        'login_required' => true,
        'login_url' => site_url('static/login.php')
    ]);
}

$titleRaw = (string)($_POST['title'] ?? '');
$title = trim((string)html_entity_decode(strip_tags($titleRaw), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
$title = preg_replace('/\s+/u', ' ', (string)$title);
$postType = trim((string)($_POST['post_type'] ?? 'text'));
$postType = in_array($postType, ['text', 'media', 'link'], true) ? $postType : 'text';

$contentRaw = (string)($_POST['content'] ?? '');
$content = trim((string)preg_replace('/\s+/u', ' ', strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $contentRaw))));
$linksRaw = (string)($_POST['links'] ?? '');
$privacy = (string)($_POST['privacy'] ?? 'public');
$privacy = in_array($privacy, ['public', 'followers', 'private'], true) ? $privacy : 'public';
$status = $privacy === 'private' ? 'draft' : 'published';

if ($title === '') {
    community_json_fail('Tieu de bai viet khong duoc de trong.');
}

if (mb_strlen($title, 'UTF-8') > 300) {
    community_json_fail('Tieu de toi da 300 ky tu.');
}

if (mb_strlen($content, 'UTF-8') > 5000) {
    community_json_fail('Noi dung toi da 5000 ky tu.');
}

$links = community_extract_links_from_textarea($linksRaw);
if (count($links) > 5) {
    $links = array_slice($links, 0, 5);
}

$uploadBaseDir = realpath(__DIR__ . '/../uploaded_img');
if ($uploadBaseDir === false) {
    community_json_fail('Thu muc upload goc khong ton tai tren server.', 500);
}

$communityDir = $uploadBaseDir . DIRECTORY_SEPARATOR . 'community';
if (!is_dir($communityDir) && !mkdir($communityDir, 0755, true)) {
    community_json_fail('Khong the tao thu muc upload cong dong.', 500);
}

$allowedMimes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
    'image/avif' => 'avif',
];
$allowedExtensions = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
    'gif' => 'image/gif',
    'avif' => 'image/avif',
];

$storedMedia = [];
$maxImages = 12;
$maxImageSize = 5 * 1024 * 1024;

if (isset($_FILES['images']) && is_array($_FILES['images']['name'])) {
    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
    $totalFiles = count($_FILES['images']['name']);

    for ($i = 0; $i < $totalFiles; $i++) {
        if (count($storedMedia) >= $maxImages) {
            break;
        }

        $error = (int)($_FILES['images']['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($error !== UPLOAD_ERR_OK) {
            community_json_fail('Upload anh that bai. Ma loi: ' . $error);
        }

        $tmpFile = (string)($_FILES['images']['tmp_name'][$i] ?? '');
        $origName = (string)($_FILES['images']['name'][$i] ?? 'image');
        $fileSize = (int)($_FILES['images']['size'][$i] ?? 0);

        if ($fileSize <= 0 || $fileSize > $maxImageSize) {
            community_json_fail('Moi anh phai nho hon 5MB.');
        }

        $mime = '';
        if ($finfo) {
            $mime = (string)finfo_file($finfo, $tmpFile);
        } elseif (function_exists('mime_content_type')) {
            $mime = (string)mime_content_type($tmpFile);
        } elseif (function_exists('getimagesize')) {
            $imageMeta = @getimagesize($tmpFile);
            $mime = is_array($imageMeta) && isset($imageMeta['mime']) ? (string)$imageMeta['mime'] : '';
        }

        if ($mime === '') {
            $extGuess = strtolower((string)pathinfo($origName, PATHINFO_EXTENSION));
            if (isset($allowedExtensions[$extGuess])) {
                $mime = $allowedExtensions[$extGuess];
            }
        }

        if (!is_string($mime) || !isset($allowedMimes[$mime])) {
            community_json_fail('Chi chap nhan JPG, PNG, WEBP, GIF, AVIF.');
        }

        $ext = $allowedMimes[$mime];
        $filename = 'community_' . $userId . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $targetPath = $communityDir . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($tmpFile, $targetPath)) {
            community_json_fail('Khong the luu tep anh tai len.', 500);
        }

        $storedMedia[] = [
            'file_path' => 'community/' . $filename,
            'original_name' => mb_substr($origName, 0, 255, 'UTF-8')
        ];
    }

    if ($finfo) {
        finfo_close($finfo);
    }
}

if ($postType === 'text' && $content === '') {
    community_json_fail('Bai dang van ban can phan noi dung chinh.');
}
if ($postType === 'media' && empty($storedMedia)) {
    community_json_fail('Bai dang hinh anh can it nhat 1 anh.');
}
if ($postType === 'link' && empty($links)) {
    community_json_fail('Bai dang lien ket can it nhat 1 URL.');
}

if ($content === '') {
    $content = $title;
}

$userStmt = $conn->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
$userStmt->execute([$userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    community_json_fail('Khong tim thay thong tin nguoi dung.', 404);
}

try {
    $conn->beginTransaction();

    $insertPost = $conn->prepare('INSERT INTO community_posts (user_id, user_name, post_title, post_type, content, privacy, status, total_reactions, total_comments) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0)');
    $insertPost->execute([$userId, (string)$user['name'], $title, $postType, $content, $privacy, $status]);
    $postId = (int)$conn->lastInsertId();

    if (!empty($storedMedia)) {
        $insertMedia = $conn->prepare('INSERT INTO community_post_media (post_id, media_type, file_path, original_name, sort_order) VALUES (?, ?, ?, ?, ?)');
        foreach ($storedMedia as $idx => $media) {
            $insertMedia->execute([$postId, 'image', $media['file_path'], $media['original_name'], $idx]);
        }
    }

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

    $conn->commit();

    if (ob_get_length() > 0) {
        ob_clean();
    }

    echo json_encode([
        'ok' => true,
        'message' => 'Dang bai cong dong thanh cong.',
        'post_id' => $postId,
        'redirect_url' => site_url('static/community_feed.php')
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    foreach ($storedMedia as $media) {
        $fullPath = $uploadBaseDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $media['file_path']);
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    community_json_fail('Khong the dang bai cong dong luc nay.', 500, [
        'debug' => $e->getMessage()
    ]);
}

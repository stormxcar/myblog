<?php
include '../components/connect.php';
include '../components/seo_helpers.php';

header('Content-Type: application/json; charset=UTF-8');

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$qLength = function_exists('mb_strlen') ? mb_strlen($q) : strlen($q);
if ($q === '' || $qLength < 2) {
    echo json_encode(['query' => $q, 'items' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $searchTerm = '%' . $q . '%';
    $stmt = $conn->prepare("SELECT id, title, image FROM posts WHERE status = 'active' AND (title LIKE ? OR category LIKE ? OR content LIKE ?) ORDER BY id DESC LIMIT 8");
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);

    $items = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $items[] = [
            'id' => (int)$row['id'],
            'title' => $row['title'],
            'image' => !empty($row['image'])
                ? (blog_is_external_url((string)$row['image']) ? (string)$row['image'] : site_url('uploaded_img/' . ltrim((string)$row['image'], '/')))
                : site_url('uploaded_img/default_img.jpg'),
            'url' => post_path((int)$row['id'], $row['title'])
        ];
    }

    echo json_encode(['query' => $q, 'items' => $items], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['query' => $q, 'items' => []], JSON_UNESCAPED_UNICODE);
}

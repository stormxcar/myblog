<?php
include '../components/connect.php';
include '../components/seo_helpers.php';

$postId = (int)($_GET['post_id'] ?? 0);
if ($postId <= 0) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

$post = null;

try {
    $select = $conn->prepare("SELECT id, title, slug FROM posts WHERE status = ? AND id = ? LIMIT 1");
    $select->execute(['active', $postId]);
    $post = $select->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Backward compatibility for databases that do not have the slug column yet.
    $select = $conn->prepare("SELECT id, title FROM posts WHERE status = ? AND id = ? LIMIT 1");
    $select->execute(['active', $postId]);
    $post = $select->fetch(PDO::FETCH_ASSOC);
}

if (!$post) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

$storedSlug = isset($post['slug']) ? trim((string)$post['slug']) : '';
$canonicalSlug = $storedSlug !== '' ? $storedSlug : post_slug((string)($post['title'] ?? ''), (int)$post['id']);
$targetUrl = site_url('post/' . rawurlencode($canonicalSlug) . '.html');

header('Location: ' . $targetUrl, true, 301);
exit;

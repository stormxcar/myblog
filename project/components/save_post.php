<?php
$is_direct_call = realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'] ?? '');
$is_direct_ajax_call = $is_direct_call && strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
if ($is_direct_ajax_call) {
    ini_set('display_errors', '0');
    ob_start();
}
include __DIR__ . '/connect.php';
include __DIR__ . '/seo_helpers.php';
include __DIR__ . '/security_helpers.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_POST['save_post'])) {
    return;
}

$is_ajax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
if ($is_ajax) {
    // Prevent PHP notices/warnings from polluting JSON payloads.
    ini_set('display_errors', '0');
    header('Content-Type: application/json; charset=utf-8');
}
$user_id = $_SESSION['user_id'] ?? '';

if ($user_id === '') {
    $response_message = 'Bạn cần đăng nhập để lưu bài viết này!';

    if ($is_ajax) {
        if (ob_get_length() > 0) {
            ob_clean();
        }
        echo json_encode([
            'ok' => false,
            'login_required' => true,
            'login_url' => site_url('static/login.php'),
            'message' => $response_message
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $_SESSION['message'] = $response_message;

    header('Location: ' . site_url('static/login.php'));
    exit;
}

blog_security_ensure_tables($conn);
$verifyState = blog_user_verification_state($conn, (int)$user_id);
if (empty($verifyState['is_verified'])) {
    $response_message = 'Tai khoan chua xac minh email. Vui long xac minh de luu bai viet.';

    if ($is_ajax) {
        if (ob_get_length() > 0) {
            ob_clean();
        }
        echo json_encode([
            'ok' => false,
            'verification_required' => true,
            'resend_url' => site_url('static/resend_verification.php') . '?email=' . rawurlencode((string)($verifyState['email'] ?? '')),
            'message' => $response_message
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $_SESSION['message'] = $response_message;
    header('Location: ' . site_url('static/resend_verification.php') . '?email=' . rawurlencode((string)($verifyState['email'] ?? '')));
    exit;
}

$post_id = (int)($_POST['post_id'] ?? 0);
if ($post_id <= 0) {
    $response_message = 'Dữ liệu bài viết không hợp lệ.';

    if ($is_ajax) {
        if (ob_get_length() > 0) {
            ob_clean();
        }
        echo json_encode([
            'ok' => false,
            'message' => $response_message
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $_SESSION['message'] = $response_message;
    $redirect_url = $_SERVER['HTTP_REFERER'] ?? site_url('static/home.php');
    header('Location: ' . $redirect_url);
    exit;
}

try {
    $verify_favorite = $conn->prepare("SELECT 1 FROM `favorite_posts` WHERE post_id = ? AND user_id = ?");
    $verify_favorite->execute([$post_id, $user_id]);

    $saved = false;
    $response_message = '';
    if ($verify_favorite->rowCount() > 0) {
        $delete_favorite = $conn->prepare("DELETE FROM `favorite_posts` WHERE post_id = ? AND user_id = ?");
        $delete_favorite->execute([$post_id, $user_id]);
        $response_message = 'Đã bỏ lưu bài viết!';
    } else {
        $insert_favorite = $conn->prepare("INSERT INTO `favorite_posts`(post_id, user_id) VALUES(?,?)");
        $insert_favorite->execute([$post_id, $user_id]);
        $response_message = 'Đã lưu bài viết!';
        $saved = true;
    }

    if ($is_ajax) {
        if (ob_get_length() > 0) {
            ob_clean();
        }
        echo json_encode([
            'ok' => true,
            'saved' => $saved,
            'message' => $response_message
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $_SESSION['message'] = $response_message;
} catch (Exception $e) {
    $response_message = 'Có lỗi xảy ra khi xử lý yêu cầu!';

    if ($is_ajax) {
        if (ob_get_length() > 0) {
            ob_clean();
        }
        echo json_encode([
            'ok' => false,
            'message' => $response_message
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $_SESSION['message'] = $response_message;
}

$redirect_url = $_SERVER['HTTP_REFERER'] ?? site_url('static/home.php');
header('Location: ' . $redirect_url);
exit;

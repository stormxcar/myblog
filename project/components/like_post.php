<?php
$is_direct_call = realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'] ?? '');
$is_direct_ajax_call = $is_direct_call && strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
if ($is_direct_ajax_call) {
   ini_set('display_errors', '0');
   ob_start();
}
include_once __DIR__ . '/connect.php';
include_once __DIR__ . '/seo_helpers.php';
include_once __DIR__ . '/feature_engine.php';
include_once __DIR__ . '/security_helpers.php';

if (isset($_POST['like_post'])) {
   if (session_status() !== PHP_SESSION_ACTIVE) {
      session_start();
   }

   if (!isset($user_id)) {
      $user_id = $_SESSION['user_id'] ?? '';
   }

   $is_ajax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
   $is_local_debug = preg_match('/^(localhost|127\\.0\\.0\\.1|::1)(:\\d+)?$/i', (string)($_SERVER['HTTP_HOST'] ?? '')) === 1;
   if ($is_ajax) {
      // Prevent PHP notices/warnings from corrupting JSON payloads.
      ini_set('display_errors', '0');
      header('Content-Type: application/json; charset=utf-8');
   }

   $json_fail = static function (string $message, array $extra = []) use ($is_ajax): void {
      if ($is_ajax) {
         if (ob_get_length() > 0) {
            ob_clean();
         }
         echo json_encode(array_merge([
            'ok' => false,
            'message' => $message,
         ], $extra), JSON_UNESCAPED_UNICODE);
         exit;
      }

      $_SESSION['message'] = $message;
   };

   blog_ensure_feature_tables($conn);
   blog_security_ensure_tables($conn);

   // người dùng đã đăng nhập
   if ($user_id != '') {
      $verifyState = blog_user_verification_state($conn, (int)$user_id);
      if (empty($verifyState['is_verified'])) {
         $json_fail('Tai khoan chua xac minh email. Vui long xac minh de tiep tuc.', [
            'verification_required' => true,
            'resend_url' => site_url('static/resend_verification.php') . '?email=' . rawurlencode((string)($verifyState['email'] ?? ''))
         ]);
         if ($is_ajax) {
            exit;
         }
         header('Location: ' . site_url('static/resend_verification.php') . '?email=' . rawurlencode((string)($verifyState['email'] ?? '')));
         exit;
      }

      $post_id = (int)($_POST['post_id'] ?? 0);
      $admin_id = (int)($_POST['admin_id'] ?? 0);

      if ($post_id <= 0) {
         $json_fail('Dữ liệu bài viết không hợp lệ.');
         if ($is_ajax) {
            exit;
         }
         $redirect_url = $_SERVER['HTTP_REFERER'] ?? site_url('static/home.php');
         header('Location: ' . $redirect_url);
         exit;
      }

      try {
         $select_post_like = $conn->prepare("SELECT * FROM `likes` WHERE post_id = ? AND user_id = ?");
         $select_post_like->execute([$post_id, $user_id]);

         $liked = false;
         $response_message = '';
         if ($select_post_like->rowCount() > 0) {
            $remove_like = $conn->prepare("DELETE FROM `likes` WHERE post_id = ? AND user_id = ?");
            $remove_like->execute([$post_id, $user_id]);
            $response_message = 'Đã hủy thích';
         } else {
            $add_like = $conn->prepare("INSERT INTO `likes`(user_id, post_id, admin_id) VALUES(?,?,?)");
            $add_like->execute([$user_id, $post_id, $admin_id]);
            $response_message = 'Đã thích';
            $liked = true;

            if ($admin_id > 0) {
               $ownerSql = 'SELECT id, name FROM users WHERE id = ? LIMIT 1';
               $ownerParams = [$admin_id];
               if (function_exists('blog_has_legacy_admin_id_column') && blog_has_legacy_admin_id_column($conn)) {
                  $ownerSql = 'SELECT id, name FROM users WHERE legacy_admin_id = ? OR id = ? LIMIT 1';
                  $ownerParams = [$admin_id, $admin_id];
               }
               $ownerStmt = $conn->prepare($ownerSql);
               $ownerStmt->execute($ownerParams);
               $owner = $ownerStmt->fetch(PDO::FETCH_ASSOC);
               if ($owner && (int)$owner['id'] !== (int)$user_id) {
                  blog_push_notification(
                     $conn,
                     (int)$owner['id'],
                     'like',
                     'Bai viet vua duoc thich',
                     'Co mot nguoi vua thich bai viet cua ban.',
                     $_SERVER['HTTP_REFERER'] ?? site_url('static/home.php')
                  );
               }
            }
         }

         if ($is_ajax) {
            $count_stmt = $conn->prepare("SELECT COUNT(*) FROM `likes` WHERE post_id = ?");
            $count_stmt->execute([$post_id]);
            if (ob_get_length() > 0) {
               ob_clean();
            }
            echo json_encode([
               'ok' => true,
               'liked' => $liked,
               'like_count' => (int)$count_stmt->fetchColumn(),
               'message' => $response_message
            ], JSON_UNESCAPED_UNICODE);
            exit;
         }

         $_SESSION['message'] = $response_message;
      } catch (Throwable $e) {
         $json_fail('Không thể cập nhật trạng thái thích lúc này.', $is_local_debug ? [
            'debug' => $e->getMessage() . ' @' . basename((string)$e->getFile()) . ':' . (int)$e->getLine()
         ] : []);
      }
   } else {
      $response_message = 'Bạn cần đăng nhập để thích bài viết này!';
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
      header('Location: ' . site_url('static/login.php')); // Chuyển hướng đến trang đăng nhập
      exit;
   }

   // header("Location: " . $_SERVER['PHP_SELF'] . '?post_id=' . $post_id);
   $redirect_url = $_SERVER['HTTP_REFERER'] ?? site_url('static/home.php');
   header("Location: " . $redirect_url);
   exit;
}

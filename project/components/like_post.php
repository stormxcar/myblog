<?php
if (isset($_POST['like_post'])) {
   // người dùng đã đăng nhập
   if ($user_id != '') {

      $post_id = $_POST['post_id'];
      $post_id = filter_var($post_id, FILTER_SANITIZE_STRING);
      $admin_id = $_POST['admin_id'];
      $admin_id = filter_var($admin_id, FILTER_SANITIZE_STRING);

      $select_post_like = $conn->prepare("SELECT * FROM `likes` WHERE post_id = ? AND user_id = ?");
      $select_post_like->execute([$post_id, $user_id]);

      if ($select_post_like->rowCount() > 0) {
         $remove_like = $conn->prepare("DELETE FROM `likes` WHERE post_id = ?");
         $remove_like->execute([$post_id]);
         $_SESSION['message'] = 'Đã hủy thích';
      } else {
         $add_like = $conn->prepare("INSERT INTO `likes`(user_id, post_id, admin_id) VALUES(?,?,?)");
         $add_like->execute([$user_id, $post_id, $admin_id]);
         $_SESSION['message'] = 'Đã thích';
      }
   } else {
      $_SESSION['message'] = 'Bạn cần đăng nhập để thích bài viết này!';
      header('Location: ../static/login.php'); // Chuyển hướng đến trang đăng nhập
      exit;
   }

   // header("Location: " . $_SERVER['PHP_SELF'] . '?post_id=' . $post_id);
   $redirect_url = $_SERVER['HTTP_REFERER'];
   header("Location: " . $redirect_url);
   exit;
}

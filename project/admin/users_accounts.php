<?php
include '../components/connect.php';
session_start();
$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
   header('location:admin_login.php');
}

// $message = [];

if (isset($_POST['update_banned_status'])) {
   $user_id = $_POST['user_id']; // Lấy ID của người dùng từ form
   $banned_status = isset($_POST['banned']) ? 1 : 0; // Nếu checkbox được check thì đặt giá trị là 1, ngược lại là 0

   // Cập nhật trạng thái banned trong cơ sở dữ liệu
   $update_banned = $conn->prepare("UPDATE `users` SET banned = ? WHERE id = ?");
   if ($update_banned->execute([$banned_status, $user_id])) {
      // $message[] = 'Trạng thái tài khoản đã được cập nhật!';
   } else {
      // $message[] = 'Có lỗi xảy ra khi cập nhật trạng thái!';
   }
}

if (isset($_POST['delete_user'])) {
   $user_id = $_POST['user_id'];

   // Xóa người dùng khỏi cơ sở dữ liệu
   $delete_user = $conn->prepare("DELETE FROM `users` WHERE id = ?");
   if ($delete_user->execute([$user_id])) {
      // $message[] = 'Người dùng đã được xóa thành công!';
   } else {
      // $message[] = 'Có lỗi xảy ra khi xóa người dùng!';
   }
}

function updateUserRatings($conn) {
   // Lấy tất cả người dùng
   $users = $conn->query("SELECT id FROM users");

   foreach ($users as $user) {
       $userId = $user['id'];

       // Lấy số lượng bình luận và lượt thích của người dùng
       $commentCount = $conn->prepare("SELECT COUNT(*) FROM comments WHERE user_id = ?");
       $commentCount->execute([$userId]);
       $numComments = $commentCount->fetchColumn();

       $likeCount = $conn->prepare("SELECT COUNT(*) FROM likes WHERE user_id = ?");
       $likeCount->execute([$userId]);
       $numLikes = $likeCount->fetchColumn();

       // Xác định mức độ tương tác
       if ($numComments > 5 && $numLikes > 5) {
           $rating = 'Cao';
       } elseif ($numComments >= 2 && $numLikes >= 2) {
           $rating = 'Ổn định';
       } else {
           $rating = 'Thấp';
       }

       // Cập nhật đánh giá vào database
       $updateRating = $conn->prepare("UPDATE users SET level_of_interaction = ? WHERE id = ?");
       $updateRating->execute([$rating, $userId]);
   }
}

// Gọi hàm để cập nhật đánh giá
updateUserRatings($conn);
?>

<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Tài khoản người dùng</title>

   <!-- font awesome cdn link  -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
   <!-- custom css file link  -->
   <link rel="stylesheet" href="../css/admin_style_edit.css">
</head>

<body>

   <?php include '../components/admin_header.php' ?>

   <!-- users accounts section starts  -->
   <section class="accounts">

      <h1 class="heading">Tài khoản người dùng</h1>

      <div class="box-container">

         <table>
            <thead>
               <th>STT</th>
               <th>UserID</th>
               <th>Tên đăng nhập</th>
               <th>Tổng bình luận</th>
               <th>Tổng lượt thích</th>
               <th>Có avatar</th>
               <th>Đánh giá <br>
                  <p>(tương tác với hệ thống)</p>
               </th>
               <th>Quản lý <br>
                  <p>(Vĩnh viễn)</p>
               </th>
               <th>Khóa TK <br>
                  <p>(Tạm thời)</p>
               </th>
            </thead>

            <tbody>
               <?php
               $select_account = $conn->prepare("SELECT * FROM `users`");
               $select_account->execute();
               if ($select_account->rowCount() > 0) {
                  $count = 1;
                  while ($fetch_accounts = $select_account->fetch(PDO::FETCH_ASSOC)) {
                     $user_id = $fetch_accounts['id'];
                     $count_user_comments = $conn->prepare("SELECT * FROM `comments` WHERE user_id = ?");
                     $count_user_comments->execute([$user_id]);
                     $total_user_comments = $count_user_comments->rowCount();
                     $count_user_likes = $conn->prepare("SELECT * FROM `likes` WHERE user_id = ?");
                     $count_user_likes->execute([$user_id]);
                     $total_user_likes = $count_user_likes->rowCount();
               ?>

                     <tr>
                        <td><?= $count++; ?></td>
                        <td><span><?= $user_id; ?></span></td>
                        <td><span><?= $fetch_accounts['name']; ?></span></td>
                        <td><span><?= $total_user_comments; ?></span></td>
                        <td><span><?= $total_user_likes; ?></span></td>
                        <td><span><?= $fetch_accounts['avatar'] == NULL ? "Không": "Có" ?></span></td>
                        <td><span><?=$fetch_accounts['level_of_interaction'] ?></span></td>
                        <td>
                           <form action="" method="post">
                              <input type="hidden" name="user_id" value="<?= $user_id; ?>">
                              <button class="delete-btn" type="submit" name="delete_user" onclick="return confirm('Bạn có chắc chắn muốn xóa người dùng này?');">Xóa</button>
                           </form>
                        </td>
                        <td>
                           <form method="post">
                              <input type="hidden" name="user_id" value="<?= $user_id; ?>">
                              <input type="checkbox" name="banned" class="banned_check" <?= $fetch_accounts['banned'] ? 'checked' : ''; ?> onchange="this.form.submit()">
                              <input type="hidden" name="update_banned_status" value="1">
                           </form>
                        </td>
                     </tr>

               <?php
                  }
               } else {
                  echo '<p class="empty">Không có tài khoản người dùng nào!</p>';
               }
               ?>
            </tbody>
         </table>
      </div>
   </section>

   <!-- custom js file link  -->
   <script src="../js/admin_script.js"></script>

</body>

</html>
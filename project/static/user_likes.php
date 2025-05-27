<?php

include '../components/connect.php';

session_start();
$message = [];

if (!isset($_SERVER['HTTP_REFERER'])) {
   header('location: home.php');
   exit;
}

if (isset($_SESSION['user_id'])) {
   $user_id = $_SESSION['user_id'];
} else {
   $user_id = '';
   header('location:home.php');
};

include '../components/like_post.php';

// Xử lý lưu bài viết
if (isset($_POST['save_post']) && isset($_POST['post_id']) && !empty($user_id)) {
   $post_id = $_POST['post_id'];

   $stmt_check = $conn->prepare("SELECT * FROM favorite_posts WHERE user_id = ? AND post_id = ?");
   $stmt_check->execute([$user_id, $post_id]);

   if ($stmt_check->rowCount() > 0) {
      $stmt_delete = $conn->prepare("DELETE FROM favorite_posts WHERE user_id = ? AND post_id = ?");
      $stmt_delete->execute([$user_id, $post_id]);
      $_SESSION['message'] = 'Đã xóa bài viết được lưu';
   } else {
      $stmt_insert = $conn->prepare("INSERT INTO favorite_posts (user_id, post_id) VALUES (?, ?)");
      $stmt_insert->execute([$user_id, $post_id]);
      $_SESSION['message'] = 'Đã lưu bài viết';
   }
   header("Location: " . $_SERVER['PHP_SELF']);
   exit;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Bài viết yêu thích</title>

   <!-- font awesome cdn link  -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
   <!-- custom css file link  -->
   <link rel="stylesheet" href="../css/style_edit.css">
   <link rel="stylesheet" href="../css/style_dark.css">
   <!-- custom js file link  -->
   <script src="../js/script_edit.js"></script>
</head>

<body>

   <!-- header section starts  -->
   <?php include '../components/user_header.php'; ?>
   <!-- header section ends -->

   <?php if (isset($_SESSION['message'])) : ?>
      <div class="message" id="message">
         <div class="message_detail">
            <i class="fa-solid fa-bell"></i>
            <span><?= $_SESSION['message'] ?></span>
         </div>

         <div class="progress-bar" id="progressBar"></div>
      </div>
   <?php
      unset($_SESSION['message']); // Clear the message after displaying it
   endif;
   ?>

   <section class="posts-container" style="padding-top:12rem">

      <h1 class="heading">Danh sách bài viết yêu thích</h1>

      <div class="box-container">

         <?php
         $select_likes = $conn->prepare("SELECT * FROM `likes` WHERE user_id = ?");
         $select_likes->execute([$user_id]);
         if ($select_likes->rowCount() > 0) {
            while ($fetch_likes = $select_likes->fetch(PDO::FETCH_ASSOC)) {
               $select_posts = $conn->prepare("SELECT * FROM `posts` WHERE id = ?");
               $select_posts->execute([$fetch_likes['post_id']]);
               if ($select_posts->rowCount() > 0) {
                  while ($fetch_posts = $select_posts->fetch(PDO::FETCH_ASSOC)) {
                     if ($fetch_posts['status'] != 'deactive') {

                        $post_id = $fetch_posts['id'];

                        $count_post_likes = $conn->prepare("SELECT * FROM `likes` WHERE post_id = ?");
                        $count_post_likes->execute([$post_id]);
                        $total_post_likes = $count_post_likes->rowCount();

                        $count_post_likes = $conn->prepare("SELECT * FROM `likes` WHERE post_id = ?");
                        $count_post_likes->execute([$post_id]);
                        $total_post_likes = $count_post_likes->rowCount();

                        $confirm_save = $conn->prepare("SELECT * FROM `favorite_posts` WHERE user_id = ? AND post_id = ?");
                        $confirm_save->execute([$user_id, $post_id]);
         ?>
                        <form class="box" method="post">
                           <input type="hidden" name="post_id" value="<?= $post_id; ?>">
                           <input type="hidden" name="admin_id" value="<?= $fetch_posts['admin_id']; ?>">
                           <div class="post-admin">
                              <div class="details_left">
                                 <i class="fas fa-user"></i>
                                 <div>
                                    <a href="author_posts.php?author=<?= $fetch_posts['name']; ?>"><?= $fetch_posts['name']; ?></a>
                                    <div><?= $fetch_posts['date']; ?></div>
                                 </div>
                              </div>
                              <button type="submit" name="save_post" class="save_mark-btn"><i class="fa-solid fa-bookmark" style="<?php if ($confirm_save->rowCount() > 0) {
                                                                                                                                       echo 'color:yellow;';
                                                                                                                                    } ?>  "></i></button>


                           </div>

                           <?php
                           if ($fetch_posts['image'] != '') {
                           ?>
                              <img src="../uploaded_img/<?= $fetch_posts['image']; ?>" class="post-image" alt="">
                           <?php
                           }
                           ?>
                           <div class="post-title"><?= $fetch_posts['title']; ?></div>
                           <div class="post-content content-30"><?= $fetch_posts['content']; ?></div>
                           <a href="view_post.php?post_id=<?= $post_id; ?>" class="inline-btn">Đọc Thêm</a>
                           <div class="icons">
                              <a href="view_post.php?post_id=<?= $post_id; ?>"><i class="fas fa-comment"></i><span>(<?= $total_post_likes; ?>)</span></a>
                              <button type="submit" name="like_post"><i class="fas fa-heart" style="<?php if ($total_post_likes > 0 and $user_id != '') {
                                                                                                         echo 'color:red;';
                                                                                                      }; ?>"></i><span>(<?= $total_post_likes; ?>)</span></button>
                           </div>

                        </form>
         <?php
                     }
                  }
               }
            }
         } else {
            echo '
            <div style="margin:0 auto;display: flex;flex-direction:column;">
            <p style="font-weight: bold;box-shadow:none !important" class="empty">Hiện tại, bạn chưa thích bài viết nào!</p>
            <a href="posts.php" class="inline-btn" style="margin-top:2rem;">Xem bài viết</a>
            </div>
            

            ';
         }
         ?>
      </div>

      </div>

   </section>

   <?php include '../components/footer.php'; ?>

</body>

</html>
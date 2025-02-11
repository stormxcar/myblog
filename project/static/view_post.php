<?php

include '../components/connect.php';

session_start();
$message = [];

if (isset($_SESSION['user_id'])) {
   $user_id = $_SESSION['user_id'];
} else {
   $user_id = '';
   if (isset($_POST['save_post']) && isset($_POST['post_id'])) {
      $_SESSION['message'] = 'Bạn cần đăng nhập để lưu bài viết này!';
      header('Location: ../static/login.php');
      exit;
   }
}

include '../components/like_post.php';

$get_id = $_GET['post_id'];

// if (!isset($get_id)) {
//    $_SESSION['message'] = 'Bạn cần đăng nhập để lưu bài viết này!';
//    header('Location: ../static/login.php');
//    exit;
// }

if (isset($_POST['add_comment'])) {


   $admin_id = $_POST['admin_id'];
   $admin_id = filter_var($admin_id, FILTER_SANITIZE_STRING);
   $user_name = $_POST['user_name'];
   $user_name = filter_var($user_name, FILTER_SANITIZE_STRING);
   $comment = $_POST['comment'];
   $comment = filter_var($comment, FILTER_SANITIZE_STRING);

   $verify_comment = $conn->prepare("SELECT * FROM `comments` WHERE post_id = ? AND admin_id = ? AND user_id = ? AND user_name = ? AND comment = ?");
   $verify_comment->execute([$get_id, $admin_id, $user_id, $user_name, $comment]);

   if ($verify_comment->rowCount() > 0) {
      $_SESSION['message'] = 'Đã gửi bình luận!';
   } else {
      $insert_comment = $conn->prepare("INSERT INTO `comments`(post_id, admin_id, user_id, user_name, comment) VALUES(?,?,?,?,?)");
      $insert_comment->execute([$get_id, $admin_id, $user_id, $user_name, $comment]);
      $_SESSION['message'] = 'Đã gửi bình luận!';
   }
}

if (isset($_POST['edit_comment'])) {
   $edit_comment_id = $_POST['edit_comment_id'];
   $edit_comment_id = filter_var($edit_comment_id, FILTER_SANITIZE_STRING);
   $comment_edit_box = $_POST['comment_edit_box'];
   $comment_edit_box = filter_var($comment_edit_box, FILTER_SANITIZE_STRING);

   $verify_comment = $conn->prepare("SELECT * FROM `comments` WHERE comment = ? AND id = ?");
   $verify_comment->execute([$comment_edit_box, $edit_comment_id]);

   if ($verify_comment->rowCount() > 0) {
      $_SESSION['message'] = 'Bình luận của bạn đã được thêm!';
   } else {
      $update_comment = $conn->prepare("UPDATE `comments` SET comment = ? WHERE id = ?");
      $update_comment->execute([$comment_edit_box, $edit_comment_id]);
      $_SESSION['message'] = 'Bình luận của bạn đã được chỉnh sửa!';
   }
}

if (isset($_POST['delete_comment'])) {
   $delete_comment_id = $_POST['comment_id'];
   $delete_comment_id = filter_var($delete_comment_id, FILTER_SANITIZE_STRING);
   $delete_comment = $conn->prepare("DELETE FROM `comments` WHERE id = ?");
   $delete_comment->execute([$delete_comment_id]);
   $_SESSION['message'] = 'Đã xóa bình luận!';
}

// Xử lý lưu bài viết
if (isset($_POST['save_post']) && isset($_POST['post_id']) && !empty($user_id)) {

   $post_id = $_POST['post_id'];

   $stmt_check = $conn->prepare("SELECT * FROM favorite_posts WHERE user_id = ? AND post_id = ?");
   $stmt_check->execute([$user_id, $post_id]);

   if ($stmt_check->rowCount() > 0) {
      $stmt_delete = $conn->prepare("DELETE FROM favorite_posts WHERE user_id = ? AND post_id = ?");
      $stmt_delete->execute([$user_id, $post_id]);
      $_SESSION['message'] = "Đã xóa khỏi danh sách yêu thích";
   } else {
      $stmt_insert = $conn->prepare("INSERT INTO favorite_posts (user_id, post_id) VALUES (?, ?)");
      $stmt_insert->execute([$user_id, $post_id]);
      $_SESSION['message'] = "Đã lưu bài viết";
   }
   header("Location: " . $_SERVER['PHP_SELF'] . '?post_id=' . $post_id);
   exit;
}

$post_id = $_GET['post_id']; // hoặc $_POST['post_id'] nếu được gửi bằng phương thức POST

// từ đường dẫn có id của bài viết hiện tại , ta sẽ lấy được loại bài viết mà bài viết này đang có rồi lấy thẻ tag đó tìm các bài viết có thẻ tag đó và hiện ra .
// Truy vấn để lấy thẻ tag của bài viết hiện tại
// Lấy thông tin bài viết hiện tại
$select_post_tag = $conn->prepare("SELECT category FROM `posts` WHERE id = ?");
$select_post_tag->execute([$get_id]);
$fetch_post_tag = $select_post_tag->fetch(PDO::FETCH_ASSOC);

$current_tag = $fetch_post_tag['category'];

// Truy vấn các bài viết liên quan
$select_related_posts = $conn->prepare("SELECT * FROM `posts` WHERE category = ? AND id != ? AND status = 'active' LIMIT 4");
$select_related_posts->execute([$current_tag, $get_id]);

?>

<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Xem bài viết</title>
   <!-- font awesome cdn link  -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
   <!-- custom css file link  -->
   <link rel="stylesheet" href="../css/style_edit.css">
   <link rel="stylesheet" href="../css/style_dark.css">
   <!-- custom js file link  -->
   <script src="../js/script_edit.js"></script>

   <!-- script framework -->
   <script type="text/javascript" src="https://platform-api.sharethis.com/js/sharethis.js#property=67aac4723093bd0013c183d7&product=sticky-share-buttons&source=platform" async="async"></script>
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
      unset($_SESSION['message']); // Xóa tin nhắn sau khi hiển thị
   endif;
   ?>

   <?php
   if (isset($_POST['open_edit_box'])) {
      $comment_id = $_POST['comment_id'];
      $comment_id = filter_var($comment_id, FILTER_SANITIZE_STRING);
   ?>
      <section class="comment-edit-form">
         <p>Chỉnh sửa bình luận của bạn</p>
         <?php
         $select_edit_comment = $conn->prepare("SELECT * FROM `comments` WHERE id = ?");
         $select_edit_comment->execute([$comment_id]);
         $fetch_edit_comment = $select_edit_comment->fetch(PDO::FETCH_ASSOC);
         ?>
         <form action="" method="POST">
            <input type="hidden" name="edit_comment_id" value="<?= $comment_id; ?>">
            <textarea name="comment_edit_box" required cols="30" rows="10" placeholder="Nhập bình luận của bạn"><?= $fetch_edit_comment['comment']; ?></textarea>
            <button type="submit" class="inline-btn" name="edit_comment">Chỉnh sửa</button>
            <div class="inline-option-btn" onclick="window.location.href = 'view_post.php?post_id=<?= $get_id; ?>';">Hủy</div>
         </form>
      </section>
   <?php
   }
   ?>

   <section class="posts-container" style="padding-bottom: 0;">

      <div class="box-container" style="overflow-x:hidden">

         <?php
         $select_posts = $conn->prepare("SELECT * FROM `posts` WHERE status = ? AND id = ?");
         $select_posts->execute(['active', $get_id]);
         if ($select_posts->rowCount() > 0) {
            while ($fetch_posts = $select_posts->fetch(PDO::FETCH_ASSOC)) {

               $post_id = $fetch_posts['id'];

               $count_post_comments = $conn->prepare("SELECT * FROM `comments` WHERE post_id = ?");
               $count_post_comments->execute([$post_id]);
               $total_post_comments = $count_post_comments->rowCount();

               $count_post_likes = $conn->prepare("SELECT * FROM `likes` WHERE post_id = ?");
               $count_post_likes->execute([$post_id]);
               $total_post_likes = $count_post_likes->rowCount();

               $confirm_likes = $conn->prepare("SELECT * FROM `likes` WHERE user_id = ? AND post_id = ?");
               $confirm_likes->execute([$user_id, $post_id]);

               $confirm_save = $conn->prepare("SELECT * FROM `favorite_posts` WHERE user_id = ? AND post_id = ?");
               $confirm_save->execute([$user_id, $post_id]);
         ?>
               <form class="box view_box" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
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
                     <img id="image_show" src="../uploaded_img/<?= $fetch_posts['image']; ?>" class="post-image" alt="">

                  <?php
                  }
                  ?>
                  <div class="post-title"><?= $fetch_posts['title']; ?></div>
                  <div class="post-content"><?= $fetch_posts['content']; ?></div>
                  <div class="icons">
                     <div><i class="fas fa-comment"></i><span>(<?= $total_post_comments; ?>)</span></div>
                     <button type="submit" name="like_post"><i class="fas fa-heart" style="<?php if ($confirm_likes->rowCount() > 0) {
                                                                                                echo 'color:var(--red);';
                                                                                             } ?>  "></i><span>(<?= $total_post_likes; ?>)</span></button>

                     


                  </div>

               </form>
         <?php
            }
         } else {
            echo '<p class="empty">Không có bài viết nào!</p>';
         }
         ?>
      </div>

   </section>

   <section class="comments-container">

      <p class="comment-title">Thêm Bình luận</p>
      <?php
      if ($user_id != '') {
         $select_admin_id = $conn->prepare("SELECT * FROM `posts` WHERE id = ?");
         $select_admin_id->execute([$get_id]);
         $fetch_admin_id = $select_admin_id->fetch(PDO::FETCH_ASSOC);
      ?>
         <form action="" method="post" class="add-comment">
            <input type="hidden" name="admin_id" value="<?= $fetch_admin_id['admin_id']; ?>">
            <input type="hidden" name="user_name" value="<?= $fetch_profile['name']; ?>">
            <p class="user"><i class="fas fa-user"></i><a href="update.php"><?= $fetch_profile['name']; ?></a></p>
            <textarea name="comment" maxlength="1000" class="comment-box" cols="30" rows="10" placeholder="Bình luận ở đây" required></textarea>
            <input type="submit" value="Gửi" class="inline-btn" name="add_comment">
         </form>
      <?php
      } else {
      ?>
         <div class="add-comment">
            <p>Vui lòng đăng nhập để bình luận</p>
            <a href="login.php" class="inline-btn">Đăng nhập ngay</a>
         </div>
      <?php
      }
      ?>

      <p class="comment-title">Bình luận:</p>
      <div class="user-comments-container">
         <?php
         $select_comments = $conn->prepare("SELECT * FROM `comments` WHERE post_id = ?");
         $select_comments->execute([$get_id]);
         if ($select_comments->rowCount() > 0) {
            while ($fetch_comments = $select_comments->fetch(PDO::FETCH_ASSOC)) {
         ?>
               <div class="show-comments" style="<?php if ($fetch_comments['user_id'] == $user_id) {
                                                      echo 'order:-1;';
                                                   } ?>">
                  <div class="comment-user">
                     <i class="fas fa-user"></i>
                     <div>
                        <span><?= $fetch_comments['user_name']; ?></span>
                        <div><?= $fetch_comments['date']; ?></div>
                     </div>
                  </div>
                  <div class="comment-box" style="<?php if ($fetch_comments['user_id'] == $user_id) {
                                                      echo 'color:var(--white); background:var(--black);';
                                                   } ?>"><?= $fetch_comments['comment']; ?>
                     <i class="fa-solid fa-ellipsis-vertical"></i>
                  </div>
                  <?php
                  if ($fetch_comments['user_id'] == $user_id) {
                  ?>

                     <form action="" method="POST" class="form_handle_comment">
                        <input type="hidden" name="comment_id" value="<?= $fetch_comments['id']; ?>">
                        <button type="submit" class="inline-option-btn" name="open_edit_box">Chỉnh sửa bình luận</button>
                        <button type="submit" class="inline-delete-btn" name="delete_comment" onclick="return confirm('Bạn chắc chắn muốn xóa bình luận này chứ?');">Xóa Bình luận</button></button>
                     </form>
                  <?php
                  }
                  ?>
               </div>
         <?php
            }
         } else {
            echo '<p class="empty">Chưa có bình luận nào được thêm cho bài viết này!</p>';
         }
         ?>
      </div>

   </section>

   <section class="posts-container" id="related-posts">

      <h1 class="heading">Bài viết liên quan</h1>

      <div class="box-container">
         <?php
         if ($select_related_posts->rowCount() > 0) {
            while ($fetch_related_posts = $select_related_posts->fetch(PDO::FETCH_ASSOC)) {
               $related_post_id = $fetch_related_posts['id'];

               $count_post_comments = $conn->prepare("SELECT * FROM `comments` WHERE post_id = ?");
               $count_post_comments->execute([$related_post_id]);
               $total_post_comments = $count_post_comments->rowCount();

               $count_post_likes = $conn->prepare("SELECT * FROM `likes` WHERE post_id = ?");
               $count_post_likes->execute([$related_post_id]);
               $total_post_likes = $count_post_likes->rowCount();

               $confirm_likes = $conn->prepare("SELECT * FROM `likes` WHERE user_id = ? AND post_id = ?");
               $confirm_likes->execute([$user_id, $related_post_id]);

               $confirm_save = $conn->prepare("SELECT * FROM `favorite_posts` WHERE user_id = ? AND post_id = ?");
               $confirm_save->execute([$user_id, $related_post_id]);
         ?>
               <form class="box_byPost" method="post">
                  <input type="hidden" name="post_id" value="<?= $related_post_id; ?>">
                  <input type="hidden" name="admin_id" value="<?= $fetch_related_posts['admin_id']; ?>">
                  <div class="post-admin">
                     <div class="details_left">
                        <i class="fas fa-user"></i>
                        <div>
                           <a href="author_posts.php?author=<?= $fetch_related_posts['name']; ?>"><?= $fetch_related_posts['name']; ?></a>
                           <div><?= $fetch_related_posts['date']; ?></div>
                        </div>
                     </div>
                     <button type="submit" name="save_post" class="save_mark-btn"><i class="fa-solid fa-bookmark" style="<?php if ($confirm_save->rowCount() > 0) {
                                                                                                                              echo 'color:yellow;';
                                                                                                                           } ?>  "></i></button>
                  </div>

                  <?php
                  if ($fetch_related_posts['image'] != '') {
                  ?>
                     <img src="../uploaded_img/<?= $fetch_related_posts['image']; ?>" class="post-image" alt="">
                  <?php
                  }
                  ?>
                  <div class="post-title"><?= $fetch_related_posts['title']; ?></div>
                  <div class="post-content content-30"><?= $fetch_related_posts['content']; ?></div>
                  <a href="view_post.php?post_id=<?= $related_post_id; ?>" class="inline-btn">Đọc thêm</a>
                  <a href="category.php?category=<?= $fetch_related_posts['category']; ?>" class="post-cat"> <i class="fas fa-tag"></i> <span><?= $fetch_related_posts['category']; ?></span></a>
                  <div class="icons">
                     <a href="view_post.php?post_id=<?= $related_post_id; ?>"><i class="fas fa-comment"></i><span>(<?= $total_post_comments; ?>)</span></a>
                     <button type="submit" name="like_post"><i class="fas fa-heart" style="<?php if ($confirm_likes->rowCount() > 0) {
                                                                                                echo 'color:var(--red);';
                                                                                             } ?>  "></i><span>(<?= $total_post_likes; ?>)</span>
                     </button>
                     <!-- <button><i class="fa-solid fa-share-from-square"></i></button> -->

                  </div>

               </form>
         <?php
            }
         } else {
            echo '<p class="empty">Không có bài viết liên quan!</p>';
         }
         ?>
      </div>

      <div id="imageModal" class="modal">
         <span id="closeModal">&times;</span>
         <img class="modal-content" id="modalImage">
         <span id="saveImage"><i class="fa-solid fa-download"></i></span>
      </div>
   </section>

   <div class="sharethis-sticky-share-buttons"></div>

   <?php include '../components/footer.php'; ?>

   <script>
      const nav_handle_comment = document.querySelectorAll('.fa-ellipsis-vertical');
      const form_handle_comment = document.querySelectorAll('.form_handle_comment');

      for (let i = 0; i < nav_handle_comment.length; i++) {
         nav_handle_comment[i].addEventListener('click', () => {
            form_handle_comment[i].classList.toggle('active');
         });
      }
   </script>


</body>
<script>
   // Lấy các phần tử cần thiết
   const modal = document.getElementById("imageModal");
   const modalImg = document.getElementById("modalImage");
   const avatar = document.getElementById("image_show");
   const closeModal = document.getElementById("closeModal");
   const saveImage = document.getElementById('saveImage');

   // Khi click vào ảnh đại diện, hiển thị ảnh trong modal
   avatar.onclick = function() {
      modal.style.display = "flex"; // Hiển thị modal
      modalImg.src = this.src; // Đặt ảnh vào modal
   }

   // Khi người dùng click vào nút đóng, ẩn modal
   closeModal.onclick = function() {
      modal.style.display = "none";
   }

   // Khi người dùng click vào vùng ngoài ảnh, ẩn modal
   modal.onclick = function(event) {
      if (event.target == modal) {
         modal.style.display = "none";
      }
   }

   saveImage.onclick = function() {
      //   const avatar = document.getElementById('avatar');
      const link = document.createElement('a');
      link.href = avatar.src; // Đường dẫn đến ảnh
      link.download = 'download_image'; // Tên file khi tải về
      link.click(); // Kích hoạt việc tải về
      saveImage.style.color = 'green';
   }
</script>


</html>
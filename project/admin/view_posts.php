<?php
include '../components/connect.php';

session_start();

if (isset($_SESSION['admin_id'])) {
    $admin_id = $_SESSION['admin_id'];
} else {
    header('location:admin_login.php');
    exit;
}

if (isset($_POST['delete'])) {
    // Kiểm tra xem post_id có tồn tại không
    if (!isset($_POST['post_id'])) {
        $message[] = 'Không tìm thấy ID bài viết!';
    } else {
        $post_id = $_POST['post_id'];

        // Lấy thông tin bài viết để kiểm tra
        $select_post = $conn->prepare("SELECT * FROM `posts` WHERE id = ? AND admin_id = ?");
        $select_post->execute([$post_id, $admin_id]);
        $fetch_post = $select_post->fetch(PDO::FETCH_ASSOC);

        // Kiểm tra nếu bài viết tồn tại
        if ($fetch_post === false) {
            $message[] = 'Bài viết không tồn tại hoặc bạn không có quyền xóa bài viết này!';
        } else {
            // Xóa bài viết
            $delete_post = $conn->prepare("DELETE FROM `posts` WHERE id = ? AND admin_id = ?");
            $delete_post->execute([$post_id, $admin_id]);

            if ($delete_post->rowCount() > 0) {
                $message[] = 'Bài viết đã được xóa!';
            } else {
                $message[] = 'Có lỗi xảy ra khi xóa bài viết!';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Bài viết</title>

   <!-- font awesome cdn link  -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

   <!-- custom css file link  -->
   <link rel="stylesheet" href="../css/admin_style_edit.css">

</head>
<body>

<?php include '../components/admin_header.php' ?>

<section class="show-posts">
   <h1 class="heading">Các bài viết của bạn</h1>
   <div class="box-container">
      <table>
         <thead>
            <tr>
               <th>STT</th>
               <th>Ảnh</th>
               <th>Trạng thái</th>
               <th>Tiêu đề</th>
               <th>Nội dung</th>
               <th>Chỉnh sửa</th>
               <th>Xóa</th>
               <th>Xem</th>
            </tr>
         </thead>
         <tbody>
            <?php
               $select_posts = $conn->prepare("SELECT * FROM `posts` WHERE admin_id = ?");
               $select_posts->execute([$admin_id]);
               if($select_posts->rowCount() > 0){
                  $count = 1; // Biến để đếm số thứ tự
                  while($fetch_posts = $select_posts->fetch(PDO::FETCH_ASSOC)){
                     $post_id = $fetch_posts['id'];

                     $count_post_comments = $conn->prepare("SELECT * FROM `comments` WHERE post_id = ?");
                     $count_post_comments->execute([$post_id]);
                     $total_post_comments = $count_post_comments->rowCount();

                     $count_post_likes = $conn->prepare("SELECT * FROM `likes` WHERE post_id = ?");
                     $count_post_likes->execute([$post_id]);
                     $total_post_likes = $count_post_likes->rowCount();
            ?>
            <tr>
               <td><?= $count++; ?></td>
               <td>
                  <?php if($fetch_posts['image'] != ''){ ?>
                  <img src="../uploaded_img/<?= $fetch_posts['image']; ?>" class="image" alt="">
                  <?php } ?>
               </td>
               <td>
                  <div class="status" style="background-color:<?php if($fetch_posts['status'] == 'active'){echo 'limegreen'; }else{echo 'coral';}; ?>;"><?= $fetch_posts['status']; ?></div>
               </td>
               <td>
                  <div class="title"><?= $fetch_posts['title']; ?></div>
               </td>
               <td>
                  <div class="posts-content content-30"><?= $fetch_posts['content']; ?></div>
               </td>
               <td>
                  <a href="edit_post.php?id=<?= $post_id; ?>" class="option-btn">Chỉnh sửa</a>
               </td>
               <td>
                  <form method="post">
                     <input type="hidden" name="post_id" value="<?= $post_id; ?>"> <!-- Thêm trường ẩn để chứa ID bài viết -->
                     <button type="submit" name="delete" class="delete-btn" onclick="return confirm('Bạn có chắc chắn muốn xóa bài viết này không ?');">Xóa</button>
                  </form>
               </td>
               <td>
                  <a href="read_post.php?post_id=<?= $post_id; ?>" class="btn">Xem bài viết</a>
               </td>
            </tr>
            <?php
                  }
               }else{
                  echo '<tr><td colspan="8"><p class="empty">Chưa có bài viết được thêm! <a href="add_posts.php" class="btn" style="margin-top:1.5rem;">thêm bài viết</a></p></td></tr>';
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

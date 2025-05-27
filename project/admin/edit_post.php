<?php

include '../components/connect.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
   header('location:admin_login.php');
}

if (isset($_POST['save'])) {
   $post_id = $_GET['id'];
   // Lấy và xử lý dữ liệu từ form
   $title = htmlspecialchars($_POST['title'], ENT_QUOTES, 'UTF-8');
   $content = $_POST['content']; // Nội dung từ CKEditor, cần xử lý riêng nếu chứa HTML
   $category = htmlspecialchars($_POST['category'], ENT_QUOTES, 'UTF-8');
   $status = htmlspecialchars($_POST['status'], ENT_QUOTES, 'UTF-8');

   // Cập nhật thông tin bài viết
   $update_post = $conn->prepare("UPDATE `posts` SET title = ?, content = ?, category = ?, status = ? WHERE id = ?");
   $update_post->execute([$title, $content, $category, $status, $post_id]);

   $message[] = 'Bài viết đã được cập nhật!';

   // Xử lý ảnh tải lên
   $old_image = $_POST['old_image'];
   $image = $_FILES['image']['name'];
   $image = htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); // Thay thế FILTER_SANITIZE_STRING
   $image_size = $_FILES['image']['size'];
   $image_tmp_name = $_FILES['image']['tmp_name'];
   $image_folder = '../uploaded_img/' . $image;

   if (!empty($image)) {
      $select_image = $conn->prepare("SELECT * FROM `posts` WHERE image = ? AND admin_id = ?");
      $select_image->execute([$image, $admin_id]);

      if ($image_size > 2000000) {
         $message[] = 'Kích thước ảnh quá lớn!';
      } elseif ($select_image->rowCount() > 0) {
         $message[] = 'Vui lòng đổi tên ảnh của bạn!';
      } else {
         // Cập nhật ảnh
         $update_image = $conn->prepare("UPDATE `posts` SET image = ? WHERE id = ?");
         move_uploaded_file($image_tmp_name, $image_folder);
         $update_image->execute([$image, $post_id]);

         // Xóa ảnh cũ nếu có
         if ($old_image != $image && $old_image != '') {
            unlink('../uploaded_img/' . $old_image);
         }
         $message[] = 'Ảnh đã được cập nhật!';
      }
   }
}


if (isset($_POST['delete_post'])) {

   $post_id = $_POST['post_id'];
   $post_id = filter_var($post_id, FILTER_SANITIZE_STRING);
   $delete_image = $conn->prepare("SELECT * FROM `posts` WHERE id = ?");
   $delete_image->execute([$post_id]);
   $fetch_delete_image = $delete_image->fetch(PDO::FETCH_ASSOC);
   if ($fetch_delete_image['image'] != '') {
      unlink('../uploaded_img/' . $fetch_delete_image['image']);
   }
   $delete_post = $conn->prepare("DELETE FROM `posts` WHERE id = ?");
   $delete_post->execute([$post_id]);
   $delete_comments = $conn->prepare("DELETE FROM `comments` WHERE post_id = ?");
   $delete_comments->execute([$post_id]);
   $message[] = 'Bài đăng này đã được xóa thành công!';
}

if (isset($_POST['delete_image'])) {

   $empty_image = '';
   $post_id = $_POST['post_id'];
   $post_id = filter_var($post_id, FILTER_SANITIZE_STRING);
   $delete_image = $conn->prepare("SELECT * FROM `posts` WHERE id = ?");
   $delete_image->execute([$post_id]);
   $fetch_delete_image = $delete_image->fetch(PDO::FETCH_ASSOC);
   if ($fetch_delete_image['image'] != '') {
      unlink('../uploaded_img/' . $fetch_delete_image['image']);
   }
   $unset_image = $conn->prepare("UPDATE `posts` SET image = ? WHERE id = ?");
   $unset_image->execute([$empty_image, $post_id]);
   $message[] = 'Ảnh đã xóa thành công!';
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Các bài đăng</title>

   <!-- font awesome cdn link  -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

   <!-- custom css file link  -->
   <link rel="stylesheet" href="../css/admin_style_edit.css">

   <script src="../plugin/ckeditor/ckeditor.js"></script>
   <script src="../plugin/ckfinder/ckfinder.js"></script>
   <script src="https://cdn.ckeditor.com/4.22.1/full/ckeditor.js"></script>

</head>

<body>

   <?php include '../components/admin_header.php' ?>

   <section class="post-editor">

      <h1 class="heading">Chỉnh Sửa Bài Viết</h1>

      <?php
      $post_id = $_GET['id'];
      $select_posts = $conn->prepare("SELECT * FROM `posts` WHERE id = ?");
      $select_posts->execute([$post_id]);
      if ($select_posts->rowCount() > 0) {
         while ($fetch_posts = $select_posts->fetch(PDO::FETCH_ASSOC)) {
      ?>
            <form action="" method="post" enctype="multipart/form-data">
               <input type="hidden" name="old_image" value="<?= $fetch_posts['image']; ?>">
               <input type="hidden" name="post_id" value="<?= $fetch_posts['id']; ?>">
               <p>Trạng Thái Bài Viết <span>*</span></p>
               <select name="status" class="box" required>
                  <option value="<?= $fetch_posts['status']; ?>" selected><?= $fetch_posts['status']; ?></option>
                  <option value="active">active</option>
                  <option value="deactive">deactive</option>
               </select>
               <p>Tiêu đề <span>*</span></p>
               <input type="text" name="title" maxlength="100" required placeholder="add post title" class="box" value="<?= $fetch_posts['title']; ?>">
               <p>Nội dung <span>*</span></p>
               <textarea name="content" id="content" class="box" required maxlength="10000" placeholder="write your content..." cols="30" rows="10"><?= htmlspecialchars($fetch_posts['content'], ENT_QUOTES, 'UTF-8'); ?></textarea>
               <script>
                  // Khởi tạo CKEditor
                  CKEDITOR.replace('content', {
                     filebrowserBrowseUrl: '../plugin/ckfinder/ckfinder.html',
                     filebrowserUploadUrl: '../plugin/ckfinder/core/connector/php/connector.php?command=QuickUpload&type=Files'
                  });

                  // Đặt nội dung ban đầu sau khi CKEditor được khởi tạo
                  CKEDITOR.instances['content'].setData(`<?= addslashes(htmlspecialchars_decode($fetch_posts['content'], ENT_QUOTES)); ?>`);
               </script>

               <p>Thể loại <span>*</span></p>
               <select name="category" class="box" required>
                  <option value="<?= $fetch_posts['category']; ?>" selected><?= $fetch_posts['category']; ?></option>
                  <option value="thien nhien">Thiên Nhiên</option>
                  <option value="giao duc">Giáo Dục</option>
                  <option value="du lich">Du lịch và phượt</option>
                  <option value="cong nghe">Công Nghệ</option>
                  <option value="thoi trang">Thời Trang</option>
                  <option value="giai tri">Giải Trí</option>
                  <option value="phim">Phim</option>
                  <option value="tro choi">Trò chơi</option>
                  <option value="am nhac">Âm nhạc</option>
                  <option value="the thao">Thể Thao</option>

               </select>
               <p>Chọn ảnh</p>
               <input type="file" name="image" class="box" accept="image/jpg, image/jpeg, image/png, image/webp">
               <?php if ($fetch_posts['image'] != '') { ?>
                  <img src="../uploaded_img/<?= $fetch_posts['image']; ?>" class="image" alt="">
                  <input type="submit" value="Xóa Ảnh" class="inline-delete-btn" name="delete_image">
               <?php } ?>
               <div class="flex-btn">
                  <input type="submit" value="Lưu" name="save" class="btn">
                  <a href="view_posts.php" class="option-btn">Quay Lại</a>
                  <input type="submit" value="Xóa" class="delete-btn" name="delete_post">
               </div>
            </form>

         <?php
         }
      } else {
         echo '<p class="empty">Không tìm thấy bài viết!</p>';
         ?>
         <div class="flex-btn">
            <a href="view_posts.php" class="option-btn">Xem bài viết</a>
            <a href="add_posts.php" class="option-btn">Thêm bài viết</a>
         </div>
      <?php
      }
      ?>

   </section>

   <script>
      ClassicEditor
         .create(document.querySelector('#content'))
         .catch(error => {
            console.error(error);
         });

      function submitForm() {
         // Đồng bộ nội dung CKEditor với textarea trước khi gửi form
         for (instance in CKEDITOR.instances) {
            CKEDITOR.instances[instance].updateElement();
         }
         return true;
      }
   </script>

   <!-- custom js file link  -->
   <script src="../js/admin_script.js"></script>

</body>

</html>
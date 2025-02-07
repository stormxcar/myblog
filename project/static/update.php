<?php

include '../components/connect.php';

session_start();
$message = [];

if(!isset($_SERVER['HTTP_REFERER'])){
   header('location: home.php');
   exit;
}

if (isset($_SESSION['user_id'])) {
   $user_id = $_SESSION['user_id'];
} else {
   $user_id = '';
   header('location:home.php');
};

if (isset($_POST['submit'])) {

   $name = $_POST['name'];
   $name = filter_var($name, FILTER_SANITIZE_STRING);

   $email = $_POST['email'];
   $email = filter_var($email, FILTER_SANITIZE_STRING);

   if (!empty($name)) {
      $update_name = $conn->prepare("UPDATE `users` SET name = ? WHERE id = ?");
      $update_name->execute([$name, $user_id]);
      $_SESSION['message'] = 'Cập nhật tên thành công ! <br>';
   }

   if (!empty($email)) {
      $select_email = $conn->prepare("SELECT * FROM `users` WHERE email = ?");
      $select_email->execute([$email]);
      if ($select_email->rowCount() > 0) {
         $_SESSION['message'] = 'email này đã tồn tại! <br>';
      } else {
         $update_email = $conn->prepare("UPDATE `users` SET email = ? WHERE id = ?");
         $update_email->execute([$email, $user_id]);
         $_SESSION['message'] = 'Cập nhật email thành công ! <br>';
      }
   }

   $empty_pass = 'da39a3ee5e6b4b0d3255bfef95601890afd80709';
   $select_prev_pass = $conn->prepare("SELECT password FROM `users` WHERE id = ?");
   $select_prev_pass->execute([$user_id]);
   $fetch_prev_pass = $select_prev_pass->fetch(PDO::FETCH_ASSOC);
   $prev_pass = $fetch_prev_pass['password'];
   $old_pass = sha1($_POST['old_pass']);
   $old_pass = filter_var($old_pass, FILTER_SANITIZE_STRING);
   $new_pass = sha1($_POST['new_pass']);
   $new_pass = filter_var($new_pass, FILTER_SANITIZE_STRING);
   $confirm_pass = sha1($_POST['confirm_pass']);
   $confirm_pass = filter_var($confirm_pass, FILTER_SANITIZE_STRING);

   if ($old_pass != $empty_pass) {
      if ($old_pass != $prev_pass) {
         $_SESSION['message'] = 'Mật khẩu cũ của bạn không đúng . Vui lòng nhập lại ! <br>';
      } elseif ($new_pass != $confirm_pass) {
         $_SESSION['message'] = 'Mật khẩu không khớp . Vui lòng nhập lại ! <br>';
      } else {
         if ($new_pass != $empty_pass) {
            $update_pass = $conn->prepare("UPDATE `users` SET password = ? WHERE id = ?");
            $update_pass->execute([$confirm_pass, $user_id]);
            $_SESSION['message'] = 'Mật khẩu đã cập nhật thành công ! <br>';
         } else {
            $_SESSION['message'] = 'Vui lòng cập nhật mật khẩu mới! <br>';
         }
      }
   }

   if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === 0) {
      $fileTmpName = $_FILES['avatar']['tmp_name'];
      $fileContent = file_get_contents($fileTmpName);

      // Cập nhật avatar vào cơ sở dữ liệu
      $update_avatar = $conn->prepare("UPDATE `users` SET avatar = ? WHERE id = ?");
      $update_avatar->execute([$fileContent, $user_id]);
      $_SESSION['message'] .= 'Ảnh đại diện đã được cập nhật!<br>';
   } else {
      $_SESSION['message'] .= 'Không có ảnh mới nào được tải lên.<br>';
   }

   header('location:update.php'); // Chuyển hướng sau khi cập nhật
   exit;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Thay đổi thông tin cá nhân</title>

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
      unset($_SESSION['message']);
   endif;
   ?>

   <section class="form-container">

      <div class="update_bg">
         <img src="../uploaded_img/banner-4.avif" alt="">
      </div>

      <form action="" method="post" enctype="multipart/form-data">
         <h3>Thay đổi thông tin cá nhân</h3>
         <label for="username">
            <span>Tên người dùng:</span>
            <input type="text" name="name" placeholder="<?= $fetch_profile['name']; ?>" class="box" maxlength="50">
         </label>

         <label for="email">
            <span>Email:</span>
            <input type="email" name="email" placeholder="<?= $fetch_profile['email']; ?>" class="box" maxlength="50" oninput="this.value = this.value.replace(/\s/g, '')">
         </label>

         <label for="pass_old">
            <span>Mật khẩu Cũ:</span>
            <input type="password" name="old_pass" placeholder="Nhập mật khẩu cũ" class="box" maxlength="50" oninput="this.value = this.value.replace(/\s/g, '')">
         </label>

         <label for="pass_new">
            <span>Mật khẩu mới:</span>
            <input type="password" name="new_pass" placeholder="Nhập mật khẩu mới" class="box" maxlength="50" oninput="this.value = this.value.replace(/\s/g, '')">
         </label>

         <label for="pass_new_conf">
            <span>Xác nhận mật khẩu mới:</span>
            <input type="password" name="confirm_pass" placeholder="Nhập lại mật khẩu mới:" class="box" maxlength="50" oninput="this.value = this.value.replace(/\s/g, '')">
         </label>

         <label for="avatar" class="avatar_select">
            <span>Chọn ảnh đại diện mới:</span>
            <div class="detail_image">
               <input type="file" name="avatar" accept="image/*" required >
               <img id="avatarPreview" src="../uploaded_img/default_img.jpg" alt="default_avatar">
            </div>

         </label>

         <input type="submit" value="Cập nhật ngay" name="submit" class="btn">
      </form>

   </section>

   <?php include '../components/footer.php'; ?>

</body>

<script>
   document.querySelector('input[name="avatar"]').addEventListener('change', function(event) {
      const reader = new FileReader();
      reader.onload = function() {
         const preview = document.getElementById('avatarPreview');
         preview.src = reader.result;
      };
      reader.readAsDataURL(event.target.files[0]);
   });
</script>

</html>
<?php

include '../components/connect.php';

session_start();
$message = [];

if (isset($_SESSION['user_id'])) {
   $user_id = $_SESSION['user_id'];
} else {
   $user_id = '';
};

if (isset($_POST['submit'])) {
   $name = $_POST['name'];
   $name = filter_var($name, FILTER_SANITIZE_STRING);
   $email = $_POST['email'];
   $email = filter_var($email, FILTER_SANITIZE_STRING);
   $pass = sha1($_POST['pass']);
   $pass = filter_var($pass, FILTER_SANITIZE_STRING);
   $cpass = sha1($_POST['cpass']);
   $cpass = filter_var($cpass, FILTER_SANITIZE_STRING);

   // Kiểm tra email đã tồn tại
   $select_user = $conn->prepare("SELECT * FROM `users` WHERE email = ?");
   $select_user->execute([$email]);
   if ($select_user->rowCount() > 0) {
      $_SESSION['message'] = 'Tài khoản với email này đã tồn tại! Vui lòng thử lại.';
   } else {
      if ($pass != $cpass) {
         $_SESSION['message'] = 'Mật khẩu nhập lại không khớp!';
      } else {
         // Xử lý ảnh đại diện nếu có
         $avatar = null;
         if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === 0) {
            $file = $_FILES['avatar'];
            $fileTmpName = $file['tmp_name'];
            $avatar = file_get_contents($fileTmpName); // Đọc nội dung ảnh vào biến
         }

         // Thực hiện chèn dữ liệu vào cơ sở dữ liệu
         $insert_user = $conn->prepare("INSERT INTO `users` (name, email, password, avatar) VALUES (?, ?, ?, ?)");
         if ($insert_user->execute([$name, $email, $cpass, $avatar])) {
            $_SESSION['message'] = 'Đăng ký tài khoản thành công';
            $_SESSION['user_id'] = $conn->lastInsertId(); // Lưu ID người dùng vào session
            header('location:home.php');
            exit;
         } else {
            $_SESSION['message'] = 'Có lỗi xảy ra khi đăng ký tài khoản!';
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
   <title>register</title>

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
      <div class="register_bg">
         <img src="../uploaded_img/banner-4.avif" alt="">
      </div>
      <form action="" method="post" enctype="multipart/form-data" > <!-- Thêm enctype ở đây -->
         <h3>Xin chào bạn mới</h3>
         <label for="username">
            <span>Tên người dùng:</span>
            <input type="text" name="name" required placeholder="Nhập tên của bạn" class="box" maxlength="50">
         </label>

         <label for="email">
            <span>Email:</span>
            <input type="email" name="email" required placeholder="Nhập email của bạn" class="box" maxlength="50" oninput="this.value = this.value.replace(/\s/g, '')">
         </label>

         <label for="pass">
            <span>Mật khẩu:</span>
            <input type="password" name="pass" required placeholder="Nhập mật khẩu" class="box" maxlength="50" oninput="this.value = this.value.replace(/\s/g, '')">
         </label>

         <label for="conf_pass">
            <span>Xác nhận mật khẩu:</span>
            <input type="password" name="cpass" required placeholder="Nhập lại mật khẩu" class="box" maxlength="50" oninput="this.value = this.value.replace(/\s/g, '')">
         </label>

         <label for="avatar" class="avatar_select">
            <span>Chọn ảnh đại diện:</span>
            <div class="detail_image">
               <input type="file" name="avatar" accept="image/*" required>
               <img id="avatarPreview" src="../uploaded_img/default_img.jpg" alt="default_avatar">
            </div>

         </label>

         <input type="submit" value="Đăng ký" name="submit" class="btn">
         <p>Bạn đã có tài khoản ? <a href="login.php">Đăng nhập ngay</a></p>
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
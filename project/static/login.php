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
   $email = $_POST['email'];
   $email = filter_var($email, FILTER_SANITIZE_STRING);
   $pass = sha1($_POST['pass']);
   $pass = filter_var($pass, FILTER_SANITIZE_STRING);

   $select_user = $conn->prepare("SELECT * FROM `users` WHERE email = ? AND password = ?");
   $select_user->execute([$email, $pass]);
   $row = $select_user->fetch(PDO::FETCH_ASSOC);

   if ($select_user->rowCount() > 0) {
      if ($row['banned'] == 1) {
         $_SESSION['message'] = 'Tài khoản của bạn đã bị khóa. <br> Vui lòng liên hệ quản trị viên để biết thêm chi tiết.';
      } else {
         $_SESSION['user_id'] = $row['id'];
         $_SESSION['message'] = 'Đăng nhập thành công';
         header('location:home.php');
         exit;
      }
   } else {
      $_SESSION['message'] = 'Tên đăng nhập hoặc mật khẩu không đúng! Vui lòng thử lại.';
   }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>login</title>

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

      <div class="login_bg" style="height:90vh">
         <img src="../uploaded_img/banner-2.avif" alt="">
      </div>

      <form action="" method="post">
         <h3>CHÀO MỪNG BẠN TRỞ LẠI</h3>
         <label for="email">
            <span>Email:</span>
            <input type="email" name="email" required placeholder="Nhập email của bạn" class="box" maxlength="50" oninput="this.value = this.value.replace(/\s/g, '')" value="nkha3561@gmail.com">
         </label>

         <label for="pass">
            <span>Mật khẩu:</span>
            <input type="password" name="pass" required placeholder="Nhập mật khẩu" class="box" maxlength="50" oninput="this.value = this.value.replace(/\s/g, '')" value="12345678">
         </label>
         

         <input type="submit" value="Đăng nhập" name="submit" class="btn">
         <!-- <a class="btn" href="">Login with Google</a> -->
         <p>Bạn chưa có tài khoản? <a href="register.php">Đăng ký bây giờ</a></p>
      </form>

   </section>

   <?php include '../components/footer.php'; ?>

</body>

</html>
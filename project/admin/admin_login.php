<?php

ob_start();

include '../components/connect.php';

session_start();

if (isset($_POST['submit'])) {

   $name = $_POST['name'];
   $name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
   $pass = sha1($_POST['pass']);
   $pass = htmlspecialchars($pass, ENT_QUOTES, 'UTF-8');

   $select_admin = $conn->prepare("SELECT * FROM `admin` WHERE name = ? AND password = ?");
   $select_admin->execute([$name, $pass]);

   if ($select_admin->rowCount() > 0) {
      $fetch_admin_id = $select_admin->fetch(PDO::FETCH_ASSOC);
      $_SESSION['admin_id'] = $fetch_admin_id['id'];
      header('location:dashboard.php');
   } else {
      $message[] = 'incorrect username or password!';
   }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Đăng nhập</title>

   <!-- font awesome cdn link  -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

   <!-- custom css file link  -->
   <link rel="stylesheet" href="../css/admin_style_edit.css">

</head>

<body style="padding-left: 0 !important;">

   <?php
   if (isset($message)) {
      foreach ($message as $message) {
         echo '
      <div class="message">
         <span>' . $message . '</span>
         <i class="fas fa-times" onclick="this.parentElement.remove();"></i>
      </div>
      ';
      }
   }
   ?>

   <!-- admin login form section starts  -->

   <section class="form-container">
      <!-- <div class="admin_login_bg">
      <img src="../uploaded_img/962470bis.jpg" alt="">
   </div> -->
      <form action="" method="POST">
         <h3>Chào mừng bạn trở lại</h3>
         <p>default username = <span>admin</span> & password = <span>111</span></p>
         <label for="admin_name">
            <span>Adminname</span>
            <input type="text" name="name" maxlength="20" required placeholder="enter your username" class="box" oninput="this.value = this.value.replace(/\s/g, '')">
         </label>

         <label for="admin_pass">
            <span>Mật khẩu</span>
            <input type="password" name="pass" maxlength="20" required placeholder="enter your password" class="box" oninput="this.value = this.value.replace(/\s/g, '')">
         </label>

         <input type="submit" value="Đăng nhập ngay" name="submit" class="btn">
      </form>

   </section>

   <!-- admin login form section ends -->


</body>

</html>
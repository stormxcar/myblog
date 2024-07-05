<?php

include '../components/connect.php';

session_start();

if(isset($_SESSION['user_id'])){
   $user_id = $_SESSION['user_id'];
}else{
   $user_id = '';
};

if(isset($_POST['submit'])){

   $name = $_POST['name'];
   $name = filter_var($name, FILTER_SANITIZE_STRING);
   $email = $_POST['email'];
   $email = filter_var($email, FILTER_SANITIZE_STRING);
   $pass = sha1($_POST['pass']);
   $pass = filter_var($pass, FILTER_SANITIZE_STRING);
   $cpass = sha1($_POST['cpass']);
   $cpass = filter_var($cpass, FILTER_SANITIZE_STRING);

   $select_user = $conn->prepare("SELECT * FROM `users` WHERE email = ?");
   $select_user->execute([$email]);
   $row = $select_user->fetch(PDO::FETCH_ASSOC);

   if($select_user->rowCount() > 0){
      $message[] = 'email already exists!';
   }else{
      if($pass != $cpass){
         $message[] = 'confirm password not matched!';
      }else{
         $insert_user = $conn->prepare("INSERT INTO `users`(name, email, password) VALUES(?,?,?)");
         $insert_user->execute([$name, $email, $cpass]);
         $select_user = $conn->prepare("SELECT * FROM `users` WHERE email = ? AND password = ?");
         $select_user->execute([$email, $pass]);
         $row = $select_user->fetch(PDO::FETCH_ASSOC);
         if($select_user->rowCount() > 0){
            $_SESSION['user_id'] = $row['id'];
            header('location:home.php');
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

</head>
<body>
   
<!-- header section starts  -->
<?php include '../components/user_header.php'; ?>
<!-- header section ends -->

<section class="form-container">
   <div class="register_bg" >
      <img src="../uploaded_img/banner-4.avif" alt="">
   </div>
   <form action="" method="post">
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
     
      <input type="submit" value="Đăng ký" name="submit" class="btn">
      <p>Bạn đã có tài khoản ? <a href="login.php">Đăng nhập ngay</a></p>
   </form>

</section>

<?php include '../components/footer.php'; ?>

<!-- custom js file link  -->
<script src="../js/script_edit.js"></script>
<script src="../js/script.js"></script>

</body>
</html>
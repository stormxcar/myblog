<?php

include '../components/connect.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if(!isset($admin_id)){
   header('location:admin_login.php');
};

if(isset($_POST['submit'])){

   $name = $_POST['name'];
   $name = filter_var($name, FILTER_SANITIZE_STRING);
   $pass = sha1($_POST['pass']);
   $pass = filter_var($pass, FILTER_SANITIZE_STRING);
   $cpass = sha1($_POST['cpass']);
   $cpass = filter_var($cpass, FILTER_SANITIZE_STRING);

   $select_admin = $conn->prepare("SELECT * FROM `admin` WHERE name = ?");
   $select_admin->execute([$name]);
   
   if($select_admin->rowCount() > 0){
      $message[] = 'tên tài khoản đã tồn tại!';
   }else{
      if($pass != $cpass){
         $message[] = 'Mật khẩu nhập lại không khớp!';
      }else{
         $insert_admin = $conn->prepare("INSERT INTO `admin`(name, password) VALUES(?,?)");
         $insert_admin->execute([$name, $cpass]);
         $message[] = 'Tài khoản admin mới đã được thêm!';
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
   <title>Đăng ký</title>

   <!-- font awesome cdn link  -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

   <!-- custom css file link  -->
   <link rel="stylesheet" href="../css/admin_style_edit.css">

</head>
<body>

<?php include '../components/admin_header.php' ?>

<!-- register admin section starts  -->

<section class="form-container">

   <form action="" method="POST">
      <h3>Đăng ký mới</h3>
      <label for="admin_name">
      <span>Adminname</span>
      <input type="text" name="name" maxlength="20" required placeholder="Nhập tên tài khoản" class="box" oninput="this.value = this.value.replace(/\s/g, '')">
      </label>
      
      <label for="admin_pass">
      <span>Mật khẩu</span>
      <input type="password" name="pass" maxlength="20" required placeholder="Nhập mật khẩu" class="box" oninput="this.value = this.value.replace(/\s/g, '')">
      </label>
      
      <label for="admin_pass_conf">
      <span>Xác nhận mật khẩu</span>
      <input type="password" name="cpass" maxlength="20" required placeholder="Nhập lại mật khẩu" class="box" oninput="this.value = this.value.replace(/\s/g, '')">
      </label>
      
      <input type="submit" value="Đăng ký ngay" name="submit" class="btn">
   </form>

</section>

<!-- register admin section ends -->


<!-- custom js file link  -->
<script src="../js/admin_script.js"></script>

</body>
</html>
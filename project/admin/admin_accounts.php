<?php

include '../components/connect.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if(!isset($admin_id)){
   header('location:admin_login.php');
}

if(isset($_POST['delete'])){
   $delete_image = $conn->prepare("SELECT * FROM `posts` WHERE admin_id = ?");
   $delete_image->execute([$admin_id]);
   while($fetch_delete_image = $delete_image->fetch(PDO::FETCH_ASSOC)){
      unlink('../uploaded_img/'.$fetch_delete_image['image']);
   }
   $delete_posts = $conn->prepare("DELETE FROM `posts` WHERE admin_id = ?");
   $delete_posts->execute([$admin_id]);
   $delete_likes = $conn->prepare("DELETE FROM `likes` WHERE admin_id = ?");
   $delete_likes->execute([$admin_id]);
   $delete_comments = $conn->prepare("DELETE FROM `comments` WHERE admin_id = ?");
   $delete_comments->execute([$admin_id]);
   $delete_admin = $conn->prepare("DELETE FROM `admin` WHERE id = ?");
   $delete_admin->execute([$admin_id]);
   header('location:../components/admin_logout.php');
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Tài khoản của quản trị viên</title>

   <!-- font awesome cdn link  -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

   <!-- custom css file link  -->
   <link rel="stylesheet" href="../css/admin_style_edit.css">

</head>
<body>

<?php include '../components/admin_header.php' ?>

<!-- admins accounts section starts  -->

<section class="accounts">
   <h1 class="heading">Tài khoản - admin</h1>
   <div class="box-container">
      
      <table>
         <thead>
            <tr>
               <th>STT</th>
               <th>admin id</th>
               <th>Tên</th>
               <th>Số bài viết</th>
               <th>Quản lý</th>
            </tr>
         </thead>
         <tbody>
            <?php
               $select_account = $conn->prepare("SELECT * FROM `admin`");
               $select_account->execute();
               if($select_account->rowCount() > 0){
                  $count = 1;
                  while($fetch_accounts = $select_account->fetch(PDO::FETCH_ASSOC)){ 
                     $count_admin_posts = $conn->prepare("SELECT * FROM `posts` WHERE admin_id = ?");
                     $count_admin_posts->execute([$fetch_accounts['id']]);
                     $total_admin_posts = $count_admin_posts->rowCount();
            ?>
            <tr>
               <td><?= $count++; ?></td>
               <td>
                  <div class="" style="order: <?php if($fetch_accounts['id'] == $admin_id){ echo '-1'; } ?>;">
                     <span><?= $fetch_accounts['id']; ?></span>
                  </div>
               </td>
               <td><span><?= $fetch_accounts['name']; ?></span></td>
               <td><span><?= $total_admin_posts; ?></span></td>
               <td>
                  <div class="flex-btn">
                     <?php
                        if($fetch_accounts['id'] == $admin_id){
                     ?>
                     <a href="update_profile.php" class="option-btn" style="margin-bottom: .5rem;">Nâng cấp thông tin </a>
                     <form action="" method="POST">
                        <input type="hidden" name="post_id" value="<?= $fetch_accounts['id']; ?>">
                        <button type="submit" name="delete" onclick="return confirm('Bạn có chắc xóa tài khoản này không?');" class="delete-btn" style="margin-bottom: .5rem;">Xóa</button>
                     </form>
                     <?php
                        }
                     ?>
                  </div>
               </td>
            </tr>
            <?php
                  }
               }else{
                  echo '<tr><td colspan="5"><p class="empty">Không có tài khoản admin nào được tìm thấy!</p></td></tr>';
               }
            ?>
         </tbody>
      </table>

      <div class="box" >
         <p style="color:blue">Đăng ký tài khoản admin tại đây</p>
         <a href="register_admin.php" class="option-btn" style="margin-bottom: .5rem;">Đăng ký</a>
         
      </div>

   </div>
</section>
<!-- admins accounts section ends -->
<!-- custom js file link  -->
<script src="../js/admin_script.js"></script>
</body>
</html>
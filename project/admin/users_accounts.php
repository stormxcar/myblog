<?php

include '../components/connect.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if(!isset($admin_id)){
   header('location:admin_login.php');
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>users accounts</title>

   <!-- font awesome cdn link  -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

   <!-- custom css file link  -->
   <link rel="stylesheet" href="../css/admin_style_edit.css">

</head>
<body>

<?php include '../components/admin_header.php' ?>

<!-- users accounts section starts  -->

<section class="accounts">

   <h1 class="heading">Tài khoản người dùng</h1>

   <div class="box-container">

   <table>
      <thead>
         <th>STT</th>
         <th>UserID</th>
         <th>username</th>
         <th>Tong binh luan</th>
         <th>Tong luot thich</th>
         <th>Danh gia</th>
         <th>Quan ly</th>
      </thead>

      <tbody>
      <?php
      $select_account = $conn->prepare("SELECT * FROM `users`");
      $select_account->execute();
      if($select_account->rowCount() > 0){
         $count = 1;
         while($fetch_accounts = $select_account->fetch(PDO::FETCH_ASSOC)){ 
            $user_id = $fetch_accounts['id']; 
            $count_user_comments = $conn->prepare("SELECT * FROM `comments` WHERE user_id = ?");
            $count_user_comments->execute([$user_id]);
            $total_user_comments = $count_user_comments->rowCount();
            $count_user_likes = $conn->prepare("SELECT * FROM `likes` WHERE user_id = ?");
            $count_user_likes->execute([$user_id]);
            $total_user_likes = $count_user_likes->rowCount();
       ?>

         <tr>
            <td><?= $count++; ?></td>
            <td>
               <span><?= $user_id; ?></span>
            </td>
            <td>
               <span><?= $fetch_accounts['name']; ?></span>
            </td>
            <td>
               <span><?= $total_user_comments; ?></span>
            </td>
            <td>
               <span><?= $total_user_likes; ?></span>
            </td>
            <td></td>
            <td></td>
         </tr>

         <?php
      }
   }else{
      echo '<p class="empty">Không có tài khoản người dùng nào !</p>';
   }
   ?>
      </tbody>
   </table>

   
   
   

   </div>

</section>

<!-- users accounts section ends -->



<!-- custom js file link  -->
<script src="../js/admin_script.js"></script>

</body>
</html>
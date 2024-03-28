<?php
if(isset($message)){
   foreach($message as $message){
      echo '
      <div class="message">
         <span>'.$message.'</span>
         <i class="fas fa-times" onclick="this.parentElement.remove();"></i>
      </div>
      ';
   }
}
?>

<header class="header">

   <section class="flex">

      <a href="home.php" class="logo">BATrav</a>

      <form action="search.php" method="POST" class="search-form">
         <input type="text" name="search_box" class="box" maxlength="100" placeholder="Tìm kiếm bài viết" required>
         <button type="submit" class="fas fa-search" name="search_btn"></button>
      </form>

      <div class="icons">
         <div id="menu-btn" class="fas fa-bars"></div>
         <div id="search-btn" class="fas fa-search"></div>
         <div id="user-btn" class="fas fa-user"></div>
      </div>

      <nav class="navbar">
         <a href="home.php"> <i class="fa-solid fa-house"></i> Trang Chủ</a>
         <a href="posts.php"> <i class="fa-solid fa-address-card"></i></i> Bài Đăng</a>
         <a href="all_category.php"> <i class="fa-solid fa-layer-group"></i></i> Loại</a>
         <a href="authors.php"><i class="fa-solid fa-at"></i></i> Tác Gỉa</a>
         <a href="login.php"><i class="fa-solid fa-right-to-bracket"></i></i> Đăng Nhập</a>
         <a href="register.php"> <i class="fa-solid fa-square-caret-right"></i></i> Đăng Ký</a>
      </nav>

      <div class="profile">
         <?php
            $select_profile = $conn->prepare("SELECT * FROM `users` WHERE id = ?");
            $select_profile->execute([$user_id]);
            if($select_profile->rowCount() > 0){
               $fetch_profile = $select_profile->fetch(PDO::FETCH_ASSOC);
         ?>
         <p class="name"><?= $fetch_profile['name']; ?></p>
         <a href="update.php" class="btn">update profile</a>
         <div class="flex-btn">
            <a href="login.php" class="option-btn">login</a>
            <a href="register.php" class="option-btn">register</a>
         </div> 
         <a href="components/user_logout.php" onclick="return confirm('logout from this website?');" class="delete-btn">logout</a>
         <?php
            }else{
         ?>
            <p class="name">Vui lòng đăng nhập trước!</p>
            <a href="login.php" class="option-btn">Đăng nhập</a>
         <?php
            }
         ?>
      </div>

   </section>

</header>
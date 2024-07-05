<?php
include '../components/connect.php';

try {
   // Kiểm tra kết nối
   $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

   // Truy vấn lấy giá trị logo
   $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'logo'");
   $stmt->execute();

   // Đặt đường dẫn mặc định nếu không tìm thấy logo
   $logo_path = '../uploaded_img/default_img.jpg';
   if ($stmt->rowCount() > 0) {
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      $logo_path = $row['setting_value'];
   }
} catch (PDOException $e) {
   echo "Connection failed: " . $e->getMessage();
}
?>


<header class="header">

   <section class="flex">

      <a href="home.php" class="logo"> <img class="logo_img" src="<?php echo $logo_path; ?>" alt="blog kingg logo"></a>

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
         <a href="home.php"> <i class="fa-solid fa-house"></i> Trang chủ</a>
         <a href="posts.php"> <i class="fa-solid fa-address-card"></i></i> Bài đăng</a>
         <a href="new_post.php"> <i class="fa-solid fa-newspaper"></i></i> Bài đăng gần đây</a>
         <a href="all_category.php"> <i class="fa-solid fa-layer-group"></i></i> Loại</a>
         <a href="login.php"><i class="fa-solid fa-right-to-bracket"></i></i> Đăng Nhập</a>
         <a href="register.php"> <i class="fa-solid fa-square-caret-right"></i></i> Đăng Ký</a>
      </nav>


      <div class="profile">
         <?php
         $select_profile = $conn->prepare("SELECT * FROM `users` WHERE id = ?");
         $select_profile->execute([$user_id]);
         if ($select_profile->rowCount() > 0) {
            $fetch_profile = $select_profile->fetch(PDO::FETCH_ASSOC);
         ?>
            <p class="name" style="border: black 1px solid; border-radius: 5px; padding: 0.7rem; font-weight:bold" ;><?= $fetch_profile['name']; ?></p>
            <a href="update.php" class="update_btn btn">
               <i class="fa-solid fa-pen-to-square"></i>
               Cập nhật thông tin</a>
            <a href="like_posts.php" class="like_btn btn">
            <i class="fa-solid fa-heart"></i>
               Bài viết đã lưu</a>
            <div class="btn_handle">
               <a href="login.php" class="login_btn btn">
               <i class="fa-solid fa-right-to-bracket"></i>
                  Đăng nhập</a>
               <a href="register.php" class="register_btn btn">
               <i class="fa-solid fa-square-caret-right"></i>
                  Đăng ký</a>
            </div>
            <a href="components/user_logout.php" onclick="return confirm('logout from this website?');" class="logout_btn btn">
            <i class="fa-solid fa-right-from-bracket"></i>
               Đăng xuất</a>
         <?php
         } else {
         ?>
            <p class="name">Vui lòng đăng nhập trước!</p>
            <a href="login.php" class="login_btn btn login_hover" style="border: black 1px solid" ;>Đăng nhập</a>
         <?php
         }
         ?>
      </div>

   </section>

</header>
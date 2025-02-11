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

$user_id = $_SESSION['user_id']; // Hoặc lấy ID người dùng từ session hoặc yêu cầu

// Lấy ảnh từ cơ sở dữ liệu
$select_avatar = $conn->prepare("SELECT avatar FROM `users` WHERE id = ?");
$select_avatar->execute([$user_id]);
$avatar = $select_avatar->fetch(PDO::FETCH_ASSOC);

if ($avatar) {
   $avatarContent = $avatar['avatar'];
   
   // Xác định loại MIME của dữ liệu ảnh
   $finfo = finfo_open(FILEINFO_MIME_TYPE);
   $avatarMime = finfo_buffer($finfo, $avatarContent);
   finfo_close($finfo);
   
   // Đảm bảo loại MIME chính xác
   if (!$avatarMime) {
       $avatarMime = 'image/jpeg'; // Mặc định nếu không xác định được
   }
   
   $avatarBase64 = base64_encode($avatarContent);
   $avatarSrc = "data:$avatarMime;base64,$avatarBase64";
} else {
   $avatarSrc = '../uploaded_img/default_avatar.png'; // Đường dẫn ảnh mặc định nếu không có ảnh
}
?>

<header class="header">
   <section class="flex">
      <a href="home.php" class="logo">
         <img class="logo_img" src="<?php echo htmlspecialchars($logo_path); ?>" alt="hay ho blog logo">
      </a>

      <form action="search.php" method="POST" class="search-form" id="searchToolTip">
         <input type="text" name="search_box" class="box" maxlength="100" placeholder="Tìm kiếm bài viết" required>
         <button type="submit" class="fas fa-search" name="search_btn"></button>
      </form>

      <div class="icons">
         <div id="menu-btn" class="fas fa-bars"></div>
         <div id="search-btn" class="fas fa-search"></div>
         <div id="user-btn">
            <img src="<?= htmlspecialchars($avatarSrc) ?>" alt="Avatar">
         </div>
      </div>

      <nav class="navbar">
         <button class="close-btn"><i class="fa-solid fa-close"></i></button>
         <a href="home.php"> <i class="fa-solid fa-house"></i> Trang chủ</a>
         <a href="posts.php"> <i class="fa-solid fa-address-card"></i></i> Bài đăng</a>
         <a href="new_post.php"> <i class="fa-solid fa-newspaper"></i></i> Bài đăng gần đây</a>
         <!-- <a href="all_category.php"> <i class="fa-solid fa-layer-group"></i></i> Loại</a> -->

         <!-- check if user is not login then show login and register button -->
          <?php
            if (!isset($_SESSION['user_id'])) {
              echo '<a href="login.php"><i class="fa-solid fa-right-to-bracket"></i></i> Đăng Nhập</a>';
              echo '<a href="register.php"> <i class="fa-solid fa-square-caret-right"></i></i> Đăng Ký</a>';
            }
          ?>
         <!-- <a href="login.php"><i class="fa-solid fa-right-to-bracket"></i></i> Đăng Nhập</a>
         <a href="register.php"> <i class="fa-solid fa-square-caret-right"></i></i> Đăng Ký</a> -->
         <button class="light_dark_btn"><span></span><p>Sáng / Tối</p></button>
      </nav>

      <div class="profile">
         <?php
         $select_profile = $conn->prepare("SELECT * FROM `users` WHERE id = ?");
         $select_profile->execute([$user_id]);
         if ($select_profile->rowCount() > 0) {
            $fetch_profile = $select_profile->fetch(PDO::FETCH_ASSOC);
         ?>
            <p class="name" style="border: black 1px solid; border-radius: 5px; padding: 0.7rem; font-weight:bold ; text-wrap:wrap" ;><?= htmlspecialchars($fetch_profile['name']); ?></p>
            <a href="update.php" class="update_btn btn">
               <i class="fa-solid fa-pen-to-square"></i>
               <span>Cập nhật thông tin</span>
            </a>
            <a href="user_likes.php" class="like_btn btn">
               <i class="fa-solid fa-heart"></i>
               <span>Bài viết yêu thích</span>
            </a>
            <a href="like_posts.php" class="save_btn btn">
               <i class="fa-solid fa-bookmark"></i>
               <span>Bài viết đã lưu</span>
            </a>
            <!-- <div class="btn_handle">
               <a href="login.php" class="login_btn btn">
                  <i class="fa-solid fa-right-to-bracket"></i>
                  <span>Đăng nhập</span>
               </a>
               <a href="register.php" class="register_btn btn">
                  <i class="fa-solid fa-square-caret-right"></i>
                  <span>Đăng ký</span>
               </a>
            </div> -->
            <a href="../components/user_logout.php" onclick="return confirm('Bạn muốn thoát khỏi website này phải không ?');" class="logout_btn btn">
               <i class="fa-solid fa-right-from-bracket"></i>
               <span>Đăng xuất</span>
            </a>
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

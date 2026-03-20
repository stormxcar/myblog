<?php
// Admin messages are rendered as toast notifications below.
?>

<link rel="stylesheet" href="../css/admin-modern.css">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
<script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

<header class="header">

   <div class="header_top">
      <div class="sidebar-collapse-control">
         <button type="button" id="sidebar-toggle-btn" aria-label="Đóng hoặc mở sidebar">
            <i class="fas fa-arrow-left" id="sidebar-toggle-icon"></i>
         </button>
      </div>

      <div class="profile">
         <?php
         $fetch_profile = blog_fetch_admin_profile($conn, $admin_id);
         $current_admin_page = basename($_SERVER['PHP_SELF'] ?? '');

         $is_active = function (array $pages) use ($current_admin_page) {
            return in_array($current_admin_page, $pages, true) ? 'active' : '';
         };
         ?>

      </div>
   </div>


   <nav class="navbar">
      <a href="dashboard.php" class="<?= $is_active(['dashboard.php']); ?>"><i class="fas fa-home"></i> <span>Dashboard</span></a>
      <a href="add_posts.php" class="<?= $is_active(['add_posts.php']); ?>"><i class="fas fa-pen"></i> <span>Thêm bài viết</span></a>
      <a href="view_posts.php" class="<?= $is_active(['view_posts.php', 'edit_post.php', 'read_post.php']); ?>"><i class="fas fa-file-lines"></i> <span>Quản lý bài viết</span></a>
      <a href="comments.php" class="<?= $is_active(['comments.php']); ?>"><i class="fas fa-comments"></i> <span>Bình luận</span></a>
      <a href="community_posts.php" class="<?= $is_active(['community_posts.php']); ?>"><i class="fas fa-users-viewfinder"></i> <span>Cộng đồng</span></a>
      <a href="users_accounts.php" class="<?= $is_active(['users_accounts.php', 'admin_accounts.php', 'register_admin.php']); ?>"><i class="fas fa-users"></i> <span>Tài khoản</span></a>
      <a href="add_cart.php" class="<?= $is_active(['add_cart.php']); ?>"><i class="fas fa-tags"></i> <span>Danh mục</span></a>
      <a href="setting.php" class="<?= $is_active(['setting.php']); ?>"><i class="fa-solid fa-gear"></i> <span>Cài đặt</span></a>
      <a href="../components/admin_logout.php" style="color:var(--red);" onclick="return confirm('Bạn có chắc chắn muốn thoát không ?');"><i class="fas fa-right-from-bracket"></i><span>Thoát</span></a>
   </nav>

   <div class="flex-btn">
      <a href="../static/login.php?next=admin" class="option-btn ">Đăng nhập</a>
      <a href="register_admin.php" class="option-btn ">Đăng ký</a>
   </div>

</header>

<div id="menu-btn" class="fas fa-bars"></div>

<?php
$admin_toasts = [];
if (isset($message) && is_array($message)) {
   foreach ($message as $msg) {
      if (!empty($msg)) {
         $admin_toasts[] = ['text' => (string)$msg, 'type' => 'info'];
      }
   }
}
if (!empty($_SESSION['message'])) {
   $admin_toasts[] = ['text' => (string)$_SESSION['message'], 'type' => 'info'];
   unset($_SESSION['message']);
}
?>

<?php if (!empty($admin_toasts)): ?>
   <script>
      (function() {
         const toasts = <?= json_encode($admin_toasts, JSON_UNESCAPED_UNICODE); ?>;
         toasts.forEach((item, index) => {
            setTimeout(() => {
               Toastify({
                  text: item.text,
                  duration: 4500,
                  gravity: 'top',
                  position: 'right',
                  close: true,
                  stopOnFocus: true,
                  style: {
                     background: '#2563eb'
                  }
               }).showToast();
            }, index * 250);
         });
      })();
   </script>
<?php endif; ?>
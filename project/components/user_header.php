<?php
include '../components/connect.php';

// Include navigation links
include_once 'navigation_links.php';
if (!function_exists('site_url')) {
   include_once 'seo_helpers.php';
}

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
   // Check if this is an AJAX request
   $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

   if ($isAjax) {
      // Return JSON error for AJAX requests
      header('Content-Type: application/json');
      echo json_encode([
         'success' => false,
         'message' => 'Không thể kết nối đến cơ sở dữ liệu. Vui lòng thử lại sau!'
      ]);
      exit;
   } else {
      // Show HTML error page for regular requests
      echo "<!DOCTYPE html><html><head><title>Database Error</title></head><body>";
      echo "<h1>Connection failed</h1>";
      echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
      echo "</body></html>";
      exit;
   }
}

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Lấy ảnh từ cơ sở dữ liệu
try {
   $select_avatar = $conn->prepare("SELECT name, avatar FROM `users` WHERE id = ?");
   $select_avatar->execute([$user_id]);
   $avatar = $select_avatar->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
   $avatar = null;
}

$display_name = $avatar && !empty($avatar['name']) ? $avatar['name'] : 'bạn';

$avatarSrc = blog_user_avatar_src($avatar['avatar'] ?? null, '../uploaded_img/default_avatar.png');
?>
<?php if (!defined('BLOG_LAYOUT_ASSETS')): ?>
   <link rel="stylesheet" href="../css/output.css">
   <link rel="stylesheet" href="../css/ui-system.css">
   <link rel="stylesheet" href="../css/gooey-toast.css">
<?php endif; ?>
<script src="../js/gooey-toast.js"></script>
<script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
<script>
   window.SEARCH_SUGGEST_URL = 'search_suggest.php';
   window.BLOG_PUSHER = {
      key: <?= json_encode((string)(getenv('PUSHER_KEY') ?: ''), JSON_UNESCAPED_UNICODE); ?>,
      cluster: <?= json_encode((string)(getenv('PUSHER_CLUSTER') ?: ''), JSON_UNESCAPED_UNICODE); ?>,
      userId: <?= json_encode((int)($user_id ?: 0), JSON_UNESCAPED_UNICODE); ?>,
      authEndpoint: <?= json_encode(site_url('static/pusher_auth.php'), JSON_UNESCAPED_UNICODE); ?>
   };
   window.BLOG_ENDPOINTS = {
      like: <?= json_encode(site_url('components/like_post.php'), JSON_UNESCAPED_UNICODE); ?>,
      save: <?= json_encode(site_url('components/save_post.php'), JSON_UNESCAPED_UNICODE); ?>,
      commentAdd: <?= json_encode(site_url('static/comment_add.php'), JSON_UNESCAPED_UNICODE); ?>,
      aiPostSummary: <?= json_encode(site_url('static/post_ai_summary_api.php'), JSON_UNESCAPED_UNICODE); ?>,
      communityCreate: <?= json_encode(site_url('static/community_create_api.php'), JSON_UNESCAPED_UNICODE); ?>,
      communityFeedList: <?= json_encode(site_url('static/community_feed_list_api.php'), JSON_UNESCAPED_UNICODE); ?>,
      communitySavedList: <?= json_encode(site_url('static/community_saved_list_api.php'), JSON_UNESCAPED_UNICODE); ?>,
      photosList: <?= json_encode(site_url('static/all_photos_list_api.php'), JSON_UNESCAPED_UNICODE); ?>,
      communityReact: <?= json_encode(site_url('static/community_react.php'), JSON_UNESCAPED_UNICODE); ?>,
      communityPollVote: <?= json_encode(site_url('static/community_poll_vote.php'), JSON_UNESCAPED_UNICODE); ?>,
      communityAction: <?= json_encode(site_url('static/community_action_api.php'), JSON_UNESCAPED_UNICODE); ?>,
      communityCommentAdd: <?= json_encode(site_url('static/community_comment_add.php'), JSON_UNESCAPED_UNICODE); ?>,
      communityManage: <?= json_encode(site_url('static/community_post_manage_api.php'), JSON_UNESCAPED_UNICODE); ?>,
      communityDigestPreference: <?= json_encode(site_url('static/community_digest_preference.php'), JSON_UNESCAPED_UNICODE); ?>,
      communityNotificationPreference: <?= json_encode(site_url('static/community_notification_preference.php'), JSON_UNESCAPED_UNICODE); ?>,
      commentVote: <?= json_encode(site_url('static/comment_vote.php'), JSON_UNESCAPED_UNICODE); ?>,
      notifications: <?= json_encode(site_url('static/notifications_api.php'), JSON_UNESCAPED_UNICODE); ?>
   };
   window.BLOG_DEBUG_ENDPOINTS = /^(localhost|127\.0\.0\.1)$/i.test(window.location.hostname);
   <?php if (isset($_SESSION['flash_message'])): ?>
      window.BLOG_FLASH_MESSAGE = <?= json_encode($_SESSION['flash_message'], JSON_UNESCAPED_UNICODE); ?>;
      window.BLOG_FLASH_TYPE = <?= json_encode($_SESSION['flash_type'] ?? 'info', JSON_UNESCAPED_UNICODE); ?>;
      <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
   <?php endif; ?>
</script>

<!-- Header with Tailwind CSS -->
<header class="bg-white dark:bg-gray-800 shadow-md sticky top-0 z-30 transition-colors duration-300">
   <div class="container-custom">
      <div class="flex items-center justify-between py-4">

         <!-- Logo Section -->
         <a href="<?= get_nav_link('home'); ?>" class="flex items-center space-x-3 group">
            <!-- <img src="<?= $logo_path; ?>" alt="Logo"
               class="h-10 w-10 rounded-full object-cover group-hover:scale-110 transition-transform duration-200"> -->
            <span class="text-xl font-bold text-gray-900 dark:text-white group-hover:text-main transition-colors">
               My Blog
            </span>
         </a>

         <!-- Desktop Navigation -->
         <nav class="hidden lg:flex items-center space-x-8">
            <a href="<?= get_nav_link('home'); ?>" class="nav-link text-sm text-gray-700 dark:text-gray-300 hover:text-main dark:hover:text-main transition-colors font-medium <?= is_active_page('home') ? 'text-main' : ''; ?>">
               Trang chủ
            </a>
            <a href="<?= get_nav_link('posts'); ?>" class="nav-link text-sm text-gray-700 dark:text-gray-300 hover:text-main dark:hover:text-main transition-colors font-medium <?= is_active_page('posts') ? 'text-main' : ''; ?>">
               Bài viết
            </a>
            <a href="<?= get_nav_link('community_feed'); ?>" class="nav-link text-sm text-gray-700 dark:text-gray-300 hover:text-main dark:hover:text-main transition-colors font-medium <?= is_active_page('community_feed') ? 'text-main' : ''; ?>">
               Cộng đồng
            </a>
            <a href="<?= get_nav_link('category'); ?>" class="nav-link text-sm text-gray-700 dark:text-gray-300 hover:text-main dark:hover:text-main transition-colors font-medium <?= is_active_page('category') ? 'text-main' : ''; ?>">
               Danh mục
            </a>
            <a href="<?= get_nav_link('all_photos'); ?>" class="nav-link text-sm text-gray-700 dark:text-gray-300 hover:text-main dark:hover:text-main transition-colors font-medium <?= is_active_page('all_photos') ? 'text-main' : ''; ?>">
               Hình ảnh
            </a>
            <a href="<?= get_nav_link('contact'); ?>" class="nav-link text-sm text-gray-700 dark:text-gray-300 hover:text-main dark:hover:text-main transition-colors font-medium <?= is_active_page('contact') ? 'text-main' : ''; ?>">
               Liên hệ
            </a>
         </nav>

         <!-- Search Form -->
         <form method="post" action="<?= get_nav_link('search'); ?>" class="hidden md:flex items-center" data-search-autocomplete>
            <div class="relative">
               <input type="text" name="search_box" placeholder="Tìm kiếm..." required maxlength="100" autocomplete="off" data-search-input
                  class="pl-4 pr-16 py-2 w-64 border border-gray-300 dark:border-gray-600 rounded-lg 
                             bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white
                             focus:outline-none focus:ring-2 focus:ring-main focus:border-transparent
                             transition-all duration-200 text-sm">
               <button type="submit" name="search_btn"
                  class="absolute inset-y-0 right-0 px-3 text-main hover:text-main/80 text-sm font-semibold" aria-label="Tìm kiếm">
                  <i class="fas fa-magnifying-glass"></i>
               </button>
               <div data-search-dropdown class="hidden absolute left-0 right-0 mt-2 z-50 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-xl overflow-hidden"></div>
            </div>
         </form>

         <!-- User Actions -->
         <div class="flex items-center space-x-4">

            <!-- Dark Mode Toggle -->
            <button class="dark-mode-toggle p-2 rounded-lg bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors" aria-label="Chuyển giao diện sáng/tối">
               <i class="fas fa-moon icon-theme-moon"></i>
               <i class="fas fa-sun icon-theme-sun hidden"></i>
            </button>

            <?php if ($user_id): ?>
               <div class="relative" id="notificationBellWrap">
                  <button id="notificationBellBtn" type="button" class="relative p-2 rounded-lg bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors" aria-label="Thông báo">
                     <i class="fas fa-bell text-gray-700 dark:text-gray-200"></i>
                     <span id="notificationBellBadge" class="absolute -top-1 -right-1 hidden min-w-[18px] h-[18px] px-1 bg-red-500 text-white text-[10px] font-semibold rounded-full items-center justify-center leading-[18px]"></span>
                  </button>
                  <div id="notificationDropdown" class="hidden absolute right-0 mt-2 w-96 max-w-[90vw] bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-xl overflow-hidden z-50">
                     <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between gap-2">
                        <strong class="text-sm text-gray-900 dark:text-white">Thông báo</strong>
                        <div class="flex items-center gap-2">
                           <button id="notificationMarkReadBtn" type="button" class="text-xs text-main hover:opacity-80">Đánh dấu đã đọc</button>
                           <button id="notificationViewAllBtn" type="button" class="text-xs text-gray-600 dark:text-gray-300 hover:text-main transition-colors">Xem tất cả</button>
                        </div>
                     </div>
                     <div id="notificationList" class="max-h-80 overflow-y-auto"></div>
                  </div>
               </div>
            <?php endif; ?>

            <?php if ($user_id): ?>
               <!-- User Menu (Logged In) -->
               <div class="relative user-menu">
                  <button class="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                     <img src="<?= $avatarSrc; ?>" alt="Avatar"
                        class="w-8 h-8 rounded-full object-cover border-2 border-gray-300 dark:border-gray-600">
                  </button>

                  <!-- Dropdown Menu -->
                  <div class="user-dropdown absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 hidden">
                     <a href="<?= get_user_link('update_profile'); ?>" class="block px-4 py-3 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        Cập nhật hồ sơ
                     </a>
                     <a href="<?= get_user_link('new_post'); ?>" class="block px-4 py-3 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        Bài viết mới nhất
                     </a>
                     <a href="<?= get_user_link('community_create'); ?>" class="block px-4 py-3 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        Đăng bài cộng đồng
                     </a>
                     <a href="<?= get_user_link('community_manage'); ?>" class="block px-4 py-3 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        Quản lý bài cộng đồng
                     </a>
                     <a href="<?= get_user_link('community_saved'); ?>" class="block px-4 py-3 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        Bài cộng đồng đã lưu
                     </a>
                     <div class="border-t border-gray-200 dark:border-gray-700"></div>
                     <a href="<?= get_user_link('user_likes'); ?>" class="block px-4 py-3 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        Bài viết yêu thích
                     </a>
                     <a href="<?= get_user_link('user_comments'); ?>" class="block px-4 py-3 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        Bình luận của tôi
                     </a>
                     <div class="border-t border-gray-200 dark:border-gray-700"></div>
                     <a href="<?= get_user_link('logout'); ?>"
                        onclick="return confirm('Bạn có chắc chắn muốn đăng xuất?');"
                        class="block px-4 py-3 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                        Đăng xuất
                     </a>
                  </div>
               </div>
            <?php else: ?>
               <!-- Auth Buttons (Not Logged In) -->
               <div class="flex">
                  <a href="<?= get_user_link('login'); ?>" class="btn-secondary">
                     Đăng nhập
                  </a>

               </div>
            <?php endif; ?>

            <!-- Mobile Menu Toggle -->
            <button class="lg:hidden p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors mobile-menu-toggle">
               <i class="fa-solid fa-bars"></i>
            </button>
         </div>
      </div>

      <!-- Mobile Navigation -->
      <div class="lg:hidden mobile-menu hidden bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
         <div class="py-4 space-y-2">
            <!-- Mobile Search -->
            <form method="post" action="<?= get_nav_link('search'); ?>" class="px-4 pb-4 border-b border-gray-200 dark:border-gray-700" data-search-autocomplete>
               <div class="relative">
                  <input type="text" name="search_box" placeholder="Tìm kiếm..." required maxlength="100" autocomplete="off" data-search-input
                     class="w-full pl-4 pr-16 py-2 border border-gray-300 dark:border-gray-600 rounded-lg 
                                bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white
                                focus:outline-none focus:ring-2 focus:ring-main focus:border-transparent">
                  <button type="submit" name="search_btn"
                     class="absolute inset-y-0 right-0 px-3 text-main text-sm font-semibold" aria-label="Tìm kiếm">
                     <i class="fas fa-magnifying-glass"></i>
                  </button>
                  <div data-search-dropdown class="hidden absolute left-0 right-0 mt-2 z-50 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-xl overflow-hidden"></div>
               </div>
            </form>

            <!-- Mobile Navigation Links -->
            <a href="<?= get_nav_link('home'); ?>" class="block px-4 py-3 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors <?= is_active_page('home') ? 'bg-gray-50 dark:bg-gray-700 text-main' : ''; ?>">
               Trang chủ
            </a>
            <a href="<?= get_nav_link('posts'); ?>" class="block px-4 py-3 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors <?= is_active_page('posts') ? 'bg-gray-50 dark:bg-gray-700 text-main' : ''; ?>">
               Bài viết
            </a>
            <a href="<?= get_nav_link('community_feed'); ?>" class="block px-4 py-3 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors <?= is_active_page('community_feed') ? 'bg-gray-50 dark:bg-gray-700 text-main' : ''; ?>">
               Cộng đồng
            </a>
            <a href="<?= get_nav_link('category'); ?>" class="block px-4 py-3 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors <?= is_active_page('category') ? 'bg-gray-50 dark:bg-gray-700 text-main' : ''; ?>">
               Danh mục
            </a>
            <a href="<?= get_nav_link('all_photos'); ?>" class="block px-4 py-3 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors <?= is_active_page('all_photos') ? 'bg-gray-50 dark:bg-gray-700 text-main' : ''; ?>">
               Hình ảnh
            </a>
            <a href="<?= get_nav_link('contact'); ?>" class="block px-4 py-3 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors <?= is_active_page('contact') ? 'bg-gray-50 dark:bg-gray-700 text-main' : ''; ?>">
               Liên hệ
            </a>

            <?php if ($user_id): ?>
               <!-- Mobile User Menu -->
               <div class="border-t border-gray-200 dark:border-gray-700 mt-2 pt-2">
                  <a href="<?= get_user_link('update_profile'); ?>" class="block px-4 py-3 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                     Cập nhật hồ sơ
                  </a>
                  <a href="<?= get_user_link('new_post'); ?>" class="block px-4 py-3 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                     Bài viết mới nhất
                  </a>
                  <a href="<?= get_user_link('community_create'); ?>" class="block px-4 py-3 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                     Đăng bài cộng đồng
                  </a>
                  <a href="<?= get_user_link('community_manage'); ?>" class="block px-4 py-3 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                     Quản lý bài cộng đồng
                  </a>
                  <a href="<?= get_user_link('community_saved'); ?>" class="block px-4 py-3 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                     Bài cộng đồng đã lưu
                  </a>
                  <a href="<?= get_user_link('user_likes'); ?>" class="block px-4 py-3 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                     Bài viết yêu thích
                  </a>
                  <a href="<?= get_user_link('user_comments'); ?>" class="block px-4 py-3 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                     Bình luận của tôi
                  </a>
                  <a href="<?= get_user_link('logout'); ?>"
                     onclick="return confirm('Bạn có chắc chắn muốn đăng xuất?');"
                     class="block px-4 py-3 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                     Đăng xuất
                  </a>
               </div>
            <?php else: ?>
               <!-- Mobile Auth Links -->
               <div class="border-t border-gray-200 dark:border-gray-700 mt-2 pt-2">
                  <a href="<?= get_user_link('login'); ?>" class="block px-4 py-3 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                     Đăng nhập
                  </a>

               </div>
            <?php endif; ?>
         </div>
      </div>
   </div>
</header>

<!-- Notification panel (slides in from right) -->
<div id="notificationPanelOverlay" class="fixed inset-0 bg-black/40 backdrop-blur-sm opacity-0 pointer-events-none transition-opacity duration-300 z-40"></div>
<div id="notificationPanel" class="fixed inset-y-0 right-0 w-full sm:w-3/4 md:w-1/2 lg:w-1/2 max-w-2xl bg-white dark:bg-gray-900 shadow-2xl transform translate-x-full transition-transform duration-300 z-50 flex flex-col">
   <div class="flex items-start justify-between gap-3 px-4 py-4 border-b border-gray-200 dark:border-gray-700">
      <div>
         <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Tất cả thông báo</h2>
         <p class="text-xs text-gray-500 dark:text-gray-400">Lọc và quản lý thông báo của bạn.</p>
      </div>
      <button id="notificationPanelCloseBtn" type="button" class="p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors" aria-label="Đóng">
         <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 8.586l4.95-4.95a1 1 0 111.414 1.414L11.414 10l4.95 4.95a1 1 0 01-1.414 1.414L10 11.414l-4.95 4.95a1 1 0 01-1.414-1.414L8.586 10 3.636 5.05a1 1 0 011.414-1.414L10 8.586z" clip-rule="evenodd" />
         </svg>
      </button>
   </div>
   <div class="px-4 py-3 space-y-3 border-b border-gray-200 dark:border-gray-700">
      <div class="flex flex-wrap items-center gap-2">
         <button type="button" data-filter="all" class="notification-filter-btn px-3 py-1 rounded-lg text-xs font-semibold text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 transition">Tất cả</button>
         <button type="button" data-filter="unread" class="notification-filter-btn px-3 py-1 rounded-lg text-xs font-semibold text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 transition">Chưa đọc</button>
         <button type="button" data-filter="read" class="notification-filter-btn px-3 py-1 rounded-lg text-xs font-semibold text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 transition">Đã đọc</button>
      </div>
      <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
         <label class="flex flex-col text-xs font-medium text-gray-500 dark:text-gray-400">
            <span>Từ</span>
            <input id="notificationFilterStart" type="date" class="mt-1 w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white focus:ring-main focus:border-main">
         </label>
         <label class="flex flex-col text-xs font-medium text-gray-500 dark:text-gray-400">
            <span>Đến</span>
            <input id="notificationFilterEnd" type="date" class="mt-1 w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white focus:ring-main focus:border-main">
         </label>
      </div>
      <div class="flex items-center justify-between gap-2">
         <button id="notificationFilterApplyBtn" type="button" class="flex-1 px-4 py-2 rounded-lg bg-main text-white text-sm font-semibold hover:bg-main/90 transition">Áp dụng</button>
         <button id="notificationFilterClearBtn" type="button" class="flex-1 px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-sm font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-700 transition">Xóa</button>
      </div>
   </div>
   <div id="notificationPanelList" class="flex-1 overflow-y-auto p-4 space-y-2"></div>
   <div class="p-4 border-t border-gray-200 dark:border-gray-700">
      <button id="notificationPanelMarkReadBtn" type="button" class="w-full px-4 py-3 rounded-lg bg-main text-white font-semibold hover:bg-main/90 transition">Đánh dấu đã đọc</button>
   </div>
</div>

<!-- Script functions are now handled by blog-global.js -->

<?php
$toast_items = [];

if (isset($_SESSION['message'])) {
   $session_message = $_SESSION['message'];
   if (is_array($session_message)) {
      foreach ($session_message as $msg) {
         if (!empty($msg)) {
            $toast_items[] = ['text' => (string)$msg, 'type' => 'info'];
         }
      }
   } elseif (!empty($session_message)) {
      $toast_items[] = ['text' => (string)$session_message, 'type' => 'info'];
   }
   unset($_SESSION['message']);
}

foreach (['success_message' => 'success', 'error_message' => 'error', 'info_message' => 'info', 'flash_message' => ($_SESSION['flash_type'] ?? 'info')] as $key => $type) {
   if (!empty($_SESSION[$key])) {
      $toast_items[] = ['text' => (string)$_SESSION[$key], 'type' => is_string($type) ? $type : 'info'];
      unset($_SESSION[$key]);
   }
}

if (isset($_SESSION['flash_type'])) {
   unset($_SESSION['flash_type']);
}
?>

<?php if (!empty($toast_items)): ?>
   <script>
      (function() {
         const toasts = <?= json_encode($toast_items, JSON_UNESCAPED_UNICODE); ?>;
         toasts.forEach((item, index) => {
            setTimeout(() => {
               if (typeof showNotification === 'function') {
                  showNotification(item.text, item.type || 'info');
               }
            }, index * 250);
         });
      })();
   </script>
<?php endif; ?>

<?php if ($user_id): ?>
   <script>
      (function() {
         const bellBtn = document.getElementById('notificationBellBtn');
         const dropdown = document.getElementById('notificationDropdown');
         const badge = document.getElementById('notificationBellBadge');
         const list = document.getElementById('notificationList');
         const markReadBtn = document.getElementById('notificationMarkReadBtn');
         const viewAllBtn = document.getElementById('notificationViewAllBtn');

         const panelOverlay = document.getElementById('notificationPanelOverlay');
         const panel = document.getElementById('notificationPanel');
         const panelCloseBtn = document.getElementById('notificationPanelCloseBtn');
         const panelList = document.getElementById('notificationPanelList');
         const panelMarkReadBtn = document.getElementById('notificationPanelMarkReadBtn');
         const filterButtons = Array.from(document.querySelectorAll('.notification-filter-btn'));
         const filterStartInput = document.getElementById('notificationFilterStart');
         const filterEndInput = document.getElementById('notificationFilterEnd');
         const filterApplyBtn = document.getElementById('notificationFilterApplyBtn');
         const filterClearBtn = document.getElementById('notificationFilterClearBtn');

         const state = {
            previewItems: [],
            panelItems: [],
            filter: 'all',
            dateFrom: '',
            dateTo: '',
            panelPage: 1,
            panelHasMore: true,
            panelLoading: false
         };

         const isPanelOpen = () => panel && !panel.classList.contains('translate-x-full');

         const formatDateTime = (value) => {
            try {
               const date = new Date(value);
               if (Number.isNaN(date.getTime())) return value;
               return date.toLocaleString();
            } catch {
               return value;
            }
         };

         const setActiveFilter = (filterKey) => {
            state.filter = filterKey;
            filterButtons.forEach((btn) => {
               const isActive = btn.dataset.filter === filterKey;
               btn.classList.toggle('bg-main', isActive);
               btn.classList.toggle('text-white', isActive);
               btn.classList.toggle('bg-gray-100', !isActive);
               btn.classList.toggle('dark:bg-gray-800', !isActive);
            });
         };

         const renderBadge = (count) => {
            badge.classList.add('inline-flex');
            if (count > 0) {
               badge.textContent = count > 99 ? '99+' : String(count);
               badge.classList.remove('hidden');
            } else {
               badge.textContent = '';
               badge.classList.add('hidden');
            }
         };

         const renderNotificationItem = (item, forPanel = false) => {
            const linkOpen = item.link ? `<a href="${item.link}" class="block ${forPanel ? 'hover:bg-gray-50 dark:hover:bg-gray-800' : 'hover:bg-gray-50 dark:hover:bg-gray-700'} rounded-lg">` : '<div>';
            const linkClose = item.link ? '</a>' : '</div>';
            const isUnread = Number(item.is_read) === 0;
            const badgeHtml = isUnread ? '<span class="inline-flex items-center px-2 py-0.5 text-[11px] font-semibold rounded-full bg-emerald-500 text-white">Moi</span>' : '';
            const containerClasses = ['px-4', 'py-3', 'border', 'border-gray-100', 'dark:border-gray-700', 'rounded-lg', 'transition-colors', 'duration-150'];
            if (isUnread) containerClasses.push('bg-emerald-50/60', 'dark:bg-emerald-900/10');

            const content = `
               <div class="${containerClasses.join(' ')}">
                  <div class="flex items-start justify-between gap-2">
                     <div>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">${item.title}</p>
                        <p class="text-xs text-gray-600 dark:text-gray-300 mt-1">${item.message}</p>
                     </div>
                     ${badgeHtml}
                  </div>
                  <p class="text-[11px] text-gray-400 mt-2">${formatDateTime(item.created_at)}</p>
               </div>
            `;

            return `${linkOpen}${content}${linkClose}`;
         };

         const renderItems = (items) => {
            const shown = Array.isArray(items) ? items.slice(0, 8) : [];
            if (!shown.length) {
               list.innerHTML = '<div class="px-4 py-5 text-sm text-gray-500 dark:text-gray-400">Chua co thong bao moi.</div>';
               return;
            }
            list.innerHTML = shown.map((item) => renderNotificationItem(item, false)).join('');
         };

         const renderPanelItems = (items, append = false) => {
            if (!Array.isArray(items) || items.length === 0) {
               if (!append) {
                  panelList.innerHTML = '<div class="text-sm text-gray-500 dark:text-gray-400">Khong tim thay thong bao phu hop.</div>';
               }
               return;
            }
            const html = items.map((item) => renderNotificationItem(item, true)).join('');
            if (append) {
               panelList.insertAdjacentHTML('beforeend', html);
            } else {
               panelList.innerHTML = html;
            }
         };

         const endpoint = () => (window.BLOG_ENDPOINTS && window.BLOG_ENDPOINTS.notifications) ? window.BLOG_ENDPOINTS.notifications : '../static/notifications_api.php';

         const fetchNotifications = async (opts = {}) => {
            const params = new URLSearchParams({
               action: 'list'
            });
            params.set('limit', String(opts.limit ?? 8));
            params.set('page', String(opts.page ?? 1));
            if (opts.status && opts.status !== 'all') params.set('status', opts.status);
            if (opts.start) params.set('start_date', opts.start);
            if (opts.end) params.set('end_date', opts.end);

            const res = await fetch(`${endpoint()}?${params.toString()}`, {
               credentials: 'same-origin'
            });
            if (!res.ok) return null;
            return res.json();
         };

         const refreshPreview = async () => {
            try {
               const data = await fetchNotifications({
                  limit: 8,
                  page: 1
               });
               if (!data || !data.ok) return;
               state.previewItems = Array.isArray(data.items) ? data.items : [];
               renderBadge(Number(data.unread || 0));
               renderItems(state.previewItems);
            } catch (err) {
               // silent
            }
         };

         const loadPanelPage = async (append = false) => {
            if (state.panelLoading) return;
            if (append && !state.panelHasMore) return;

            state.panelLoading = true;
            try {
               const pageToLoad = append ? state.panelPage : 1;
               const data = await fetchNotifications({
                  limit: 20,
                  page: pageToLoad,
                  status: state.filter,
                  start: state.dateFrom,
                  end: state.dateTo
               });
               if (!data || !data.ok) return;

               renderBadge(Number(data.unread || 0));
               const incoming = Array.isArray(data.items) ? data.items : [];
               if (append) {
                  state.panelItems = state.panelItems.concat(incoming);
               } else {
                  state.panelItems = incoming;
               }
               renderPanelItems(incoming, append);

               state.panelHasMore = !!data.has_more;
               state.panelPage = data.next_page ? Number(data.next_page) : pageToLoad;
            } catch (err) {
               // silent
            } finally {
               state.panelLoading = false;
            }
         };

         const resetAndLoadPanel = async () => {
            state.panelItems = [];
            state.panelPage = 1;
            state.panelHasMore = true;
            if (panelList) {
               panelList.innerHTML = '<div class="text-sm text-gray-500 dark:text-gray-400">Dang tai thong bao...</div>';
            }
            await loadPanelPage(false);
         };

         const openPanel = () => {
            if (!panel || !panelOverlay) return;
            panelOverlay.classList.remove('opacity-0', 'pointer-events-none');
            panelOverlay.classList.add('opacity-100', 'pointer-events-auto');
            panel.classList.remove('translate-x-full');
            panel.classList.add('translate-x-0');
            document.body.classList.add('overflow-hidden');
         };

         const closePanel = () => {
            if (!panel || !panelOverlay) return;
            panelOverlay.classList.add('opacity-0', 'pointer-events-none');
            panelOverlay.classList.remove('opacity-100', 'pointer-events-auto');
            panel.classList.add('translate-x-full');
            panel.classList.remove('translate-x-0');
            document.body.classList.remove('overflow-hidden');
         };

         const markAllRead = async () => {
            try {
               await fetch(endpoint() + '?action=mark_read', {
                  method: 'POST',
                  credentials: 'same-origin'
               });
            } catch (err) {
               // noop
            }
            await refreshPreview();
            if (isPanelOpen()) {
               resetAndLoadPanel();
            }
         };

         const connectPusher = () => {
            if (typeof window.Pusher === 'undefined' || !window.BLOG_PUSHER) {
               return;
            }
            const pusherCfg = window.BLOG_PUSHER;
            if (!pusherCfg.key || !pusherCfg.cluster || !pusherCfg.userId) {
               return;
            }

            try {
               const pusher = new Pusher(pusherCfg.key, {
                  cluster: pusherCfg.cluster,
                  forceTLS: true,
                  authEndpoint: pusherCfg.authEndpoint,
               });
               const channel = pusher.subscribe('private-user-notifications-' + String(pusherCfg.userId));
               channel.bind('notification:new', function(payload) {
                  const unreadFromEvent = Number(payload && payload.unread_count ? payload.unread_count : 0);
                  if (Number.isFinite(unreadFromEvent) && unreadFromEvent >= 0) {
                     renderBadge(unreadFromEvent);
                  }
                  refreshPreview();
                  if (isPanelOpen()) {
                     resetAndLoadPanel();
                  }
               });
            } catch (err) {
               // fallback polling below is still active
            }
         };

         bellBtn?.addEventListener('click', function() {
            dropdown.classList.toggle('hidden');
         });

         document.addEventListener('click', function(e) {
            if (!e.target.closest('#notificationBellWrap')) {
               dropdown?.classList.add('hidden');
            }
         });

         viewAllBtn?.addEventListener('click', async function() {
            dropdown?.classList.add('hidden');
            openPanel();
            await resetAndLoadPanel();
         });

         panelOverlay?.addEventListener('click', closePanel);
         panelCloseBtn?.addEventListener('click', closePanel);
         document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
               closePanel();
            }
         });

         panelList?.addEventListener('scroll', function() {
            if (!isPanelOpen()) return;
            const threshold = 160;
            if (panelList.scrollTop + panelList.clientHeight >= panelList.scrollHeight - threshold) {
               loadPanelPage(true);
            }
         });

         markReadBtn?.addEventListener('click', markAllRead);
         panelMarkReadBtn?.addEventListener('click', markAllRead);

         filterButtons.forEach((btn) => {
            btn.addEventListener('click', function() {
               setActiveFilter(btn.dataset.filter || 'all');
            });
         });

         filterApplyBtn?.addEventListener('click', function() {
            state.dateFrom = filterStartInput?.value || '';
            state.dateTo = filterEndInput?.value || '';
            resetAndLoadPanel();
         });

         filterClearBtn?.addEventListener('click', function() {
            state.dateFrom = '';
            state.dateTo = '';
            setActiveFilter('all');
            if (filterStartInput) filterStartInput.value = '';
            if (filterEndInput) filterEndInput.value = '';
            resetAndLoadPanel();
         });

         setActiveFilter('all');
         refreshPreview();
         connectPusher();
         setInterval(() => {
            refreshPreview();
            if (isPanelOpen()) {
               resetAndLoadPanel();
            }
         }, 10000);
      })();
   </script>
<?php endif; ?>
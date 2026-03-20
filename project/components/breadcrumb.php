<?php

/**
 * Breadcrumb Component
 * Hiển thị đường dẫn điều hướng
 */

// Include navigation links nếu chưa được include
if (!function_exists('get_nav_link')) {
    include_once 'navigation_links.php';
}

function render_breadcrumb($breadcrumb_items = [])
{
    if (empty($breadcrumb_items)) {
        return;
    }
?>
    <nav class="bg-white/90 dark:bg-gray-900/75 border-b border-gray-200 dark:border-gray-700 backdrop-blur" aria-label="Breadcrumb">
        <div class="container-custom">
            <ol class="flex items-center py-3 text-sm overflow-hidden">
                <?php foreach ($breadcrumb_items as $index => $item): ?>
                    <li class="flex items-center min-w-0">
                        <?php if ($index > 0): ?>
                            <i class="fas fa-chevron-right text-gray-300 dark:text-gray-600 mx-2 text-xs shrink-0"></i>
                        <?php endif; ?>

                        <?php if (!empty($item['url']) && $index < count($breadcrumb_items) - 1): ?>
                            <a href="<?= $item['url']; ?>"
                                class="text-gray-600 dark:text-gray-400 hover:text-main dark:hover:text-main transition-colors whitespace-nowrap">
                                <?= htmlspecialchars($item['title']); ?>
                            </a>
                        <?php else: ?>
                            <span class="text-gray-900 dark:text-white font-semibold truncate max-w-[60vw]" title="<?= htmlspecialchars($item['title']); ?>">
                                <?= htmlspecialchars($item['title']); ?>
                            </span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </nav>
<?php
}

// Helper function to get page title for breadcrumb
function get_page_breadcrumb_title($page_name)
{
    $titles = [
        'home_tailwind' => 'Trang chủ',
        'posts_tailwind' => 'Bài viết',
        'category_tailwind' => 'Danh mục',
        'all_photos_tailwind' => 'Hình ảnh',
        'search_tailwind' => 'Tìm kiếm',
        'contact' => 'Liên hệ',
        'login_tailwind' => 'Đăng nhập',
        'register_tailwind' => 'Đăng ký',
        'forgot_pass_tailwind' => 'Quên mật khẩu',
        'reset_pass_tailwind' => 'Đặt lại mật khẩu',
        'update_tailwind' => 'Cập nhật hồ sơ',
        'new_post_tailwind' => 'Viết bài mới',
        'community_feed' => 'Cộng đồng',
        'community_create' => 'Tạo bài cộng đồng',
        'community_manage' => 'Quản lý bài cộng đồng',
        'user_likes_tailwind' => 'Bài viết yêu thích',
        'user_comments_tailwind' => 'Bình luận của tôi',
        'view_post_tailwind' => 'Xem bài viết',
        'author_posts_tailwind' => 'Bài viết của tác giả',
    ];

    return isset($titles[$page_name]) ? $titles[$page_name] : 'Trang';
}

// Auto generate breadcrumb based on current page
function auto_breadcrumb($custom_title = '')
{
    $current_page = get_current_page();
    $breadcrumb = [];

    // Always start with home
    $breadcrumb[] = [
        'title' => 'Trang chủ',
        'url' => get_nav_link('home')
    ];

    // Add current page
    if ($current_page !== 'home_tailwind' && $current_page !== 'home') {
        $title = !empty($custom_title) ? $custom_title : get_page_breadcrumb_title($current_page);
        $breadcrumb[] = [
            'title' => $title,
            'url' => ''
        ];
    }

    return $breadcrumb;
}
?>
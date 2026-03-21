<?php

/**
 * Navigation Links Configuration
 * Tập trung quản lý tất cả các liên kết trong website
 */

// Định nghĩa base paths
$base_path = '../static/';
$admin_path = '../admin/';
$component_path = '../components/';

// Main Navigation Links
$nav_links = [
    'home' => $base_path . 'home.php',
    'posts' => $base_path . 'posts.php',
    'community_feed' => $base_path . 'community_feed.php',
    'category' => $base_path . 'category.php',
    'all_photos' => $base_path . 'all_photos.php',
    'search' => $base_path . 'search.php',
    'contact' => $base_path . 'contact.php',
    'menu' => $base_path . 'menu.php',
    'about' => $base_path . 'introduce.php',
];

// User Account Links
$user_links = [
    'login' => $base_path . 'login.php',
    'register' => $base_path . 'register.php',
    'forgot_password' => $base_path . 'forgot_pass.php',
    'reset_password' => $base_path . 'reset_pass.php',
    'update_profile' => $base_path . 'update.php',
    'new_post' => $base_path . 'new_post.php',
    'community_create' => $base_path . 'community_create.php',
    'community_manage' => $base_path . 'community_manage.php',
    'community_saved' => $base_path . 'community_saved.php',
    'user_likes' => $base_path . 'user_likes.php',
    'user_comments' => $base_path . 'user_comments.php',
    'view_post' => $base_path . 'view_post.php',
    'author_posts' => $base_path . 'author_posts.php',
    'logout' => $component_path . 'user_logout.php',
];

// Admin Links
$admin_links = [
    'admin_login' => $base_path . 'login.php?next=admin',
    'dashboard' => $admin_path . 'dashboard.php',
    'add_posts' => $admin_path . 'add_posts.php',
    'view_posts' => $admin_path . 'view_posts.php',
    'edit_post' => $admin_path . 'edit_post.php',
    'users_accounts' => $admin_path . 'users_accounts.php',
    'admin_accounts' => $admin_path . 'admin_accounts.php',
    'comments' => $admin_path . 'comments.php',
    'setting' => $admin_path . 'setting.php',
    'admin_logout' => $component_path . 'admin_logout.php',
];

// Helper functions
function get_nav_link($key)
{
    global $nav_links;
    return isset($nav_links[$key]) ? $nav_links[$key] : '#';
}

function get_user_link($key)
{
    global $user_links;
    return isset($user_links[$key]) ? $user_links[$key] : '#';
}

function get_admin_link($key)
{
    global $admin_links;
    return isset($admin_links[$key]) ? $admin_links[$key] : '#';
}

// Get current page name for active states
function get_current_page()
{
    return basename($_SERVER['PHP_SELF'], '.php');
}

function is_active_page($page_name)
{
    $current = get_current_page();
    return $current === $page_name ? 'active' : '';
}

// Generate breadcrumb
function generate_breadcrumb($current_title = '')
{
    $breadcrumb = [];
    $breadcrumb[] = ['title' => 'Trang chủ', 'url' => get_nav_link('home')];

    if (!empty($current_title)) {
        $breadcrumb[] = ['title' => $current_title, 'url' => ''];
    }

    return $breadcrumb;
}

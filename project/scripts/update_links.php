<?php

/**
 * Update Links Script
 * Script này sẽ cập nhật tất cả các liên kết trong các trang để sử dụng phiên bản Tailwind
 */

echo "🔄 Bắt đầu cập nhật liên kết...\n";

$project_path = __DIR__ . '/../';
$static_path = $project_path . 'static/';

// Danh sách các file cần cập nhật
$files_to_update = [
    'home.php',
    'posts.php',
    'category.php',
    'all_photos.php',
    'search.php',
    'login.php',
    'register.php',
    'forgot_pass.php',
    'reset_pass.php',
    'update.php',
    'new_post.php',
    'user_likes.php',
    'user_comments.php',
    'view_post.php',
    'author_posts.php'
];

// Mapping các liên kết cần thay thế
$link_replacements = [
    // Basic pages
    'href="home.php"' => 'href="home.php"',
    'href="posts.php"' => 'href="posts.php"',
    'href="category.php"' => 'href="category.php"',
    'href="all_photos.php"' => 'href="all_photos.php"',
    'href="search.php"' => 'href="search.php"',
    'href="login.php"' => 'href="login.php"',
    'href="register.php"' => 'href="register.php"',
    'href="forgot_pass.php"' => 'href="forgot_pass.php"',
    'href="reset_pass.php"' => 'href="reset_pass.php"',
    'href="update.php"' => 'href="update.php"',
    'href="new_post.php"' => 'href="new_post.php"',
    'href="user_likes.php"' => 'href="user_likes.php"',
    'href="user_comments.php"' => 'href="user_comments.php"',
    'href="view_post.php"' => 'href="view_post.php"',
    'href="author_posts.php"' => 'href="author_posts.php"',

    // Form actions
    'action="home.php"' => 'action="home.php"',
    'action="posts.php"' => 'action="posts.php"',
    'action="category.php"' => 'action="category.php"',
    'action="search.php"' => 'action="search.php"',
    'action="login.php"' => 'action="login.php"',
    'action="register.php"' => 'action="register.php"',
    'action="forgot_pass.php"' => 'action="forgot_pass.php"',
    'action="reset_pass.php"' => 'action="reset_pass.php"',
    'action="update.php"' => 'action="update.php"',
    'action="new_post.php"' => 'action="new_post.php"',

    // Location redirects
    "Location: home.php" => "Location: home.php",
    "Location: posts.php" => "Location: posts.php",
    "Location: category.php" => "Location: category.php",
    "Location: login.php" => "Location: login.php",
    "Location: register.php" => "Location: register.php",
    "Location: update.php" => "Location: update.php",

    // JavaScript redirects
    "window.location = 'home.php'" => "window.location = 'home.php'",
    "window.location = 'posts.php'" => "window.location = 'posts.php'",
    "window.location = 'login.php'" => "window.location = 'login.php'",
];

$total_replacements = 0;

foreach ($files_to_update as $file) {
    $file_path = $static_path . $file;

    if (!file_exists($file_path)) {
        echo "⚠️  File không tồn tại: $file\n";
        continue;
    }

    $content = file_get_contents($file_path);
    $original_content = $content;
    $file_replacements = 0;

    foreach ($link_replacements as $old_link => $new_link) {
        $count = 0;
        $content = str_replace($old_link, $new_link, $content, $count);
        $file_replacements += $count;
    }

    if ($file_replacements > 0) {
        file_put_contents($file_path, $content);
        echo "✅ Cập nhật $file: $file_replacements liên kết\n";
        $total_replacements += $file_replacements;
    } else {
        echo "➖ Không có liên kết nào cần cập nhật trong $file\n";
    }
}

echo "\n🎉 Hoàn thành! Tổng cộng cập nhật $total_replacements liên kết.\n";
echo "📋 Danh sách các trang đã được cập nhật:\n";
foreach ($files_to_update as $file) {
    if (file_exists($static_path . $file)) {
        echo "   ✓ $file\n";
    }
}

echo "\n📝 Lưu ý: Hãy kiểm tra các trang để đảm bảo tất cả hoạt động bình thường.\n";


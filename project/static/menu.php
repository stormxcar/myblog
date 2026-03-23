<?php
$page_title = "Menu điều hướng - My Blog";
include '../components/layout_header.php';
include '../components/breadcrumb.php';

// Include navigation links
include '../components/navigation_links.php';

// Render breadcrumb
$breadcrumb = auto_breadcrumb('Menu điều hướng');
render_breadcrumb($breadcrumb);
?>

<main class="min-h-screen bg-gray-50 dark:bg-gray-900 py-8">
    <div class="container-custom">
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">
                <i class="fas fa-sitemap mr-3 text-main"></i>
                Menu điều hướng
            </h1>
            <p class="text-lg text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
                Tất cả các trang trong website với phiên bản Tailwind CSS mới
            </p>
        </div>

        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
            <!-- Main Pages -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                    <i class="fas fa-home mr-3 text-blue-500"></i>
                    Trang chính
                </h3>
                <ul class="space-y-3">
                    <li>
                        <a href="<?= get_nav_link('home'); ?>"
                            class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors group">
                            <span class="flex items-center">
                                <i class="fas fa-home mr-3 text-gray-600 dark:text-gray-400 group-hover:text-main"></i>
                                Trang chủ
                            </span>
                            <i class="fas fa-arrow-right text-gray-400 group-hover:text-main transition-colors"></i>
                        </a>
                    </li>
                    <li>
                        <a href="<?= get_nav_link('posts'); ?>"
                            class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors group">
                            <span class="flex items-center">
                                <i class="fas fa-blog mr-3 text-gray-600 dark:text-gray-400 group-hover:text-main"></i>
                                Bài viết
                            </span>
                            <i class="fas fa-arrow-right text-gray-400 group-hover:text-main transition-colors"></i>
                        </a>
                    </li>
                    <li>
                        <a href="<?= get_nav_link('category'); ?>"
                            class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors group">
                            <span class="flex items-center">
                                <i class="fas fa-tags mr-3 text-gray-600 dark:text-gray-400 group-hover:text-main"></i>
                                Danh mục
                            </span>
                            <i class="fas fa-arrow-right text-gray-400 group-hover:text-main transition-colors"></i>
                        </a>
                    </li>
                    <li>
                        <a href="<?= get_nav_link('all_photos'); ?>"
                            class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors group">
                            <span class="flex items-center">
                                <i class="fas fa-images mr-3 text-gray-600 dark:text-gray-400 group-hover:text-main"></i>
                                Hình ảnh
                            </span>
                            <i class="fas fa-arrow-right text-gray-400 group-hover:text-main transition-colors"></i>
                        </a>
                    </li>
                    <li>
                        <a href="<?= get_nav_link('search'); ?>"
                            class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors group">
                            <span class="flex items-center">
                                <i class="fas fa-search mr-3 text-gray-600 dark:text-gray-400 group-hover:text-main"></i>
                                Tìm kiếm
                            </span>
                            <i class="fas fa-arrow-right text-gray-400 group-hover:text-main transition-colors"></i>
                        </a>
                    </li>
                    <li>
                        <a href="<?= get_nav_link('contact'); ?>"
                            class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors group">
                            <span class="flex items-center">
                                <i class="fas fa-envelope mr-3 text-gray-600 dark:text-gray-400 group-hover:text-main"></i>
                                Liên hệ
                            </span>
                            <i class="fas fa-arrow-right text-gray-400 group-hover:text-main transition-colors"></i>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- User Account -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                    <i class="fas fa-user mr-3 text-green-500"></i>
                    Tài khoản
                </h3>
                <ul class="space-y-3">
                    <li>
                        <a href="<?= get_user_link('login'); ?>"
                            class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors group">
                            <span class="flex items-center">
                                <i class="fas fa-sign-in-alt mr-3 text-gray-600 dark:text-gray-400 group-hover:text-main"></i>
                                Đăng nhập
                            </span>
                            <i class="fas fa-arrow-right text-gray-400 group-hover:text-main transition-colors"></i>
                        </a>
                    </li>
                    <li>
                        <a href="<?= get_user_link('register'); ?>"
                            class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors group">
                            <span class="flex items-center">
                                <i class="fas fa-user-plus mr-3 text-gray-600 dark:text-gray-400 group-hover:text-main"></i>
                                Đăng ký
                            </span>
                            <i class="fas fa-arrow-right text-gray-400 group-hover:text-main transition-colors"></i>
                        </a>
                    </li>
                    <li>
                        <a href="<?= get_user_link('forgot_password'); ?>"
                            class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors group">
                            <span class="flex items-center">
                                <i class="fas fa-key mr-3 text-gray-600 dark:text-gray-400 group-hover:text-main"></i>
                                Quên mật khẩu
                            </span>
                            <i class="fas fa-arrow-right text-gray-400 group-hover:text-main transition-colors"></i>
                        </a>
                    </li>
                    <li>
                        <a href="<?= get_user_link('reset_password'); ?>"
                            class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors group">
                            <span class="flex items-center">
                                <i class="fas fa-lock mr-3 text-gray-600 dark:text-gray-400 group-hover:text-main"></i>
                                Đặt lại mật khẩu
                            </span>
                            <i class="fas fa-arrow-right text-gray-400 group-hover:text-main transition-colors"></i>
                        </a>
                    </li>
                    <li>
                        <a href="<?= get_user_link('update_profile'); ?>"
                            class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors group">
                            <span class="flex items-center">
                                <i class="fas fa-user-edit mr-3 text-gray-600 dark:text-gray-400 group-hover:text-main"></i>
                                Cập nhật hồ sơ
                            </span>
                            <i class="fas fa-arrow-right text-gray-400 group-hover:text-main transition-colors"></i>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- User Content -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-4 flex items-center">
                    <i class="fas fa-heart mr-3 text-red-500"></i>
                    Nội dung cá nhân
                </h3>
                <ul class="space-y-3">
                    <li>
                        <a href="<?= get_user_link('new_post'); ?>"
                            class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors group">
                            <span class="flex items-center">
                                <i class="fas fa-plus mr-3 text-gray-600 dark:text-gray-400 group-hover:text-main"></i>
                                Bài viết mới nhất
                            </span>
                            <i class="fas fa-arrow-right text-gray-400 group-hover:text-main transition-colors"></i>
                        </a>
                    </li>
                    <li>
                        <a href="<?= get_user_link('user_likes'); ?>"
                            class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors group">
                            <span class="flex items-center">
                                <i class="fas fa-heart mr-3 text-red-500 group-hover:text-main"></i>
                                Bài viết yêu thích
                            </span>
                            <i class="fas fa-arrow-right text-gray-400 group-hover:text-main transition-colors"></i>
                        </a>
                    </li>
                    <li>
                        <a href="<?= get_user_link('user_comments'); ?>"
                            class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors group">
                            <span class="flex items-center">
                                <i class="fas fa-comment mr-3 text-blue-500 group-hover:text-main"></i>
                                Bình luận của tôi
                            </span>
                            <i class="fas fa-arrow-right text-gray-400 group-hover:text-main transition-colors"></i>
                        </a>
                    </li>
                    <li>
                        <a href="<?= get_user_link('author_posts'); ?>"
                            class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors group">
                            <span class="flex items-center">
                                <i class="fas fa-user-edit mr-3 text-gray-600 dark:text-gray-400 group-hover:text-main"></i>
                                Bài viết của tác giả
                            </span>
                            <i class="fas fa-arrow-right text-gray-400 group-hover:text-main transition-colors"></i>
                        </a>
                    </li>
                    <li>
                        <a href="<?= get_user_link('view_post'); ?>"
                            class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors group">
                            <span class="flex items-center">
                                <i class="fas fa-eye mr-3 text-gray-600 dark:text-gray-400 group-hover:text-main"></i>
                                Xem bài viết
                            </span>
                            <i class="fas fa-arrow-right text-gray-400 group-hover:text-main transition-colors"></i>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="mt-12 bg-gradient-to-r from-main/10 to-blue-500/10 rounded-xl p-8">
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-6 text-center">
                <i class="fas fa-rocket mr-3 text-main"></i>
                Liên kết nhanh
            </h3>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <a href="<?= get_nav_link('home'); ?>"
                    class="flex items-center justify-center p-4 bg-white dark:bg-gray-800 rounded-lg shadow hover:shadow-lg transition-all duration-300 group">
                    <i class="fas fa-home mr-3 text-main group-hover:scale-110 transition-transform"></i>
                    <span class="font-semibold text-gray-900 dark:text-white">Trang chủ</span>
                </a>
                <a href="<?= get_user_link('login'); ?>"
                    class="flex items-center justify-center p-4 bg-white dark:bg-gray-800 rounded-lg shadow hover:shadow-lg transition-all duration-300 group">
                    <i class="fas fa-sign-in-alt mr-3 text-green-500 group-hover:scale-110 transition-transform"></i>
                    <span class="font-semibold text-gray-900 dark:text-white">Đăng nhập</span>
                </a>
                <a href="<?= get_user_link('user_likes'); ?>"
                    class="flex items-center justify-center p-4 bg-white dark:bg-gray-800 rounded-lg shadow hover:shadow-lg transition-all duration-300 group">
                    <i class="fas fa-heart mr-3 text-red-500 group-hover:scale-110 transition-transform"></i>
                    <span class="font-semibold text-gray-900 dark:text-white">Yêu thích</span>
                </a>
                <a href="<?= get_user_link('user_comments'); ?>"
                    class="flex items-center justify-center p-4 bg-white dark:bg-gray-800 rounded-lg shadow hover:shadow-lg transition-all duration-300 group">
                    <i class="fas fa-comment mr-3 text-blue-500 group-hover:scale-110 transition-transform"></i>
                    <span class="font-semibold text-gray-900 dark:text-white">Bình luận</span>
                </a>
            </div>
        </div>

        <!-- Status -->
        <div class="mt-12 text-center">
            <div class="inline-flex items-center px-6 py-3 bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200 rounded-full">
                <i class="fas fa-check-circle mr-3"></i>
                <span class="font-semibold">Tất cả trang đã được chuyển đổi sang Tailwind CSS!</span>
            </div>
        </div>
    </div>
</main>

<?php include '../components/layout_footer.php'; ?>
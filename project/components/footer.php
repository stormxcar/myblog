<?php
// ========================================
// FOOTER COMPONENT WITH TAILWIND CSS
// ========================================

// Kết nối CSDL và truy vấn settings
if (!isset($conn)) {
    include '../components/connect.php';
}

try {
    // Lấy tất cả settings cần thiết trong 1 query
    $select_settings = $conn->prepare("
        SELECT setting_key, setting_value 
        FROM `settings` 
        WHERE setting_key IN (
            'footer_text', 'link_facebook', 'link_google', 'link_twitter', 'link_youtube',
            'lienhe_diachi', 'lienhe_dienthoai', 'lienhe_fax', 'lienhe_email', 'lienhe_zalo', 'lienhe_name'
        )
    ");
    $select_settings->execute();
    $settings = $select_settings->fetchAll(PDO::FETCH_KEY_PAIR);

    // Gán giá trị với fallback defaults
    $footer_text = $settings['footer_text'] ?? 'My Blog';
    $link_facebook = $settings['link_facebook'] ?? '#';
    $link_google = $settings['link_google'] ?? '#';
    $link_twitter = $settings['link_twitter'] ?? '#';
    $link_youtube = $settings['link_youtube'] ?? '#';
    $diachi_text = $settings['lienhe_diachi'] ?? 'Chưa cập nhật địa chỉ';
    $dienthoai_text = $settings['lienhe_dienthoai'] ?? 'Chưa cập nhật SĐT';
    $fax_text = $settings['lienhe_fax'] ?? 'Chưa cập nhật fax';
    $email_text = $settings['lienhe_email'] ?? 'contact@myblog.com';
    $zalo_text = $settings['lienhe_zalo'] ?? '#';
    $name_text = $settings['lienhe_name'] ?? 'My Blog';
} catch (PDOException $e) {
    // Fallback khi có lỗi database
    $footer_text = 'My Blog';
    $link_facebook = $link_google = $link_twitter = $link_youtube = '#';
    $diachi_text = $dienthoai_text = $fax_text = $email_text = $zalo_text = $name_text = 'Đang cập nhật...';
}
?>

<!-- ========================================
     FOOTER WITH TAILWIND CSS
     ======================================== -->
<footer class="bg-gray-900 dark:bg-black text-white">

    <!-- Main Footer Content -->
    <div class="container-custom py-16">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 lg:gap-12">

            <!-- About Section -->
            <div class="space-y-6">
                <div>
                    <h2 class="text-2xl font-bold text-white mb-4 relative">
                        BLOG CỦA TÔI
                        <div class="absolute bottom-0 left-0 w-12 h-1 bg-main rounded-full"></div>
                    </h2>
                    <p class="text-gray-300 leading-relaxed">
                        Một hành trình lan tỏa niềm đam mê với một tinh thần lạc quan. Hãy cùng tôi khám phá những trải nghiệm sống đầy màu sắc và tìm thấy cảm hứng trong từng câu chuyện của chính mình.
                    </p>
                </div>

                <!-- Social Media Links -->
                <div>
                    <h3 class="text-lg font-semibold mb-4">Kết nối với chúng tôi</h3>
                    <div class="flex space-x-4">
                        <a href="<?= htmlspecialchars($link_facebook); ?>" target="_blank"
                            class="group w-10 h-10 bg-blue-600 hover:bg-blue-700 rounded-full flex items-center justify-center transition-all duration-300 hover:scale-110">
                            <i class="fab fa-facebook-f text-white group-hover:animate-pulse"></i>
                        </a>
                        <a href="<?= htmlspecialchars($link_google); ?>" target="_blank"
                            class="group w-10 h-10 bg-red-600 hover:bg-red-700 rounded-full flex items-center justify-center transition-all duration-300 hover:scale-110">
                            <i class="fab fa-google text-white group-hover:animate-pulse"></i>
                        </a>
                        <a href="<?= htmlspecialchars($link_twitter); ?>" target="_blank"
                            class="group w-10 h-10 bg-sky-500 hover:bg-sky-600 rounded-full flex items-center justify-center transition-all duration-300 hover:scale-110">
                            <i class="fab fa-twitter text-white group-hover:animate-pulse"></i>
                        </a>
                        <a href="<?= htmlspecialchars($link_youtube); ?>" target="_blank"
                            class="group w-10 h-10 bg-red-500 hover:bg-red-600 rounded-full flex items-center justify-center transition-all duration-300 hover:scale-110">
                            <i class="fab fa-youtube text-white group-hover:animate-pulse"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Contact Info Section -->
            <div class="space-y-6">
                <div>
                    <h2 class="text-2xl font-bold text-white mb-4 relative">
                        THÔNG TIN LIÊN HỆ
                        <div class="absolute bottom-0 left-0 w-12 h-1 bg-main rounded-full"></div>
                    </h2>
                    <div class="space-y-4">
                        <div class="flex items-start space-x-3 group">
                            <div class="w-5 h-5 mt-1 text-main group-hover:text-orange-400 transition-colors">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div>
                                <span class="font-semibold text-gray-300">Địa chỉ:</span>
                                <p class="text-gray-400"><?= htmlspecialchars($diachi_text); ?></p>
                            </div>
                        </div>

                        <div class="flex items-center space-x-3 group">
                            <div class="w-5 h-5 text-main group-hover:text-green-400 transition-colors">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div>
                                <span class="font-semibold text-gray-300">Điện thoại:</span>
                                <a href="tel:<?= htmlspecialchars($dienthoai_text); ?>"
                                    class="text-gray-400 hover:text-main transition-colors ml-2">
                                    <?= htmlspecialchars($dienthoai_text); ?>
                                </a>
                            </div>
                        </div>

                        <div class="flex items-center space-x-3 group">
                            <div class="w-5 h-5 text-main group-hover:text-blue-400 transition-colors">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div>
                                <span class="font-semibold text-gray-300">Email:</span>
                                <a href="mailto:<?= htmlspecialchars($email_text); ?>"
                                    class="text-gray-400 hover:text-main transition-colors ml-2">
                                    <?= htmlspecialchars($email_text); ?>
                                </a>
                            </div>
                        </div>

                        <?php if (!empty($zalo_text) && $zalo_text !== '#'): ?>
                            <div class="flex items-center space-x-3 group">
                                <div class="w-5 h-5 text-main group-hover:text-blue-500 transition-colors">
                                    <i class="fas fa-comment-dots"></i>
                                </div>
                                <div>
                                    <span class="font-semibold text-gray-300">Zalo:</span>
                                    <a href="<?= htmlspecialchars($zalo_text); ?>" target="_blank"
                                        class="text-gray-400 hover:text-main transition-colors ml-2">
                                        <?= htmlspecialchars($name_text); ?>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($fax_text) && $fax_text !== 'Chưa cập nhật fax'): ?>
                            <div class="flex items-center space-x-3 group">
                                <div class="w-5 h-5 text-main group-hover:text-gray-400 transition-colors">
                                    <i class="fas fa-fax"></i>
                                </div>
                                <div>
                                    <span class="font-semibold text-gray-300">Fax:</span>
                                    <span class="text-gray-400 ml-2"><?= htmlspecialchars($fax_text); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Navigation Links Section -->
            <div class="space-y-6">
                <div>
                    <h2 class="text-2xl font-bold text-white mb-4 relative">
                        DANH MỤC
                        <div class="absolute bottom-0 left-0 w-12 h-1 bg-main rounded-full"></div>
                    </h2>
                    <nav>
                        <ul class="space-y-3">
                            <li>
                                <a href="home.php"
                                    class="flex items-center space-x-2 text-gray-400 hover:text-main transition-colors duration-200 group">
                                    <i class="fas fa-home group-hover:translate-x-1 transition-transform duration-200"></i>
                                    <span>Trang Chủ</span>
                                </a>
                            </li>
                            <li>
                                <a href="introduce.php"
                                    class="flex items-center space-x-2 text-gray-400 hover:text-main transition-colors duration-200 group">
                                    <i class="fas fa-info-circle group-hover:translate-x-1 transition-transform duration-200"></i>
                                    <span>Giới Thiệu</span>
                                </a>
                            </li>
                            <li>
                                <a href="posts.php"
                                    class="flex items-center space-x-2 text-gray-400 hover:text-main transition-colors duration-200 group">
                                    <i class="fas fa-blog group-hover:translate-x-1 transition-transform duration-200"></i>
                                    <span>Bài Viết</span>
                                </a>
                            </li>
                            <li>
                                <a href="contact.php"
                                    class="flex items-center space-x-2 text-gray-400 hover:text-main transition-colors duration-200 group">
                                    <i class="fas fa-envelope group-hover:translate-x-1 transition-transform duration-200"></i>
                                    <span>Liên Hệ</span>
                                </a>
                            </li>
                            <li>
                                <a href="all_photos.php"
                                    class="flex items-center space-x-2 text-gray-400 hover:text-main transition-colors duration-200 group">
                                    <i class="fas fa-images group-hover:translate-x-1 transition-transform duration-200"></i>
                                    <span>Hình Ảnh</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>

                <!-- Newsletter Subscription (Optional) -->
                <div class="bg-gray-800 dark:bg-gray-900 p-6 rounded-lg">
                    <h3 class="text-lg font-semibold text-white mb-3">Đăng ký nhận tin</h3>
                    <p class="text-gray-400 text-sm mb-4">Nhận thông báo về bài viết mới</p>
                    <form class="flex">
                        <input type="email" placeholder="Email của bạn..."
                            class="flex-1 px-4 py-2 bg-gray-700 border border-gray-600 rounded-l-lg text-white placeholder-gray-400 focus:outline-none focus:border-main">
                        <button type="submit"
                            class="px-4 py-2 bg-main hover:bg-opacity-90 text-white rounded-r-lg transition-colors">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer Bottom -->
    <div class="border-t border-gray-800 dark:border-gray-700">
        <div class="container-custom py-6">
            <div class="flex flex-col md:flex-row items-center justify-between space-y-4 md:space-y-0">
                <div class="text-center md:text-left">
                    <p class="text-gray-400">
                        &copy; <?= date('Y'); ?> bởi
                        <span class="font-semibold text-main"><?= htmlspecialchars($footer_text); ?></span>.
                        Tất cả quyền được bảo lưu.
                    </p>
                </div>

                <!-- Additional Links -->
                <div class="flex items-center space-x-6 text-sm">
                    <a href="#" class="text-gray-400 hover:text-main transition-colors">Chính sách bảo mật</a>
                    <a href="#" class="text-gray-400 hover:text-main transition-colors">Điều khoản sử dụng</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Scroll to Top Button (moved to layout_footer.php) -->
</footer>

<!-- Additional Footer Scripts -->
<script>
    // Newsletter subscription
    document.querySelector('footer form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const email = this.querySelector('input[type="email"]').value;
        if (email) {
            showMessage('Cảm ơn bạn đã đăng ký nhận tin!', 'success');
            this.reset();
        }
    });

    // Smooth scroll for footer links
    document.querySelectorAll('footer a[href^="#"]').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth'
                });
            }
        });
    });

    // Social media analytics (optional)
    document.querySelectorAll('footer .fab').forEach(icon => {
        icon.closest('a').addEventListener('click', function() {
            // Track social media clicks
            console.log('Social media clicked:', this.href);
        });
    });
</script>
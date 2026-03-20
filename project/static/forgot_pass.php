<?php
include '../components/connect.php';
include '../components/seo_helpers.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../vendor/autoload.php';

$message = '';
$message_type = '';

if (isset($_POST['submit'])) {
    $email = $_POST['email'];
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);

    // Kiểm tra xem email có tồn tại trong cơ sở dữ liệu không
    $select_user = $conn->prepare("SELECT * FROM `users` WHERE email = ?");
    $select_user->execute([$email]);

    if ($select_user->rowCount() > 0) {
        // Tạo mã xác nhận
        $reset_code = bin2hex(random_bytes(16));

        // Lưu mã xác nhận vào cơ sở dữ liệu
        $update_user = $conn->prepare("UPDATE `users` SET reset_code = ? WHERE email = ?");
        $update_user->execute([$reset_code, $email]);

        // Gửi mã xác nhận đến email của người dùng
        $mail = new PHPMailer(true);

        try {
            //Server settings
            $mail->isSMTP();
            $mail->Host = $_ENV['SMTP_HOST'];
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['SMTP_USER']; // Thay thế bằng email của bạn
            $mail->Password = $_ENV['SMTP_PASS']; // Thay thế bằng mật khẩu ứng dụng của bạn
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            // Thiết lập mã hóa ký tự
            $mail->CharSet = 'UTF-8';

            //Recipients
            $mail->setFrom($_ENV['SMTP_USER'], 'blog website');
            $mail->addAddress($email);

            //Content
            $mail->isHTML(true);
            $mail->Subject = "Mã xác nhận đặt lại mật khẩu";
            $mail->Body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f8fafc;'>
                    <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; border-radius: 10px; text-align: center; color: white; margin-bottom: 20px;'>
                        <h1 style='margin: 0; font-size: 24px;'>Đặt lại mật khẩu</h1>
                        <p style='margin: 10px 0 0 0; opacity: 0.9;'>Blog Website</p>
                    </div>
                    
                    <div style='background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);'>
                        <h2 style='color: #2d3748; margin-bottom: 20px;'>Xin chào!</h2>
                        
                        <p style='color: #4a5568; line-height: 1.6; margin-bottom: 20px;'>
                            Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn. 
                            Vui lòng sử dụng mã xác nhận bên dưới để tiếp tục:
                        </p>
                        
                        <div style='background: #edf2f7; padding: 20px; border-radius: 8px; text-align: center; margin: 20px 0;'>
                            <div style='font-size: 24px; font-weight: bold; color: #2d3748; letter-spacing: 2px; font-family: monospace;'>
                                $reset_code
                            </div>
                        </div>
                        
                        <div style='text-align: center; margin: 30px 0;'>
                            <a href='http://localhost/blogging%20website/project/static/reset_pass.php?email=$email&code=$reset_code' 
                               style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: bold; transition: all 0.3s ease;'>
                                Đặt lại mật khẩu ngay
                            </a>
                        </div>
                        
                        <div style='border-top: 1px solid #e2e8f0; padding-top: 20px; margin-top: 30px;'>
                            <p style='color: #718096; font-size: 14px; margin: 0;'>
                                <strong>Lưu ý:</strong> Mã này sẽ hết hiệu lực sau 1 giờ. 
                                Nếu bạn không yêu cầu đặt lại mật khẩu, vui lòng bỏ qua email này.
                            </p>
                        </div>
                    </div>
                    
                    <div style='text-align: center; margin-top: 20px; color: #718096; font-size: 12px;'>
                        <p>© 2024 Blog Website. Tất cả quyền được bảo lưu.</p>
                    </div>
                </div>
            ";

            $mail->send();
            $message = 'Mã xác nhận đã được gửi đến email của bạn. Vui lòng kiểm tra hộp thư đến hoặc thư mục spam.';
            $message_type = 'success';
        } catch (Exception $e) {
            $message = 'Gửi email thất bại. Vui lòng thử lại sau. Lỗi: ' . $mail->ErrorInfo;
            $message_type = 'error';
        }
    } else {
        $message = 'Email không tồn tại trong hệ thống. Vui lòng kiểm tra lại hoặc đăng ký tài khoản mới.';
        $message_type = 'error';
    }
}

$page_title = 'Quên mật khẩu - My Blog';
$page_description = 'Nhập email để nhận mã xác nhận và đặt lại mật khẩu tài khoản My Blog.';
$page_robots = 'noindex,follow,max-image-preview:large';
$page_canonical = canonical_current_url();
$page_og_image = site_url('uploaded_img/logo-removebg.png');
?>

<?php include '../components/layout_header.php'; ?>

<?php if (!empty($message)) : ?>
    <script>
        window.BLOG_FLASH_MESSAGE = <?= json_encode($message, JSON_UNESCAPED_UNICODE); ?>;
        window.BLOG_FLASH_TYPE = <?= json_encode($message_type === 'success' ? 'success' : 'error', JSON_UNESCAPED_UNICODE); ?>;
    </script>
<?php endif; ?>

<main class="min-h-screen bg-gray-50 dark:bg-gray-900 pt-20">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-sm mx-auto auth-card">
            <!-- Header Section -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full mb-4">
                    <i class="fas fa-key text-white text-2xl"></i>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Quên mật khẩu?</h1>
                <p class="text-gray-600 dark:text-gray-400">
                    Đừng lo lắng! Chúng tôi sẽ gửi mã xác nhận đến email của bạn
                </p>
            </div>

            <!-- Form Section -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="p-5">
                    <form action="" method="post" id="forgotPasswordForm" novalidate>
                        <div class="space-y-6">
                            <!-- Email Input -->
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    <i class="fas fa-envelope text-main mr-2"></i>
                                    Email của bạn
                                </label>
                                <div class="relative">
                                    <input type="email"
                                        id="email"
                                        name="email"
                                        required
                                        maxlength="50"
                                        placeholder="Nhập địa chỉ email của bạn"
                                        class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-main focus:border-transparent dark:bg-gray-700 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 transition-all duration-300"
                                        oninput="this.value = this.value.replace(/\s/g, '')">
                                    <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                                        <div id="emailValidIcon" class="hidden">
                                            <i class="fas fa-check text-green-500"></i>
                                        </div>
                                        <div id="emailInvalidIcon" class="hidden">
                                            <i class="fas fa-times text-red-500"></i>
                                        </div>
                                    </div>
                                </div>
                                <div id="emailError" class="mt-1 text-sm text-red-600 dark:text-red-400 hidden"></div>
                                <div id="emailHelp" class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Nhập email mà bạn đã sử dụng để đăng ký tài khoản
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div>
                                <button type="submit"
                                    name="submit"
                                    id="submitBtn"
                                    class="w-full btn-primary relative overflow-hidden">
                                    <span id="submitText" class="flex items-center justify-center">
                                        <i class="fas fa-paper-plane mr-2"></i>
                                        Gửi mã xác nhận
                                    </span>
                                    <span id="loadingText" class="hidden items-center justify-center">
                                        <i class="fas fa-spinner fa-spin mr-2"></i>
                                        Đang gửi...
                                    </span>
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Additional Info -->
                    <div class="mt-6 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800 auth-optional">
                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0">
                                <i class="fas fa-info-circle text-blue-500 mt-0.5"></i>
                            </div>
                            <div class="text-sm text-blue-800 dark:text-blue-200">
                                <h4 class="font-medium mb-1">Lưu ý quan trọng:</h4>
                                <ul class="space-y-1 text-blue-700 dark:text-blue-300">
                                    <li>• Mã xác nhận sẽ được gửi đến email của bạn</li>
                                    <li>• Kiểm tra cả hộp thư đến và thư mục spam</li>
                                    <li>• Mã có hiệu lực trong 1 giờ</li>
                                    <li>• Nếu không nhận được email, hãy thử lại sau 5 phút</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Links -->
                <div class="px-5 py-4 bg-gray-50 dark:bg-gray-700 border-t border-gray-200 dark:border-gray-600">
                    <div class="flex flex-col sm:flex-row items-center justify-between space-y-2 sm:space-y-0">
                        <a href="login.php" class="inline-flex items-center text-sm text-gray-600 dark:text-gray-400 hover:text-main transition-colors">
                            <i class="fas fa-arrow-left mr-2"></i>
                            Quay lại đăng nhập
                        </a>
                        <a href="#" onclick="history.back(); return false;" class="inline-flex items-center text-sm text-gray-600 dark:text-gray-400 hover:text-main transition-colors">
                            <i class="fas fa-clock-rotate-left mr-2"></i>
                            Quay lại trang trước
                        </a>
                        <a href="register.php" class="inline-flex items-center text-sm text-gray-600 dark:text-gray-400 hover:text-main transition-colors">
                            Chưa có tài khoản? Đăng ký
                            <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Security Notice -->
            <div class="mt-4 text-center auth-optional">
                <div class="inline-flex items-center space-x-2 text-sm text-gray-500 dark:text-gray-400">
                    <i class="fas fa-shield-alt text-green-500"></i>
                    <span>Thông tin của bạn được bảo mật tuyệt đối</span>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Enhanced JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('forgotPasswordForm');
        const emailInput = document.getElementById('email');
        const submitBtn = document.getElementById('submitBtn');
        const submitText = document.getElementById('submitText');
        const loadingText = document.getElementById('loadingText');
        const emailValidIcon = document.getElementById('emailValidIcon');
        const emailInvalidIcon = document.getElementById('emailInvalidIcon');
        const emailError = document.getElementById('emailError');
        const emailHelp = document.getElementById('emailHelp');

        // Email validation
        function validateEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }

        // Real-time email validation
        emailInput.addEventListener('input', function() {
            const email = this.value.trim();

            if (email === '') {
                // Reset state
                emailValidIcon.classList.add('hidden');
                emailInvalidIcon.classList.add('hidden');
                emailError.classList.add('hidden');
                emailHelp.classList.remove('hidden');
                this.classList.remove('border-green-500', 'border-red-500');
                this.classList.add('border-gray-300', 'dark:border-gray-600');
            } else if (validateEmail(email)) {
                // Valid email
                emailValidIcon.classList.remove('hidden');
                emailInvalidIcon.classList.add('hidden');
                emailError.classList.add('hidden');
                emailHelp.classList.add('hidden');
                this.classList.remove('border-gray-300', 'dark:border-gray-600', 'border-red-500');
                this.classList.add('border-green-500');
            } else {
                // Invalid email
                emailValidIcon.classList.add('hidden');
                emailInvalidIcon.classList.remove('hidden');
                emailError.textContent = 'Vui lòng nhập địa chỉ email hợp lệ';
                emailError.classList.remove('hidden');
                emailHelp.classList.add('hidden');
                this.classList.remove('border-gray-300', 'dark:border-gray-600', 'border-green-500');
                this.classList.add('border-red-500');
            }
        });

        // Form submission
        form.addEventListener('submit', function(e) {
            const email = emailInput.value.trim();

            if (!validateEmail(email)) {
                e.preventDefault();
                emailInput.focus();

                // Shake animation for invalid input
                emailInput.style.animation = 'shake 0.5s ease-in-out';
                setTimeout(() => {
                    emailInput.style.animation = '';
                }, 500);
                return;
            }

            // Show loading state
            submitBtn.disabled = true;
            submitText.classList.add('hidden');
            submitText.classList.remove('flex');
            loadingText.classList.remove('hidden');
            loadingText.classList.add('flex');

            // Add loading animation to button
            submitBtn.classList.add('opacity-75');
        });

        // Prevent multiple form submissions
        let isSubmitting = false;
        form.addEventListener('submit', function(e) {
            if (isSubmitting) {
                e.preventDefault();
                return;
            }
            isSubmitting = true;
        });
    });

    // Add shake animation for invalid inputs
    const style = document.createElement('style');
    style.textContent = `
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }
`;
    document.head.appendChild(style);
</script>

<?php include '../components/layout_footer.php'; ?>
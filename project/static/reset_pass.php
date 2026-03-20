<?php
include '../components/connect.php';
include '../components/seo_helpers.php';

session_start();
$toastMessage = '';
$toastType = 'info';

// Kiểm tra sự tồn tại của các tham số email và code trong URL
if (!isset($_GET['email']) || !isset($_GET['code'])) {
    $_SESSION['flash_message'] = 'Liên kết không hợp lệ. Vui lòng thử lại.';
    $_SESSION['flash_type'] = 'error';
    header('Location: login.php');
    exit();
}

$email = $_GET['email'];
$reset_code = $_GET['code'];

if (isset($_POST['submit'])) {
    $email = $_POST['email'];
    $reset_code = $_POST['reset_code'];
    $new_pass = sha1($_POST['new_pass']);
    $confirm_pass = sha1($_POST['confirm_pass']);

    // Kiểm tra mã xác nhận
    $select_user = $conn->prepare("SELECT * FROM `users` WHERE email = ? AND reset_code = ?");
    $select_user->execute([$email, $reset_code]);

    if ($select_user->rowCount() > 0) {
        if ($new_pass == $confirm_pass) {
            // Cập nhật mật khẩu mới
            $update_user = $conn->prepare("UPDATE `users` SET password = ?, reset_code = NULL WHERE email = ?");
            $update_user->execute([$new_pass, $email]);
            $_SESSION['flash_message'] = 'Mật khẩu của bạn đã được thay đổi thành công. Vui lòng đăng nhập lại.';
            $_SESSION['flash_type'] = 'success';
            header('Location: login.php');
            exit();
        } else {
            $toastMessage = 'Mật khẩu xác nhận không khớp.';
            $toastType = 'error';
        }
    } else {
        $toastMessage = 'Mã xác nhận không đúng hoặc đã hết hạn.';
        $toastType = 'error';
    }
}

if (isset($_SESSION['flash_message']) && $toastMessage === '') {
    $toastMessage = $_SESSION['flash_message'];
    $toastType = $_SESSION['flash_type'] ?? 'info';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

$page_title = 'Đặt lại mật khẩu - My Blog';
$page_description = 'Đặt lại mật khẩu tài khoản My Blog an toàn qua mã xác nhận email.';
$page_canonical = canonical_current_url();
$page_og_image = site_url('uploaded_img/logo-removebg.png');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="<?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="robots" content="noindex,follow,max-image-preview:large">
    <link rel="canonical" href="<?= htmlspecialchars($page_canonical, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:url" content="<?= htmlspecialchars($page_canonical, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?= htmlspecialchars($page_og_image, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($page_og_image, ENT_QUOTES, 'UTF-8'); ?>">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">

    <!-- Tailwind CSS -->
    <link href="../css/output.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/blog-modern.css">
</head>

<body class="bg-gray-100 dark:bg-gray-900 min-h-screen auth-page">
    <!-- Main Container -->
    <main class="auth-shell" role="main">
        <div class="min-h-screen flex items-center justify-center px-4 sm:px-6 lg:px-8 w-full">
            <div class="max-w-md w-full auth-card">
                <!-- Header -->
                <header class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-orange-500 to-red-600 rounded-full mb-3">
                        <i class="fas fa-key text-white text-2xl"></i>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Đặt lại mật khẩu</h1>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Tạo mật khẩu mới để bảo mật tài khoản</p>
                </header>

                <!-- Reset Form -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-6 border border-gray-200 dark:border-gray-700">
                    <!-- Email Info -->
                    <div class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-info-circle text-blue-600"></i>
                            <span class="text-blue-800 dark:text-blue-200 text-sm">
                                Đặt lại mật khẩu cho: <strong><?= htmlspecialchars($email) ?></strong>
                            </span>
                        </div>
                    </div>

                    <form action="" method="post" class="space-y-6">
                        <!-- Hidden Fields -->
                        <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
                        <input type="hidden" name="reset_code" value="<?= htmlspecialchars($reset_code) ?>">

                        <!-- Reset Code Display -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                <i class="fas fa-shield-alt mr-2 text-main"></i>Mã xác nhận
                            </label>
                            <div class="bg-gray-50 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <span class="font-mono text-lg text-gray-900 dark:text-white tracking-wider">
                                        <?= htmlspecialchars($reset_code) ?>
                                    </span>
                                    <i class="fas fa-check-circle text-green-500"></i>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Mã này đã được xác thực</p>
                            </div>
                        </div>

                        <!-- New Password -->
                        <div>
                            <label for="new_pass" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                <i class="fas fa-lock mr-2 text-main"></i>Mật khẩu mới
                            </label>
                            <div class="relative">
                                <input type="password" name="new_pass" id="new_pass" required
                                    placeholder="Nhập mật khẩu mới"
                                    class="form-input pl-12 pr-12"
                                    maxlength="50"
                                    oninput="this.value = this.value.replace(/\s/g, '')"
                                    onkeyup="checkPasswordStrength(this.value)">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-lock text-gray-400"></i>
                                </div>
                                <button type="button" onclick="togglePassword('new_pass')" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <i id="eyeIcon1" class="fas fa-eye text-gray-400 hover:text-gray-600 transition-colors"></i>
                                </button>
                            </div>

                            <!-- Password Strength Indicator -->
                            <div class="mt-3">
                                <div class="flex space-x-1">
                                    <div id="strength1" class="h-2 w-1/4 bg-gray-200 dark:bg-gray-600 rounded"></div>
                                    <div id="strength2" class="h-2 w-1/4 bg-gray-200 dark:bg-gray-600 rounded"></div>
                                    <div id="strength3" class="h-2 w-1/4 bg-gray-200 dark:bg-gray-600 rounded"></div>
                                    <div id="strength4" class="h-2 w-1/4 bg-gray-200 dark:bg-gray-600 rounded"></div>
                                </div>
                                <p id="strengthText" class="text-xs text-gray-500 dark:text-gray-400 mt-1">Độ mạnh mật khẩu</p>
                            </div>

                            <!-- Password Requirements -->
                            <div class="mt-3 space-y-1">
                                <p class="text-xs text-gray-500 dark:text-gray-400">Mật khẩu nên có:</p>
                                <ul class="text-xs text-gray-500 dark:text-gray-400 space-y-1">
                                    <li id="req-length" class="flex items-center">
                                        <i class="fas fa-times-circle text-red-500 mr-2"></i>
                                        Ít nhất 6 ký tự
                                    </li>
                                    <li id="req-upper" class="flex items-center">
                                        <i class="fas fa-times-circle text-red-500 mr-2"></i>
                                        Có chữ hoa và chữ thường
                                    </li>
                                    <li id="req-number" class="flex items-center">
                                        <i class="fas fa-times-circle text-red-500 mr-2"></i>
                                        Có số
                                    </li>
                                    <li id="req-special" class="flex items-center">
                                        <i class="fas fa-times-circle text-red-500 mr-2"></i>
                                        Có ký tự đặc biệt
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Confirm Password -->
                        <div>
                            <label for="confirm_pass" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                <i class="fas fa-lock mr-2 text-main"></i>Xác nhận mật khẩu mới
                            </label>
                            <div class="relative">
                                <input type="password" name="confirm_pass" id="confirm_pass" required
                                    placeholder="Nhập lại mật khẩu mới"
                                    class="form-input pl-12 pr-12"
                                    maxlength="50"
                                    oninput="this.value = this.value.replace(/\s/g, '')"
                                    onkeyup="checkPasswordMatch()"
                                    onpaste="return false"
                                    ondrop="return false"
                                    autocomplete="new-password">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-lock text-gray-400"></i>
                                </div>
                                <button type="button" onclick="togglePassword('confirm_pass')" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <i id="eyeIcon2" class="fas fa-eye text-gray-400 hover:text-gray-600 transition-colors"></i>
                                </button>
                            </div>
                            <div id="passwordMatch" class="mt-2 text-xs hidden">
                                <span id="matchText"></span>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" name="submit" id="submitBtn"
                            class="w-full py-3 text-base font-semibold bg-main text-white rounded-lg hover:bg-blue-700 transition-all duration-300 flex items-center justify-center space-x-2">
                            <i class="fas fa-key"></i>
                            <span>Đặt lại mật khẩu</span>
                        </button>

                        <!-- Back to Login -->
                        <div class="text-center pt-4">
                            <a href="login.php" class="text-gray-600 dark:text-gray-400 hover:text-main transition-colors text-sm flex items-center justify-center space-x-2">
                                <i class="fas fa-arrow-left"></i>
                                <span>Quay lại đăng nhập</span>
                            </a>
                            <div class="mt-3 flex items-center justify-center gap-4 text-sm">
                                <a href="home.php" class="text-gray-500 dark:text-gray-400 hover:text-main transition-colors">Về trang chủ</a>
                                <a href="#" onclick="history.back(); return false;" class="text-gray-500 dark:text-gray-400 hover:text-main transition-colors">Quay lại trang trước</a>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Security Notice -->
                <div class="mt-6 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 auth-optional">
                    <div class="flex items-start space-x-3">
                        <i class="fas fa-exclamation-triangle text-yellow-600 mt-1"></i>
                        <div>
                            <h3 class="font-semibold text-yellow-800 dark:text-yellow-200 text-sm">Lưu ý bảo mật</h3>
                            <ul class="text-yellow-700 dark:text-yellow-300 text-xs mt-1 space-y-1">
                                <li>• Không chia sẻ mật khẩu với bất kỳ ai</li>
                                <li>• Sử dụng mật khẩu mạnh và duy nhất</li>
                                <li>• Đăng xuất khỏi thiết bị công cộng</li>
                                <li>• Thay đổi mật khẩu định kỳ</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Help Section -->
                <div class="mt-6 text-center auth-optional">
                    <p class="text-gray-500 dark:text-gray-400 text-sm">
                        Cần hỗ trợ?
                        <a href="contact.php" class="text-main hover:text-blue-700 font-medium transition-colors">
                            Liên hệ với chúng tôi
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </main>

    <!-- Dark Mode Toggle -->
    <button onclick="toggleDarkMode()"
        class="fixed bottom-6 right-6 w-12 h-12 bg-main text-white rounded-full shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-110">
        <i class="fas fa-moon dark:hidden"></i>
        <i class="fas fa-sun hidden dark:block"></i>
    </button>

    <!-- Enhanced JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toastMessage = <?= json_encode($toastMessage, JSON_UNESCAPED_UNICODE); ?>;
            const toastType = <?= json_encode($toastType, JSON_UNESCAPED_UNICODE); ?>;

            function showNotification(message, type = 'info') {
                const colors = {
                    success: '#16a34a',
                    error: '#dc2626',
                    warning: '#d97706',
                    info: '#2563eb'
                };

                if (typeof Toastify !== 'undefined') {
                    Toastify({
                        text: message,
                        duration: 4200,
                        gravity: 'top',
                        position: 'right',
                        close: true,
                        stopOnFocus: true,
                        style: {
                            background: colors[type] || colors.info
                        }
                    }).showToast();
                    return;
                }

                alert(message);
            }

            if (toastMessage) {
                showNotification(toastMessage, toastType || 'info');
            }

            // Dark mode functionality
            function initDarkMode() {
                const isDark = localStorage.getItem('darkMode') === 'true';
                if (isDark) {
                    document.documentElement.classList.add('dark');
                }
            }

            window.toggleDarkMode = function() {
                document.documentElement.classList.toggle('dark');
                localStorage.setItem('darkMode', document.documentElement.classList.contains('dark'));
            }

            // Form validation
            const form = document.querySelector('form');
            const newPasswordInput = document.getElementById('new_pass');
            const confirmPasswordInput = document.getElementById('confirm_pass');
            const submitBtn = document.getElementById('submitBtn');

            // Form submission with loading state
            form.addEventListener('submit', function(e) {
                const password = newPasswordInput.value;
                const confirmPassword = confirmPasswordInput.value;

                if (password !== confirmPassword) {
                    e.preventDefault();
                    showNotification('Mật khẩu xác nhận không khớp!', 'error');
                    return;
                }

                if (password.length < 6) {
                    e.preventDefault();
                    showNotification('Mật khẩu phải có ít nhất 6 ký tự!', 'warning');
                    return;
                }

                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Đang xử lý...';
                submitBtn.disabled = true;

                const controls = this.querySelectorAll('input:not([type="hidden"]), textarea, select, button');
                controls.forEach((control) => {
                    control.disabled = true;
                });
            });

            initDarkMode();
        });

        // Password toggle function
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const eyeIcon = fieldId === 'new_pass' ? document.getElementById('eyeIcon1') : document.getElementById('eyeIcon2');

            if (field.type === 'password') {
                field.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        }

        // Password strength checker
        function checkPasswordStrength(password) {
            const strengthBars = ['strength1', 'strength2', 'strength3', 'strength4'];
            const strengthText = document.getElementById('strengthText');
            const requirements = {
                length: document.getElementById('req-length'),
                upper: document.getElementById('req-upper'),
                number: document.getElementById('req-number'),
                special: document.getElementById('req-special')
            };

            // Reset bars
            strengthBars.forEach(bar => {
                document.getElementById(bar).className = 'h-2 w-1/4 bg-gray-200 dark:bg-gray-600 rounded';
            });

            let strength = 0;
            let text = 'Rất yếu';

            // Check requirements
            const hasLength = password.length >= 6;
            const hasUpper = password.match(/[a-z]/) && password.match(/[A-Z]/);
            const hasNumber = password.match(/\d/);
            const hasSpecial = password.match(/[^a-zA-Z\d]/);

            // Update requirement indicators
            updateRequirement(requirements.length, hasLength);
            updateRequirement(requirements.upper, hasUpper);
            updateRequirement(requirements.number, hasNumber);
            updateRequirement(requirements.special, hasSpecial);

            if (hasLength) strength++;
            if (hasUpper) strength++;
            if (hasNumber) strength++;
            if (hasSpecial) strength++;

            if (strength >= 1) {
                document.getElementById('strength1').className = 'h-2 w-1/4 bg-red-500 rounded';
                text = 'Yếu';
            }
            if (strength >= 2) {
                document.getElementById('strength2').className = 'h-2 w-1/4 bg-yellow-500 rounded';
                text = 'Trung bình';
            }
            if (strength >= 3) {
                document.getElementById('strength3').className = 'h-2 w-1/4 bg-blue-500 rounded';
                text = 'Mạnh';
            }
            if (strength >= 4) {
                document.getElementById('strength4').className = 'h-2 w-1/4 bg-green-500 rounded';
                text = 'Rất mạnh';
            }

            strengthText.textContent = text;
        }

        function updateRequirement(element, isValid) {
            const icon = element.querySelector('i');
            if (isValid) {
                icon.className = 'fas fa-check-circle text-green-500 mr-2';
                element.className = 'flex items-center text-green-600';
            } else {
                icon.className = 'fas fa-times-circle text-red-500 mr-2';
                element.className = 'flex items-center text-gray-500 dark:text-gray-400';
            }
        }

        // Password match checker
        function checkPasswordMatch() {
            const password = document.getElementById('new_pass').value;
            const confirmPassword = document.getElementById('confirm_pass').value;
            const matchDiv = document.getElementById('passwordMatch');
            const matchText = document.getElementById('matchText');

            if (confirmPassword.length > 0) {
                matchDiv.classList.remove('hidden');
                if (password === confirmPassword) {
                    matchText.textContent = '✓ Mật khẩu khớp';
                    matchText.className = 'text-green-600';
                } else {
                    matchText.textContent = '✗ Mật khẩu không khớp';
                    matchText.className = 'text-red-600';
                }
            } else {
                matchDiv.classList.add('hidden');
            }
        }
    </script>
</body>

</html>
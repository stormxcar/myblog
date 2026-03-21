<?php

include '../components/connect.php';
include '../components/seo_helpers.php';

session_start();
$toastMessage = '';
$toastType = 'info';
$submittedIdentifier = '';

if (!function_exists('redirect_with_fallback')) {
    function redirect_with_fallback($url)
    {
        header('Location: ' . $url);
        echo '<script>window.location.href=' . json_encode($url, JSON_UNESCAPED_UNICODE) . ';</script>';
        exit;
    }
}

if (!function_exists('blog_password_matches')) {
    function blog_password_matches($rawPassword, $storedPassword)
    {
        $stored = (string)$storedPassword;
        if ($stored === '') {
            return false;
        }

        $sha1Pass = sha1($rawPassword);
        if (hash_equals($stored, $sha1Pass) || hash_equals($stored, (string)$rawPassword)) {
            return true;
        }

        // Support migrated accounts that already use password_hash.
        if (strlen($stored) >= 60 && password_verify((string)$rawPassword, $stored)) {
            return true;
        }

        return false;
    }
}

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} else {
    $user_id = '';
};

$rememberedEmail = isset($_COOKIE['remembered_email']) ? filter_var($_COOKIE['remembered_email'], FILTER_SANITIZE_EMAIL) : '';
$nextTarget = trim((string)($_GET['next'] ?? $_POST['next'] ?? ''));
$nextTarget = $nextTarget === 'admin' ? 'admin' : '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $identifier = trim((string)($_POST['email'] ?? ''));
    $identifier = filter_var($identifier, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $submittedIdentifier = $identifier;
    $rawPass = (string)($_POST['pass'] ?? '');

    if ($identifier === '' || $rawPass === '') {
        $toastMessage = 'Vui lòng nhập đầy đủ tài khoản và mật khẩu.';
        $toastType = 'error';
    } else {

        try {
            $selectColumns = 'id, name, email, password';
            if (blog_has_admin_role_column($conn)) {
                $selectColumns .= ', role';
            }
            if (blog_db_has_column($conn, 'users', 'banned')) {
                $selectColumns .= ', banned';
            }
            if (blog_has_legacy_admin_id_column($conn)) {
                $selectColumns .= ', legacy_admin_id';
            }

            $select_user = $conn->prepare("SELECT {$selectColumns} FROM `users` WHERE (email = ? OR name = ?) ORDER BY id DESC LIMIT 30");
            $select_user->execute([$identifier, $identifier]);

            $row = null;
            while ($candidate = $select_user->fetch(PDO::FETCH_ASSOC)) {
                if (blog_password_matches($rawPass, $candidate['password'] ?? '')) {
                    $row = $candidate;
                    break;
                }
            }

            if ($row) {
                $isBanned = isset($row['banned']) && (int)$row['banned'] === 1;
                if ($isBanned) {
                    $toastMessage = 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên để biết thêm chi tiết.';
                    $toastType = 'error';
                } else {
                    // Normalize legacy plaintext passwords to SHA1 after a successful login.
                    $currentStoredPass = (string)($row['password'] ?? '');
                    $sha1Pass = sha1($rawPass);
                    if ($currentStoredPass !== '' && $currentStoredPass !== $sha1Pass && strlen($currentStoredPass) < 60) {
                        $upgradeStmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ? LIMIT 1');
                        $upgradeStmt->execute([$sha1Pass, (int)$row['id']]);
                    }

                    $rememberMe = isset($_POST['remember_me']);
                    if ($rememberMe) {
                        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
                        setcookie('remembered_email', $identifier, [
                            'expires' => time() + (60 * 60 * 24 * 30),
                            'path' => '/',
                            'secure' => $isSecure,
                            'httponly' => true,
                            'samesite' => 'Lax'
                        ]);
                    } else {
                        setcookie('remembered_email', '', [
                            'expires' => time() - 3600,
                            'path' => '/',
                            'secure' => false,
                            'httponly' => true,
                            'samesite' => 'Lax'
                        ]);
                    }

                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $row['id'];

                    $roleRaw = $row['role'] ?? '';
                    $role = strtolower(trim((string)$roleRaw));
                    $isAdminRole = ($role === 'admin');
                    if (!$isAdminRole && isset($row['legacy_admin_id']) && (int)$row['legacy_admin_id'] > 0) {
                        $isAdminRole = true;
                    }

                    if ($nextTarget === 'admin' && !$isAdminRole) {
                        unset($_SESSION['admin_id']);
                        $_SESSION['flash_message'] = 'Tài khoản này không có quyền quản trị.';
                        $_SESSION['flash_type'] = 'error';
                        redirect_with_fallback('login.php?next=admin');
                    }

                    if ($isAdminRole) {
                        $adminIdentity = !empty($row['legacy_admin_id']) ? (int)$row['legacy_admin_id'] : (int)$row['id'];
                        $_SESSION['admin_id'] = $adminIdentity;
                        $_SESSION['flash_message'] = 'Đăng nhập quản trị thành công';
                        $_SESSION['flash_type'] = 'success';
                        redirect_with_fallback('../admin/dashboard.php');
                    }

                    unset($_SESSION['admin_id']);
                    $_SESSION['flash_message'] = 'Đăng nhập thành công';
                    $_SESSION['flash_type'] = 'success';
                    redirect_with_fallback('home.php?message=' . urlencode('Đăng nhập thành công'));
                }
            } else {
                $toastMessage = 'Tên đăng nhập hoặc mật khẩu không đúng! Vui lòng thử lại.';
                $toastType = 'error';
            }
        } catch (Exception $e) {
            $toastMessage = 'Có lỗi đăng nhập. Vui lòng thử lại sau.';
            $toastType = 'error';
        }
    }
}

if (isset($_SESSION['admin_id']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $_SESSION['flash_message'] = 'Bạn đã đăng nhập với quyền quản trị.';
    $_SESSION['flash_type'] = 'info';
    redirect_with_fallback('../admin/dashboard.php');
}

if (!empty($user_id) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $_SESSION['flash_message'] = 'Bạn đã đăng nhập.';
    $_SESSION['flash_type'] = 'info';
    redirect_with_fallback('home.php?message=' . urlencode('Bạn đã đăng nhập'));
}

if (isset($_SESSION['flash_message']) && $toastMessage === '') {
    $toastMessage = $_SESSION['flash_message'];
    $toastType = $_SESSION['flash_type'] ?? 'info';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

$page_title = 'Đăng nhập - My Blog';
$page_description = 'Đăng nhập My Blog để đọc bài viết, lưu bài yêu thích và tham gia bình luận.';
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
    <link rel="stylesheet" href="../css/gooey-toast.css">

    <!-- Tailwind CSS -->
    <link href="../css/output.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/blog-modern.css">
</head>

<body class="bg-gray-100 dark:bg-gray-900 min-h-screen auth-page">
    <main class="auth-shell" role="main">
        <!-- Main Container -->
        <section class="min-h-screen flex w-full items-center justify-center" aria-labelledby="login-title">
            <!-- Left Side - Form -->
            <div class="flex-1 flex items-center justify-center px-4 sm:px-6 lg:px-8">
                <div class="max-w-md w-full auth-card">
                    <!-- Logo/Header -->
                    <header class="text-center mb-6">
                        <div class="inline-flex items-center justify-center w-16 h-16 bg-main rounded-full mb-3">
                            <i class="fas fa-blog text-white text-2xl"></i>
                        </div>
                        <h1 id="login-title" class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Chào mừng trở lại</h1>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Đăng nhập để tiếp tục đọc và tương tác</p>
                    </header>

                    <!-- Login Form -->
                    <form action="" method="post" class="space-y-6">
                        <!-- Identifier Field -->
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                <i class="fas fa-user mr-2 text-main"></i>Tài khoản (email hoặc username)
                            </label>
                            <div class="relative">
                                <input type="text" name="email" id="email" required
                                    placeholder="Nhập email hoặc username"
                                    class="form-input"
                                    maxlength="80"
                                    oninput="this.value = this.value.replace(/\s/g, '')"
                                    value="<?= htmlspecialchars($submittedIdentifier !== '' ? $submittedIdentifier : $rememberedEmail, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="next" value="<?= htmlspecialchars($nextTarget, ENT_QUOTES, 'UTF-8'); ?>">

                            </div>
                        </div>

                        <!-- Password Field -->
                        <div>
                            <label for="pass" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                <i class="fas fa-lock mr-2 text-main"></i>Mật khẩu
                            </label>
                            <div class="relative">
                                <input type="password" name="pass" id="pass" required
                                    placeholder="Nhập mật khẩu"
                                    class="form-input pr-12"
                                    maxlength="50"
                                    oninput="this.value = this.value.replace(/\s/g, '')"
                                    onpaste="return false"
                                    ondrop="return false"
                                    value="">

                                <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <i id="eyeIcon" class="fas fa-eye text-gray-400 hover:text-gray-600 transition-colors"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Forgot Password -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <input id="remember-me" name="remember_me" type="checkbox" <?= $rememberedEmail !== '' ? 'checked' : ''; ?>
                                    class="h-4 w-4 text-main focus:ring-main border-gray-300 rounded">
                                <label for="remember-me" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                    Ghi nhớ đăng nhập
                                </label>
                            </div>
                            <a href="forgot_pass.php" class="text-sm text-main hover:text-blue-700 font-medium transition-colors">
                                Quên mật khẩu?
                            </a>
                        </div>

                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Bằng việc đăng nhập, bạn đồng ý với
                            <button type="button" id="openTerms" class="text-main hover:text-blue-700">Điều khoản dịch vụ</button>
                            và
                            <button type="button" id="openPrivacy" class="text-main hover:text-blue-700">Chính sách bảo mật</button>.
                        </p>

                        <!-- Submit Button -->
                        <button type="submit" name="submit" class="w-full btn-primary py-3 text-base font-semibold">
                            <i class="fas fa-sign-in-alt mr-2"></i>
                            Đăng nhập
                        </button>

                        <!-- Divider -->
                        <div class="relative my-6 auth-optional">
                            <div class="absolute inset-0 flex items-center">
                                <div class="w-full border-t border-gray-300 dark:border-gray-600"></div>
                            </div>
                            <div class="relative flex justify-center text-sm">
                                <span class="px-2 bg-gray-100 dark:bg-gray-900 text-gray-500 dark:text-gray-400">hoặc</span>
                            </div>
                        </div>

                        <!-- Social Login (placeholder) -->
                        <div class="space-y-3 auth-optional">
                            <button type="button" class="w-full flex items-center justify-center px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-all duration-300 shadow-lg hover:shadow-xl">
                                <i class="fab fa-google text-red-500 mr-3 text-lg"></i>
                                Đăng nhập với Google
                            </button>

                            <button type="button" class="w-full flex items-center justify-center px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-all duration-300 shadow-lg hover:shadow-xl">
                                <i class="fab fa-facebook text-blue-600 mr-3 text-lg"></i>
                                Đăng nhập với Facebook
                            </button>
                        </div>

                        <!-- Register Link -->
                        <div class="text-center pt-4">
                            <p class="text-gray-600 dark:text-gray-400">
                                Bạn chưa có tài khoản?
                                <a href="register.php" class="text-main hover:text-blue-700 font-semibold transition-colors">
                                    Đăng ký ngay
                                </a>
                            </p>
                            <div class="mt-3 flex items-center justify-center gap-4 text-sm">
                                <a href="home.php" class="text-gray-500 dark:text-gray-400 hover:text-main transition-colors">Về trang chủ</a>
                                <a href="#" onclick="history.back(); return false;" class="text-gray-500 dark:text-gray-400 hover:text-main transition-colors">Quay lại trang trước</a>
                            </div>
                        </div>
                    </form>

                    <!-- Features -->
                    <div class="mt-8 grid grid-cols-3 gap-4 text-center auth-optional">
                        <div class="p-3">
                            <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900/20 rounded-lg flex items-center justify-center mx-auto mb-2">
                                <i class="fas fa-shield-alt text-blue-600"></i>
                            </div>
                            <p class="text-xs text-gray-600 dark:text-gray-400">Bảo mật cao</p>
                        </div>
                        <div class="p-3">
                            <div class="w-10 h-10 bg-green-100 dark:bg-green-900/20 rounded-lg flex items-center justify-center mx-auto mb-2">
                                <i class="fas fa-rocket text-green-600"></i>
                            </div>
                            <p class="text-xs text-gray-600 dark:text-gray-400">Trải nghiệm nhanh</p>
                        </div>
                        <div class="p-3">
                            <div class="w-10 h-10 bg-purple-100 dark:bg-purple-900/20 rounded-lg flex items-center justify-center mx-auto mb-2">
                                <i class="fas fa-users text-purple-600"></i>
                            </div>
                            <p class="text-xs text-gray-600 dark:text-gray-400">Cộng đồng</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side - Image/Background -->
            <div class="hidden lg:block relative flex-1 auth-hero">
                <div class="absolute inset-0 bg-gradient-to-br from-main to-blue-600"></div>
                <div class="absolute inset-0 bg-black bg-opacity-20"></div>
                <img src="../uploaded_img/banner-2.avif"
                    alt="Login Background"
                    class="w-full h-full object-cover">

                <!-- Overlay Content -->
                <div class="absolute inset-0 flex items-center justify-center">
                    <div class="text-center text-white p-8">
                        <h2 class="text-4xl font-bold mb-4">Khám phá thế giới blog</h2>
                        <p class="text-xl mb-8 opacity-90">Tham gia cộng đồng chia sẻ kiến thức và trải nghiệm</p>

                        <!-- Stats -->
                        <div class="grid grid-cols-3 gap-8 mt-12">
                            <div class="text-center">
                                <div class="text-3xl font-bold">1000+</div>
                                <div class="text-sm opacity-75">Bài viết</div>
                            </div>
                            <div class="text-center">
                                <div class="text-3xl font-bold">500+</div>
                                <div class="text-sm opacity-75">Thành viên</div>
                            </div>
                            <div class="text-center">
                                <div class="text-3xl font-bold">50+</div>
                                <div class="text-sm opacity-75">Chuyên đề</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Floating Elements -->
                <div class="absolute top-20 left-20 w-20 h-20 bg-white bg-opacity-20 rounded-full animate-bounce-custom"></div>
                <div class="absolute bottom-20 right-20 w-16 h-16 bg-white bg-opacity-20 rounded-full animate-bounce-custom delay-200"></div>
                <div class="absolute top-1/2 left-10 w-12 h-12 bg-white bg-opacity-20 rounded-full animate-bounce-custom delay-500"></div>
            </div>
        </section>
    </main>

    <!-- Dark Mode Toggle -->
    <button onclick="toggleDarkMode()"
        class="fixed bottom-6 right-6 w-12 h-12 bg-main text-white rounded-full shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-110">
        <i class="fas fa-moon dark:hidden"></i>
        <i class="fas fa-sun hidden dark:block"></i>
    </button>

    <div id="policyModal" class="fixed inset-0 bg-black/60 z-50 hidden items-center justify-center px-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl w-full max-w-2xl max-h-[85vh] overflow-hidden shadow-2xl">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 id="policyModalTitle" class="text-lg font-bold text-gray-900 dark:text-white">Điều khoản dịch vụ</h2>
                <button type="button" id="closePolicyModal" class="text-gray-500 hover:text-gray-800 dark:hover:text-gray-200">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="policyModalContent" class="px-5 py-4 text-sm leading-6 text-gray-700 dark:text-gray-300 overflow-y-auto max-h-[65vh]"></div>
        </div>
    </div>

    <!-- Enhanced JavaScript -->
    <script src="../js/gooey-toast.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toastMessage = <?= json_encode($toastMessage, JSON_UNESCAPED_UNICODE); ?>;
            const toastType = <?= json_encode($toastType, JSON_UNESCAPED_UNICODE); ?>;

            if (toastMessage) {
                const colors = {
                    success: '#16a34a',
                    error: '#dc2626',
                    warning: '#d97706',
                    info: '#2563eb'
                };
                Toastify({
                    text: toastMessage,
                    duration: 4000,
                    gravity: 'top',
                    position: 'right',
                    close: true,
                    stopOnFocus: true,
                    style: {
                        background: colors[toastType] || colors.info
                    }
                }).showToast();
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

            // Password toggle
            window.togglePassword = function() {
                const passwordInput = document.getElementById('pass');
                const eyeIcon = document.getElementById('eyeIcon');

                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    eyeIcon.classList.remove('fa-eye');
                    eyeIcon.classList.add('fa-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    eyeIcon.classList.remove('fa-eye-slash');
                    eyeIcon.classList.add('fa-eye');
                }
            }

            // Form validation
            const form = document.querySelector('form');
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('pass');

            function showError(input, message) {
                const errorDiv = input.parentNode.querySelector('.error-message');
                if (errorDiv) errorDiv.remove();

                const error = document.createElement('div');
                error.className = 'error-message text-red-500 text-sm mt-1';
                error.textContent = message;
                input.parentNode.appendChild(error);
                input.classList.add('border-red-500');
            }

            function clearError(input) {
                const errorDiv = input.parentNode.querySelector('.error-message');
                if (errorDiv) errorDiv.remove();
                input.classList.remove('border-red-500');
            }

            emailInput.addEventListener('input', function() {
                clearError(this);
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (this.value && !emailRegex.test(this.value)) {
                    showError(this, 'Email không hợp lệ');
                }
            });

            passwordInput.addEventListener('input', function() {
                clearError(this);
                if (this.value && this.value.length < 3) {
                    showError(this, 'Mật khẩu phải có ít nhất 3 ký tự');
                }
            });

            passwordInput.addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'v') {
                    e.preventDefault();
                }
            });

            // Form submission with loading state
            form.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');

                // Basic validation
                if (!emailInput.value || !passwordInput.value) {
                    e.preventDefault();
                    if (!emailInput.value) showError(emailInput, 'Vui lòng nhập email');
                    if (!passwordInput.value) showError(passwordInput, 'Vui lòng nhập mật khẩu');
                    return;
                }

                // Show loading state
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Đang đăng nhập...';
                submitBtn.disabled = true;
            });

            // Input focus effects
            const inputs = document.querySelectorAll('input[type="text"], input[type="email"], input[type="password"]');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentNode.classList.add('ring-2', 'ring-main', 'ring-opacity-50');
                });

                input.addEventListener('blur', function() {
                    this.parentNode.classList.remove('ring-2', 'ring-main', 'ring-opacity-50');
                });
            });

            // Initialize functions
            initDarkMode();

            const modal = document.getElementById('policyModal');
            const modalTitle = document.getElementById('policyModalTitle');
            const modalContent = document.getElementById('policyModalContent');

            const termsText = `
                <p><strong>Điều khoản dịch vụ</strong></p>
                <p>Bạn chịu trách nhiệm về nội dung đăng tải và bình luận trên hệ thống.</p>
                <p>Không đăng nội dung vi phạm pháp luật, spam hoặc xâm phạm quyền của người khác.</p>
                <p>Hệ thống có quyền tạm khóa tài khoản nếu phát hiện hành vi bất thường hoặc vi phạm chính sách.</p>
            `;

            const privacyText = `
                <p><strong>Chính sách bảo mật</strong></p>
                <p>Chúng tôi chỉ thu thập dữ liệu cần thiết để cung cấp dịch vụ đăng nhập và cá nhân hóa trải nghiệm.</p>
                <p>Mật khẩu được lưu ở dạng mã hóa, không lưu dạng văn bản thuần.</p>
                <p>Cookie "ghi nhớ đăng nhập" chỉ lưu email để tiện nhập lại, không lưu mật khẩu.</p>
            `;

            function openPolicyModal(title, content) {
                modalTitle.textContent = title;
                modalContent.innerHTML = content;
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }

            function closePolicyModal() {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }

            document.getElementById('openTerms')?.addEventListener('click', () => openPolicyModal('Điều khoản dịch vụ', termsText));
            document.getElementById('openPrivacy')?.addEventListener('click', () => openPolicyModal('Chính sách bảo mật', privacyText));
            document.getElementById('closePolicyModal')?.addEventListener('click', closePolicyModal);
            modal?.addEventListener('click', (e) => {
                if (e.target === modal) closePolicyModal();
            });

            // Animate form on load
            const formElements = document.querySelectorAll('.space-y-6 > *');
            formElements.forEach((element, index) => {
                element.style.opacity = '0';
                element.style.transform = 'translateY(20px)';

                setTimeout(() => {
                    element.style.transition = 'all 0.6s ease';
                    element.style.opacity = '1';
                    element.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>

</html>
<?php

include '../components/connect.php';
include '../components/seo_helpers.php';
include '../components/security_helpers.php';

session_start();
blog_security_ensure_tables($conn);
$toastMessage = '';
$toastType = 'info';
$registerCaptchaRequired = false;
$registerChallengeProvider = '';
$registerChallengeSiteKey = '';

if (!function_exists('redirect_with_fallback')) {
    function redirect_with_fallback($url)
    {
        header('Location: ' . $url);
        echo '<script>window.location.href=' . json_encode($url, JSON_UNESCAPED_UNICODE) . ';</script>';
        exit;
    }
}

if (!function_exists('blog_is_gmail_address')) {
    function blog_is_gmail_address($email)
    {
        $email = trim((string)$email);
        if ($email === '') {
            return false;
        }

        return (bool)preg_match('/^[A-Z0-9._%+-]+@gmail\.com$/i', $email);
    }
}

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} else {
    $user_id = '';
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $name = trim(strip_tags((string)($_POST['name'] ?? '')));
    $email = trim((string)($_POST['email'] ?? ''));
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    $captchaIdentifier = $email !== '' ? $email : 'anonymous';
    $registerCaptchaRequired = blog_captcha_should_show($conn, 'register', $captchaIdentifier, 3, 900);
    $registerChallengeProvider = blog_human_challenge_provider();
    $registerChallengeSiteKey = blog_human_challenge_site_key();
    $rawPass = (string)($_POST['pass'] ?? '');
    $rawCpass = (string)($_POST['cpass'] ?? '');

    if (!blog_csrf_validate('register_form', $_POST['_csrf_token'] ?? '')) {
        $toastMessage = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang và thử lại.';
        $toastType = 'error';
    } else {
        $limitState = blog_rate_limit_state($conn, 'register', $captchaIdentifier, 8, 900, 900);
        if (!empty($limitState['blocked'])) {
            $retryAfter = max(1, (int)($limitState['retry_after'] ?? 0));
            $minutes = (int)ceil($retryAfter / 60);
            $toastMessage = 'Bạn đang thử đăng ký quá nhiều lần. Vui lòng thử lại sau khoảng ' . $minutes . ' phút.';
            $toastType = 'error';
        } elseif ($registerCaptchaRequired && ($registerChallengeProvider === '' || $registerChallengeSiteKey === '')) {
            blog_rate_limit_record_failure($conn, 'register', $captchaIdentifier, 8, 900, 900);
            $toastMessage = 'Hệ thống xác thực bot chưa được cấu hình. Vui lòng liên hệ quản trị viên.';
            $toastType = 'error';
        } elseif ($registerCaptchaRequired && !blog_human_challenge_verify($_POST[blog_human_challenge_token_field()] ?? '')) {
            blog_rate_limit_record_failure($conn, 'register', $captchaIdentifier, 8, 900, 900);
            $toastMessage = 'Vui lòng xác thực CAPTCHA hợp lệ.';
            $toastType = 'error';
        } elseif ($name === '' || mb_strlen($name, 'UTF-8') < 2) {
            $toastMessage = 'Ten nguoi dung toi thieu 2 ky tu.';
            $toastType = 'error';
            blog_rate_limit_record_failure($conn, 'register', $captchaIdentifier, 8, 900, 900);
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $toastMessage = 'Email khong hop le.';
            $toastType = 'error';
            blog_rate_limit_record_failure($conn, 'register', $captchaIdentifier, 8, 900, 900);
        } elseif (!blog_is_gmail_address($email)) {
            $toastMessage = 'He thong hien chi ho tro dang ky bang email @gmail.com.';
            $toastType = 'error';
            blog_rate_limit_record_failure($conn, 'register', $captchaIdentifier, 8, 900, 900);
        } elseif ($rawPass === '' || strlen($rawPass) < 6) {
            $toastMessage = 'Mat khau toi thieu 6 ky tu.';
            $toastType = 'error';
            blog_rate_limit_record_failure($conn, 'register', $captchaIdentifier, 8, 900, 900);
        } elseif (!hash_equals($rawPass, $rawCpass)) {
            $toastMessage = 'Mat khau nhap lai khong khop!';
            $toastType = 'error';
            blog_rate_limit_record_failure($conn, 'register', $captchaIdentifier, 8, 900, 900);
        } else {
            $checkUser = $conn->prepare("SELECT id FROM `users` WHERE email = ? OR name = ? LIMIT 1");
            $checkUser->execute([$email, $name]);
            if ($checkUser->fetch(PDO::FETCH_ASSOC)) {
                $toastMessage = 'Email hoac ten nguoi dung da ton tai!';
                $toastType = 'error';
                blog_rate_limit_record_failure($conn, 'register', $captchaIdentifier, 8, 900, 900);
            } else {
                $avatar = null;
                if (isset($_FILES['avatar']) && (int)($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    $uploadResult = blog_cloudinary_upload($_FILES['avatar'], blog_cloudinary_default_folder() . '/avatars');
                    if (!($uploadResult['ok'] ?? false)) {
                        $toastMessage = (string)($uploadResult['error'] ?? 'Không thể upload ảnh đại diện.');
                        $toastType = 'error';
                        blog_rate_limit_record_failure($conn, 'register', $captchaIdentifier, 8, 900, 900);
                    } else {
                        $avatar = (string)$uploadResult['secure_url'];
                    }
                }

                if ($toastType === 'error' && $toastMessage !== '') {
                    // Avatar upload failed, keep validation error and stop creating account.
                } else {
                    $passHash = password_hash($rawPass, PASSWORD_DEFAULT);
                    $insertColumns = ['name', 'email', 'password', 'avatar'];
                    $insertValues = [$name, $email, $passHash, $avatar];
                    if (blog_db_has_column($conn, 'users', 'is_verified')) {
                        $insertColumns[] = 'is_verified';
                        $insertValues[] = 0;
                    }
                    $insertSql = 'INSERT INTO `users` (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', array_fill(0, count($insertColumns), '?')) . ')';
                    $insertUser = $conn->prepare($insertSql);
                    if ($insertUser->execute($insertValues)) {
                        blog_rate_limit_record_success($conn, 'register', $captchaIdentifier);
                        $newUserId = (int)$conn->lastInsertId();

                        $verifyResult = blog_issue_and_send_verification($conn, $newUserId, $email, $name);
                        if (!empty($verifyResult['ok'])) {
                            $_SESSION['flash_message'] = 'Dang ky thanh cong. Vui long kiem tra email de xac minh tai khoan.';
                            $_SESSION['flash_type'] = 'success';
                        } else {
                            $_SESSION['flash_message'] = 'Dang ky thanh cong. Khong gui duoc email xac minh, vui long yeu cau gui lai.';
                            $_SESSION['flash_type'] = 'warning';
                        }

                        redirect_with_fallback('resend_verification.php?email=' . rawurlencode($email));
                    } else {
                        $toastMessage = 'Co loi xay ra khi dang ky tai khoan!';
                        $toastType = 'error';
                        blog_rate_limit_record_failure($conn, 'register', $captchaIdentifier, 8, 900, 900);
                    }
                }
            }
        }
    }
}

if (!$registerCaptchaRequired) {
    $registerEmailProbe = trim((string)($_POST['email'] ?? ''));
    $registerIdentifierProbe = $registerEmailProbe !== '' ? $registerEmailProbe : 'anonymous';
    $registerCaptchaRequired = blog_captcha_should_show($conn, 'register', $registerIdentifierProbe, 3, 900);
}
$registerChallengeProvider = blog_human_challenge_provider();
$registerChallengeSiteKey = blog_human_challenge_site_key();

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

$page_title = 'Đăng ký - My Blog';
$page_description = 'Tạo tài khoản My Blog để đăng bài, bình luận và lưu bài viết yêu thích.';
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
    <!-- Main Container -->
    <main class="auth-shell" role="main">
        <div class="min-h-screen flex w-full items-center justify-center">
            <!-- Left Side - Image/Background -->
            <div class="hidden lg:block relative flex-1 auth-hero">
                <div class="absolute inset-0 bg-gradient-to-br from-green-500 to-blue-600"></div>
                <div class="absolute inset-0 bg-black bg-opacity-20"></div>
                <img src="../uploaded_img/banner-4.avif"
                    alt="Register Background"
                    class="w-full h-full object-cover">

                <!-- Overlay Content -->
                <div class="absolute inset-0 flex items-center justify-center">
                    <div class="text-center text-white p-8">
                        <h2 class="text-4xl font-bold mb-4">Tham gia cộng đồng</h2>
                        <p class="text-xl mb-8 opacity-90">Bắt đầu hành trình chia sẻ và khám phá kiến thức</p>

                        <!-- Benefits -->
                        <div class="space-y-6 mt-12">
                            <div class="flex items-center space-x-4">
                                <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                                    <i class="fas fa-pen-fancy text-xl"></i>
                                </div>
                                <div class="text-left">
                                    <h3 class="font-semibold">Viết bài và chia sẻ</h3>
                                    <p class="text-sm opacity-75">Chia sẻ kiến thức và kinh nghiệm của bạn</p>
                                </div>
                            </div>

                            <div class="flex items-center space-x-4">
                                <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                                    <i class="fas fa-users text-xl"></i>
                                </div>
                                <div class="text-left">
                                    <h3 class="font-semibold">Kết nối cộng đồng</h3>
                                    <p class="text-sm opacity-75">Tương tác với những người cùng đam mê</p>
                                </div>
                            </div>

                            <div class="flex items-center space-x-4">
                                <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                                    <i class="fas fa-trophy text-xl"></i>
                                </div>
                                <div class="text-left">
                                    <h3 class="font-semibold">Học hỏi và phát triển</h3>
                                    <p class="text-sm opacity-75">Nâng cao kỹ năng và kiến thức</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Floating Elements -->
                <div class="absolute top-20 right-20 w-20 h-20 bg-white bg-opacity-20 rounded-full animate-bounce-custom"></div>
                <div class="absolute bottom-20 left-20 w-16 h-16 bg-white bg-opacity-20 rounded-full animate-bounce-custom delay-200"></div>
                <div class="absolute top-1/2 right-10 w-12 h-12 bg-white bg-opacity-20 rounded-full animate-bounce-custom delay-500"></div>
            </div>

            <!-- Right Side - Form -->
            <div class="flex-1 flex items-center justify-center px-4 sm:px-6 lg:px-8">
                <div class="max-w-md w-full auth-card">
                    <!-- Logo/Header -->
                    <header class="text-center mb-6">
                        <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-green-500 to-blue-600 rounded-full mb-3">
                            <i class="fas fa-user-plus text-white text-2xl"></i>
                        </div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Tạo tài khoản mới</h1>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Bắt đầu hành trình chia sẻ nội dung của bạn</p>
                    </header>

                    <!-- Register Form -->
                    <form action="" method="post" enctype="multipart/form-data" class="space-y-6">
                        <?= blog_csrf_input('register_form'); ?>
                        <!-- Avatar Upload -->
                        <div class="text-center">
                            <label for="avatar" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">
                                <i class="fas fa-camera mr-2 text-main"></i>Ảnh đại diện
                            </label>
                            <div class="relative inline-block">
                                <div class="w-24 h-24 rounded-full overflow-hidden border-4 border-gray-200 dark:border-gray-600 mx-auto mb-4 group cursor-pointer">
                                    <img id="avatarPreview" src="../uploaded_img/default_img.jpg"
                                        alt="Avatar Preview"
                                        class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300">
                                    <div class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300 rounded-full">
                                        <i class="fas fa-camera text-white text-xl"></i>
                                    </div>
                                </div>
                                <input type="file" name="avatar" id="avatar" accept="image/*"
                                    class="absolute inset-0 w-24 h-24 opacity-0 cursor-pointer rounded-full mx-auto"
                                    onchange="previewAvatar(this)">
                                <div class="absolute -bottom-2 -right-2 w-8 h-8 bg-main rounded-full flex items-center justify-center shadow-lg">
                                    <i class="fas fa-plus text-white text-sm"></i>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Nhấp để chọn ảnh đại diện</p>
                        </div>

                        <!-- Name Field -->
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                <i class="fas fa-user mr-2 text-main"></i>Tên người dùng
                            </label>
                            <div class="relative">
                                <input type="text" name="name" id="name" required
                                    placeholder="Nhập tên của bạn"
                                    class="form-input"
                                    maxlength="50">

                            </div>
                        </div>

                        <!-- Email Field -->
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                <i class="fas fa-envelope mr-2 text-main"></i>Email
                            </label>
                            <div class="relative">
                                <input type="email" name="email" id="email" required
                                    placeholder="Nhập email Gmail của bạn"
                                    class="form-input "
                                    maxlength="50"
                                    pattern="^[A-Za-z0-9._%+-]+@gmail\.com$"
                                    title="Vui lòng dùng email @gmail.com"
                                    oninput="this.value = this.value.replace(/\s/g, '')">

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
                                    onkeyup="checkPasswordStrength(this.value)">

                                <button type="button" onclick="togglePassword('pass')" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <i id="eyeIcon1" class="fas fa-eye text-gray-400 hover:text-gray-600 transition-colors"></i>
                                </button>
                            </div>
                            <!-- Password Strength Indicator -->
                            <div class="mt-2">
                                <div class="flex space-x-1">
                                    <div id="strength1" class="h-2 w-1/4 bg-gray-200 dark:bg-gray-600 rounded"></div>
                                    <div id="strength2" class="h-2 w-1/4 bg-gray-200 dark:bg-gray-600 rounded"></div>
                                    <div id="strength3" class="h-2 w-1/4 bg-gray-200 dark:bg-gray-600 rounded"></div>
                                    <div id="strength4" class="h-2 w-1/4 bg-gray-200 dark:bg-gray-600 rounded"></div>
                                </div>
                                <p id="strengthText" class="text-xs text-gray-500 dark:text-gray-400 mt-1">Độ mạnh mật khẩu</p>
                            </div>
                        </div>

                        <!-- Confirm Password Field -->
                        <div>
                            <label for="cpass" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                <i class="fas fa-lock mr-2 text-main"></i>Xác nhận mật khẩu
                            </label>
                            <div class="relative">
                                <input type="password" name="cpass" id="cpass" required
                                    placeholder="Nhập lại mật khẩu"
                                    class="form-input pr-12"
                                    maxlength="50"
                                    oninput="this.value = this.value.replace(/\s/g, '')"
                                    onkeyup="checkPasswordMatch()"
                                    onpaste="return false"
                                    ondrop="return false"
                                    autocomplete="new-password">

                                <button type="button" onclick="togglePassword('cpass')" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <i id="eyeIcon2" class="fas fa-eye text-gray-400 hover:text-gray-600 transition-colors"></i>
                                </button>
                            </div>
                            <div id="passwordMatch" class="mt-1 text-xs hidden">
                                <span id="matchText"></span>
                            </div>
                        </div>

                        <!-- Terms and Conditions -->
                        <div class="flex items-center">
                            <input id="terms" name="terms" type="checkbox" required
                                class="h-4 w-4 text-main focus:ring-main border-gray-300 rounded">
                            <label for="terms" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                Tôi đồng ý với
                                <button type="button" id="openTerms" class="text-main hover:text-blue-700 font-medium">Điều khoản dịch vụ</button>
                                và
                                <button type="button" id="openPrivacy" class="text-main hover:text-blue-700 font-medium">Chính sách bảo mật</button>
                            </label>
                        </div>

                        <?php if ($registerCaptchaRequired): ?>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    <i class="fas fa-shield-alt mr-2 text-main"></i>Xác minh an toàn
                                </label>
                                <?php if ($registerChallengeProvider === 'turnstile' && $registerChallengeSiteKey !== ''): ?>
                                    <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars($registerChallengeSiteKey, ENT_QUOTES, 'UTF-8'); ?>"></div>
                                <?php elseif ($registerChallengeProvider === 'recaptcha' && $registerChallengeSiteKey !== ''): ?>
                                    <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars($registerChallengeSiteKey, ENT_QUOTES, 'UTF-8'); ?>"></div>
                                <?php else: ?>
                                    <p class="text-sm text-red-600">Chưa cấu hình Turnstile/reCAPTCHA cho môi trường này.</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Submit Button -->
                        <button type="submit" name="submit" id="submitBtn"
                            class="w-full py-3 text-base font-semibold rounded-lg transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed bg-gray-400 text-white">
                            <i class="fas fa-user-plus mr-2"></i>
                            Đăng ký tài khoản
                        </button>

                        <!-- Divider -->
                        <div class="relative my-6 auth-optional">
                            <div class="absolute inset-0 flex items-center">
                                <div class="w-full border-t border-gray-300 dark:border-gray-600"></div>
                            </div>
                            <div class="relative flex justify-center text-sm">
                                <span class="px-2 bg-gray-100 dark:bg-gray-900 text-gray-500 dark:text-gray-400">hoặc đăng ký với</span>
                            </div>
                        </div>

                        <!-- Social Registration -->
                        <div class="space-y-3 auth-optional">
                            <button type="button" class="w-full flex items-center justify-center px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-all duration-300 shadow-lg hover:shadow-xl">
                                <i class="fab fa-google text-red-500 mr-3 text-lg"></i>
                                Đăng ký với Google
                            </button>

                            <button type="button" class="w-full flex items-center justify-center px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-all duration-300 shadow-lg hover:shadow-xl">
                                <i class="fab fa-facebook text-blue-600 mr-3 text-lg"></i>
                                Đăng ký với Facebook
                            </button>
                        </div>

                        <!-- Login Link -->
                        <div class="text-center pt-4">
                            <p class="text-gray-600 dark:text-gray-400">
                                Đã có tài khoản?
                                <a href="login.php" class="text-main hover:text-blue-700 font-semibold transition-colors">
                                    Đăng nhập ngay
                                </a>
                            </p>
                            <div class="mt-3 flex items-center justify-center gap-4 text-sm">
                                <a href="home.php" class="text-gray-500 dark:text-gray-400 hover:text-main transition-colors">Về trang chủ</a>
                                <a href="#" onclick="history.back(); return false;" class="text-gray-500 dark:text-gray-400 hover:text-main transition-colors">Quay lại trang trước</a>
                            </div>
                        </div>
                    </form>

                    <!-- Security Features -->
                    <div class="mt-8 grid grid-cols-2 gap-4 text-center auth-optional">
                        <div class="p-3">
                            <div class="w-10 h-10 bg-green-100 dark:bg-green-900/20 rounded-lg flex items-center justify-center mx-auto mb-2">
                                <i class="fas fa-shield-alt text-green-600"></i>
                            </div>
                            <p class="text-xs text-gray-600 dark:text-gray-400">Bảo mật SSL</p>
                        </div>
                        <div class="p-3">
                            <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900/20 rounded-lg flex items-center justify-center mx-auto mb-2">
                                <i class="fas fa-user-shield text-blue-600"></i>
                            </div>
                            <p class="text-xs text-gray-600 dark:text-gray-400">Bảo vệ dữ liệu</p>
                        </div>
                    </div>
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
                    duration: 4500,
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

            // Form validation
            const form = document.querySelector('form');
            const nameInput = document.getElementById('name');
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('pass');
            const confirmPasswordInput = document.getElementById('cpass');
            const termsCheckbox = document.getElementById('terms');
            const submitBtn = document.getElementById('submitBtn');

            function validateForm() {
                const isNameValid = nameInput.value.trim().length >= 2;
                const isEmailValid = /^[A-Za-z0-9._%+-]+@gmail\.com$/i.test(emailInput.value.trim());
                const isPasswordValid = passwordInput.value.length >= 6;
                const isPasswordMatch = passwordInput.value === confirmPasswordInput.value && confirmPasswordInput.value.length > 0;
                const isTermsAccepted = termsCheckbox.checked;
                const isCaptchaValid = true;

                if (isNameValid && isEmailValid && isPasswordValid && isPasswordMatch && isTermsAccepted && isCaptchaValid) {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('bg-gray-400');
                    submitBtn.classList.add('bg-main', 'hover:bg-blue-700');
                } else {
                    submitBtn.disabled = true;
                    submitBtn.classList.add('bg-gray-400');
                    submitBtn.classList.remove('bg-main', 'hover:bg-blue-700');
                }
            }

            // Real-time validation
            [nameInput, emailInput, passwordInput, confirmPasswordInput, termsCheckbox].forEach(input => {
                input.addEventListener('input', validateForm);
                input.addEventListener('change', validateForm);
            });

            // Initialize validation
            validateForm();

            // Form submission with loading state
            form.addEventListener('submit', function(e) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Đang tạo tài khoản...';
                submitBtn.disabled = true;
            });

            initDarkMode();

            document.getElementById('cpass').addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'v') {
                    e.preventDefault();
                }
            });

            const modal = document.getElementById('policyModal');
            const modalTitle = document.getElementById('policyModalTitle');
            const modalContent = document.getElementById('policyModalContent');

            const termsText = `
                <p><strong>Điều khoản dịch vụ</strong></p>
                <p>Bạn chịu trách nhiệm về thông tin và nội dung được tạo trên tài khoản của mình.</p>
                <p>Không sử dụng dịch vụ cho mục đích spam, lừa đảo hoặc hành vi vi phạm pháp luật.</p>
                <p>Tài khoản vi phạm nghiêm trọng có thể bị tạm khóa hoặc chấm dứt quyền truy cập.</p>
            `;

            const privacyText = `
                <p><strong>Chính sách bảo mật</strong></p>
                <p>Chúng tôi thu thập dữ liệu tối thiểu để vận hành dịch vụ và cải thiện trải nghiệm.</p>
                <p>Mật khẩu được lưu ở dạng mã hóa và không hiển thị công khai.</p>
                <p>Dữ liệu cá nhân không được chia sẻ cho bên thứ ba nếu không có sự cho phép hợp lệ.</p>
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
        });

        // Avatar preview function
        function previewAvatar(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('avatarPreview').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Password toggle function
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const eyeIcon = fieldId === 'pass' ? document.getElementById('eyeIcon1') : document.getElementById('eyeIcon2');

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

            // Reset bars
            strengthBars.forEach(bar => {
                document.getElementById(bar).className = 'h-2 w-1/4 bg-gray-200 dark:bg-gray-600 rounded';
            });

            let strength = 0;
            let text = 'Rất yếu';
            let color = 'bg-red-500';

            if (password.length >= 6) strength++;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
            if (password.match(/\d/)) strength++;
            if (password.match(/[^a-zA-Z\d]/)) strength++;

            if (strength >= 1) {
                document.getElementById('strength1').className = 'h-2 w-1/4 bg-red-500 rounded';
                text = 'Yếu';
            }
            if (strength >= 2) {
                document.getElementById('strength2').className = 'h-2 w-1/4 bg-yellow-500 rounded';
                text = 'Trung bình';
                color = 'bg-yellow-500';
            }
            if (strength >= 3) {
                document.getElementById('strength3').className = 'h-2 w-1/4 bg-blue-500 rounded';
                text = 'Mạnh';
                color = 'bg-blue-500';
            }
            if (strength >= 4) {
                document.getElementById('strength4').className = 'h-2 w-1/4 bg-green-500 rounded';
                text = 'Rất mạnh';
                color = 'bg-green-500';
            }

            strengthText.textContent = text;
        }

        // Password match checker
        function checkPasswordMatch() {
            const password = document.getElementById('pass').value;
            const confirmPassword = document.getElementById('cpass').value;
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
    <?php if ($registerCaptchaRequired && $registerChallengeProvider === 'turnstile' && $registerChallengeSiteKey !== ''): ?>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <?php elseif ($registerCaptchaRequired && $registerChallengeProvider === 'recaptcha' && $registerChallengeSiteKey !== ''): ?>
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>
</body>

</html>
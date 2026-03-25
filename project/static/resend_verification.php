<?php
include '../components/connect.php';
include '../components/seo_helpers.php';
include '../components/security_helpers.php';

session_start();
blog_security_ensure_tables($conn);

$message = '';
$messageType = 'info';
$prefillEmail = trim((string)($_GET['email'] ?? $_POST['email'] ?? ''));
$prefillEmail = filter_var($prefillEmail, FILTER_SANITIZE_EMAIL);
$resendCaptchaRequired = false;
$resendChallengeProvider = '';
$resendChallengeSiteKey = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    $identifier = $email !== '' ? $email : 'anonymous';

    $resendCaptchaRequired = blog_captcha_should_show($conn, 'resend_verification', $identifier, 3, 900);
    $resendChallengeProvider = blog_human_challenge_provider();
    $resendChallengeSiteKey = blog_human_challenge_site_key();

    if (!blog_csrf_validate('resend_verification_form', $_POST['_csrf_token'] ?? '')) {
        $message = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang và thử lại.';
        $messageType = 'error';
    } else {
        $limitState = blog_rate_limit_state($conn, 'resend_verification', $identifier, 6, 900, 900);
        if (!empty($limitState['blocked'])) {
            $retryAfter = max(1, (int)($limitState['retry_after'] ?? 0));
            $minutes = (int)ceil($retryAfter / 60);
            $message = 'Bạn đã yêu cầu quá nhiều lần. Vui lòng thử lại sau khoảng ' . $minutes . ' phút.';
            $messageType = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            blog_rate_limit_record_failure($conn, 'resend_verification', $identifier, 6, 900, 900);
            $message = 'Email không hợp lệ.';
            $messageType = 'error';
        } elseif ($resendCaptchaRequired && ($resendChallengeProvider === '' || $resendChallengeSiteKey === '')) {
            blog_rate_limit_record_failure($conn, 'resend_verification', $identifier, 6, 900, 900);
            $message = 'Hệ thống xác thực bot chưa được cấu hình. Vui lòng liên hệ quản trị viên.';
            $messageType = 'error';
        } elseif ($resendCaptchaRequired && !blog_human_challenge_verify($_POST[blog_human_challenge_token_field()] ?? '')) {
            blog_rate_limit_record_failure($conn, 'resend_verification', $identifier, 6, 900, 900);
            $message = 'Vui lòng hoàn tất xác minh bot.';
            $messageType = 'error';
        } else {
            $stmt = $conn->prepare('SELECT id, name, email, is_verified FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && (int)($user['is_verified'] ?? 0) !== 1) {
                $result = blog_issue_and_send_verification($conn, (int)$user['id'], (string)$user['email'], (string)$user['name']);
                if (!empty($result['ok'])) {
                    blog_rate_limit_record_success($conn, 'resend_verification', $identifier);
                } else {
                    blog_rate_limit_record_failure($conn, 'resend_verification', $identifier, 6, 900, 900);
                }
            } else {
                blog_rate_limit_record_success($conn, 'resend_verification', $identifier);
            }

            $message = 'Nếu email tồn tại và chưa xác minh, chúng tôi đã gửi lại liên kết xác minh.';
            $messageType = 'success';
        }
    }

    $prefillEmail = $email;
}

if (!$resendCaptchaRequired && $prefillEmail !== '') {
    $resendCaptchaRequired = blog_captcha_should_show($conn, 'resend_verification', $prefillEmail, 3, 900);
}
$resendChallengeProvider = blog_human_challenge_provider();
$resendChallengeSiteKey = blog_human_challenge_site_key();

$page_title = 'Gửi lại xác minh email - My Blog';
$page_description = 'Yêu cầu gửi lại email xác minh tài khoản My Blog.';
$page_robots = 'noindex,follow,max-image-preview:large';
$page_canonical = canonical_current_url();
$page_og_image = blog_brand_logo_url();
?>

<?php include '../components/layout_header.php'; ?>

<?php if (!empty($message)) : ?>
    <script>
        window.BLOG_FLASH_MESSAGE = <?= json_encode($message, JSON_UNESCAPED_UNICODE); ?>;
        window.BLOG_FLASH_TYPE = <?= json_encode($messageType, JSON_UNESCAPED_UNICODE); ?>;
    </script>
<?php endif; ?>

<main class="min-h-screen bg-gray-50 dark:bg-gray-900 pt-20">
    <div class="container mx-auto px-4 py-10">
        <div class="max-w-lg mx-auto bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 p-8">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-3">Gửi lại email xác minh</h1>
            <p class="text-gray-600 dark:text-gray-300 mb-6">Nhập email đã đăng ký để nhận lại liên kết xác minh.</p>

            <form action="" method="post" class="space-y-5">
                <?= blog_csrf_input('resend_verification_form'); ?>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email</label>
                    <input type="email" id="email" name="email" required maxlength="120"
                        value="<?= htmlspecialchars($prefillEmail, ENT_QUOTES, 'UTF-8'); ?>"
                        class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-main focus:border-transparent dark:bg-gray-700 dark:text-white"
                        oninput="this.value = this.value.replace(/\s/g, '')">
                </div>

                <?php if ($resendCaptchaRequired): ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Xác minh an toàn</label>
                        <?php if ($resendChallengeProvider === 'turnstile' && $resendChallengeSiteKey !== ''): ?>
                            <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars($resendChallengeSiteKey, ENT_QUOTES, 'UTF-8'); ?>"></div>
                        <?php elseif ($resendChallengeProvider === 'recaptcha' && $resendChallengeSiteKey !== ''): ?>
                            <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars($resendChallengeSiteKey, ENT_QUOTES, 'UTF-8'); ?>"></div>
                        <?php else: ?>
                            <p class="text-sm text-red-600">Chưa cấu hình Turnstile/reCAPTCHA cho môi trường này.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <button type="submit" class="w-full px-5 py-3 rounded-lg bg-main text-white font-semibold hover:bg-main/90 transition-colors">Gửi lại email xác minh</button>
            </form>

            <div class="mt-6 text-sm">
                <a href="login.php" class="text-main hover:opacity-80">Quay lại đăng nhập</a>
            </div>
        </div>
    </div>
</main>

<?php if ($resendCaptchaRequired && $resendChallengeProvider === 'turnstile' && $resendChallengeSiteKey !== ''): ?>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php elseif ($resendCaptchaRequired && $resendChallengeProvider === 'recaptcha' && $resendChallengeSiteKey !== ''): ?>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
<?php endif; ?>

<?php include '../components/layout_footer.php'; ?>
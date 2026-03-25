<?php
include '../components/connect.php';
include '../components/seo_helpers.php';
include '../components/security_helpers.php';

session_start();
blog_security_ensure_tables($conn);

$status = 'error';
$message = 'Lien ket xac minh khong hop le hoac da het han.';
$email = trim((string)($_GET['email'] ?? ''));
$token = trim((string)($_GET['token'] ?? ''));

if ($email !== '' && $token !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $stmt = $conn->prepare('SELECT id, name, email, is_verified, verification_token_hash, verification_token_expires_at FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $userId = (int)($user['id'] ?? 0);
        $isVerified = (int)($user['is_verified'] ?? 0) === 1;
        if ($isVerified) {
            $status = 'success';
            $message = 'Email da duoc xac minh truoc do. Ban co the dang nhap ngay.';
        } else {
            $tokenHash = (string)($user['verification_token_hash'] ?? '');
            $expiresAtRaw = (string)($user['verification_token_expires_at'] ?? '');
            $expiresTs = strtotime($expiresAtRaw) ?: 0;
            $tokenValid = $tokenHash !== '' && hash_equals($tokenHash, hash('sha256', $token));

            if ($tokenValid && $expiresTs > time()) {
                blog_mark_user_verified($conn, $userId, null);
                $status = 'success';
                $message = 'Xac minh email thanh cong. Ban co the dang nhap de su dung day du chuc nang.';
            } else {
                if ($expiresTs > 0 && $expiresTs <= time()) {
                    $clear = $conn->prepare('UPDATE users SET verification_token_hash = NULL, verification_token_expires_at = NULL WHERE id = ? LIMIT 1');
                    $clear->execute([$userId]);
                    $message = 'Lien ket xac minh da het han. Vui long yeu cau gui lai email xac minh.';
                }
            }
        }
    }
}

$page_title = 'Xac minh email - My Blog';
$page_description = 'Xac minh email tai khoan My Blog de mo khoa day du tinh nang.';
$page_robots = 'noindex,follow,max-image-preview:large';
$page_canonical = canonical_current_url();
$page_og_image = blog_brand_logo_url();
?>

<?php include '../components/layout_header.php'; ?>

<main class="min-h-screen bg-gray-50 dark:bg-gray-900 pt-20">
    <div class="container mx-auto px-4 py-10">
        <div class="max-w-xl mx-auto bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 p-8 text-center">
            <?php if ($status === 'success'): ?>
                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                    <i class="fas fa-check text-green-600 text-2xl"></i>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-3">Xac minh thanh cong</h1>
                <p class="text-gray-600 dark:text-gray-300 mb-6"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
                <a href="login.php" class="inline-flex items-center px-5 py-3 rounded-lg bg-main text-white font-semibold hover:bg-main/90 transition-colors">Dang nhap ngay</a>
            <?php else: ?>
                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                    <i class="fas fa-triangle-exclamation text-amber-600 text-2xl"></i>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-3">Khong the xac minh</h1>
                <p class="text-gray-600 dark:text-gray-300 mb-6"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
                <a href="resend_verification.php<?= $email !== '' ? '?email=' . rawurlencode($email) : ''; ?>" class="inline-flex items-center px-5 py-3 rounded-lg bg-main text-white font-semibold hover:bg-main/90 transition-colors">Gui lai email xac minh</a>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include '../components/layout_footer.php'; ?>
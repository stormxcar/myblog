<?php
include '../components/connect.php';

session_start();
$message = [];

if (!isset($_SERVER['HTTP_REFERER'])) {
    header('location: home.php');
    exit;
}

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} else {
    $user_id = '';
    header('location: home.php');
    exit;
}

// Get current user data
$select_user = $conn->prepare("SELECT * FROM `users` WHERE id = ?");
$select_user->execute([$user_id]);
$fetch_user = $select_user->fetch(PDO::FETCH_ASSOC);

if (!$fetch_user) {
    header('location: home.php');
    exit;
}

if (isset($_POST['submit'])) {
    $name = $_POST['name'];
    $name = filter_var($name, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    $email = $_POST['email'];
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);

    $success_messages = [];
    $error_messages = [];

    // Update name
    if (!empty($name) && $name != $fetch_user['name']) {
        if (strlen($name) >= 2 && strlen($name) <= 50) {
            $update_name = $conn->prepare("UPDATE `users` SET name = ? WHERE id = ?");
            $update_name->execute([$name, $user_id]);
            $success_messages[] = 'Cập nhật tên thành công!';
        } else {
            $error_messages[] = 'Tên phải từ 2-50 ký tự!';
        }
    }

    // Update email
    if (!empty($email) && $email != $fetch_user['email']) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $select_email = $conn->prepare("SELECT * FROM `users` WHERE email = ? AND id != ?");
            $select_email->execute([$email, $user_id]);
            if ($select_email->rowCount() > 0) {
                $error_messages[] = 'Email này đã được sử dụng!';
            } else {
                $update_email = $conn->prepare("UPDATE `users` SET email = ? WHERE id = ?");
                $update_email->execute([$email, $user_id]);
                $success_messages[] = 'Cập nhật email thành công!';
            }
        } else {
            $error_messages[] = 'Định dạng email không hợp lệ!';
        }
    }

    // Update password
    $empty_pass = 'da39a3ee5e6b4b0d3255bfef95601890afd80709';
    $old_pass = sha1($_POST['old_pass']);
    $old_pass = filter_var($old_pass, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $new_pass = sha1($_POST['new_pass']);
    $new_pass = filter_var($new_pass, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $confirm_pass = sha1($_POST['confirm_pass']);
    $confirm_pass = filter_var($confirm_pass, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    if ($old_pass != $empty_pass) {
        if ($old_pass != $fetch_user['password']) {
            $error_messages[] = 'Mật khẩu cũ không đúng!';
        } elseif ($new_pass != $confirm_pass) {
            $error_messages[] = 'Mật khẩu mới không khớp!';
        } elseif (strlen($_POST['new_pass']) < 6) {
            $error_messages[] = 'Mật khẩu mới phải có ít nhất 6 ký tự!';
        } else {
            if ($new_pass != $empty_pass) {
                $update_pass = $conn->prepare("UPDATE `users` SET password = ? WHERE id = ?");
                $update_pass->execute([$confirm_pass, $user_id]);
                $success_messages[] = 'Mật khẩu đã cập nhật thành công!';
            } else {
                $error_messages[] = 'Vui lòng nhập mật khẩu mới!';
            }
        }
    }

    // Update avatar
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = $_FILES['avatar']['type'];
        $file_size = $_FILES['avatar']['size'];

        if (!in_array($file_type, $allowed_types)) {
            $error_messages[] = 'Chỉ chấp nhận file ảnh (JPEG, PNG, GIF, WebP)!';
        } elseif ($file_size > 5 * 1024 * 1024) { // 5MB
            $error_messages[] = 'Kích thước file không được vượt quá 5MB!';
        } else {
            $fileTmpName = $_FILES['avatar']['tmp_name'];
            $fileContent = file_get_contents($fileTmpName);

            $update_avatar = $conn->prepare("UPDATE `users` SET avatar = ? WHERE id = ?");
            $update_avatar->execute([$fileContent, $user_id]);
            $success_messages[] = 'Ảnh đại diện đã được cập nhật!';
        }
    }

    // Set session messages
    if (!empty($success_messages)) {
        $_SESSION['success_message'] = implode('<br>', $success_messages);
    }
    if (!empty($error_messages)) {
        $_SESSION['error_message'] = implode('<br>', $error_messages);
    }

    // Refresh user data
    $select_user->execute([$user_id]);
    $fetch_user = $select_user->fetch(PDO::FETCH_ASSOC);
}
?>

<?php include '../components/layout_header.php'; ?>

<?php
// Include breadcrumb component
include '../components/breadcrumb.php';

// Generate breadcrumb for update profile page
$breadcrumb_items = auto_breadcrumb('Cập nhật hồ sơ');
render_breadcrumb($breadcrumb_items);
?>

<main class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- Header Section -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-main to-blue-600 rounded-full mb-4">
                    <i class="fas fa-user-edit text-white text-2xl"></i>
                </div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">Cập nhật hồ sơ</h1>
                <p class="text-gray-600 dark:text-gray-400">
                    Quản lý thông tin tài khoản và bảo mật của bạn
                </p>
            </div>

            <!-- Alert Messages -->
            <?php if (isset($_SESSION['success_message'])) : ?>
                <div id="successMessage" class="mb-6 p-4 rounded-lg border bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800 text-green-800 dark:text-green-200">
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0">
                            <i class="fas fa-check-circle text-green-500"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-medium"><?= $_SESSION['success_message'] ?></p>
                        </div>
                        <button onclick="this.parentElement.parentElement.style.display='none'" class="flex-shrink-0 text-green-400 hover:text-green-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])) : ?>
                <div id="errorMessage" class="mb-6 p-4 rounded-lg border bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800 text-red-800 dark:text-red-200">
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-red-500"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-medium"><?= $_SESSION['error_message'] ?></p>
                        </div>
                        <button onclick="this.parentElement.parentElement.style.display='none'" class="flex-shrink-0 text-red-400 hover:text-red-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- User Info Sidebar -->
                <div class="lg:col-span-1">
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-200 dark:border-gray-700 p-6">
                        <div class="text-center">
                            <!-- Avatar -->
                            <div class="relative inline-block mb-4">
                                <?php if (!empty($fetch_user['avatar'])) : ?>
                                    <img src="data:image/jpeg;base64,<?= base64_encode($fetch_user['avatar']) ?>"
                                        alt="Avatar"
                                        class="w-24 h-24 rounded-full object-cover border-4 border-main shadow-lg">
                                <?php else : ?>
                                    <div class="w-24 h-24 bg-main rounded-full flex items-center justify-center text-white text-2xl font-bold border-4 border-main shadow-lg">
                                        <?= strtoupper(substr($fetch_user['name'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="absolute bottom-0 right-0 w-6 h-6 bg-green-500 rounded-full border-2 border-white"></div>
                            </div>

                            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1"><?= htmlspecialchars($fetch_user['name']) ?></h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4"><?= htmlspecialchars($fetch_user['email']) ?></p>

                            <!-- User Stats -->
                            <div class="grid grid-cols-2 gap-4 text-center">
                                <?php
                                $user_stats = $conn->prepare("
                                    SELECT 
                                        (SELECT COUNT(*) FROM comments WHERE user_id = ?) as comments,
                                        (SELECT COUNT(*) FROM likes WHERE user_id = ?) as likes
                                ");
                                $user_stats->execute([$user_id, $user_id]);
                                $stats = $user_stats->fetch(PDO::FETCH_ASSOC);
                                ?>
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3">
                                    <div class="text-2xl font-bold text-main"><?= $stats['comments'] ?></div>
                                    <div class="text-xs text-gray-600 dark:text-gray-400">Bình luận</div>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3">
                                    <div class="text-2xl font-bold text-red-500"><?= $stats['likes'] ?></div>
                                    <div class="text-xs text-gray-600 dark:text-gray-400">Lượt thích</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="mt-6 bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-200 dark:border-gray-700 p-6">
                        <h4 class="font-bold text-gray-900 dark:text-white mb-4">Truy cập nhanh</h4>
                        <div class="space-y-2">
                            <a href="community_manage.php" class="flex items-center space-x-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-main/10 transition-colors group">
                                <i class="fas fa-layer-group text-main group-hover:text-blue-600"></i>
                                <span class="text-sm text-gray-700 dark:text-gray-300 group-hover:text-main">Quan ly bai viet cong dong</span>
                            </a>
                            <a href="user_comments.php" class="flex items-center space-x-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-main/10 transition-colors group">
                                <i class="fas fa-comments text-main group-hover:text-blue-600"></i>
                                <span class="text-sm text-gray-700 dark:text-gray-300 group-hover:text-main">Bình luận của tôi</span>
                            </a>
                            <a href="user_likes.php" class="flex items-center space-x-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-main/10 transition-colors group">
                                <i class="fas fa-heart text-red-500 group-hover:text-red-600"></i>
                                <span class="text-sm text-gray-700 dark:text-gray-300 group-hover:text-main">Bài viết đã thích</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Update Form -->
                <div class="lg:col-span-2">
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <form action="" method="post" enctype="multipart/form-data" id="updateForm" novalidate>
                            <!-- Personal Information -->
                            <div class="p-6 border-b border-gray-200 dark:border-gray-600">
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-6 flex items-center">
                                    <i class="fas fa-user text-main mr-2"></i>
                                    Thông tin cá nhân
                                </h3>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Name -->
                                    <div>
                                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Tên hiển thị
                                        </label>
                                        <input type="text"
                                            id="name"
                                            name="name"
                                            value="<?= htmlspecialchars($fetch_user['name']) ?>"
                                            maxlength="50"
                                            placeholder="Nhập tên của bạn"
                                            class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-main focus:border-transparent dark:bg-gray-700 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 transition-all duration-300">
                                        <div id="nameError" class="mt-1 text-sm text-red-600 dark:text-red-400 hidden"></div>
                                        <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                            Tên này sẽ hiển thị công khai trên các bài viết và bình luận
                                        </div>
                                    </div>

                                    <!-- Email -->
                                    <div>
                                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Email
                                        </label>
                                        <input type="email"
                                            id="email"
                                            name="email"
                                            value="<?= htmlspecialchars($fetch_user['email']) ?>"
                                            maxlength="50"
                                            placeholder="Nhập email của bạn"
                                            class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-main focus:border-transparent dark:bg-gray-700 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 transition-all duration-300">
                                        <div id="emailError" class="mt-1 text-sm text-red-600 dark:text-red-400 hidden"></div>
                                        <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                            Email được sử dụng để đăng nhập và nhận thông báo
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Avatar Upload -->
                            <div class="p-6 border-b border-gray-200 dark:border-gray-600">
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-6 flex items-center">
                                    <i class="fas fa-image text-main mr-2"></i>
                                    Ảnh đại diện
                                </h3>

                                <div class="flex items-start space-x-6">
                                    <!-- Current Avatar Preview -->
                                    <div class="flex-shrink-0">
                                        <div id="avatarPreview" class="relative">
                                            <?php if (!empty($fetch_user['avatar'])) : ?>
                                                <img src="data:image/jpeg;base64,<?= base64_encode($fetch_user['avatar']) ?>"
                                                    alt="Avatar hiện tại"
                                                    class="w-20 h-20 rounded-full object-cover border-2 border-gray-300 dark:border-gray-600">
                                            <?php else : ?>
                                                <div class="w-20 h-20 bg-gray-200 dark:bg-gray-700 rounded-full flex items-center justify-center text-gray-400 text-xl border-2 border-gray-300 dark:border-gray-600">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Upload Area -->
                                    <div class="flex-1">
                                        <label for="avatar" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Chọn ảnh mới
                                        </label>
                                        <div class="relative">
                                            <input type="file"
                                                id="avatar"
                                                name="avatar"
                                                accept="image/*"
                                                class="hidden"
                                                onchange="previewImage(this)">
                                            <label for="avatar" class="cursor-pointer inline-flex items-center justify-center px-4 py-2 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg hover:border-main hover:bg-main/5 transition-colors">
                                                <div class="text-center">
                                                    <i class="fas fa-cloud-upload-alt text-2xl text-gray-400 mb-2"></i>
                                                    <div class="text-sm text-gray-600 dark:text-gray-400">
                                                        Nhấp để chọn ảnh hoặc kéo thả
                                                    </div>
                                                    <div class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                                                        PNG, JPG, GIF, WebP (tối đa 5MB)
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                        <div id="avatarError" class="mt-1 text-sm text-red-600 dark:text-red-400 hidden"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Password Update -->
                            <div class="p-6">
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-6 flex items-center">
                                    <i class="fas fa-lock text-main mr-2"></i>
                                    Đổi mật khẩu
                                </h3>

                                <div class="space-y-4">
                                    <!-- Current Password -->
                                    <div>
                                        <label for="old_pass" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Mật khẩu hiện tại
                                        </label>
                                        <div class="relative">
                                            <input type="password"
                                                id="old_pass"
                                                name="old_pass"
                                                placeholder="Nhập mật khẩu hiện tại"
                                                class="w-full px-4 py-3 pr-12 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-main focus:border-transparent dark:bg-gray-700 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 transition-all duration-300">
                                            <button type="button" onclick="togglePassword('old_pass')" class="absolute inset-y-0 right-0 flex items-center pr-3">
                                                <i class="fas fa-eye text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"></i>
                                            </button>
                                        </div>
                                        <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                            Để trống nếu không muốn đổi mật khẩu
                                        </div>
                                    </div>

                                    <!-- New Password -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label for="new_pass" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                Mật khẩu mới
                                            </label>
                                            <div class="relative">
                                                <input type="password"
                                                    id="new_pass"
                                                    name="new_pass"
                                                    placeholder="Nhập mật khẩu mới"
                                                    class="w-full px-4 py-3 pr-12 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-main focus:border-transparent dark:bg-gray-700 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 transition-all duration-300"
                                                    oninput="checkPasswordStrength(this.value)">
                                                <button type="button" onclick="togglePassword('new_pass')" class="absolute inset-y-0 right-0 flex items-center pr-3">
                                                    <i class="fas fa-eye text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"></i>
                                                </button>
                                            </div>
                                            <div id="passwordStrength" class="mt-2 hidden">
                                                <div class="flex space-x-1">
                                                    <div class="h-2 w-1/4 bg-gray-200 dark:bg-gray-600 rounded-full">
                                                        <div id="strength1" class="h-full rounded-full transition-colors"></div>
                                                    </div>
                                                    <div class="h-2 w-1/4 bg-gray-200 dark:bg-gray-600 rounded-full">
                                                        <div id="strength2" class="h-full rounded-full transition-colors"></div>
                                                    </div>
                                                    <div class="h-2 w-1/4 bg-gray-200 dark:bg-gray-600 rounded-full">
                                                        <div id="strength3" class="h-full rounded-full transition-colors"></div>
                                                    </div>
                                                    <div class="h-2 w-1/4 bg-gray-200 dark:bg-gray-600 rounded-full">
                                                        <div id="strength4" class="h-full rounded-full transition-colors"></div>
                                                    </div>
                                                </div>
                                                <div id="strengthText" class="text-sm mt-1"></div>
                                            </div>
                                        </div>

                                        <div>
                                            <label for="confirm_pass" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                Xác nhận mật khẩu
                                            </label>
                                            <div class="relative">
                                                <input type="password"
                                                    id="confirm_pass"
                                                    name="confirm_pass"
                                                    placeholder="Nhập lại mật khẩu mới"
                                                    class="w-full px-4 py-3 pr-12 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-main focus:border-transparent dark:bg-gray-700 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 transition-all duration-300">
                                                <button type="button" onclick="togglePassword('confirm_pass')" class="absolute inset-y-0 right-0 flex items-center pr-3">
                                                    <i class="fas fa-eye text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"></i>
                                                </button>
                                            </div>
                                            <div id="passwordMatch" class="mt-1 text-sm hidden"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700 border-t border-gray-200 dark:border-gray-600">
                                <div class="flex items-center justify-between">
                                    <a href="home.php" class="inline-flex items-center text-sm text-gray-600 dark:text-gray-400 hover:text-main transition-colors">
                                        <i class="fas fa-arrow-left mr-2"></i>
                                        Quay lại trang chủ
                                    </a>

                                    <button type="submit"
                                        name="submit"
                                        id="submitBtn"
                                        class="btn-primary relative overflow-hidden">
                                        <span id="submitText" class="flex items-center">
                                            <i class="fas fa-save mr-2"></i>
                                            Cập nhật thông tin
                                        </span>
                                        <span id="loadingText" class="hidden flex items-center">
                                            <i class="fas fa-spinner fa-spin mr-2"></i>
                                            Đang cập nhật...
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Enhanced JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('updateForm');
        const nameInput = document.getElementById('name');
        const emailInput = document.getElementById('email');
        const newPassInput = document.getElementById('new_pass');
        const confirmPassInput = document.getElementById('confirm_pass');

        // Real-time validation
        nameInput.addEventListener('input', function() {
            const name = this.value.trim();
            const nameError = document.getElementById('nameError');

            if (name.length < 2 && name.length > 0) {
                nameError.textContent = 'Tên phải có ít nhất 2 ký tự';
                nameError.classList.remove('hidden');
                this.classList.add('border-red-500');
            } else {
                nameError.classList.add('hidden');
                this.classList.remove('border-red-500');
            }
        });

        emailInput.addEventListener('input', function() {
            const email = this.value.trim();
            const emailError = document.getElementById('emailError');
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            if (email.length > 0 && !emailRegex.test(email)) {
                emailError.textContent = 'Định dạng email không hợp lệ';
                emailError.classList.remove('hidden');
                this.classList.add('border-red-500');
            } else {
                emailError.classList.add('hidden');
                this.classList.remove('border-red-500');
            }
        });

        // Password confirmation check
        function checkPasswordMatch() {
            const newPass = newPassInput.value;
            const confirmPass = confirmPassInput.value;
            const matchDiv = document.getElementById('passwordMatch');

            if (confirmPass.length > 0) {
                if (newPass === confirmPass) {
                    matchDiv.textContent = 'Mật khẩu khớp';
                    matchDiv.className = 'mt-1 text-sm text-green-600 dark:text-green-400';
                    matchDiv.classList.remove('hidden');
                    confirmPassInput.classList.remove('border-red-500');
                    confirmPassInput.classList.add('border-green-500');
                } else {
                    matchDiv.textContent = 'Mật khẩu không khớp';
                    matchDiv.className = 'mt-1 text-sm text-red-600 dark:text-red-400';
                    matchDiv.classList.remove('hidden');
                    confirmPassInput.classList.remove('border-green-500');
                    confirmPassInput.classList.add('border-red-500');
                }
            } else {
                matchDiv.classList.add('hidden');
                confirmPassInput.classList.remove('border-red-500', 'border-green-500');
            }
        }

        newPassInput.addEventListener('input', checkPasswordMatch);
        confirmPassInput.addEventListener('input', checkPasswordMatch);

        // Form submission
        form.addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            const submitText = document.getElementById('submitText');
            const loadingText = document.getElementById('loadingText');

            submitBtn.disabled = true;
            submitText.classList.add('hidden');
            loadingText.classList.remove('hidden');
            submitBtn.classList.add('opacity-75');
        });

        // Auto-hide alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('#successMessage, #errorMessage');
            alerts.forEach(alert => {
                if (alert) {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(() => alert.remove(), 300);
                }
            });
        }, 5000);
    });

    // Password visibility toggle
    function togglePassword(inputId) {
        const input = document.getElementById(inputId);
        const icon = input.nextElementSibling.querySelector('i');

        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    // Password strength checker
    function checkPasswordStrength(password) {
        const strengthDiv = document.getElementById('passwordStrength');
        const strengthText = document.getElementById('strengthText');
        const bars = ['strength1', 'strength2', 'strength3', 'strength4'];

        if (password.length === 0) {
            strengthDiv.classList.add('hidden');
            return;
        }

        strengthDiv.classList.remove('hidden');

        let score = 0;
        let feedback = '';

        // Length check
        if (password.length >= 8) score++;
        if (password.length >= 12) score++;

        // Character variety
        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
        if (/\d/.test(password)) score++;
        if (/[^A-Za-z0-9]/.test(password)) score++;

        // Reset bars
        bars.forEach(bar => {
            document.getElementById(bar).className = 'h-full rounded-full transition-colors';
        });

        // Set strength bars and text
        if (score <= 1) {
            document.getElementById('strength1').classList.add('bg-red-500');
            feedback = 'Rất yếu';
            strengthText.className = 'text-sm mt-1 text-red-600 dark:text-red-400';
        } else if (score <= 2) {
            document.getElementById('strength1').classList.add('bg-orange-500');
            document.getElementById('strength2').classList.add('bg-orange-500');
            feedback = 'Yếu';
            strengthText.className = 'text-sm mt-1 text-orange-600 dark:text-orange-400';
        } else if (score <= 3) {
            document.getElementById('strength1').classList.add('bg-yellow-500');
            document.getElementById('strength2').classList.add('bg-yellow-500');
            document.getElementById('strength3').classList.add('bg-yellow-500');
            feedback = 'Trung bình';
            strengthText.className = 'text-sm mt-1 text-yellow-600 dark:text-yellow-400';
        } else {
            bars.forEach(bar => {
                document.getElementById(bar).classList.add('bg-green-500');
            });
            feedback = 'Mạnh';
            strengthText.className = 'text-sm mt-1 text-green-600 dark:text-green-400';
        }

        strengthText.textContent = feedback;
    }

    // Image preview
    function previewImage(input) {
        const avatarError = document.getElementById('avatarError');
        avatarError.classList.add('hidden');

        if (input.files && input.files[0]) {
            const file = input.files[0];
            const maxSize = 5 * 1024 * 1024; // 5MB
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

            if (!allowedTypes.includes(file.type)) {
                avatarError.textContent = 'Chỉ chấp nhận file ảnh (JPEG, PNG, GIF, WebP)';
                avatarError.classList.remove('hidden');
                input.value = '';
                return;
            }

            if (file.size > maxSize) {
                avatarError.textContent = 'Kích thước file không được vượt quá 5MB';
                avatarError.classList.remove('hidden');
                input.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('avatarPreview');
                preview.innerHTML = `
                <img src="${e.target.result}" 
                     alt="Ảnh xem trước" 
                     class="w-20 h-20 rounded-full object-cover border-2 border-main shadow-lg">
            `;
            };
            reader.readAsDataURL(file);
        }
    }
</script>

<?php include '../components/layout_footer.php'; ?>
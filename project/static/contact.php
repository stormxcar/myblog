<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../vendor/autoload.php';
include '../components/connect.php';

session_start();

// Lấy các giá trị settings
function get_setting($conn, $key)
{
    try {
        $stmt = $conn->prepare("SELECT setting_value FROM `settings` WHERE setting_key = ?");
        $stmt->execute([$key]);
        return $stmt->fetchColumn();
    } catch (Exception $e) {
        // Return default values if database error occurs
        return null;
    }
}

try {
    $image_url = get_setting($conn, 'lienhe_image') ?: '../uploaded_img/default_img.jpg';
    $tieude_text = get_setting($conn, 'lienhe_tieude') ?: 'Liên hệ với chúng tôi';
    $noidung_text = get_setting($conn, 'lienhe_noidung') ?: 'Chúng tôi luôn sẵn sàng lắng nghe ý kiến của bạn';
    $email_text = get_setting($conn, 'lienhe_email') ?: 'your-email@example.com';
    $name_text = get_setting($conn, 'lienhe_name') ?: 'Nhập họ tên của bạn';
} catch (Exception $e) {
    // Use default values if any database error occurs
    $image_url = '../uploaded_img/default_img.jpg';
    $tieude_text = 'Liên hệ với chúng tôi';
    $noidung_text = 'Chúng tôi luôn sẵn sàng lắng nghe ý kiến của bạn';
    $email_text = 'your-email@example.com';
    $name_text = 'Nhập họ tên của bạn';
}

$success_message = '';
$error_message = '';

if (isset($_POST['submit'])) {
    try {
        $user_email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $user_name = trim(strip_tags((string)($_POST['name'] ?? '')));
        $message = trim((string)($_POST['noi_dung'] ?? ''));

        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if (filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
            $mail = new PHPMailer(true);

            $mail->isSMTP();
            $mail->Host = $_ENV['SMTP_HOST'] ?: 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['SMTP_USER'] ?: 'your-email@gmail.com';
            $mail->Password = $_ENV['SMTP_PASS'] ?: 'zurvtpnwcfusumju';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($user_email, $user_name);
            $mail->addAddress($email_text);

            $mail->isHTML(true);
            $mail->Subject = "Liên hệ từ $user_name";
            $mail->Body = "
                <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                    <h2 style='color: #4f46e5;'>Tin nhắn liên hệ mới</h2>
                    <p><strong>Từ:</strong> $user_name</p>
                    <p><strong>Email:</strong> $user_email</p>
                    <p><strong>Nội dung:</strong></p>
                    <div style='background: #f3f4f6; padding: 15px; border-radius: 8px; margin: 10px 0;'>
                        " . nl2br($message) . "
                    </div>
                </div>
            ";

            $mail->send();

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Email đã được gửi thành công! Chúng tôi sẽ phản hồi sớm nhất có thể.'
                ]);
                exit;
            } else {
                $success_message = 'Email đã được gửi thành công! Chúng tôi sẽ phản hồi sớm nhất có thể.';
            }
        } else {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Email không hợp lệ. Vui lòng kiểm tra lại.'
                ]);
                exit;
            } else {
                $error_message = 'Email không hợp lệ. Vui lòng kiểm tra lại.';
            }
        }
    } catch (Exception $e) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi gửi email. Vui lòng thử lại sau.'
            ]);
            exit;
        } else {
            $error_message = 'Có lỗi xảy ra khi gửi email. Vui lòng thử lại sau.';
        }
    }
}

// Kiểm tra user đăng nhập
$user_id = $_SESSION['user_id'] ?? '';
$page_title = "Liên hệ - My Blog";
?>

<?php
// Only include layout header if not AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    include '../components/layout_header.php';
}
?>

<?php
// Include breadcrumb component
include '../components/breadcrumb.php';

// Generate breadcrumb for contact page
$breadcrumb_items = auto_breadcrumb('Liên hệ');

// Only render breadcrumb if not AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    render_breadcrumb($breadcrumb_items);
}
?>

<?php
// Only output HTML if not AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
?>

    <main class="min-h-screen pt-4">
        <div class="container-custom py-8">

            <!-- Page Header -->
            <div class="text-center mb-12">
                <h1 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">
                    <i class="fas fa-envelope mr-3 text-main"></i>
                    <?= htmlspecialchars($tieude_text) ?>
                </h1>
                <p class="text-lg text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
                    <?= htmlspecialchars($noidung_text) ?>
                </p>
            </div>

            <!-- Success/Error Messages -->
            <?php if ($success_message): ?>
                <div class="mb-8 max-w-4xl mx-auto">
                    <div class="bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 mr-3"></i>
                            <span class="text-green-800 dark:text-green-200 font-medium"><?= $success_message ?></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="mb-8 max-w-4xl mx-auto">
                    <div class="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                            <span class="text-red-800 dark:text-red-200 font-medium"><?= $error_message ?></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Main Content -->
            <div class="max-w-6xl mx-auto">
                <div class="grid lg:grid-cols-2 gap-12 items-center">

                    <!-- Left Side - Image -->
                    <div class="order-2 lg:order-1">
                        <div class="relative group">
                            <div class="absolute inset-0 bg-gradient-to-r from-main/20 to-purple-500/20 rounded-2xl transform rotate-3 group-hover:rotate-6 transition-transform duration-300"></div>
                            <div class="relative bg-white dark:bg-gray-800 rounded-2xl p-2 shadow-xl">
                                <img src="<?= htmlspecialchars($image_url) ?>"
                                    alt="Hình liên hệ"
                                    class="w-full h-64 md:h-80 object-cover rounded-xl"
                                    loading="lazy">
                            </div>
                        </div>

                        <!-- Contact Info -->
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-2xl p-8 shadow-xl">
                            <div class="flex items-center space-x-3 text-gray-600 dark:text-gray-400">
                                <div class="w-10 h-10 bg-main/10 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-envelope text-main"></i>
                                </div>
                                <span><?= htmlspecialchars($email_text) ?></span>
                            </div>
                            <div class="flex items-center space-x-3 text-gray-600 dark:text-gray-400">
                                <div class="w-10 h-10 bg-main/10 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-clock text-main"></i>
                                </div>
                                <span>Phản hồi trong vòng 24 giờ</span>
                            </div>
                            <div class="flex items-center space-x-3 text-gray-600 dark:text-gray-400">
                                <div class="w-10 h-10 bg-main/10 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-heart text-main"></i>
                                </div>
                                <span>Hỗ trợ 24/7</span>
                            </div>
                        </div>
                    </div>

                    <!-- Right Side - Form -->
                    <div class="order-1 lg:order-2">
                        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-8">
                            <div class="mb-6">
                                <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                                    <i class="fas fa-paper-plane mr-2 text-main"></i>
                                    Gửi tin nhắn
                                </h2>
                                <p class="text-gray-600 dark:text-gray-400">
                                    Điền thông tin bên dưới và chúng tôi sẽ liên hệ với bạn sớm nhất
                                </p>
                            </div>

                            <form action="" method="post" class="space-y-6" aria-label="Form liên hệ">
                                <!-- Email -->
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        <i class="fas fa-envelope mr-2 text-main"></i>
                                        Email *
                                    </label>
                                    <input type="email"
                                        id="email"
                                        name="email"
                                        placeholder="<?= htmlspecialchars($email_text) ?>"
                                        required
                                        class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg 
                                              bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white
                                              focus:outline-none focus:ring-2 focus:ring-main focus:border-transparent
                                              transition-all duration-200 placeholder-gray-500">
                                </div>

                                <!-- Name -->
                                <div>
                                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        <i class="fas fa-user mr-2 text-main"></i>
                                        Họ tên *
                                    </label>
                                    <input type="text"
                                        id="name"
                                        name="name"
                                        placeholder="<?= htmlspecialchars($name_text) ?>"
                                        required
                                        class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg 
                                              bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white
                                              focus:outline-none focus:ring-2 focus:ring-main focus:border-transparent
                                              transition-all duration-200 placeholder-gray-500">
                                </div>

                                <!-- Message -->
                                <div>
                                    <label for="noi_dung" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        <i class="fas fa-comment mr-2 text-main"></i>
                                        Nội dung *
                                    </label>
                                    <textarea name="noi_dung"
                                        id="noi_dung"
                                        rows="5"
                                        required
                                        placeholder="Nhập nội dung tin nhắn của bạn..."
                                        class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg 
                                                 bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white
                                                 focus:outline-none focus:ring-2 focus:ring-main focus:border-transparent
                                                 transition-all duration-200 placeholder-gray-500 resize-none"></textarea>
                                </div>

                                <!-- Submit Button -->
                                <div class="pt-4">
                                    <button type="submit"
                                        name="submit"
                                        class="w-full btn-primary group">
                                        <span class="flex items-center justify-center">
                                            <i class="fas fa-paper-plane mr-2 group-hover:translate-x-1 transition-transform"></i>
                                            Gửi tin nhắn
                                        </span>
                                    </button>
                                </div>
                            </form>

                            <!-- Additional Info -->
                            <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                                <p class="text-sm text-gray-600 dark:text-gray-400 text-center">
                                    <i class="fas fa-shield-alt mr-2 text-main"></i>
                                    Thông tin của bạn được bảo mật tuyệt đối
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script>
        // AJAX handler for contact form
        document.addEventListener('DOMContentLoaded', function() {
            const contactForm = document.querySelector('form[aria-label="Form liên hệ"]');
            if (contactForm) {
                contactForm.addEventListener('submit', function(e) {
                    e.preventDefault(); // Prevent page reload

                    const formData = new FormData(this);
                    const submitButton = this.querySelector('button[name="submit"]');
                    const originalHTML = submitButton.innerHTML;

                    // Disable button and show loading state
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<span class="flex items-center justify-center"><i class="fas fa-spinner fa-spin mr-2"></i>Đang gửi...</span>';

                    // Send AJAX request
                    fetch(window.location.href, {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                        .then(response => {
                            // Check if response is JSON
                            const contentType = response.headers.get('content-type');
                            if (contentType && contentType.includes('application/json')) {
                                return response.json();
                            } else {
                                // If not JSON, treat as error and get text
                                return response.text().then(text => {
                                    throw new Error('Server returned HTML instead of JSON: ' + text.substring(0, 200));
                                });
                            }
                        })
                        .then(data => {
                            // Re-enable button
                            submitButton.disabled = false;
                            submitButton.innerHTML = originalHTML;

                            if (data.success) {
                                // Reset form
                                this.reset();
                                // Show success message using Toastify
                                if (typeof toastify !== 'undefined') {
                                    toastify({
                                        text: data.message,
                                        duration: 3000,
                                        gravity: "top",
                                        position: "right",
                                        style: {
                                            background: "linear-gradient(to right, #00b09b, #96c93d)"
                                        },
                                    }).showToast();
                                } else {
                                    alert(data.message);
                                }
                            } else {
                                // Show error message using Toastify
                                if (typeof toastify !== 'undefined') {
                                    toastify({
                                        text: data.message,
                                        duration: 3000,
                                        gravity: "top",
                                        position: "right",
                                        style: {
                                            background: "linear-gradient(to right, #ff5f6d, #ffc371)"
                                        },
                                    }).showToast();
                                } else {
                                    alert(data.message);
                                }
                            }
                        })
                        .catch(error => {
                            // Re-enable button
                            submitButton.disabled = false;
                            submitButton.innerHTML = originalHTML;

                            // Check if it's a JSON parsing error
                            if (error.message.includes('Server returned HTML')) {
                                if (typeof toastify !== 'undefined') {
                                    toastify({
                                        text: 'Có lỗi máy chủ. Vui lòng thử lại sau!',
                                        duration: 3000,
                                        gravity: "top",
                                        position: "right",
                                        style: {
                                            background: "linear-gradient(to right, #ff5f6d, #ffc371)"
                                        },
                                    }).showToast();
                                } else {
                                    alert('Có lỗi máy chủ. Vui lòng thử lại sau!');
                                }
                                console.error('Server error:', error.message);
                            } else {
                                // Show error message
                                if (typeof toastify !== 'undefined') {
                                    toastify({
                                        text: 'Có lỗi kết nối!',
                                        duration: 3000,
                                        gravity: "top",
                                        position: "right",
                                        style: {
                                            background: "linear-gradient(to right, #ff5f6d, #ffc371)"
                                        },
                                    }).showToast();
                                } else {
                                    alert('Có lỗi kết nối!');
                                }
                                console.error('Error:', error);
                            }
                        });
                });
            }
        });
    </script>

    <?php
    // Only include layout footer if not AJAX request
    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
        include '../components/layout_footer.php';
    }
    ?>

<?php
} // End of AJAX check
?>
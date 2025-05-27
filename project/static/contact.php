<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../vendor/autoload.php';
include '../components/connect.php';

// Lấy các giá trị settings
function get_setting($conn, $key) {
    $stmt = $conn->prepare("SELECT setting_value FROM `settings` WHERE setting_key = ?");
    $stmt->execute([$key]);
    return $stmt->fetchColumn();
}

$image_url = get_setting($conn, 'lienhe_image');
$tieude_text = get_setting($conn, 'lienhe_tieude');
$noidung_text = get_setting($conn, 'lienhe_noidung');
$email_text = get_setting($conn, 'lienhe_email');
$name_text = get_setting($conn, 'lienhe_name');

if (isset($_POST['submit'])) {
    $user_email = $_POST['email'];
    $user_name = $_POST['name'];
    $message = $_POST['noi_dung'];

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USER'];
        $mail->Password = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom($user_email, $user_name);
        $mail->addAddress($email_text);

        $mail->isHTML(true);
        $mail->Subject = "Liên hệ từ $user_name";
        $mail->Body = "Email: $user_email<br><br>Nội dung:<br>$message";

        $mail->send();
        echo "<script>alert('Email đã được gửi thành công!');</script>";
    } catch (Exception $e) {
        echo "<script>alert('Gửi email thất bại. Vui lòng thử lại sau.');</script>";
    }
}
?>

<main>
    <section class="contact" id="contact" aria-label="Liên hệ">
        <figure class="contact_left">
            <img src="<?= htmlspecialchars($image_url) ?>" alt="Hình liên hệ" loading="lazy">
        </figure>
        <div class="contact_right">
            <header class="contact_right-top">
                <h1><?= htmlspecialchars($tieude_text) ?></h1>
                <p><?= htmlspecialchars($noidung_text) ?></p>
            </header>
            <section class="contact_right-bottom">
                <h2>Liên hệ</h2>
                <form action="" class="book_box" method="post" aria-label="Form liên hệ">
                    <div>
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" placeholder="<?= htmlspecialchars($email_text) ?>" required>
                    </div>
                    <div>
                        <label for="name">Họ tên</label>
                        <input type="text" id="name" name="name" placeholder="<?= htmlspecialchars($name_text) ?>" required>
                    </div>
                    <div>
                        <label for="noi_dung">Nội dung</label>
                        <textarea name="noi_dung" id="noi_dung" cols="20" rows="4" required></textarea>
                    </div>
                    <button type="submit" name="submit" id="btnGui">Gửi</button>
                </form>
            </section>
        </div>
    </section>
</main>

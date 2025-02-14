<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../vendor/autoload.php';

include '../components/connect.php';

$select_lienhe_image = $conn->prepare("SELECT setting_value FROM `settings` WHERE setting_key = 'lienhe_image'");
$select_lienhe_image->execute();
$image_url = $select_lienhe_image->fetchColumn();

$select_lienhe_tieude = $conn->prepare("SELECT setting_value FROM `settings` WHERE setting_key = 'lienhe_tieude'");
$select_lienhe_tieude->execute();
$tieude_text = $select_lienhe_tieude->fetchColumn();

$select_lienhe_noidung = $conn->prepare("SELECT setting_value FROM `settings` WHERE setting_key = 'lienhe_noidung'");
$select_lienhe_noidung->execute();
$noidung_text = $select_lienhe_noidung->fetchColumn();

$select_email = $conn->prepare("SELECT setting_value FROM `settings` where setting_key = 'lienhe_email'");
$select_email->execute();
$email_text = $select_email->fetchColumn();

$select_name = $conn->prepare("SELECT setting_value FROM `settings` where setting_key = 'lienhe_name'");
$select_name->execute();
$name_text = $select_name->fetchColumn();

if (isset($_POST['submit'])) {
    $user_email = $_POST['email'];
    $user_name = $_POST['name'];
    $message = $_POST['noi_dung'];

    $mail = new PHPMailer(true);

    try {
        //Server settings
        $mail->isSMTP();
        $mail->Host = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USER'];
        $mail->Password = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->CharSet = 'UTF-8';

        //Recipients
        $mail->setFrom($user_email, $user_name);
        $mail->addAddress($email_text);

        //Content
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

<section class="contact" id="contact">
    <div class="contact_left">
        <img src="<?= $image_url ?>" alt="">
    </div>
    <div class="contact_right">
        <div class="contact_right-top">
            <h1><?= $tieude_text ?></h1>
            <p><?= $noidung_text ?></p>
        </div>
        <div class="contact_right-bottom">
            <h1>LIÊN HỆ</h1>
            <form action="" class="book_box" method="post">
                <h2>Email</h2>
                <input type="email" name="email" placeholder="<?= $email_text ?>" required>
                <h2>Họ tên</h2>
                <input type="text" name="name" placeholder="<?= $name_text ?>" required>
                <h2>Nội dung</h2>
                <textarea name="noi_dung" id="noi_dung" cols="20" rows="4" required></textarea>
                <button type="submit" name="submit" id="btnGui">Gửi</button>
            </form>
        </div>
    </div>
</section>
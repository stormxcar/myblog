<?php
include '../components/connect.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../vendor/autoload.php';

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
            $mail->Body = "Mã xác nhận của bạn là: $reset_code<br><br>Vui lòng nhấp vào liên kết sau để đặt lại mật khẩu của bạn: <a href='http://localhost/blogging%20website/project/static/reset_pass.php?email=$email&code=$reset_code'>Đặt lại mật khẩu</a>";

            $mail->send();
            echo "<script>alert('Mã xác nhận đã được gửi đến email của bạn.');</script>";
        } catch (Exception $e) {
            echo "<script>alert('Gửi email thất bại. Vui lòng thử lại sau.');</script>";
        }
    } else {
        echo "<script>alert('Email không tồn tại trong hệ thống.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quên mật khẩu</title>

    <!-- font awesome cdn link  -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <!-- custom css file link  -->
    <link rel="stylesheet" href="../css/style_edit.css">
    <link rel="stylesheet" href="../css/style_dark.css">
</head>

<body>

    <!-- header section starts  -->
    <?php include '../components/user_header.php'; ?>
    <!-- header section ends -->

    <section class="form-container">
        <form action="" method="post">
            <h3>Quên mật khẩu</h3>
            <label for="email">
                <span>Email:</span>
                <input type="email" name="email" required placeholder="Nhập email của bạn" class="box" maxlength="50" oninput="this.value = this.value.replace(/\s/g, '')">
            </label>
            <input type="submit" value="Gửi mã xác nhận" name="submit" class="btn">
        </form>
    </section>

    <?php include '../components/footer.php'; ?>

</body>

</html>
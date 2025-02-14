<?php
include '../components/connect.php';

// Kiểm tra sự tồn tại của các tham số email và code trong URL
if (!isset($_GET['email']) || !isset($_GET['code'])) {
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
            echo "<script>alert('Mật khẩu của bạn đã được thay đổi thành công.');</script>";
        } else {
            echo "<script>alert('Mật khẩu xác nhận không khớp.');</script>";
        }
    } else {
        echo "<script>alert('Mã xác nhận không đúng.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt lại mật khẩu</title>

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
            <h3>Đặt lại mật khẩu</h3>
            <label for="email">
                <span>Email:</span>
                <input type="email" name="email" required placeholder="Nhập email của bạn" class="box" maxlength="50" oninput="this.value = this.value.replace(/\s/g, '')">
            </label>
            <label for="reset_code">
                <span>Mã xác nhận:</span>
                <input type="text" name="reset_code" required placeholder="Nhập mã xác nhận" class="box" maxlength="50" oninput="this.value = this.value.replace(/\s/g, '')">
            </label>
            <label for="new_pass">
                <span>Mật khẩu mới:</span>
                <input type="password" name="new_pass" required placeholder="Nhập mật khẩu mới" class="box" maxlength="50" oninput="this.value = this.value.replace(/\s/g, '')">
            </label>
            <label for="confirm_pass">
                <span>Xác nhận mật khẩu:</span>
                <input type="password" name="confirm_pass" required placeholder="Xác nhận mật khẩu mới" class="box" maxlength="50" oninput="this.value = this.value.replace(/\s/g, '')">
            </label>
            <input type="submit" value="Đặt lại mật khẩu" name="submit" class="btn">
        </form>
    </section>

    

</body>

</html>
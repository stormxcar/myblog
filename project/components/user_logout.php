<?php
include 'connect.php';
include 'security_helpers.php';

session_start();

blog_forget_remember_login($conn);

// Lưu thông báo vào SESSION trước khi hủy và hủy SESSION
$_SESSION['message'] = "Đăng xuất tài khoản thành công! <br> Vui lòng đăng nhập tài khoản khác để tiếp tục";

// Hủy SESSION
session_unset();
session_destroy();

// Chuyển hướng đến trang home.php
header('Location: ../static/home.php');
exit;

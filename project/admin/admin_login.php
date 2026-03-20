<?php
session_start();
if (!empty($_SESSION['admin_id'])) {
   header('location:dashboard.php');
   exit;
}

$_SESSION['flash_message'] = 'Vui lòng đăng nhập bằng form chung.';
$_SESSION['flash_type'] = 'info';
header('location:../static/login.php?next=admin');
exit;

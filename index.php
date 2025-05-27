<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Kiểm tra file home.php có tồn tại không
if (!file_exists(__DIR__ . '/static/home.php')) {
    die("Error: home.php not found in static directory.");
}

// Chuyển hướng đến static/home.php
header("Location: static/home.php");
exit;
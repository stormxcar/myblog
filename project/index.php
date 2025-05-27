<?php

// Kiểm tra nếu URL không tồn tại
if (!file_exists($_SERVER['REQUEST_URI']) && !is_dir($_SERVER['REQUEST_URI'])) {
    http_response_code(404);
    include '404.php';
    exit;
}


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Kiểm tra file home.php có tồn tại không
if (!file_exists(__DIR__ . '/static/home.php')) {
    die("Error: home.php not found in static directory.");
}

header("Location: /static/home.php");
exit;

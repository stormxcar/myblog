<?php
require 'vendor/autoload.php'; // Nếu dùng Composer
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
  $url = parse_url(getenv("JAWSDB_URL"));
  $servername = $url["host"];
  $username = $url["user"];
  $password = $url["pass"];
  $dbname = substr($url["path"], 1);
  $port = $url["port"] ?? 3306; // Lấy port từ JAWSDB_URL, mặc định 3306 nếu không có

  $conn = new PDO("mysql:host=$servername;port=$port;dbname=$dbname", $username, $password);
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  // Kiểm tra kết nối
  echo "Connected successfully to the database.";
} catch (PDOException $e) {
  echo "Connection failed: " . $e->getMessage();
}
<?php
require __DIR__ . '/../../vendor/autoload.php'; // Điều chỉnh đường dẫn lên thư mục gốc
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../'); // Đường dẫn đến thư mục chứa .env
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
    $port = $url["port"] ?? 3306;

    $conn = new PDO("mysql:host=$servername;port=$port;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>
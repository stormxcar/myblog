<?php
require __DIR__ . '/../../vendor/autoload.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    // Chỉ load .env nếu không chạy trên Heroku
    if (getenv('DYNO') === false) { // Heroku thiết lập biến DYNO
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();
    }

    $url = parse_url(getenv("JAWSDB_URL"));
    if ($url === false) {
        throw new Exception("JAWSDB_URL environment variable not set.");
    }

    $servername = $url["host"];
    $username = $url["user"];
    $password = $url["pass"];
    $dbname = substr($url["path"], 1);
    $port = $url["port"] ?? 3306;

    $conn = new PDO("mysql:host=$servername;port=$port;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    echo "Connection failed: " . $e->getMessage();
    exit;
}
?>
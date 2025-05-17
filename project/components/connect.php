<?php
require 'vendor/autoload.php'; // Nếu dùng Composer
$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();

try {
    $url = parse_url(getenv("CLEARDB_DATABASE_URL"));
    $servername = $url["host"];
    $username = $url["user"];
    $password = $url["pass"];
    $dbname = substr($url["path"], 1);

    $conn = new PDO("mysql:host=$servername;port=3306;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>
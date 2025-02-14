<?php

require '../../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// localhost
// sql309.infinityfree.com
$servername = $_ENV['DB_HOST'];
// root
// if0_36177097
$username = $_ENV['DB_USER'];
// " "
// DQeaxOvzlDUM6
$password = $_ENV['DB_PASS'];
// blog_db
// if0_36177097_blog_db2
$dbname = $_ENV['DB_NAME'];

try {
  // dbname : blog_db
  $conn = new PDO("mysql:host=$servername;port=3306;dbname=$dbname", $username, $password);
  // set the PDO error mode to exception
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  // echo "connect succesfully";
} catch(PDOException $e) {
  echo "Connection failed: " . $e->getMessage();
}
?>

<?php

$servername = 'db'; // Tên service MySQL trong docker-compose.yml
$username = 'root';
$password = 'root';
$dbname = 'blog_db';

try {
  // dbname : blog_db
  $conn = new PDO("mysql:host=$servername;port=3306;dbname=$dbname", $username, $password);
  // set the PDO error mode to exception
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  // echo "connect successfully";
} catch(PDOException $e) {
  echo "Connection failed: " . $e->getMessage();
}
?>

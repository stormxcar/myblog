<?php
// localhost
// sql309.infinityfree.com
$servername = "localhost";
// root
// if0_36177097
$username = "root";
// " "
// DQeaxOvzlDUM6
$password = "";
// blog_db
// if0_36177097_blog_db2
$dbname = "blog_db";

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

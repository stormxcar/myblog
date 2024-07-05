<?php

include '../components/connect.php';

session_start();

if (isset($_SESSION['user_id'])) {
   $user_id = $_SESSION['user_id'];
} else {
   $user_id = '';
};

include '../components/like_post.php';

$select_tag = $conn->prepare("SELECT name FROM `cart`");
$select_tag->execute();
$tag_text = $select_tag->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>category</title>

   <!-- font awesome cdn link  -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

   <!-- custom css file link  -->
   <link rel="stylesheet" href="../css/style_edit.css">

</head>

<body>

   <!-- header section starts  -->
   <?php include '../components/user_header.php'; ?>
   <!-- header section ends -->

   <section class="categories">

      <h1 class="heading">Thể loại bài đăng</h1>

      <div class="box-container">

         <?php
         $index = 1;
         foreach ($tag_text as $tag) {
            $category_name = htmlspecialchars($tag['name']);
            $category_link = urlencode($tag['name']);
            echo '<div class="box"><span>' . sprintf('%02d', $index) . '</span><a href="category.php?category=' . $category_link . '">' . $category_name . '</a></div>';
            $index++;
         }
         ?>
      </div>

   </section>


   <?php include '../components/footer.php'; ?>


   <!-- custom js file link  -->
   <script src="../js/script_edit.js"></script>
   <script src="../js/script.js"></script>
</body>

</html>
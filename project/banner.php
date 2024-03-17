<?php

include 'components/connect.php';

session_start();

if(isset($_SESSION['user_id'])){
   $user_id = $_SESSION['user_id'];
}else{
   $user_id = '';
};


?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>posts</title>

   <!-- font awesome cdn link  -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

   <link rel="stylesheet" href="css/style_edit.css">

</head>
<body>
   
<?php include 'components/user_header.php'; ?>

<section class="banner-container">

   <h1 class="heading">Banner</h1>

   <div class="box-container">
   <div class="slider-container">
    <div class="slider">
        <div class="slide">
            <img src="uploaded_img/<?= $fetch_posts['image']; ?>" class="post-image" alt="Slide 1">
            <div class="caption">
                <h2>Tiêu đề Slide 1</h2>
                <button class="btn">Button 1</button>
            </div>
        </div>
        <div class="slide">
            <img src="https://unsplash.com/photos/two-smiling-woman-hurry-to-the-taxi--5tSGRZCGw4" alt="Slide 2">
            <div class="caption">
                <h2>Tiêu đề Slide 2</h2>
                <button class="btn">Button 2</button>
            </div>
        </div>
        <div class="slide">
            <img src="https://unsplash.com/photos/two-smiling-woman-hurry-to-the-taxi--5tSGRZCGw4" alt="Slide 3">
            <div class="caption">
                <h2>Tiêu đề Slide 3</h2>
                <button class="btn">Button 3</button>
            </div>
        </div>
    </div>
   </div>
      
   </div>

</section>







<?php include 'components/footer.php'; ?>

<script src="js/script.js"></script>

</body>
</html>
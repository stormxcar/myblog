<?php

include 'components/connect.php';

session_start();

if(isset($_SESSION['user_id'])){
   $user_id = $_SESSION['user_id'];
}else{
   $user_id = '';
};

include 'components/like_post.php';

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
   <link rel="stylesheet" href="css/style_edit.css">

</head>
<body>
   
<!-- header section starts  -->
<?php include 'components/user_header.php'; ?>
<!-- header section ends -->




<section class="categories">

   <h1 class="heading">Thể loại bài đăng</h1>

   <div class="box-container">
      <div class="box"><span>01</span><a href="category.php?category=nature">Thiên nhiên</a></div>
      <div class="box"><span>02</span><a href="category.php?category=eduction">Giáo dục</a></div>
      <div class="box"><span>03</span><a href="category.php?category=pets and animals">Thú Cưng</a></div>
      <div class="box"><span>04</span><a href="category.php?category=technology">Công Nghệ</a></div>
      <div class="box"><span>05</span><a href="category.php?category=fashion">Thời Trang</a></div>
      <div class="box"><span>06</span><a href="category.php?category=entertainment">Giải Trí</a></div>
      <div class="box"><span>07</span><a href="category.php?category=movies">Phim</a></div>
      <div class="box"><span>08</span><a href="category.php?category=gaming">Trò Chơi</a></div>
      <div class="box"><span>09</span><a href="category.php?category=music">Âm Nhạc</a></div>
      <div class="box"><span>10</span><a href="category.php?category=sports">Thể Thao</a></div>
      <div class="box"><span>11</span><a href="category.php?category=news">Tin Tức</a></div>
      <div class="box"><span>12</span><a href="category.php?category=travel">Du Lịch</a></div>
      <div class="box"><span>13</span><a href="category.php?category=comedy">Hài Hước</a></div>
      <div class="box"><span>14</span><a href="category.php?category=design and development">Thiết kế</a></div>
      <div class="box"><span>15</span><a href="category.php?category=food and drinks">Thức ăn và Đồ uống</a></div>
      <div class="box"><span>16</span><a href="category.php?category=lifestyle">Phong Cách Sống</a></div>
      <div class="box"><span>17</span><a href="category.php?category=health and fitness">Sức khỏe</a></div>
      <div class="box"><span>18</span><a href="category.php?category=business">Kinh Doanh</a></div>
      <div class="box"><span>19</span><a href="category.php?category=shopping">Mua Sắm</a></div>
      <div class="box"><span>20</span><a href="category.php?category=animations">Hoạt Hình</a></div>
   </div>

</section>


<?php include 'components/footer.php'; ?>







<!-- custom js file link  -->
<script src="./js/script.js"></script>
</body>
<script src="./js/script.js"></script>
</html>
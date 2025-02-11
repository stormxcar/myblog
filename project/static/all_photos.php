<?php
include '../components/connect.php';

session_start();

if (isset($_SESSION['user_id'])) {
   $user_id = $_SESSION['user_id'];
} else {
   $user_id = '';
};

?>

<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Tất cả ảnh</title>

   <!-- font awesome cdn link  -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
   <!-- custom css file link  -->
   <link rel="stylesheet" href="../css/style_edit.css">
   <link rel="stylesheet" href="../css/style_dark.css">

   <script src="https://cdnjs.cloudflare.com/ajax/libs/masonry/4.2.2/masonry.pkgd.min.js"></script>
</head>

<body>
   <?php include '../components/user_header.php'; ?>

   <section style="margin-top: 5rem;" class="photo-gallery">
      <h1 class="heading">Tất cả ảnh</h1>
      <div class="gallery-grid">
      <?php
      // Truy vấn để lấy tất cả các bức ảnh từ cơ sở dữ liệu
      $select_photos = $conn->prepare("SELECT image FROM `posts` WHERE status = ? ORDER BY id DESC");
      $select_photos->execute(['active']);

      if ($select_photos->rowCount() > 0) {
         while ($fetch_photos = $select_photos->fetch(PDO::FETCH_ASSOC)) {
            $photo_url = '../uploaded_img/' . $fetch_photos['image'];
            $photo_alt = $fetch_photos['image'];
      ?>
            <div class="gallery-item">
               <img src="<?= $photo_url; ?>" alt="<?= $photo_alt; ?>">
            </div>
      <?php
         }
      } else {
         echo '<p class="empty">Chưa có ảnh nào được thêm!</p>';
      }
      ?>
      </div>
   </section>

   <?php include '../components/footer.php'; ?>

   <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-element-bundle.min.js"></script>
   <script defer src="../js/script_edit.js"></script>
   <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
</body>

<script>
   AOS.init();

   document.addEventListener('DOMContentLoaded', function () {
      var elem = document.querySelector('.gallery-grid');
      var msnry = new Masonry(elem, {
         // options
         itemSelector: '.gallery-item',
         columnWidth: '.gallery-item',
         percentPosition: true
      });
    });
</script>

</html>
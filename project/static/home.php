<?php
include '../components/connect.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
$message = [];

if (isset($_SESSION['user_id'])) {
   $user_id = $_SESSION['user_id'];
} else {
   $user_id = '';
   if (isset($_POST['save_post']) && isset($_POST['post_id'])) {
      $_SESSION['message'] = 'Bạn cần đăng nhập để lưu bài viết này!';
      header('Location: ../static/login.php');
      exit;
   }
};

include '../components/like_post.php';
// Truy vấn để lấy đường dẫn ảnh
$settings_query = $conn->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('banner_slide_1', 'banner_slide_2', 'banner_slide_3', 'banner_slide_4')");
$settings_query->execute();
$settings = $settings_query->fetchAll(PDO::FETCH_KEY_PAIR);

// Đảm bảo rằng tất cả các banner có giá trị mặc định nếu không có trong database
$default_images = [
   'banner_slide_1' => '../uploaded_img/default_img.jpg',
   'banner_slide_2' => '../uploaded_img/default_img.jpg',
   'banner_slide_3' => '../uploaded_img/default_img.jpg',
   'banner_slide_4' => '../uploaded_img/default_img.jpg',
];

$banner_images = array_merge($default_images, $settings);

// Xử lý lưu bài viết
if (isset($_POST['save_post']) && isset($_POST['post_id']) && !empty($user_id)) {
   $post_id = $_POST['post_id'];

   $stmt_check = $conn->prepare("SELECT * FROM favorite_posts WHERE user_id = ? AND post_id = ?");
   $stmt_check->execute([$user_id, $post_id]);

   if ($stmt_check->rowCount() > 0) {
      $stmt_delete = $conn->prepare("DELETE FROM favorite_posts WHERE user_id = ? AND post_id = ?");
      $stmt_delete->execute([$user_id, $post_id]);
      $_SESSION['message'] = 'Đã xóa bài viết được lưu';
   } else {
      $stmt_insert = $conn->prepare("INSERT INTO favorite_posts (user_id, post_id) VALUES (?, ?)");
      $stmt_insert->execute([$user_id, $post_id]);
      $_SESSION['message'] = 'Đã lưu bài viết vào danh sách yêu thích';
   }

   // Redirect hoặc xử lý tiếp theo sau khi lưu thay đổi
   header("Location: " . $_SERVER['PHP_SELF']);
   exit;
}

// Truy vấn các bài viết từ cơ sở dữ liệu
$select_posts = $conn->prepare("SELECT * FROM `posts` WHERE status = ? ORDER BY date DESC LIMIT 5");
$select_posts->execute(['active']);
$posts = $select_posts->fetchAll(PDO::FETCH_ASSOC);

?>


<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Trang chủ</title>

   <!-- font awesome cdn link  -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
   <!-- custom css file link  -->
   <link rel="stylesheet" href="../css/style_edit.css">
   <link rel="stylesheet" href="../css/style_dark.css">
   <!-- custom js file link -->
   <!-- <script defer src="../js/script.js"></script> -->

   <link rel="stylesheet" href="https://unpkg.com/tippy.js@6/dist/tippy.css">

   <!-- Script framework -->
   <script src="https://unpkg.com/@popperjs/core@2"></script>
   <script src="https://unpkg.com/tippy.js@6"></script>
   <script src="https://cdnjs.cloudflare.com/ajax/libs/masonry/4.2.2/masonry.pkgd.min.js"></script>

</head>

<body>
   <div id="loader-wrapper" class="loader-wrapper">
      <div class="loader"></div>
   </div>

   <?php include '../components/user_header.php'; ?>

   <?php if (isset($_SESSION['message'])) : ?>
      <div class="message" id="message">
         <div class="message_detail">
            <i class="fa-solid fa-bell"></i>
            <span><?= $_SESSION['message'] ?></span>
         </div>

         <div class="progress-bar" id="progressBar"></div>
      </div>
   <?php
      unset($_SESSION['message']);
   endif;
   ?>

   <div class="banner-container">
      <h1 class="heading"></h1>

      <swiper-container class="mySwiper" pagination="true" pagination-clickable="true" navigation="true" space-between="30" centered-slides="true" autoplay-delay="5000" autoplay-disable-on-interaction="false">
         <?php foreach ($posts as $post) : ?>
            <swiper-slide>
            <div class="background-blur" style="background-image: linear-gradient(to top, rgba(0, 0, 0, 0.7) 70%, rgba(0, 0, 0, 0) 100%), url('../uploaded_img/<?= $post['image']; ?>');"></div>
               <div class="banner_content">
                  <!-- <div class="img_main_banner">
                     <img src="../uploaded_img/<?= $post['image']; ?>" alt="">
                  </div> -->
                  <div class="post-slide">
                     <?php
                     $title = $post['title'];
                     $shortTitle = mb_substr($title, 0 , 60) . '...';

                     $content = $post['content'];
                     $shortContent = mb_substr($content, 0, 100) . '...';
                     ?>

                     <a 
                     style="font-weight: bold;font-size: 7rem;margin-bottom: 1rem;"
                     href="view_post.php?post_id=<?= $post['id']; ?>" class="post-title"><?= $shortTitle; ?></a>
                     <a href="view_post.php?post_id=<?= $post['id']; ?>" class="post-content content-200"><?= $post['content']; ?></a>
                     <!-- <a href="view_post.php?post_id=<?= $post['id']; ?>" class="inline-btn" style="border: none !important;">Đọc thêm</a> -->
                  </div>
               </div>

            </swiper-slide>
         <?php endforeach; ?>
      </swiper-container>
   </div>


   <?php include './introduce.php' ?>

   <!-- posts part -->
   <section class="posts-container" id="news">

      <h1 class="heading">Bài viết phổ biến</h1>

      <?php
      $count_posts = $conn->prepare("SELECT COUNT(*) FROM `posts`");
      $count_posts->execute();
      $num_posts = $count_posts->fetchColumn();

      if ($num_posts >= 4) {
      ?>

         <div class="btn_post">
            <button class="prev" id="prevBtn"><i class="fa-solid fa-chevron-left"></i></button>
            <button class="next" id="nextBtn"><i class="fa-solid fa-chevron-right"></i></button>
         </div>

      <?php
      }
      ?>

      <div class="box-container">

         <?php
         $select_posts = $conn->prepare("SELECT * FROM `posts` WHERE status = ?");
         $select_posts->execute(['active']);
         if ($select_posts->rowCount() > 0) {
            while ($fetch_posts = $select_posts->fetch(PDO::FETCH_ASSOC)) {

               $post_id = $fetch_posts['id'];

               $count_post_comments = $conn->prepare("SELECT * FROM `comments` WHERE post_id = ?");
               $count_post_comments->execute([$post_id]);
               $total_post_comments = $count_post_comments->rowCount();

               $count_post_likes = $conn->prepare("SELECT * FROM `likes` WHERE post_id = ?");
               $count_post_likes->execute([$post_id]);
               $total_post_likes = $count_post_likes->rowCount();

               $confirm_likes = $conn->prepare("SELECT * FROM `likes` WHERE user_id = ? AND post_id = ?");
               $confirm_likes->execute([$user_id, $post_id]);

               $confirm_save = $conn->prepare("SELECT * FROM `favorite_posts` WHERE user_id = ? AND post_id = ?");
               $confirm_save->execute([$user_id, $post_id]);
         ?>
               <form class="box_byPost" method="post">
                  <input type="hidden" name="post_id" value="<?= $post_id; ?>">
                  <input type="hidden" name="admin_id" value="<?= $fetch_posts['admin_id']; ?>">
                  <div class="post-admin">
                     <div class="details_left">
                        <i class="fas fa-user"></i>
                        <div>
                           <a href="author_posts.php?author=<?= $fetch_posts['name']; ?>"><?= $fetch_posts['name']; ?></a>
                           <div><?= $fetch_posts['date']; ?></div>
                        </div>
                     </div>
                     <button type="submit" name="save_post" class="save_mark-btn"><i class="fa-solid fa-bookmark" style="<?php if ($confirm_save->rowCount() > 0) {
                                                                                                                              echo 'color:yellow;';
                                                                                                                           } ?>  "></i></button>
                  </div>

                  <?php
                  if ($fetch_posts['image'] != '') {
                  ?>
                     <img src="../uploaded_img/<?= $fetch_posts['image']; ?>" class="post-image" alt="">
                  <?php
                  }
                  ?>
                  <a href="view_post.php?post_id=<?= $post_id; ?>" class="post-title"><?= $fetch_posts['title']; ?></a>
                  <a href="view_post.php?post_id=<?= $post_id; ?>" class="post-content content-30"><?= $fetch_posts['content']; ?></a>
                  <a href="view_post.php?post_id=<?= $post_id; ?>" class="inline-btn">Đọc thêm</a>
                  <a href="category.php?category=<?= $fetch_posts['category']; ?>" class="post-cat"> <i class="fas fa-tag"></i> <span><?= $fetch_posts['category']; ?></span></a>
                  <div class="icons">
                     <a href="view_post.php?post_id=<?= $post_id; ?>"><i class="fas fa-comment"></i><span>(<?= $total_post_comments; ?>)</span></a>
                     <button type="submit" name="like_post"><i class="fas fa-heart" style="<?php if ($confirm_likes->rowCount() > 0) {
                                                                                                echo 'color:var(--red);';
                                                                                             } ?>  "></i><span>(<?= $total_post_likes; ?>)</span>
                     </button>
                     <!-- <button><i class="fa-solid fa-share-from-square"></i></button> -->
                     <!-- <div class="sharethis-sticky-share-buttons"></div> -->
                  </div>

               </form>
         <?php
            }
         } else {
            echo '<p class="empty">Chưa bài viết nào được thêm!</p>';
         }
         ?>
      </div>


      <div class="more-btn" style="text-align: center; margin-top:1rem;">
         <a href="posts.php" class="inline-btn">Xem tất cả</a>
      </div>


   </section>

   <section class="posts-container-byCategoryAndNewBlog">

      <div class="posts-container-byCategory">
         <div class="top_byCategory">
            <h1 class="heading">Danh mục</h1>
            <div>
               <!-- get all categories in select -->
               <?php
               $select_categories = $conn->prepare("SELECT * FROM `cart`");
               $select_categories->execute();
               $fetch_categories = $select_categories->fetchAll(PDO::FETCH_ASSOC);
               ?>
               <select id="categorySelect" name="category">
                  <option value="all">Tất cả</option>
                  <?php
                  foreach ($fetch_categories as $category) {
                     $category_id = $category['category_id'];
                     $category_name = $category['name'];
                     echo '<option value="' . $category_name . '">' . $category_name . '</option>';
                  }
                  ?>
               </select>
            </div>
         </div>

         <div class="box-container-grid">
            <?php
            // Lấy bài viết cho danh mục đầu tiên là gồm tất cả bài viết
            $first_category = 'all';
            $select_posts_grid = $conn->prepare("SELECT p.*, c.name as category_name, ad.name as author_name FROM posts p
                                                   INNER JOIN cart c ON c.category_id = p.tag_id
                                                   INNER JOIN admin ad ON ad.id = p.admin_id
                                                   WHERE p.status = ?
                                                   ORDER BY p.id DESC LIMIT 4");
            $select_posts_grid->execute(['active']);

            if ($select_posts_grid->rowCount() > 0) {
               while ($fetch_posts = $select_posts_grid->fetch(PDO::FETCH_ASSOC)) {
                  $post_id = $fetch_posts['id'];

                  $count_post_comments = $conn->prepare("SELECT * FROM `comments` WHERE post_id = ?");
                  $count_post_comments->execute([$post_id]);
                  $total_post_comments = $count_post_comments->rowCount();

                  $count_post_likes = $conn->prepare("SELECT * FROM `likes` WHERE post_id = ?");
                  $count_post_likes->execute([$post_id]);
                  $total_post_likes = $count_post_likes->rowCount();

                  $confirm_likes = $conn->prepare("SELECT * FROM `likes` WHERE user_id = ? AND post_id = ?");
                  $confirm_likes->execute([$user_id, $post_id]);

                  $confirm_save = $conn->prepare("SELECT * FROM `favorite_posts` WHERE user_id = ? AND post_id = ?");
                  $confirm_save->execute([$user_id, $post_id]);
            ?>

                  <form class="box_byPost_category" method="post">
                     <input type="hidden" name="post_id" value="<?= $post_id; ?>">
                     <input type="hidden" name="admin_id" value="<?= $fetch_posts['admin_id']; ?>">
                     <div class="post-admin">
                        <div class="details_left">
                           <i class="fas fa-user"></i>
                           <div>
                              <a href="author_posts.php?author=<?= $fetch_posts['author_name']; ?>"><?= $fetch_posts['author_name']; ?></a>
                              <div><?= $fetch_posts['date']; ?></div>
                           </div>
                        </div>
                        <button type="submit" name="save_post" class="save_mark-btn"><i class="fa-solid fa-bookmark" style="<?php if ($confirm_save->rowCount() > 0) {
                                                                                                                                 echo 'color:yellow;';
                                                                                                                              } ?>  "></i></button>
                     </div>

                     <?php
                     if ($fetch_posts['image'] != '') {
                     ?>
                        <img src="../uploaded_img/<?= $fetch_posts['image']; ?>" class="post-image" alt="">
                     <?php
                     }
                     ?>
                     <a href="view_post.php?post_id=<?= $post_id; ?>" class="post-title"><?= $fetch_posts['title']; ?></a>
                     <a href="view_post.php?post_id=<?= $post_id; ?>" class="post-content content-30"><?= $fetch_posts['content']; ?></a>
                     <a href="view_post.php?post_id=<?= $post_id; ?>" class="inline-btn">Đọc thêm</a>
                     <a href="category.php?category=<?= $fetch_posts['category']; ?>" class="post-cat"> <i class="fas fa-tag"></i> <span><?= $fetch_posts['category']; ?></span></a>
                     <div class="icons">
                        <a href="view_post.php?post_id=<?= $post_id; ?>"><i class="fas fa-comment"></i><span>(<?= $total_post_comments; ?>)</span></a>
                        <button type="submit" name="like_post"><i class="fas fa-heart" style="<?php if ($confirm_likes->rowCount() > 0) {
                                                                                                   echo 'color:var(--red);';
                                                                                                } ?>  "></i><span>(<?= $total_post_likes; ?>)</span>
                        </button>
                        <!-- <button><i class="fa-solid fa-share-from-square"></i></button> -->
                     </div>
                  </form>

            <?php
               }
            } else {

               echo '<p class="không có bài viết nào cho thể loại này!</p>';
            }
            ?>
         </div>
         <div class="more-btn btn_byCategory" style="text-align: center; margin-top:1rem;">
            <?php
            if ($first_category == 'all') {
               echo '<a href="posts.php" class="inline-btn" id="viewAllBtn">Xem tất cả</a>';
            } else {
               echo '<a href="category.php?category=' . $first_category . '" class="inline-btn" id="viewAllBtn">Xem tất cả</a>';
            }
            ?>
         </div>

      </div>

      <div class="posts-container-newBlog">
         <h1 class="heading">Bài viết mới nhất</h1>
         <div class="box-container-newBlog">
            <?php
            $select_posts = $conn->prepare("SELECT * FROM `posts` WHERE status = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 5 DAY)");
            $select_posts->execute(['active']);
            if ($select_posts->rowCount() > 0) {
               while ($fetch_posts = $select_posts->fetch(PDO::FETCH_ASSOC)) {
                  $post_id = $fetch_posts['id'];

                  $count_post_comments = $conn->prepare("SELECT * FROM `comments` WHERE post_id = ?");
                  $count_post_comments->execute([$post_id]);
                  $total_post_comments = $count_post_comments->rowCount();

                  $count_post_likes = $conn->prepare("SELECT * FROM `likes` WHERE post_id = ?");
                  $count_post_likes->execute([$post_id]);
                  $total_post_likes = $count_post_likes->rowCount();

                  $confirm_likes = $conn->prepare("SELECT * FROM `likes` WHERE user_id = ? AND post_id = ?");
                  $confirm_likes->execute([$user_id, $post_id]);
            ?>
                  <form class="box_byPost_newBlog" method="post">
                     <input type="hidden" name="post_id" value="<?= $post_id; ?>">
                     <input type="hidden" name="admin_id" value="<?= $fetch_posts['admin_id']; ?>">
                     <div class="post-admin">
                        <div class="details_left">
                           <i class="fas fa-user"></i>
                           <div>
                              <a href="author_posts.php?author=<?= $fetch_posts['name']; ?>"><?= $fetch_posts['name']; ?></a>
                              <div><?= $fetch_posts['date']; ?></div>
                           </div>
                        </div>
                     </div>
                     <a href="view_post.php?post_id=<?= $post_id; ?>" class="post-title"><?= $fetch_posts['title']; ?></a>
                     <div style="display: flex; align-items: center;justify-content: space-between;">
                        <a href="category.php?category=<?= $fetch_posts['category']; ?>" class="post-cat"> <i class="fas fa-tag"></i> <span><?= $fetch_posts['category']; ?></span></a>
                        <div style="display: flex; align-items: center;">
                           <a href="view_post.php?post_id=<?= $post_id; ?>"><i style="font-size: 1.5rem;margin-right: 0.5rem" class="fas fa-comment"></i><span style="font-size: 1.5rem;margin-right: 1rem">(<?= $total_post_comments; ?>)</span></a>
                           <button style="outline: none;" type="submit" name="like_post"><i style="font-size: 1.5rem;margin-right: 0.5rem;" class="fas fa-heart" style="<?php if ($confirm_likes->rowCount() > 0) {
                                                                                                                                                                           echo 'color:var(--red);';
                                                                                                                                                                        } ?>  "></i><span style="font-size: 1.5rem;">(<?= $total_post_likes; ?>)</span>
                           </button>
                        </div>
                     </div>

                  </form>
            <?php
               }
            } else {
               echo '<p class="empty">Chưa bài viết nào được thêm!</p>';
            }
            ?>
         </div>
      </div>

   </section>

   <section class="photo-gallery">
      <h1 class="heading">Bộ sưu tập ảnh</h1>
      <div class="gallery-grid">
         <?php
         // Truy vấn để lấy các bức ảnh từ cơ sở dữ liệu
         $select_photos = $conn->prepare("SELECT image FROM `posts` WHERE status = ? ORDER BY id DESC LIMIT 6");
         $select_photos->execute(['active']);

         // Đếm tổng số ảnh
         $count_photos = $conn->prepare("SELECT COUNT(*) FROM `posts` WHERE status = ?");
         $count_photos->execute(['active']);
         $total_photos = $count_photos->fetchColumn();

         if ($select_photos->rowCount() > 0) {
            $photos = $select_photos->fetchAll(PDO::FETCH_ASSOC);
            foreach ($photos as $index => $photo) {
               $photo_url = '../uploaded_img/' . $photo['image'];
               $photo_alt = $photo['image'];

               if ($index == 5 && $total_photos > 6) {
                  // Ảnh cuối cùng hiển thị số lượng ảnh còn lại
                  $remaining_photos = $total_photos - 6;
         ?>
                  <div class="gallery-item more-photos">
                     <a href="all_photos.php">
                        <img src="<?= $photo_url; ?>" alt="<?= $photo_alt; ?>">
                        <div class="overlay">
                           <span>+<?= $remaining_photos; ?> ảnh</span>
                        </div>
                     </a>
                  </div>
               <?php
               } else {
               ?>
                  <div class="gallery-item">
                     <img src="<?= $photo_url; ?>" alt="<?= $photo_alt; ?>">
                  </div>
         <?php
               }
            }
         } else {
            echo '<p class="empty">Chưa có ảnh nào được thêm!</p>';
         }
         ?>
      </div>
   </section>



   <?php include './contact.php' ?>

   <?php include '../components/footer.php'; ?>

   <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-element-bundle.min.js"></script>
   <script defer src="../js/script_edit.js"></script>
   <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

</body>

<script type="module">
   import Swiper from 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.mjs'

   var swiper = new Swiper(".swiper-container", {
      pagination: {
         el: ".swiper-pagination",
         clickable: true,
      },
      navigation: {
         nextEl: ".swiper-button-next",
         prevEl: ".swiper-button-prev",
      },
      autoplay: {
         delay: 5000,
         disableOnInteraction: false,
      },
      on: {
         slideChange: function() {
            var currentSlide = this.slides[this.activeIndex];
            var modal = currentSlide.querySelector(".modal");
            // Remove any existing animation classes
            modal.classList.remove("slideInLeft", "slideInRight");
            // Add animation class based on direction
            if (this.direction === "next") {
               modal.classList.add("slideInRight");
            } else {
               modal.classList.add("slideInLeft");
            }
            // Show modal
            modal.classList.add("show");
         },
      },
   });

   window.addEventListener("load", function() {
      const loaderWrapper = document.getElementById('loader-wrapper');
      loaderWrapper.style.display = 'none'; // Ẩn loader sau khi trang đã tải xong
   });



   document.addEventListener('DOMContentLoaded', function() {
      var elem = document.querySelector('.gallery-grid');
      var msnry = new Masonry(elem, {
         // options
         itemSelector: '.gallery-item',
         columnWidth: '.gallery-item',
         percentPosition: true
      });


      const categorySelect = document.getElementById('categorySelect');
      const moreBtn = document.querySelector('.btn_byCategory a'); // Lấy thẻ <a> trong more-btn

      categorySelect.addEventListener('change', function() {
         const selectedCategory = this.value;
         fetchPostsByCategory(selectedCategory);
         moreBtn.href = `category.php?category=${selectedCategory}`; // Cập nhật đường dẫn
      });

      function fetchPostsByCategory(category) {
         const xhr = new XMLHttpRequest();
         xhr.open('GET', `fetch_posts.php?category=${category}`, true);
         xhr.onload = function() {
            if (this.status === 200) {
               document.querySelector('.box-container-grid').innerHTML = this.responseText;

               // Kiểm tra nếu có bài viết thì hiển thị nút
               if (this.responseText.includes('class="box_byPost_category"')) {
                  moreBtn.style.display = 'block';
                  moreBtn.href = `category.php?category=${category}`;
               } else {
                  moreBtn.style.display = 'none';
               }

               if (window.totalPosts <= 4) {
                  moreBtn.style.display = 'none';
               } else {
                  moreBtn.style.display = 'block';
               }
            }
         };
         xhr.send();
      }
   });
</script>

<script>
   AOS.init();
</script>

</html>
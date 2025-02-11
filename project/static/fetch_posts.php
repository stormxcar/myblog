<?php
include '../components/connect.php';

session_start();

if (isset($_SESSION['user_id'])) {
   $user_id = $_SESSION['user_id'];
} else {
   $user_id = '';
};

$category = isset($_GET['category']) ? $_GET['category'] : 'all';

// Đếm tổng số bài viết trong danh mục
if ($category === 'all') {
   $count_posts = $conn->prepare("SELECT COUNT(*) FROM posts WHERE status = ?");
   $count_posts->execute(['active']);
} else {
   $count_posts = $conn->prepare("SELECT COUNT(*) FROM posts p
                                  INNER JOIN cart c ON c.category_id = p.tag_id
                                  WHERE p.status = ? AND c.name = ?");
   $count_posts->execute(['active', $category]);
}
$total_posts = $count_posts->fetchColumn();

// Lấy bài viết cho danh mục
if ($category === 'all') {
   $select_posts_grid = $conn->prepare("SELECT p.*, c.name as category_name, ad.name as author_name FROM posts p
                                          INNER JOIN cart c ON c.category_id = p.tag_id
                                          INNER JOIN admin ad ON ad.id = p.admin_id
                                          WHERE p.status = ? ORDER BY p.id DESC LIMIT 4");
   $select_posts_grid->execute(['active']);
} else {
   $select_posts_grid = $conn->prepare("SELECT p.*, c.name as category_name, ad.name as author_name FROM posts p
                                          INNER JOIN cart c ON c.category_id = p.tag_id
                                          INNER JOIN admin ad ON ad.id = p.admin_id
                                          WHERE p.status = ? AND c.name = ?
                                          ORDER BY p.id DESC LIMIT 4");
   $select_posts_grid->execute(['active', $category]);
}

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
         <a href="view_post.php?post_id=<?= $post_id; ?>" class="post-content">
            <?php 
                if(strlen($fetch_posts['content']) > 50){
                    echo substr($fetch_posts['content'], 0, 50) . '...';
                }else{
                    echo $fetch_posts['content'];
                }
            ?>
         </a>
         <a href="view_post.php?post_id=<?= $post_id; ?>" class="inline-btn">Đọc thêm</a>
         <a href="category.php?category=<?= $fetch_posts['category_name']; ?>" class="post-cat"> <i class="fas fa-tag"></i> <span><?= $fetch_posts['category_name']; ?></span></a>
         <div class="icons">
            <a href="view_post.php?post_id=<?= $post_id; ?>"><i class="fas fa-comment"></i><span>(<?= $total_post_comments; ?>)</span></a>
            <button type="submit" name="like_post"><i class="fas fa-heart" style="<?php if ($confirm_likes->rowCount() > 0) {
                                                                                       echo 'color:var(--red);';
                                                                                    } ?>  "></i><span>(<?= $total_post_likes; ?>)</span>
            </button>
            <button><i class="fa-solid fa-share-from-square"></i></button>
         </div>
      </form>
      <?php
   }
} else {
   echo '<p class="empty">Không tìm thấy bài đăng nào cho thể loại này!</p>';
}

// Trả về tổng số bài viết trong danh mục
echo "<script>window.totalPosts = $total_posts;</script>";
?>
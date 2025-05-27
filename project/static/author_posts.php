<?php

include '../components/connect.php';

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
}

$author = isset($_GET['author']) ? $_GET['author'] : '';

include '../components/like_post.php';

// Xử lý lưu bài viết
if (isset($_POST['save_post']) && isset($_POST['post_id']) && !empty($user_id)) {
   $post_id = $_POST['post_id'];
   $stmt_check = $conn->prepare("SELECT * FROM favorite_posts WHERE user_id = ? AND post_id = ?");
   $stmt_check->execute([$user_id, $post_id]);

   if ($stmt_check->rowCount() > 0) {
      $stmt_delete = $conn->prepare("DELETE FROM favorite_posts WHERE user_id = ? AND post_id = ?");
      $stmt_delete->execute([$user_id, $post_id]);
      $_SESSION['message'] = 'Đã xóa khỏi danh sách bài viết yêu thích';
   } else {
      $stmt_insert = $conn->prepare("INSERT INTO favorite_posts (user_id, post_id) VALUES (?, ?)");
      $stmt_insert->execute([$user_id, $post_id]);
      $_SESSION['message'] = 'Đã lưu bài viết yêu thích';
   }
   header("Location: " . $_SERVER['PHP_SELF'] . '?author=' . urlencode($author));
   exit;
}

?>
<!DOCTYPE html>
<html lang="vi">

<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Bài viết của <?= htmlspecialchars($author) ?></title>
   <meta name="description" content="Tổng hợp các bài viết của tác giả <?= htmlspecialchars($author) ?> trên blog.">
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
   <link rel="stylesheet" href="../css/style_edit.css">
   <link rel="stylesheet" href="../css/style_dark.css">
   <script src="../js/script_edit.js"></script>
</head>

<body>
   <?php include '../components/user_header.php'; ?>

   <?php if (isset($_SESSION['message'])) : ?>
      <div class="message" id="message" role="alert">
         <div class="message_detail">
            <i class="fa-solid fa-bell" aria-hidden="true"></i>
            <span><?= $_SESSION['message'] ?></span>
         </div>
         <div class="progress-bar" id="progressBar"></div>
      </div>
      <?php unset($_SESSION['message']); ?>
   <?php endif; ?>

   <main>
      <header class="author-header">
         <h1>Bài viết của <span><?= htmlspecialchars($author) ?></span></h1>
      </header>
      <section class="posts-container" aria-label="Danh sách bài viết">
         <div class="box-container">
            <?php
            $select_posts = $conn->prepare("SELECT * FROM `posts` WHERE name = ? and status = ?");
            $select_posts->execute([$author, 'active']);
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
                  <article class="box" itemscope itemtype="https://schema.org/Article">
                     <form method="post">
                        <input type="hidden" name="post_id" value="<?= $post_id; ?>">
                        <input type="hidden" name="admin_id" value="<?= $fetch_posts['admin_id']; ?>">
                        <header class="post-admin">
                           <div class="details_left">
                              <i class="fas fa-user" aria-hidden="true"></i>
                              <div>
                                 <a href="author_posts.php?author=<?= urlencode($fetch_posts['name']); ?>" title="Xem bài viết của <?= htmlspecialchars($fetch_posts['name']); ?>">
                                    <span itemprop="author"><?= htmlspecialchars($fetch_posts['name']); ?></span>
                                 </a>
                                 <div><time datetime="<?= $fetch_posts['date']; ?>" itemprop="datePublished"><?= $fetch_posts['date']; ?></time></div>
                              </div>
                           </div>
                           <button type="submit" name="save_post" class="save_mark-btn" aria-label="Lưu bài viết">
                              <i class="fa-solid fa-bookmark" style="<?= $confirm_save->rowCount() > 0 ? 'color:yellow;' : '' ?>"></i>
                           </button>
                        </header>
                        <?php if ($fetch_posts['image'] != '') : ?>
                           <img src="../uploaded_img/<?= htmlspecialchars($fetch_posts['image']); ?>" class="post-image" alt="<?= htmlspecialchars($fetch_posts['title']); ?>" itemprop="image">
                        <?php endif; ?>
                        <h2 class="post-title" itemprop="headline"><?= htmlspecialchars($fetch_posts['title']); ?></h2>
                        <div class="post-content content-30" itemprop="articleBody"><?= htmlspecialchars($fetch_posts['content']); ?></div>
                        <a href="view_post.php?post_id=<?= $post_id; ?>" class="inline-btn" title="Đọc thêm về <?= htmlspecialchars($fetch_posts['title']); ?>">Đọc thêm</a>
                        <footer class="icons">
                           <a href="view_post.php?post_id=<?= $post_id; ?>" title="Bình luận">
                              <i class="fas fa-comment" aria-hidden="true"></i>
                              <span>(<?= $total_post_comments; ?>)</span>
                           </a>
                           <button type="submit" name="like_post" aria-label="Thích bài viết">
                              <i class="fas fa-heart" style="<?= $confirm_likes->rowCount() > 0 ? 'color:var(--red);' : '' ?>"></i>
                              <span>(<?= $total_post_likes; ?>)</span>
                           </button>
                        </footer>
                     </form>
                  </article>
            <?php
               }
            } else {
               echo '<p class="empty">Không tìm thấy bài đăng nào cho tác giả này!</p>';
            }
            ?>
         </div>
      </section>
   </main>
   <?php include '../components/footer.php'; ?>
</body>
</html>

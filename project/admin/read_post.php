<?php

include '../components/connect.php';

if (!function_exists('decode_deep')) {
   function decode_deep(string $input, int $depth = 3): string
   {
      $result = $input;
      for ($i = 0; $i < $depth; $i++) {
         $next = html_entity_decode($result, ENT_QUOTES | ENT_HTML5, 'UTF-8');
         if ($next === $result) {
            break;
         }
         $result = $next;
      }
      return $result;
   }
}

session_start();

$admin_id = $_SESSION['admin_id'] ?? null;
if (!$admin_id) {
   header('location:admin_login.php');
   exit;
}

$post_id = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;

if (isset($_POST['delete'])) {
   $p_id = (int)($_POST['post_id'] ?? 0);

   if ($p_id > 0) {
      $delete_image = $conn->prepare("SELECT image, admin_id FROM `posts` WHERE id = ? LIMIT 1");
      $delete_image->execute([$p_id]);
      $fetch_delete_image = $delete_image->fetch(PDO::FETCH_ASSOC);

      if (!$fetch_delete_image || (int)$fetch_delete_image['admin_id'] !== (int)$admin_id) {
         $_SESSION['message'] = 'Bạn không có quyền xóa bài viết này.';
         header('location:read_post.php?post_id=' . (int)$p_id);
         exit;
      }

      if ($fetch_delete_image && !empty($fetch_delete_image['image'])) {
         blog_delete_image_resource((string)$fetch_delete_image['image']);
      }

      $delete_post = $conn->prepare("DELETE FROM `posts` WHERE id = ?");
      $delete_post->execute([$p_id]);

      $delete_comments = $conn->prepare("DELETE FROM `comments` WHERE post_id = ?");
      $delete_comments->execute([$p_id]);

      $_SESSION['message'] = 'Đã xóa bài viết.';
      header('location:view_posts.php');
      exit;
   }
}

if (isset($_POST['delete_comment'])) {
   $comment_id = (int)($_POST['comment_id'] ?? 0);
   if ($comment_id > 0) {
      $delete_comment = $conn->prepare("DELETE FROM `comments` WHERE id = ?");
      $delete_comment->execute([$comment_id]);
      $message[] = 'Bình luận đã được xóa.';
   }
}

$post = null;
$total_post_comments = 0;
$total_post_likes = 0;

if ($post_id > 0) {
   $select_post = $conn->prepare("SELECT * FROM `posts` WHERE id = ? LIMIT 1");
   $select_post->execute([$post_id]);
   $post = $select_post->fetch(PDO::FETCH_ASSOC);

   if ($post) {
      $count_post_comments = $conn->prepare("SELECT COUNT(*) FROM `comments` WHERE post_id = ?");
      $count_post_comments->execute([$post_id]);
      $total_post_comments = (int)$count_post_comments->fetchColumn();

      $count_post_likes = $conn->prepare("SELECT COUNT(*) FROM `likes` WHERE post_id = ?");
      $count_post_likes->execute([$post_id]);
      $total_post_likes = (int)$count_post_likes->fetchColumn();
   }
}

$is_post_owner = $post ? ((int)$post['admin_id'] === (int)$admin_id) : false;
$post_tags = $post ? blog_get_post_tags($conn, $post_id) : [];

?>

<!DOCTYPE html>
<html lang="vi">

<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Chi tiết bài viết</title>

   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
   <style>
      .post-detail-wrap {
         display: grid;
         grid-template-columns: 1fr;
         gap: 1.5rem;
      }

      .post-main-card {
         border: 1px solid rgba(148, 163, 184, 0.28);
         border-radius: 1rem;
         padding: 1.25rem;
         background: rgba(255, 255, 255, 0.88);
      }

      .post-main-card .title {
         font-size: 1.95rem;
         font-weight: 700;
         line-height: 1.35;
         margin: .8rem 0;
         color: #0f172a;
      }

      .post-main-card .content {
         margin-top: 1rem;
         line-height: 1.75;
         color: #1e293b;
      }

      .post-image {
         width: 100%;
         max-height: 420px;
         object-fit: cover;
         border-radius: .9rem;
         border: 1px solid rgba(148, 163, 184, 0.35);
      }

      .status-badge {
         display: inline-flex;
         align-items: center;
         padding: .35rem .7rem;
         border-radius: .6rem;
         font-size: .92rem;
         font-weight: 700;
         text-transform: uppercase;
         letter-spacing: .02em;
      }

      .status-badge.active {
         color: #166534;
         background: #dcfce7;
      }

      .status-badge.deactive {
         color: #b91c1c;
         background: #fee2e2;
      }

      .stats-row {
         margin-top: 1rem;
         display: flex;
         gap: .7rem;
         flex-wrap: wrap;
      }

      .stat-pill {
         border: 1px solid rgba(148, 163, 184, .32);
         border-radius: 999px;
         padding: .5rem .9rem;
         display: inline-flex;
         align-items: center;
         gap: .45rem;
         color: #334155;
         background: #fff;
      }

      .comments-card {
         border: 1px solid rgba(148, 163, 184, 0.28);
         border-radius: 1rem;
         background: rgba(255, 255, 255, 0.9);
         padding: 1.15rem;
      }

      .comment-item {
         border: 1px solid rgba(148, 163, 184, 0.25);
         border-radius: .85rem;
         padding: .9rem;
         background: #ffffff;
      }

      .comment-item+.comment-item {
         margin-top: .9rem;
      }

      .comment-header {
         display: flex;
         align-items: center;
         justify-content: space-between;
         gap: .75rem;
         margin-bottom: .6rem;
      }

      .comment-author {
         display: inline-flex;
         align-items: center;
         gap: .45rem;
         color: #0f172a;
         font-weight: 600;
      }

      .comment-date {
         color: #64748b;
         font-size: .9rem;
      }

      @media (prefers-color-scheme: dark) {

         .post-main-card,
         .comments-card,
         .comment-item,
         .stat-pill {
            background: rgba(15, 23, 42, 0.88);
            border-color: rgba(148, 163, 184, 0.2);
         }

         .post-main-card .title,
         .comment-author {
            color: #f1f5f9;
         }

         .post-main-card .content,
         .stat-pill,
         .comment-date {
            color: #cbd5e1;
         }
      }
   </style>
</head>

<body class="ui-page">

   <?php include '../components/admin_header.php' ?>

   <section class="ui-container" style="padding-bottom: 6rem;">
      <h1 class="heading ui-title">Chi tiết bài viết</h1>

      <?php if (!$post_id): ?>
         <p class="empty">Thiếu mã bài viết. Vui lòng quay lại danh sách bài viết.</p>
      <?php elseif (!$post): ?>
         <p class="empty">Không tìm thấy bài viết hoặc bạn không có quyền truy cập.</p>
      <?php else: ?>
         <div class="post-detail-wrap">
            <article class="post-main-card">
               <form method="post" class="w-full">
                  <input type="hidden" name="post_id" value="<?= (int)$post_id; ?>">

                  <div class="status-badge <?= $post['status'] === 'active' ? 'active' : 'deactive'; ?>">
                     <?= htmlspecialchars((string)$post['status'], ENT_QUOTES, 'UTF-8'); ?>
                  </div>

                  <?php if (!empty($post['image'])): ?>
                     <div style="margin-top: .9rem;">
                        <img src="<?= htmlspecialchars(blog_post_image_src((string)$post['image'], '../uploaded_img/', '../uploaded_img/default_img.jpg'), ENT_QUOTES, 'UTF-8'); ?>" class="post-image" alt="Ảnh bài viết">
                     </div>
                  <?php endif; ?>

                  <h2 class="title"><?= htmlspecialchars(decode_deep((string)$post['title']), ENT_QUOTES, 'UTF-8'); ?></h2>

                  <div class="stats-row">
                     <span class="stat-pill"><i class="fas fa-heart" style="color:#e11d48"></i> <?= (int)$total_post_likes; ?> lượt thích</span>
                     <span class="stat-pill"><i class="fas fa-comment" style="color:#0284c7"></i> <?= (int)$total_post_comments; ?> bình luận</span>
                     <span class="stat-pill"><i class="fas fa-calendar"></i> <?= htmlspecialchars((string)($post['date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                  </div>

                  <?php if (!empty($post_tags)): ?>
                     <div class="stats-row">
                        <?php foreach ($post_tags as $tag): ?>
                           <a href="../static/search.php?tag=<?= urlencode((string)$tag['slug']); ?>"
                              class="stat-pill"
                              style="text-decoration:none;">
                              #<?= htmlspecialchars((string)$tag['name'], ENT_QUOTES, 'UTF-8'); ?>
                           </a>
                        <?php endforeach; ?>
                     </div>
                  <?php endif; ?>

                  <div class="content"><?= $post['content']; ?></div>

                  <div class="flex-btn" style="margin-top:1.3rem; gap:.5rem;">
                     <?php if ($is_post_owner): ?>
                        <a href="edit_post.php?id=<?= (int)$post_id; ?>" class="inline-option-btn">Chỉnh sửa</a>
                     <?php endif; ?>
                     <a href="view_posts.php" class="option-btn ui-btn-warning">Quay lại</a>
                     <?php if ($is_post_owner): ?>
                        <button type="submit" name="delete" class="inline-delete-btn" onclick="return confirm('Bạn có chắc chắn muốn xóa bài viết này không?');">Xóa bài viết</button>
                     <?php endif; ?>
                  </div>
               </form>
            </article>

            <section class="comments-card">
               <p class="comment-title" style="margin-bottom:.85rem;">Bình luận</p>

               <?php
               $select_comments = $conn->prepare("SELECT * FROM `comments` WHERE post_id = ? ORDER BY id DESC");
               $select_comments->execute([(int)$post_id]);
               ?>

               <?php if ($select_comments->rowCount() > 0): ?>
                  <?php while ($fetch_comments = $select_comments->fetch(PDO::FETCH_ASSOC)): ?>
                     <div class="comment-item">
                        <div class="comment-header">
                           <span class="comment-author"><i class="fas fa-user-circle"></i> <?= htmlspecialchars((string)$fetch_comments['user_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                           <span class="comment-date"><?= htmlspecialchars((string)$fetch_comments['date'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>

                        <div class="text" style="margin-bottom:.8rem; color:inherit;">
                           <?= htmlspecialchars((string)$fetch_comments['comment'], ENT_QUOTES, 'UTF-8'); ?>
                        </div>

                        <form action="" method="post">
                           <input type="hidden" name="comment_id" value="<?= (int)$fetch_comments['id']; ?>">
                           <button type="submit" class="inline-delete-btn" name="delete_comment" onclick="return confirm('Bạn có chắc muốn xóa bình luận này?');">Xóa bình luận</button>
                        </form>
                     </div>
                  <?php endwhile; ?>
               <?php else: ?>
                  <p class="empty">Chưa có bình luận nào cho bài viết này.</p>
               <?php endif; ?>
            </section>
         </div>
      <?php endif; ?>
   </section>

   <script src="../js/admin_script.js"></script>

</body>

</html>
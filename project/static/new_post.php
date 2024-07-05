<?php
include '../components/connect.php';

session_start();

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} else {
    $user_id = '';
};

include '../components/like_post.php';

// Xác định trang hiện tại
$items_per_page = 4;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page <= 0) {
    $current_page = 1;
}

// Đếm tổng số bài viết mới đăng trong 3 ngày gần đây
$count_posts = $conn->prepare("SELECT COUNT(*) FROM `posts` WHERE status = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 3 DAY)");
$count_posts->execute(['active']);
$total_posts = $count_posts->fetchColumn();

// Tính tổng số trang
$total_pages = ceil($total_posts / $items_per_page);

// Tính toán giới hạn và bù trừ cho truy vấn SQL
$offset = ($current_page - 1) * $items_per_page;

$select_posts = $conn->prepare("SELECT * FROM `posts` WHERE status = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 3 DAY) LIMIT $items_per_page OFFSET $offset");
$select_posts->execute(['active']);

// Xử lý lưu bài viết
if (isset($_POST['save_post']) && isset($_POST['post_id']) && !empty($user_id)) {
    $post_id = $_POST['post_id'];

    // Kiểm tra xem bài viết đã được lưu chưa
    $stmt_check = $conn->prepare("SELECT * FROM favorite_posts WHERE user_id = ? AND post_id = ?");
    $stmt_check->execute([$user_id, $post_id]);

    if ($stmt_check->rowCount() > 0) {
        // Nếu đã lưu, thì xóa khỏi danh sách yêu thích
        $stmt_delete = $conn->prepare("DELETE FROM favorite_posts WHERE user_id = ? AND post_id = ?");
        $stmt_delete->execute([$user_id, $post_id]);
    } else {
        // Nếu chưa lưu, thêm vào danh sách yêu thích
        $stmt_insert = $conn->prepare("INSERT INTO favorite_posts (user_id, post_id) VALUES (?, ?)");
        $stmt_insert->execute([$user_id, $post_id]);
    }

    // Redirect hoặc xử lý tiếp theo sau khi lưu thay đổi
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Các bài viết mới đăng</title>
    <!-- font awesome cdn link  -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <link rel="stylesheet" href="../css/style_edit.css">
</head>

<body>
    <?php include '../components/user_header.php'; ?>
    <section class="posts-container">
        <h1 class="heading">Bài Viết Mới Đăng</h1>
        <div class="box-container view_all">

            <?php
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
                    <form class="box" method="post">
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
                        <div class="post-title"><?= $fetch_posts['title']; ?></div>
                        <div class="post-content content-30"><?= $fetch_posts['content']; ?></div>
                        <a href="view_post.php?post_id=<?= $post_id; ?>" class="inline-btn">Đọc thêm</a>
                        <a href="category.php?category=<?= $fetch_posts['category']; ?>" class="post-cat"> <i class="fas fa-tag"></i> <span><?= $fetch_posts['category']; ?></span></a>
                        <div class="icons">
                            <a href="view_post.php?post_id=<?= $post_id; ?>"><i class="fas fa-comment"></i><span>(<?= $total_post_comments; ?>)</span></a>
                            <button type="submit" name="like_post"><i class="fas fa-heart" style="<?php if ($confirm_likes->rowCount() > 0) {
                                                                                                        echo 'color:var(--red);';
                                                                                                    } ?>  "></i><span>(<?= $total_post_likes; ?>)</span></button>
                            <button><i class="fa-solid fa-share-from-square"></i>Chia sẻ</button>
                        </div>
                    </form>
            <?php
                }
            } else {
                echo '<p class="empty">Chưa bài viết nào được thêm!</p>';
            }
            ?>
        </div>

        <!-- Phân trang -->
        <div class="pagination">
            <?php if ($current_page > 1) : ?>
                <a href="?page=<?= $current_page - 1 ?>" class="prev-btn">Trang trước</a>
            <?php endif; ?>

            <span>Trang <?= $current_page ?> / <?= $total_pages ?></span>

            <?php if ($current_page < $total_pages) : ?>
                <a href="?page=<?= $current_page + 1 ?>" class="next-btn">Trang sau</a>
            <?php endif; ?>
        </div>

    </section>

    <?php include '../components/footer.php'; ?>

    <script src="../js/script_edit.js"></script>
    <script src="../js/script.js"></script>

</body>

</html>
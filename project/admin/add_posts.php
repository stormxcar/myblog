<?php

include '../components/connect.php';
include '../components/feature_engine.php';
include '../components/seo_helpers.php';

session_start();

$admin_id = $_SESSION['admin_id'] ?? null;
if (!$admin_id) {
    header('location:admin_login.php');
    exit;
}

$fetch_profile = blog_fetch_admin_profile($conn, $admin_id);

if (isset($_POST['publish']) || isset($_POST['draft'])) {
    $content = (string)($_POST['content'] ?? '');
    $name = htmlspecialchars((string)($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $title = htmlspecialchars((string)($_POST['title'] ?? ''), ENT_QUOTES, 'UTF-8');
    $category = htmlspecialchars((string)($_POST['category'] ?? ''), ENT_QUOTES, 'UTF-8');

    $image = $_FILES['image']['name'] ?? '';
    $image = htmlspecialchars($image, ENT_QUOTES, 'UTF-8');
    $image_size = (int)($_FILES['image']['size'] ?? 0);
    $image_tmp_name = $_FILES['image']['tmp_name'] ?? '';
    $image_folder = '../uploaded_img/' . $image;

    $select_image = $conn->prepare('SELECT id FROM posts WHERE image = ? AND admin_id = ? LIMIT 1');
    $select_image->execute([$image, (int)$admin_id]);

    if ($image !== '') {
        if ($select_image->rowCount() > 0) {
            $message[] = 'Tên ảnh đã tồn tại.';
        } elseif ($image_size > 2000000) {
            $message[] = 'Kích thước ảnh quá lớn (tối đa 2MB).';
        } else {
            move_uploaded_file($image_tmp_name, $image_folder);
        }
    }

    $status = isset($_POST['publish']) ? 'active' : 'deactive';

    if ($title === '' || $content === '' || $category === '' || $name === '') {
        $message[] = 'Vui lòng nhập đầy đủ thông tin bắt buộc.';
    } elseif ($image !== '' && $select_image->rowCount() > 0) {
        $message[] = 'Vui lòng đổi tên ảnh trước khi tải lên.';
    } else {
        $select_tag_id = $conn->prepare('SELECT category_id FROM cart WHERE name = ? LIMIT 1');
        $select_tag_id->execute([$category]);
        $tag = $select_tag_id->fetch(PDO::FETCH_ASSOC);
        $tag_id = isset($tag['category_id']) ? (int)$tag['category_id'] : null;

        $date = date('Y-m-d');
        $insert_post = $conn->prepare('INSERT INTO posts (admin_id, name, title, content, category, image, status, tag_id, date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $insert_post->execute([(int)$admin_id, $name, $title, $content, $category, $image, $status, $tag_id, $date]);

        $newPostId = (int)$conn->lastInsertId();

        if ($status === 'active' && $newPostId > 0) {
            blog_ensure_feature_tables($conn);
            $postLink = site_url('static/view_post.php?post_id=' . $newPostId);
            blog_notify_all_users(
                $conn,
                'new_post',
                'Bài viết mới',
                '"' . mb_substr($title, 0, 80, 'UTF-8') . '" vừa được đăng.',
                $postLink,
                0
            );
        }

        $_SESSION['message'] = isset($_POST['publish']) ? 'Bài viết đã được đăng thành công.' : 'Đã lưu bản nháp.';
        header('location:view_posts.php');
        exit;
    }
}

$select_categories = $conn->prepare('SELECT * FROM cart ORDER BY name ASC');
$select_categories->execute();
$categories = $select_categories->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm bài đăng</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <script src="https://cdn.ckeditor.com/4.22.1/full/ckeditor.js"></script>
</head>

<body class="ui-page">


    <?php include '../components/admin_header.php' ?>

    <section class="post-editor ui-container">

        <h1 class="heading ui-title">Thêm bài đăng mới</h1>

        <form action="" method="post" enctype="multipart/form-data" onsubmit="return submitForm()" class="ui-card" data-no-submit-lock="true">
            <input type="hidden" name="name" value="<?= htmlspecialchars((string)($fetch_profile['name'] ?? 'admin'), ENT_QUOTES, 'UTF-8'); ?>">

            <p>Tiêu đề bài viết <span>*</span></p>
            <input type="text" name="title" maxlength="100" required placeholder="Thêm tiêu đề" class="box ui-input">

            <p>Nội dung bài viết <span>*</span></p>
            <textarea name="content" id="content" class="box content_add ui-textarea" required maxlength="10000" placeholder="Viết nội dung..." cols="30" rows="10"></textarea>

            <p>Thể loại bài viết <span>*</span></p>
            <select name="category" class="box ui-select" required>
                <option value="" selected disabled>-- Chọn thể loại --</option>
                <?php foreach ($categories as $category) : ?>
                    <option value="<?= htmlspecialchars($category['name']); ?>"><?= htmlspecialchars($category['name']); ?></option>
                <?php endforeach; ?>
            </select>

            <p>Chọn ảnh bài viết</p>
            <input type="file" name="image" class="box ui-input" accept="image/jpg, image/jpeg, image/png, image/webp">

            <div class="flex-btn">
                <input type="submit" value="Lưu công khai" name="publish" class="btn ui-btn">
                <input type="submit" value="Lưu bản nháp" name="draft" class="option-btn ui-btn-warning">
            </div>
        </form>
    </section>

    <script>
        CKEDITOR.replace('content', {
            filebrowserBrowseUrl: '../plugin/ckfinder/ckfinder.html',
            filebrowserUploadUrl: '../plugin/ckfinder/core/connector/php/connector.php?command=QuickUpload&type=Files'
        });

        function submitForm() {
            for (let instance in CKEDITOR.instances) {
                CKEDITOR.instances[instance].updateElement();
            }
            return true;
        }
    </script>

    <script src="../js/admin_script.js"></script>

</body>

</html>
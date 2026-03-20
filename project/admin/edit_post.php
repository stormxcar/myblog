<?php

include '../components/connect.php';
include_once '../components/seo_helpers.php';

session_start();

$admin_id = $_SESSION['admin_id'] ?? null;
if (!isset($admin_id)) {
    header('location:../static/login.php?next=admin');
    exit;
}

$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($post_id <= 0) {
    $_SESSION['message'] = 'Post ID khong hop le.';
    header('location:view_posts.php');
    exit;
}

$categories_stmt = $conn->prepare('SELECT category_id, name FROM cart ORDER BY name ASC');
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

if (isset($_POST['save'])) {
    $title = trim((string)($_POST['title'] ?? ''));
    $content = (string)($_POST['content'] ?? '');
    $category = trim((string)($_POST['category'] ?? ''));
    $status = trim((string)($_POST['status'] ?? 'deactive'));

    if ($title === '' || $content === '' || $category === '') {
        $message[] = 'Vui long nhap day du thong tin bat buoc.';
    } else {
        if ($status !== 'active' && $status !== 'deactive') {
            $status = 'deactive';
        }

        $update_post = $conn->prepare('UPDATE posts SET title = ?, content = ?, category = ?, status = ? WHERE id = ? AND admin_id = ?');
        $update_post->execute([
            htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
            $content,
            htmlspecialchars($category, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($status, ENT_QUOTES, 'UTF-8'),
            $post_id,
            (int)$admin_id,
        ]);

        $generated_slug = post_slug($title, (int)$post_id);
        try {
            $update_slug = $conn->prepare('UPDATE posts SET slug = ? WHERE id = ? AND admin_id = ?');
            $update_slug->execute([$generated_slug, $post_id, (int)$admin_id]);
        } catch (Exception $e) {
            // Keep compatibility if slug column is missing.
        }

        $message[] = 'Da cap nhat bai viet thanh cong.';

        $old_image = (string)($_POST['old_image'] ?? '');
        $image_name = $_FILES['image']['name'] ?? '';
        $image_size = (int)($_FILES['image']['size'] ?? 0);
        $image_tmp_name = $_FILES['image']['tmp_name'] ?? '';

        if (!empty($image_name)) {
            $safe_image_name = htmlspecialchars($image_name, ENT_QUOTES, 'UTF-8');
            $image_folder = '../uploaded_img/' . $safe_image_name;

            if ($image_size > 2000000) {
                $message[] = 'Kich thuoc anh qua lon (toi da 2MB).';
            } else {
                $dup_stmt = $conn->prepare('SELECT id FROM posts WHERE image = ? AND admin_id = ? AND id != ? LIMIT 1');
                $dup_stmt->execute([$safe_image_name, (int)$admin_id, $post_id]);

                if ($dup_stmt->fetch(PDO::FETCH_ASSOC)) {
                    $message[] = 'Ten anh da ton tai, vui long doi ten anh.';
                } else {
                    if (move_uploaded_file($image_tmp_name, $image_folder)) {
                        $update_image = $conn->prepare('UPDATE posts SET image = ? WHERE id = ? AND admin_id = ?');
                        $update_image->execute([$safe_image_name, $post_id, (int)$admin_id]);

                        if ($old_image !== '' && $old_image !== $safe_image_name && file_exists('../uploaded_img/' . $old_image)) {
                            @unlink('../uploaded_img/' . $old_image);
                        }

                        $message[] = 'Da cap nhat anh bai viet.';
                    } else {
                        $message[] = 'Khong the tai len anh, vui long thu lai.';
                    }
                }
            }
        }
    }
}

if (isset($_POST['delete_post'])) {
    $delete_image = $conn->prepare('SELECT image FROM posts WHERE id = ? AND admin_id = ?');
    $delete_image->execute([$post_id, (int)$admin_id]);
    $fetch_delete_image = $delete_image->fetch(PDO::FETCH_ASSOC);

    if ($fetch_delete_image && !empty($fetch_delete_image['image'])) {
        $image_path = '../uploaded_img/' . $fetch_delete_image['image'];
        if (file_exists($image_path)) {
            @unlink($image_path);
        }
    }

    $delete_post = $conn->prepare('DELETE FROM posts WHERE id = ? AND admin_id = ?');
    $delete_post->execute([$post_id, (int)$admin_id]);

    $delete_comments = $conn->prepare('DELETE FROM comments WHERE post_id = ?');
    $delete_comments->execute([$post_id]);

    $_SESSION['message'] = 'Da xoa bai viet thanh cong.';
    header('location:view_posts.php');
    exit;
}

if (isset($_POST['delete_image'])) {
    $old_image = (string)($_POST['old_image'] ?? '');

    if ($old_image !== '') {
        $image_path = '../uploaded_img/' . $old_image;
        if (file_exists($image_path)) {
            @unlink($image_path);
        }
    }

    $unset_image = $conn->prepare('UPDATE posts SET image = ? WHERE id = ? AND admin_id = ?');
    $unset_image->execute(['', $post_id, (int)$admin_id]);

    $message[] = 'Da xoa anh bai viet.';
}

$select_post = $conn->prepare('SELECT * FROM posts WHERE id = ? AND admin_id = ? LIMIT 1');
$select_post->execute([$post_id, (int)$admin_id]);
$post = $select_post->fetch(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Chinh sua bai viet</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <script src="https://cdn.ckeditor.com/4.22.1/full/ckeditor.js"></script>
</head>

<body class="ui-page">

    <?php include '../components/admin_header.php'; ?>

    <section class="post-editor ui-container">
        <h1 class="heading ui-title">Chinh sua bai viet</h1>

        <?php if (!$post): ?>
            <p class="empty">Khong tim thay bai viet hoac ban khong co quyen truy cap.</p>
            <div class="flex-btn">
                <a href="view_posts.php" class="option-btn ui-btn-warning">Quay lai danh sach</a>
                <a href="add_posts.php" class="btn ui-btn">Them bai moi</a>
            </div>
        <?php else: ?>
            <form action="" method="post" enctype="multipart/form-data" class="ui-card" data-no-submit-lock="true">
                <input type="hidden" name="old_image" value="<?= htmlspecialchars((string)$post['image'], ENT_QUOTES, 'UTF-8'); ?>">

                <p>Trang thai bai viet <span>*</span></p>
                <select name="status" class="box ui-select" required>
                    <option value="active" <?= $post['status'] === 'active' ? 'selected' : ''; ?>>active</option>
                    <option value="deactive" <?= $post['status'] === 'deactive' ? 'selected' : ''; ?>>deactive</option>
                </select>

                <p>Tieu de <span>*</span></p>
                <input
                    type="text"
                    name="title"
                    maxlength="150"
                    required
                    class="box ui-input"
                    value="<?= htmlspecialchars((string)$post['title'], ENT_QUOTES, 'UTF-8'); ?>">

                <p>Noi dung <span>*</span></p>
                <textarea
                    name="content"
                    id="content"
                    class="box ui-textarea"
                    required
                    rows="12"><?= htmlspecialchars((string)$post['content'], ENT_QUOTES, 'UTF-8'); ?></textarea>

                <p>Danh muc <span>*</span></p>
                <select name="category" class="box ui-select" required>
                    <option value="" disabled>-- Chon danh muc --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?>" <?= $post['category'] === $cat['name'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <p>Cap nhat anh</p>
                <input type="file" name="image" class="box ui-input" accept="image/jpg,image/jpeg,image/png,image/webp">

                <?php if (!empty($post['image'])): ?>
                    <img src="../uploaded_img/<?= htmlspecialchars((string)$post['image'], ENT_QUOTES, 'UTF-8'); ?>" class="image" alt="Post image">
                    <div class="flex-btn" style="margin-top:.8rem; margin-bottom:1rem;">
                        <button type="submit" name="delete_image" class="inline-delete-btn ui-btn-danger">Xoa anh hien tai</button>
                    </div>
                <?php endif; ?>

                <div class="flex-btn">
                    <button type="submit" name="save" class="btn ui-btn">Luu thay doi</button>
                    <a href="view_posts.php" class="option-btn ui-btn-warning">Quay lai</a>
                    <button type="submit" name="delete_post" class="delete-btn ui-btn-danger" onclick="return confirm('Ban co chac muon xoa bai viet nay khong?');">Xoa bai viet</button>
                </div>
            </form>
        <?php endif; ?>
    </section>

    <script>
        if (document.getElementById('content')) {
            CKEDITOR.replace('content', {
                filebrowserBrowseUrl: '../plugin/ckfinder/ckfinder.html',
                filebrowserUploadUrl: '../plugin/ckfinder/core/connector/php/connector.php?command=QuickUpload&type=Files'
            });
        }
    </script>

    <script src="../js/admin_script.js"></script>
</body>

</html>
<?php
http_response_code(404); // Trả về mã trạng thái 404
include '../components/seo_helpers.php';

$page_title = '404 - Không tìm thấy trang';
$page_description = 'Trang bạn đang tìm không tồn tại hoặc đã được di chuyển.';
$page_canonical = canonical_current_url();
$page_og_image = site_url('uploaded_img/logo-removebg.png');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="<?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="robots" content="noindex,follow,max-image-preview:large">
    <link rel="canonical" href="<?= htmlspecialchars($page_canonical, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:url" content="<?= htmlspecialchars($page_canonical, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?= htmlspecialchars($page_og_image, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($page_og_image, ENT_QUOTES, 'UTF-8'); ?>">

    <!-- font awesome cdn link  -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <link rel="stylesheet" href="../css/output.css">
    <link rel="stylesheet" href="../css/ui-system.css">
    <link rel="stylesheet" href="../css/blog-modern.css">
</head>
<style>
    .error-page {
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
    }

    .error-page .container {
        text-align: center;
    }

    .error-page h1 {
        font-size: 10rem;
        color: #ff0000;
    }

    .error-page h2 {
        font-size: 2rem;
        color: #333;
    }

    .error-page p {
        font-size: 1rem;
        color: #333;
    }

    .error-page a {
        display: inline-block;
        padding: 10px 20px;
        background-color: rgb(62, 123, 221);
        color: #fff;
        text-decoration: none;
        border-radius: 5px;
        margin-top: 20px;
    }
</style>

<body>

    <section class="error-page">
        <div class="container">
            <h1>404</h1>
            <h2>Oops! Hình như bạn đã lạc đường rồi.</h2>
            <p>Đừng lo, chúng tôi sẽ giúp bạn tìm đường về.</p>
            <a href="home.php">Trang chủ</a>
        </div>
    </section>


</body>

</html>
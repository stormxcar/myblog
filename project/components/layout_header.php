<?php
if (!isset($page_title)) {
    $page_title = 'My Blog';
}

if (!isset($page_description)) {
    $page_description = 'Blog chia sẻ kiến thức, công nghệ và trải nghiệm thực tế.';
}

if (!isset($page_robots)) {
    $page_robots = 'index,follow,max-image-preview:large';
}

include_once 'navigation_links.php';
include_once 'seo_helpers.php';

if (!defined('BLOG_LAYOUT_ASSETS')) {
    define('BLOG_LAYOUT_ASSETS', true);
}

if (!isset($page_canonical)) {
    $page_canonical = canonical_current_url();
}

$og_image = isset($page_og_image) && !empty($page_og_image)
    ? $page_og_image
    : site_url('uploaded_img/logo-removebg.png');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title; ?></title>
    <meta name="description" content="<?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="robots" content="<?= htmlspecialchars($page_robots, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="google-site-verification" content="B3jnzqQq3jbsdlIom-VU6HaRVK4Owj1ZZkV6iXYeY4M" />
    <link rel="canonical" href="<?= htmlspecialchars($page_canonical, ENT_QUOTES, 'UTF-8'); ?>">

    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:url" content="<?= htmlspecialchars($page_canonical, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?= htmlspecialchars($og_image, ENT_QUOTES, 'UTF-8'); ?>">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($og_image, ENT_QUOTES, 'UTF-8'); ?>">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../uploaded_img/logo-removebg.png">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

    <!-- Tailwind CSS Output -->
    <link rel="stylesheet" href="../css/output.css">
    <link rel="stylesheet" href="../css/ui-system.css">
    <link rel="stylesheet" href="../css/blog-modern.css">
    <link rel="stylesheet" href="../css/gooey-toast.css">

    <!-- External Libraries -->
    <link rel="stylesheet" href="https://unpkg.com/tippy.js@6/dist/tippy.css">

    <!-- Scripts -->
    <script src="https://unpkg.com/@popperjs/core@2"></script>
    <script src="https://unpkg.com/tippy.js@6"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/masonry/4.2.2/masonry.pkgd.min.js"></script>
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-element-bundle.min.js"></script>
    <script src="../js/gooey-toast.js"></script>

    <!-- Custom Scripts -->
    <script src="../js/blog-global.js" defer></script>
    <script src="../js/script_edit.js" defer></script>

    <!-- Custom Styles for specific pages -->
    <?php if (isset($additional_styles)): ?>
        <?php foreach ($additional_styles as $style): ?>
            <link rel="stylesheet" href="<?= $style; ?>">
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Custom Scripts for specific pages -->
    <?php if (isset($additional_scripts)): ?>
        <?php foreach ($additional_scripts as $script): ?>
            <script src="<?= $script; ?>" defer></script>
        <?php endforeach; ?>
    <?php endif; ?>
</head>

<body class="bg-light-bg dark:bg-main transition-colors duration-300">

    <!-- Loader -->
    <div id="loader-wrapper" class="page-loader" role="status" aria-live="polite" aria-label="Đang tải trang">
        <div class="page-loader__panel">
            <div class="page-loader__brand">My Blog</div>
            <div class="page-loader__label">Đang chuẩn bị nội dung...</div>
            <div class="page-loader__bar"><span></span></div>
        </div>
    </div>

    <?php include '../components/user_header.php'; ?>

    <!-- Main Content Area -->
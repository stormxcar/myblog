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

if (!function_exists('layout_asset_url')) {
    function layout_asset_url($path)
    {
        $path = trim((string)$path);
        if ($path === '') {
            return '';
        }

        if (
            strpos($path, 'http://') === 0
            || strpos($path, 'https://') === 0
            || strpos($path, '//') === 0
            || strpos($path, 'data:') === 0
        ) {
            return $path;
        }

        $normalized = preg_replace('#^(\./|\.\./)+#', '', $path);
        return site_url(ltrim((string)$normalized, '/'));
    }
}

if (!defined('BLOG_LAYOUT_ASSETS')) {
    define('BLOG_LAYOUT_ASSETS', true);
}

if (!isset($page_canonical)) {
    $page_canonical = canonical_current_url();
}

$og_image = isset($page_og_image) && !empty($page_og_image)
    ? $page_og_image
    : blog_brand_logo_url();

$brand_name = blog_brand_name();
$brand_logo = blog_brand_logo_url();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-2Q0NFTQD9G"></script>
    <script>
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            dataLayer.push(arguments);
        }
        gtag('js', new Date());

        gtag('config', 'G-2Q0NFTQD9G');
    </script>

    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title; ?></title>
    <meta name="description" content="<?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="robots" content="<?= htmlspecialchars($page_robots, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="google-site-verification" content="B3jnzqQq3jbsdlIom-VU6HaRVK4Owj1ZZkV6iXYeY4M" />
    <link rel="canonical" href="<?= htmlspecialchars($page_canonical, ENT_QUOTES, 'UTF-8'); ?>">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= htmlspecialchars($brand_name, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:title" content="<?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:url" content="<?= htmlspecialchars($page_canonical, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?= htmlspecialchars($og_image, ENT_QUOTES, 'UTF-8'); ?>">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($og_image, ENT_QUOTES, 'UTF-8'); ?>">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars(site_url('favicon.ico'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="shortcut icon" href="<?= htmlspecialchars(site_url('favicon.ico'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($brand_logo, ENT_QUOTES, 'UTF-8'); ?>">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

    <!-- Tailwind CSS Output -->
    <link rel="stylesheet" href="<?= htmlspecialchars(layout_asset_url('css/output.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(layout_asset_url('css/ui-system.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(layout_asset_url('css/blog-modern.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(layout_asset_url('css/gooey-toast.css'), ENT_QUOTES, 'UTF-8'); ?>">

    <!-- External Libraries -->
    <link rel="stylesheet" href="https://unpkg.com/tippy.js@6/dist/tippy.css">

    <!-- Scripts -->
    <script src="https://unpkg.com/@popperjs/core@2"></script>
    <script src="https://unpkg.com/tippy.js@6"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/masonry/4.2.2/masonry.pkgd.min.js"></script>
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-element-bundle.min.js"></script>
    <script src="<?= htmlspecialchars(layout_asset_url('js/gooey-toast.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>

    <!-- Custom Scripts -->
    <script src="<?= htmlspecialchars(layout_asset_url('js/blog-global.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
    <script src="<?= htmlspecialchars(layout_asset_url('js/script_edit.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>

    <!-- Custom Styles for specific pages -->
    <?php if (isset($additional_styles)): ?>
        <?php foreach ($additional_styles as $style): ?>
            <link rel="stylesheet" href="<?= htmlspecialchars(layout_asset_url($style), ENT_QUOTES, 'UTF-8'); ?>">
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Custom Scripts for specific pages -->
    <?php if (isset($additional_scripts)): ?>
        <?php foreach ($additional_scripts as $script): ?>
            <script src="<?= htmlspecialchars(layout_asset_url($script), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php
    $globalOrgStructuredData = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => $brand_name,
        'url' => site_url(''),
        'logo' => $brand_logo,
    ];

    $globalWebsiteStructuredData = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => $brand_name,
        'url' => site_url(''),
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => site_url('static/search.php?q={search_term_string}'),
            'query-input' => 'required name=search_term_string',
        ],
    ];

    $structuredItems = [$globalOrgStructuredData, $globalWebsiteStructuredData];

    if (isset($page_structured_data)) {
        if (is_array($page_structured_data) && array_keys($page_structured_data) === range(0, count($page_structured_data) - 1)) {
            $structuredItems = array_merge($structuredItems, $page_structured_data);
        } elseif (is_array($page_structured_data)) {
            $structuredItems[] = $page_structured_data;
        }
    }

    foreach ($structuredItems as $structuredItem) {
        if (!is_array($structuredItem) || empty($structuredItem)) {
            continue;
        }

        $jsonLd = json_encode($structuredItem, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($jsonLd) || $jsonLd === '') {
            continue;
        }
        echo '<script type="application/ld+json">' . $jsonLd . '</script>';
    }
    ?>
</head>

<body class="bg-light-bg dark:bg-main transition-colors duration-300">

    <!-- Loader -->
    <div id="loader-wrapper" class="page-loader" role="status" aria-live="polite" aria-label="Đang tải trang">
        <div class="page-loader__panel">
            <div class="page-loader__brand"><?= htmlspecialchars($brand_name, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="page-loader__label">Đang chuẩn bị nội dung...</div>
            <div class="page-loader__bar"><span></span></div>
        </div>
    </div>

    <?php include '../components/user_header.php'; ?>

    <!-- Main Content Area -->
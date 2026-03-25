<?php
include_once '../components/seo_helpers.php';

$faviconUrl = blog_brand_logo_url();

if (!headers_sent()) {
    header('Cache-Control: public, max-age=604800');
    header('Location: ' . $faviconUrl, true, 302);
}

exit;

<?php
http_response_code(404); // Trả về mã trạng thái 404
include '../components/seo_helpers.php';

$page_title = '404 - Không tìm thấy trang';
$page_description = 'Trang bạn đang tìm không tồn tại hoặc đã được di chuyển.';
$page_canonical = canonical_current_url();
$page_og_image = blog_brand_logo_url();
$brand_name = blog_brand_name();
$brand_logo = blog_brand_logo_url();
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
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($brand_logo, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($brand_logo, ENT_QUOTES, 'UTF-8'); ?>">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <style>
        :root {
            --bg: #041226;
            --bg-soft: #0b1f39;
            --card: rgba(14, 30, 54, 0.82);
            --line: rgba(255, 255, 255, 0.18);
            --text: #e2ecff;
            --muted: #9fb2d5;
            --brand: #2f80ed;
            --brand-2: #46c6ff;
            --danger: #ff6b6b;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            min-height: 100%;
            font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, "Helvetica Neue", Arial, sans-serif;
            color: var(--text);
            background: radial-gradient(1200px 700px at -10% -10%, #11417e 0%, transparent 60%), radial-gradient(1000px 600px at 110% 20%, #0f4a86 0%, transparent 58%), linear-gradient(145deg, var(--bg) 0%, var(--bg-soft) 100%);
        }

        .page-wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 16px;
        }

        .shell {
            width: 100%;
            max-width: 1120px;
            gap: 24px;
            align-items: stretch;
        }

        .panel {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 22px;
            backdrop-filter: blur(10px);
            box-shadow: 0 18px 50px rgba(0, 0, 0, 0.35);
        }

        .left {
            padding: 28px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .right {
            padding: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--text);
            margin-bottom: 16px;
        }

        .brand img {
            width: 44px;
            height: 44px;
            object-fit: contain;
        }

        .brand span {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            width: fit-content;
            padding: 6px 12px;
            border-radius: 999px;
            border: 1px solid rgba(255, 107, 107, 0.35);
            background: rgba(255, 107, 107, 0.1);
            color: #ffc2c2;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            margin-bottom: 12px;
        }

        h1 {
            margin: 0;
            font-size: clamp(28px, 4.1vw, 46px);
            line-height: 1.14;
        }

        .desc {
            margin-top: 14px;
            color: var(--muted);
            font-size: 16px;
            line-height: 1.65;
            max-width: 60ch;
        }

        .actions {
            margin-top: 22px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            border-radius: 12px;
            height: 46px;
            padding: 0 18px;
            font-size: 14px;
            font-weight: 700;
            transition: transform .18s ease, opacity .18s ease, background-color .18s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-primary {
            color: #fff;
            background: linear-gradient(135deg, var(--brand), var(--brand-2));
        }

        .btn-ghost {
            color: var(--text);
            border: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.04);
        }

        .search-box {
            margin-top: 20px;
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 10px;
        }

        .search-field {
            position: relative;
        }

        .search-field i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #8da3c8;
            font-size: 14px;
        }

        .search-field input {
            width: 100%;
            height: 46px;
            border-radius: 12px;
            border: 1px solid var(--line);
            background: rgba(0, 0, 0, 0.2);
            color: var(--text);
            padding: 0 14px 0 38px;
            outline: none;
            font-size: 14px;
        }

        .search-field input::placeholder {
            color: #8da3c8;
        }

        .search-field input:focus {
            border-color: rgba(70, 198, 255, 0.6);
            box-shadow: 0 0 0 3px rgba(70, 198, 255, 0.2);
        }

        .search-go {
            border: 0;
            border-radius: 12px;
            height: 46px;
            padding: 0 18px;
            color: #07203f;
            font-weight: 800;
            cursor: pointer;
            background: linear-gradient(135deg, #7be5ff, #4ec7ff);
        }

        .num {
            margin: 0;
            font-size: clamp(88px, 14vw, 170px);
            line-height: 0.9;
            font-weight: 900;
            letter-spacing: -0.04em;
            color: #ffffff;
            text-shadow: 0 6px 30px rgba(0, 0, 0, 0.45);
        }

        .sub {
            margin-top: 8px;
            color: #9fd7ff;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.22em;
            text-transform: uppercase;
        }

        .hint {
            margin: 16px auto 0;
            max-width: 32ch;
            color: var(--muted);
            line-height: 1.65;
            font-size: 14px;
        }

        .site-link {
            margin-top: 18px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #9fd7ff;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
        }

        .site-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 980px) {
            .shell {
                grid-template-columns: 1fr;
                max-width: 700px;
            }

            .right {
                min-height: 320px;
            }
        }

        @media (max-width: 560px) {

            .left,
            .right {
                padding: 20px;
            }

            .search-box {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <main class="page-wrap">
        <section class="shell">
            <article class="panel left">
                <a href="<?= htmlspecialchars(site_url('home'), ENT_QUOTES, 'UTF-8'); ?>" class="brand" aria-label="<?= htmlspecialchars($brand_name, ENT_QUOTES, 'UTF-8'); ?>">
                    <img src="<?= htmlspecialchars($brand_logo, ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlspecialchars($brand_name, ENT_QUOTES, 'UTF-8'); ?> logo">
                    <span><?= htmlspecialchars($brand_name, ENT_QUOTES, 'UTF-8'); ?></span>
                </a>

                <div class="badge">404 - Not Found</div>
                <h1>Trang bạn tìm không còn ở đây nữa</h1>
                <p class="desc">Liên kết có thể đã được cập nhật sang URL mới chuẩn SEO. Bạn có thể về trang chủ, mở danh sách bài viết, hoặc tìm nhanh nội dung ngay bên dưới.</p>

                <div class="actions">
                    <a href="<?= htmlspecialchars(site_url('home'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary">
                        <i class="fas fa-house"></i>
                        <span>Về trang chủ</span>
                    </a>
                    <a href="<?= htmlspecialchars(site_url('posts'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-ghost">
                        <i class="fas fa-newspaper"></i>
                        <span>Xem bài viết</span>
                    </a>
                </div>

                <form action="<?= htmlspecialchars(site_url('search'), ENT_QUOTES, 'UTF-8'); ?>" method="get" class="search-box">
                    <label class="search-field" for="search404">
                        <i class="fas fa-search"></i>
                        <input id="search404" name="q" type="text" maxlength="120" placeholder="Tìm theo tiêu đề, chủ đề, từ khóa...">
                    </label>
                    <button type="submit" class="search-go">Tìm kiếm</button>
                </form>
            </article>


        </section>
    </main>
</body>

</html>
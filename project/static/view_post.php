<?php

include '../components/connect.php';
include_once '../components/seo_helpers.php';
include_once '../components/feature_engine.php';

session_start();
$message = [];

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} else {
    $user_id = '';
}

include '../components/like_post.php';
include '../components/save_post.php';
blog_ensure_feature_tables($conn);

if (!function_exists('blog_decode_html_entities_deep')) {
    function blog_decode_html_entities_deep(string $input, int $maxDepth = 3): string
    {
        $decoded = $input;
        for ($i = 0; $i < $maxDepth; $i++) {
            $next = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($next === $decoded) {
                break;
            }
            $decoded = $next;
        }
        return $decoded;
    }
}

// Resolve post ID from slug route (/post/my-title-123.html) or legacy ?post_id=
$slug_param = isset($_GET['slug']) ? trim($_GET['slug']) : '';
$legacy_post_id = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;

$get_id = 0;
$current_post = null;

if ($slug_param !== '') {
    // Prefer exact DB slug match when slug column exists.
    try {
        $select_by_slug = $conn->prepare("SELECT * FROM `posts` WHERE status = ? AND slug = ? LIMIT 1");
        $select_by_slug->execute(['active', $slug_param]);
        $current_post = $select_by_slug->fetch(PDO::FETCH_ASSOC);
        if ($current_post) {
            $get_id = (int)$current_post['id'];
        }
    } catch (Exception $e) {
        // Slug column may not exist yet; fallback to slug parser.
    }

    if ($get_id <= 0) {
        $get_id = extract_post_id_from_slug($slug_param);
    }
}

if ($get_id <= 0 && $legacy_post_id > 0) {
    $get_id = $legacy_post_id;
}

if ($get_id <= 0) {
    header('Location: home.php');
    exit;
}

if (!$current_post) {
    try {
        $select_current_post = $conn->prepare("SELECT * FROM `posts` WHERE status = ? AND id = ? LIMIT 1");
        $select_current_post->execute(['active', $get_id]);
        $current_post = $select_current_post->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Backward compatibility for databases that don't have the slug column yet.
        $select_current_post = $conn->prepare("SELECT id, title, content FROM `posts` WHERE status = ? AND id = ? LIMIT 1");
        $select_current_post->execute(['active', $get_id]);
        $current_post = $select_current_post->fetch(PDO::FETCH_ASSOC);
    }
}

if (!$current_post) {
    header('Location: home.php');
    exit;
}

$stored_slug = isset($current_post['slug']) ? trim((string)$current_post['slug']) : '';
$canonical_slug = $stored_slug !== '' ? $stored_slug : post_slug($current_post['title'], $current_post['id']);
$canonical_post_url = site_url('post/' . rawurlencode($canonical_slug) . '.html');

if ($slug_param === '' || $slug_param !== $canonical_slug) {
    header('Location: ' . $canonical_post_url, true, 301);
    exit;
}

$decoded_current_title = blog_decode_html_entities_deep((string)$current_post['title']);
$decoded_current_content = blog_decode_html_entities_deep((string)$current_post['content']);
$category_label = trim((string)($current_post['category'] ?? ''));
$article_plain = preg_replace('/\s+/u', ' ', trim(strip_tags($decoded_current_content)));
$article_plain = (string)($article_plain ?? '');
if ($article_plain === '') {
    $article_plain = $decoded_current_title;
}

$page_title = $decoded_current_title;
if ($category_label !== '') {
    $page_title .= ' | ' . $category_label;
}
$page_title .= ' | My Blog';

$page_description = mb_substr($article_plain, 0, 150, 'UTF-8');
if (mb_strlen($article_plain, 'UTF-8') > 150) {
    $page_description .= '...';
}

$page_canonical = $canonical_post_url;
$page_robots = 'index,follow,max-image-preview:large';
$page_og_image = blog_post_image_src((string)($current_post['image'] ?? ''), '../uploaded_img/', '../uploaded_img/default_img.jpg');

$article_date_raw = trim((string)($current_post['date'] ?? ''));
$article_ts = $article_date_raw !== '' ? strtotime($article_date_raw) : false;
$article_published_iso = $article_ts !== false ? gmdate(DATE_ATOM, $article_ts) : gmdate(DATE_ATOM);
$article_modified_iso = $article_published_iso;
$article_author_name = trim((string)($current_post['name'] ?? 'My Blog'));
if ($article_author_name === '') {
    $article_author_name = 'My Blog';
}

$keywords = [];
if ($category_label !== '') {
    $keywords[] = $category_label;
}
$keywords[] = 'blog';
$keywords[] = 'du lich';
$keywords[] = 'trai nghiem';
$article_keywords = implode(', ', array_values(array_unique($keywords)));

// Lấy thông tin user profile nếu đã đăng nhập
$fetch_profile = null;
if (!empty($user_id)) {
    $select_profile = $conn->prepare("SELECT * FROM `users` WHERE id = ?");
    $select_profile->execute([$user_id]);
    $fetch_profile = $select_profile->fetch(PDO::FETCH_ASSOC);
}

if (isset($_POST['add_comment'])) {
    $is_ajax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

    if ($user_id === '') {
        $_SESSION['message'] = 'Vui lòng đăng nhập để bình luận.';
        if ($is_ajax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'login_required' => true,
                'login_url' => site_url('static/login.php'),
                'message' => $_SESSION['message']
            ]);
            exit;
        }
    }

    $admin_id = (int)($_POST['admin_id'] ?? 0);
    $user_name = trim(strip_tags((string)($_POST['user_name'] ?? '')));
    $comment = trim((string)($_POST['comment'] ?? ''));
    $parent_comment_id = isset($_POST['parent_comment_id']) ? (int)$_POST['parent_comment_id'] : 0;
    if ($parent_comment_id < 0) {
        $parent_comment_id = 0;
    }

    $spamReason = blog_detect_spam_or_toxic($comment);
    if ($spamReason !== '') {
        $_SESSION['message'] = $spamReason;
        if ($is_ajax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'message' => $_SESSION['message']
            ]);
            exit;
        }
    } else {
        $verify_comment = $conn->prepare("SELECT * FROM `comments` WHERE post_id = ? AND admin_id = ? AND user_id = ? AND user_name = ? AND comment = ?");
        $verify_comment->execute([$get_id, $admin_id, $user_id, $user_name, $comment]);

        if ($verify_comment->rowCount() > 0) {
            $_SESSION['message'] = 'Đã gửi bình luận!';
            if ($is_ajax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'ok' => true,
                    'message' => $_SESSION['message']
                ]);
                exit;
            }
        } else {
            $insert_comment = $conn->prepare("INSERT INTO `comments`(post_id, parent_comment_id, admin_id, user_id, user_name, comment) VALUES(?,?,?,?,?,?)");
            $insert_comment->execute([$get_id, $parent_comment_id > 0 ? $parent_comment_id : null, $admin_id, $user_id, $user_name, $comment]);
            $_SESSION['message'] = 'Đã gửi bình luận!';
            $new_comment_id = (int)$conn->lastInsertId();

            // Notify post author/admin for new comment.
            $ownerStmt = $conn->prepare('SELECT id FROM users WHERE legacy_admin_id = ? LIMIT 1');
            $ownerStmt->execute([(int)$admin_id]);
            $ownerUser = $ownerStmt->fetch(PDO::FETCH_ASSOC);
            if ($ownerUser && (int)$ownerUser['id'] !== (int)$user_id) {
                blog_push_notification(
                    $conn,
                    (int)$ownerUser['id'],
                    'comment',
                    'Binh luan moi',
                    $user_name . ' vua binh luan ve bai viet cua ban.',
                    post_path((int)$get_id)
                );
            }

            // Notify parent comment owner when this is a reply.
            if ($parent_comment_id > 0) {
                $parentStmt = $conn->prepare('SELECT user_id FROM comments WHERE id = ? LIMIT 1');
                $parentStmt->execute([(int)$parent_comment_id]);
                $parentComment = $parentStmt->fetch(PDO::FETCH_ASSOC);
                $parentUserId = (int)($parentComment['user_id'] ?? 0);
                if ($parentUserId > 0 && $parentUserId !== (int)$user_id) {
                    blog_push_notification(
                        $conn,
                        $parentUserId,
                        'comment_reply',
                        'Binh luan duoc tra loi',
                        $user_name . ' da tra loi binh luan cua ban.',
                        post_path((int)$get_id)
                    );
                }
            }

            // Notify mentioned users.
            $mentions = blog_extract_mentions($comment);
            if (!empty($mentions)) {
                $placeholders = implode(',', array_fill(0, count($mentions), '?'));
                $mentionStmt = $conn->prepare("SELECT id, name FROM users WHERE LOWER(name) IN ({$placeholders}) LIMIT 10");
                $mentionStmt->execute($mentions);
                foreach ($mentionStmt->fetchAll(PDO::FETCH_ASSOC) as $mentioned) {
                    $mentionedId = (int)$mentioned['id'];
                    if ($mentionedId !== (int)$user_id) {
                        blog_push_notification(
                            $conn,
                            $mentionedId,
                            'mention',
                            'Ban duoc nhac ten',
                            $user_name . ' da nhac toi ban trong mot binh luan.',
                            post_path((int)$get_id)
                        );
                    }
                }
            }

            if ($is_ajax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'ok' => true,
                    'message' => $_SESSION['message'],
                    'comment' => [
                        'id' => $new_comment_id,
                        'parent_comment_id' => $parent_comment_id,
                        'user_name' => $user_name,
                        'comment' => $comment,
                        'date' => date('d/m/Y')
                    ]
                ]);
                exit;
            }
        }
    }
}

if (isset($_POST['vote_comment'])) {
    $commentVoteId = (int)($_POST['comment_id'] ?? 0);
    $voteType = (string)($_POST['vote_type'] ?? '');
    $voteValue = $voteType === 'up' ? 1 : ($voteType === 'down' ? -1 : 0);

    if ($user_id === '') {
        $_SESSION['message'] = 'Vui long dang nhap de binh chon binh luan.';
    } elseif ($commentVoteId > 0 && $voteValue !== 0) {
        $voteStmt = $conn->prepare('INSERT INTO comment_votes (comment_id, user_id, vote) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE vote = VALUES(vote), created_at = CURRENT_TIMESTAMP');
        $voteStmt->execute([$commentVoteId, (int)$user_id, $voteValue]);
        $_SESSION['message'] = 'Da cap nhat danh gia binh luan.';
    }
}

if (isset($_POST['edit_comment'])) {
    $edit_comment_id = $_POST['edit_comment_id'];
    $edit_comment_id = (int)($_POST['edit_comment_id'] ?? 0);
    $comment_edit_box = trim((string)($_POST['comment_edit_box'] ?? ''));

    $verify_comment = $conn->prepare("SELECT * FROM `comments` WHERE comment = ? AND id = ?");
    $verify_comment->execute([$comment_edit_box, $edit_comment_id]);

    if ($verify_comment->rowCount() > 0) {
        $_SESSION['message'] = 'Bình luận của bạn đã được thêm!';
    } else {
        $update_comment = $conn->prepare("UPDATE `comments` SET comment = ? WHERE id = ?");
        $update_comment->execute([$comment_edit_box, $edit_comment_id]);
        $_SESSION['message'] = 'Bình luận của bạn đã được chỉnh sửa!';
    }
}

if (isset($_POST['delete_comment'])) {
    $delete_comment_id = $_POST['comment_id'];
    $delete_comment_id = (int)($_POST['comment_id'] ?? 0);
    $queue = [(int)$delete_comment_id];
    $toDelete = [];

    while (!empty($queue)) {
        $current = array_shift($queue);
        $toDelete[] = $current;
        $childStmt = $conn->prepare("SELECT id FROM `comments` WHERE parent_comment_id = ?");
        $childStmt->execute([$current]);
        foreach ($childStmt->fetchAll(PDO::FETCH_ASSOC) as $child) {
            $queue[] = (int)$child['id'];
        }
    }

    if (!empty($toDelete)) {
        $placeholders = implode(',', array_fill(0, count($toDelete), '?'));
        $delete_comment = $conn->prepare("DELETE FROM `comments` WHERE id IN ({$placeholders})");
        $delete_comment->execute($toDelete);
    }
    $_SESSION['message'] = 'Đã xóa bình luận!';
}

// Lấy thông tin bài viết hiện tại
$select_post_tag = $conn->prepare("SELECT category FROM `posts` WHERE id = ?");
$select_post_tag->execute([$get_id]);
$fetch_post_tag = $select_post_tag->fetch(PDO::FETCH_ASSOC);

// Nếu không tìm thấy post, thử lấy bằng query khác
if (!$fetch_post_tag) {
    $select_post_check = $conn->prepare("SELECT id, category FROM `posts` WHERE id = ?");
    $select_post_check->execute([$get_id]);
    $fetch_post_tag = $select_post_check->fetch(PDO::FETCH_ASSOC);
}

// Nếu vẫn không tìm thấy, hiển thị thông báo lỗi thay vì redirect
if (!$fetch_post_tag) {
    echo '<div class="container mx-auto px-4 py-8">
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            <h2 class="font-bold">Bài viết không tồn tại</h2>
            <p>Bài viết bạn tìm kiếm không có trong hệ thống hoặc đã bị xóa.</p>
            <a href="home.php" class="text-blue-600 hover:text-blue-800">Quay lại trang chủ</a>
        </div>
    </div>';
    exit;
}

$current_tag = $fetch_post_tag['category'];

// Truy vấn các bài viết liên quan
$select_related_posts = $conn->prepare("SELECT * FROM `posts` WHERE category = ? AND id != ? AND status = 'active' LIMIT 4");
$select_related_posts->execute([$current_tag, $get_id]);
$related_posts = $select_related_posts->fetchAll(PDO::FETCH_ASSOC);

$aiSummary = blog_generate_quick_summary($decoded_current_content);
$aiSummaryDisplay = blog_decode_html_entities_deep((string)$aiSummary);

$personalized_posts = [];
if ($user_id !== '') {
    $personalized_stmt = $conn->prepare("SELECT p.*, 
        (CASE WHEN p.category IN (
            SELECT p2.category
            FROM likes l2
            JOIN posts p2 ON p2.id = l2.post_id
            WHERE l2.user_id = ?
        ) THEN 2 ELSE 0 END
        + CASE WHEN p.id IN (
            SELECT ure.post_id FROM user_read_events ure WHERE ure.user_id = ? GROUP BY ure.post_id HAVING SUM(ure.seconds_read) >= 30
        ) THEN 1 ELSE 0 END
        + COALESCE((SELECT COUNT(*) FROM likes l3 WHERE l3.post_id = p.id), 0)
        ) AS rec_score
        FROM posts p
        WHERE p.status = 'active' AND p.id != ?
        ORDER BY rec_score DESC, p.id DESC
        LIMIT 4");
    $personalized_stmt->execute([(int)$user_id, (int)$user_id, (int)$get_id]);
    $personalized_posts = $personalized_stmt->fetchAll(PDO::FETCH_ASSOC);
}

$metric_post_ids = [(int)$get_id];
foreach ($related_posts as $related_row) {
    $metric_post_ids[] = (int)$related_row['id'];
}
$metric_post_ids = array_values(array_unique(array_filter(array_map('intval', $metric_post_ids), function ($value) {
    return $value > 0;
})));

$post_comment_count_map = [];
$post_like_count_map = [];
$user_liked_post_map = [];
$user_saved_post_map = [];

if (!empty($metric_post_ids)) {
    $metric_placeholders = implode(',', array_fill(0, count($metric_post_ids), '?'));

    $comment_count_stmt = $conn->prepare("SELECT post_id, COUNT(*) AS total_comments FROM `comments` WHERE post_id IN ({$metric_placeholders}) GROUP BY post_id");
    $comment_count_stmt->execute($metric_post_ids);
    foreach ($comment_count_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $post_comment_count_map[(int)$row['post_id']] = (int)$row['total_comments'];
    }

    $like_count_stmt = $conn->prepare("SELECT post_id, COUNT(*) AS total_likes FROM `likes` WHERE post_id IN ({$metric_placeholders}) GROUP BY post_id");
    $like_count_stmt->execute($metric_post_ids);
    foreach ($like_count_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $post_like_count_map[(int)$row['post_id']] = (int)$row['total_likes'];
    }

    if ($user_id !== '') {
        $liked_params = array_merge([(int)$user_id], $metric_post_ids);
        $liked_stmt = $conn->prepare("SELECT post_id FROM `likes` WHERE user_id = ? AND post_id IN ({$metric_placeholders})");
        $liked_stmt->execute($liked_params);
        foreach ($liked_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $user_liked_post_map[(int)$row['post_id']] = true;
        }

        $saved_stmt = $conn->prepare("SELECT post_id FROM `favorite_posts` WHERE user_id = ? AND post_id IN ({$metric_placeholders})");
        $saved_stmt->execute($liked_params);
        foreach ($saved_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $user_saved_post_map[(int)$row['post_id']] = true;
        }
    }
}

if (!function_exists('render_comment_branch')) {
    function render_comment_branch($nodesByParent, $parentId, $user_id, $get_id, $depth = 0, $topLevelIds = null)
    {
        if (!isset($nodesByParent[$parentId])) {
            return;
        }

        foreach ($nodesByParent[$parentId] as $fetch_comments) {
            if ($parentId === 0 && is_array($topLevelIds) && !in_array((int)$fetch_comments['id'], $topLevelIds, true)) {
                continue;
            }
            $commentId = (int)$fetch_comments['id'];
            $isOwner = (string)$fetch_comments['user_id'] === (string)$user_id;
            $badge = blog_user_badge($fetch_comments['interaction_score'] ?? 0);
            $mlClass = $depth > 0 ? 'ml-5 md:ml-8 border-l-2 border-main/30 pl-4 comment-reply-item' : '';

            echo '<div data-comment-id="' . $commentId . '" class="' . $mlClass . ' flex space-x-4 p-4 rounded-xl transition-all duration-300 hover:bg-gray-50 dark:hover:bg-gray-700/50 ' . ($isOwner ? 'bg-main/5 border border-main/20' : '') . '">';
            echo '<div class="w-10 h-10 bg-main rounded-full flex items-center justify-center text-white font-semibold flex-shrink-0">' . htmlspecialchars(strtoupper(substr((string)$fetch_comments['user_name'], 0, 1)), ENT_QUOTES, 'UTF-8') . '</div>';
            echo '<div class="flex-1">';
            echo '<div class="flex items-center justify-between mb-2">';
            echo '<div><span class="font-semibold text-gray-900 dark:text-white">' . htmlspecialchars((string)$fetch_comments['user_name'], ENT_QUOTES, 'UTF-8') . '</span>';
            echo '<span class="ml-2 px-2 py-1 text-[11px] rounded-full ' . htmlspecialchars($badge['class'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($badge['label'], ENT_QUOTES, 'UTF-8') . '</span>';
            if ($isOwner) {
                echo '<span class="ml-2 px-2 py-1 text-xs bg-main text-white rounded-full">Bạn</span>';
            }
            echo '</div>';
            echo '<div class="flex items-center space-x-2"><span class="text-sm text-gray-500 dark:text-gray-400">' . htmlspecialchars(date('d/m/Y', strtotime((string)$fetch_comments['date'])), ENT_QUOTES, 'UTF-8') . '</span>';
            if ($isOwner) {
                echo '<div class="relative">';
                echo '<button onclick="toggleCommentMenu(' . $commentId . ')" class="p-1 rounded-full hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"><i class="fas fa-ellipsis-v text-gray-400"></i></button>';
                echo '<div id="comment-menu-' . $commentId . '" class="absolute right-0 top-8 bg-white dark:bg-gray-700 rounded-lg shadow-lg border border-gray-200 dark:border-gray-600 py-2 z-10 hidden">';
                echo '<form action="" method="POST" class="inline">';
                echo '<input type="hidden" name="comment_id" value="' . $commentId . '">';
                echo '<button type="submit" name="open_edit_box" class="w-full px-4 py-2 text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 flex items-center"><i class="fas fa-edit mr-2"></i>Chỉnh sửa</button>';
                echo '<button type="submit" name="delete_comment" onclick="return confirm(\'Bạn chắc chắn muốn xóa bình luận này?\')" class="w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 flex items-center"><i class="fas fa-trash mr-2"></i>Xóa</button>';
                echo '</form></div></div>';
            }
            echo '</div></div>';
            echo '<div class="text-gray-700 dark:text-gray-300 leading-relaxed">' . nl2br(htmlspecialchars((string)$fetch_comments['comment'], ENT_QUOTES, 'UTF-8')) . '</div>';
            echo '<div class="mt-3 flex items-center gap-2 flex-wrap" data-comment-actions>';
            echo '<button type="button" data-comment-vote data-vote-type="up" data-comment-id="' . $commentId . '" class="px-2 py-1 rounded bg-green-100 text-green-700 text-xs hover:bg-green-200"><i class="fas fa-thumbs-up mr-1"></i><span id="comment-like-count-' . $commentId . '">' . (int)$fetch_comments['up_count'] . '</span></button>';
            echo '<button type="button" data-comment-vote data-vote-type="down" data-comment-id="' . $commentId . '" class="px-2 py-1 rounded bg-rose-100 text-rose-700 text-xs hover:bg-rose-200"><i class="fas fa-thumbs-down mr-1"></i><span id="comment-dislike-count-' . $commentId . '">' . (int)$fetch_comments['down_count'] . '</span></button>';
            if ($user_id !== '') {
                echo '<button type="button" class="px-2 py-1 rounded bg-gray-100 text-gray-700 text-xs hover:bg-gray-200" onclick="replyToComment(' . $commentId . ')"><i class="fas fa-reply mr-1"></i>Trả lời</button>';
            } else {
                echo '<a href="login.php" class="px-2 py-1 rounded bg-gray-100 text-gray-700 text-xs hover:bg-gray-200 inline-flex items-center"><i class="fas fa-reply mr-1"></i>Trả lời</a>';
            }

            if (isset($nodesByParent[$commentId])) {
                echo '<button type="button" class="px-2 py-1 rounded bg-blue-100 text-blue-700 text-xs hover:bg-blue-200" onclick="toggleReplies(' . $commentId . ')"><i class="fas fa-code-branch mr-1"></i><span id="reply-toggle-text-' . $commentId . '">Ẩn phản hồi (' . count($nodesByParent[$commentId]) . ')</span></button>';
            }
            echo '</div></div></div>';

            if (isset($nodesByParent[$commentId])) {
                echo '<div id="replies-' . $commentId . '" class="mt-3 space-y-3">';
                render_comment_branch($nodesByParent, $commentId, $user_id, $get_id, $depth + 1);
                echo '</div>';
            }
        }
    }
}

?>

<?php include '../components/layout_header.php'; ?>

<?php
// Include breadcrumb component
include '../components/breadcrumb.php';

// Generate breadcrumb for view post page
$breadcrumb_items = [
    [
        'title' => 'Trang chủ',
        'url' => get_nav_link('home')
    ],
    [
        'title' => 'Bài viết',
        'url' => get_nav_link('posts')
    ],
    [
        'title' => $decoded_current_title,
        'url' => ''
    ]
];
render_breadcrumb($breadcrumb_items);
?>

<main class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <style>
        .article-content-render ul,
        .article-content-render ol {
            margin: 1rem 0 1rem 1.25rem;
            padding-left: 1rem;
        }

        .article-content-render ul {
            list-style: disc;
        }

        .article-content-render ol {
            list-style: decimal;
        }

        .article-content-render li {
            display: list-item;
            margin: 0.35rem 0;
        }

        .article-content-render h1,
        .article-content-render h2,
        .article-content-render h3,
        .article-content-render h4,
        .article-content-render h5,
        .article-content-render h6 {
            margin: 1rem 0 0.5rem;
            font-weight: 700;
            line-height: 1.35;
        }

        .article-content-render p {
            margin: 0.75rem 0;
        }
    </style>
    <script type="application/ld+json">
        <?= json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            '@id' => $canonical_post_url,
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $canonical_post_url,
            ],
            'headline' => $decoded_current_title,
            'description' => $page_description,
            'url' => $canonical_post_url,
            'datePublished' => $article_published_iso,
            'dateModified' => $article_modified_iso,
            'articleSection' => $category_label !== '' ? $category_label : null,
            'keywords' => $article_keywords,
            'image' => [
                '@type' => 'ImageObject',
                'url' => $page_og_image,
            ],
            'author' => [
                '@type' => 'Person',
                'name' => $article_author_name,
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'My Blog',
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => blog_brand_logo_url(),
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
    </script>

    <!-- Edit Comment Modal -->
    <?php if (isset($_POST['open_edit_box'])) {
        $comment_id = $_POST['comment_id'];
        $comment_id = (int)($_POST['comment_id'] ?? 0);
    ?>
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-8 max-w-2xl w-full mx-4 shadow-2xl">
                <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Chỉnh sửa bình luận</h3>
                <?php
                $select_edit_comment = $conn->prepare("SELECT * FROM `comments` WHERE id = ?");
                $select_edit_comment->execute([$comment_id]);
                $fetch_edit_comment = $select_edit_comment->fetch(PDO::FETCH_ASSOC);
                ?>
                <form action="" method="POST" class="space-y-4">
                    <input type="hidden" name="edit_comment_id" value="<?= $comment_id; ?>">
                    <textarea name="comment_edit_box" required rows="6"
                        class="form-textarea"
                        placeholder="Nhập bình luận của bạn"><?= $fetch_edit_comment['comment']; ?></textarea>
                    <div class="flex space-x-4">
                        <button type="submit" name="edit_comment" class="btn-primary">
                            <i class="fas fa-save mr-2"></i>Lưu thay đổi
                        </button>
                        <a href="<?= post_path($get_id, $current_post['title']); ?>" class="btn-secondary">
                            <i class="fas fa-times mr-2"></i>Hủy
                        </a>
                    </div>
                </form>
            </div>
        </div>
    <?php } ?>

    <div class="container-custom py-8">
        <!-- Main Post -->
        <article class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl overflow-hidden mb-12">
            <?php
            $select_posts = $conn->prepare("SELECT * FROM `posts` WHERE status = ? AND id = ?");
            $select_posts->execute(['active', $get_id]);
            if ($select_posts->rowCount() > 0) {
                while ($fetch_posts = $select_posts->fetch(PDO::FETCH_ASSOC)) {
                    $post_id = $fetch_posts['id'];
                    $post_tags = blog_get_post_tags($conn, $post_id);
                    $total_post_comments = (int)($post_comment_count_map[(int)$post_id] ?? 0);
                    $total_post_likes = (int)($post_like_count_map[(int)$post_id] ?? 0);
                    $is_post_liked = !empty($user_liked_post_map[(int)$post_id]);
                    $is_post_saved = !empty($user_saved_post_map[(int)$post_id]);
            ?>
                    <form method="post" action="">
                        <input type="hidden" name="post_id" value="<?= $post_id; ?>">
                        <input type="hidden" name="admin_id" value="<?= $fetch_posts['admin_id']; ?>">

                        <!-- Post Header -->
                        <div class="p-6 border-b border-gray-200 dark:border-gray-600">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-4">
                                    <div class="w-12 h-12 bg-main rounded-full flex items-center justify-center text-white font-bold text-lg">
                                        <?= strtoupper(substr($fetch_posts['name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <a href="author_posts.php?author=<?= $fetch_posts['name']; ?>"
                                            class="text-lg font-semibold text-gray-900 dark:text-white hover:text-main transition-colors">
                                            <?= $fetch_posts['name']; ?>
                                        </a>
                                        <div class="text-gray-500 dark:text-gray-400 text-sm">
                                            <i class="fas fa-clock mr-1"></i>
                                            <?= date('d/m/Y', strtotime($fetch_posts['date'])); ?>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" name="save_post"
                                    class="p-3 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors group">
                                    <i class="fas fa-bookmark text-xl <?= $is_post_saved ? 'text-yellow-500' : 'text-gray-400 group-hover:text-yellow-500' ?> transition-colors"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Post Image -->
                        <!-- <?php if ($fetch_posts['image'] != '') : ?>
                            <div class="relative">
                                <img id="image_show"
                                    src="<?= htmlspecialchars(blog_post_image_src((string)$fetch_posts['image'], '../uploaded_img/', '../uploaded_img/default_img.jpg'), ENT_QUOTES, 'UTF-8'); ?>"
                                    alt="<?= $fetch_posts['title']; ?>"
                                    fetchpriority="high"
                                    decoding="async"
                                    class="w-full h-64 md:h-96 object-cover cursor-pointer hover:scale-105 transition-transform duration-500">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/30 to-transparent"></div>
                            </div>
                        <?php endif; ?> -->

                        <!-- Post Content -->
                        <div class="p-6">
                            <?php $decoded_post_title = blog_decode_html_entities_deep((string)$fetch_posts['title']); ?>
                            <h1 class="text-3xl md:text-4xl font-bold text-gray-900 dark:text-white mb-6">
                                <?= htmlspecialchars($decoded_post_title, ENT_QUOTES, 'UTF-8'); ?>
                            </h1>

                            <div class="article-content-render prose prose-lg max-w-none text-gray-700 dark:text-gray-300 mb-8">
                                <?= blog_render_rich_content_html(blog_decode_html_entities_deep((string)$fetch_posts['content'])); ?>
                            </div>

                            <!-- Category Tag -->
                            <a href="category.php?category=<?= $fetch_posts['category']; ?>"
                                class="inline-flex items-center space-x-2 bg-main/10 text-main px-4 py-2 rounded-full font-medium hover:bg-main/20 transition-colors">
                                <i class="fas fa-tag"></i>
                                <span><?= $fetch_posts['category']; ?></span>
                            </a>

                            <?php if (!empty($post_tags)): ?>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <?php foreach ($post_tags as $tag): ?>
                                        <a href="search.php?tag=<?= urlencode((string)$tag['slug']); ?>&size=12&page=1"
                                            class="inline-flex items-center bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 px-3 py-1 rounded-full text-sm hover:bg-main hover:text-white transition-colors">
                                            #<?= htmlspecialchars((string)$tag['name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Post Actions -->
                        <div class="p-6 border-t border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-6">
                                    <div class="flex items-center space-x-2 text-gray-600 dark:text-gray-400">
                                        <i class="fas fa-comment text-lg"></i>
                                        <span class="font-semibold"><?= $total_post_comments; ?> bình luận</span>
                                    </div>

                                    <button type="submit" name="like_post"
                                        class="flex items-center space-x-2 hover:text-red-500 transition-colors <?= $is_post_liked ? 'text-red-500' : 'text-gray-600 dark:text-gray-400' ?>">
                                        <i class="fas fa-heart text-lg"></i>
                                        <span class="font-semibold"><?= $total_post_likes; ?> lượt thích</span>
                                    </button>
                                </div>

                                <!-- Share Buttons -->
                                <div class="flex items-center space-x-3">
                                    <button type="button" onclick="sharePost('facebook')"
                                        class="social-icon bg-blue-600 hover:bg-blue-700">
                                        <i class="fab fa-facebook-f"></i>
                                    </button>
                                    <button type="button" onclick="sharePost('twitter')"
                                        class="social-icon bg-blue-400 hover:bg-blue-500">
                                        <i class="fab fa-twitter"></i>
                                    </button>
                                    <button type="button" onclick="sharePost('copy')"
                                        class="social-icon bg-gray-600 hover:bg-gray-700">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
            <?php
                }
            } else {
                echo '<div class="text-center py-16">';
                echo '<i class="fas fa-exclamation-triangle text-6xl text-gray-300 dark:text-gray-600 mb-4"></i>';
                echo '<p class="text-xl text-gray-500 dark:text-gray-400">Bài viết không tồn tại!</p>';
                echo '</div>';
            }
            ?>
        </article>

        <!-- AI Assistant -->
        <section class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-6 mb-12">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                <i class="fas fa-wand-magic-sparkles text-main"></i>
                AI Trợ lý bài viết <span class="text-sm text-gray-400">(tính năng đang được tối ưu)</span>
            </h2>

            <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 bg-gray-50 dark:bg-gray-700/30">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Tóm tắt 30 giây đọc</h3>
                    <div class="flex items-center gap-2">
                        <div id="aiSummaryModeGroup" class="inline-flex rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden">
                            <button type="button"
                                class="ai-summary-mode-btn px-3 py-1.5 text-xs font-semibold bg-main text-white"
                                data-summary-mode="short">
                                Ngắn
                            </button>
                            <button type="button"
                                class="ai-summary-mode-btn px-3 py-1.5 text-xs font-semibold bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200"
                                data-summary-mode="detailed">
                                Chi tiết
                            </button>
                        </div>
                        <button type="button"
                            id="aiSummaryBtn"
                            class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-main text-white text-sm font-medium hover:opacity-90 transition disabled:opacity-60 disabled:cursor-not-allowed">
                            <i class="fas fa-bolt"></i>
                            Tóm tắt AI (Ngắn)
                        </button>
                    </div>
                </div>
                <p id="aiSummaryText" class="text-sm text-gray-700 dark:text-gray-300 leading-6 whitespace-pre-line"><?= htmlspecialchars($aiSummaryDisplay, ENT_QUOTES, 'UTF-8'); ?></p>
                <p id="aiSummaryMeta" class="hidden text-xs text-gray-500 dark:text-gray-400 mt-2">Nguồn: nội bộ</p>
                <div id="aiSummaryDebugWrap" class="hidden mt-3 rounded-lg border border-amber-300 bg-amber-50 text-amber-900 p-3 text-xs dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-200">
                    <p class="font-semibold mb-1">Chi tiết lỗi Gemini</p>
                    <pre id="aiSummaryDebug" class="whitespace-pre-wrap break-words"></pre>
                </div>
            </div>
        </section>

        <!-- Comments Section -->
        <section class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-6 mb-12">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-8 flex items-center">
                <i class="fas fa-comments mr-3 text-main"></i>
                Bình luận
            </h2>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 flex flex-col">

                    <!-- Comments List -->
                    <div id="commentsList" class="space-y-6 order-1">
                        <?php
                        $commentsPerPage = 10;
                        $commentsPage = max(1, (int)($_GET['comments_page'] ?? 1));

                        $select_total_comments = $conn->prepare("SELECT COUNT(*) FROM `comments` WHERE post_id = ? AND parent_comment_id IN (0, NULL)");
                        $select_total_comments->execute([$get_id]);
                        $totalTopLevel = (int)$select_total_comments->fetchColumn();
                        $pagesCount = max(1, (int)ceil($totalTopLevel / $commentsPerPage));
                        if ($commentsPage > $pagesCount) {
                            $commentsPage = $pagesCount;
                        }

                        $offset = ($commentsPage - 1) * $commentsPerPage;

                        $select_comments = $conn->prepare("SELECT c.*, COALESCE(u.level_of_interaction, 0) AS interaction_score,
                    COALESCE((SELECT SUM(v.vote) FROM comment_votes v WHERE v.comment_id = c.id), 0) AS vote_score,
                    COALESCE((SELECT SUM(CASE WHEN v.vote = 1 THEN 1 ELSE 0 END) FROM comment_votes v WHERE v.comment_id = c.id), 0) AS up_count,
                    COALESCE((SELECT SUM(CASE WHEN v.vote = -1 THEN 1 ELSE 0 END) FROM comment_votes v WHERE v.comment_id = c.id), 0) AS down_count
                    FROM `comments` c
                    LEFT JOIN `users` u ON u.id = c.user_id
                    WHERE c.post_id = ?
                    ORDER BY c.date DESC");
                        $select_comments->execute([$get_id]);
                        $allComments = $select_comments->fetchAll(PDO::FETCH_ASSOC);
                        if (!empty($allComments)) {
                            $nodesByParent = [];
                            foreach ($allComments as $commentRow) {
                                $parentId = isset($commentRow['parent_comment_id']) && (int)$commentRow['parent_comment_id'] > 0
                                    ? (int)$commentRow['parent_comment_id']
                                    : 0;
                                if (!isset($nodesByParent[$parentId])) {
                                    $nodesByParent[$parentId] = [];
                                }
                                $nodesByParent[$parentId][] = $commentRow;
                            }

                            $topLevelIds = array_column($nodesByParent[0] ?? [], 'id');
                            $pageTopLevelIds = array_slice($topLevelIds, $offset, $commentsPerPage);
                            render_comment_branch($nodesByParent, 0, $user_id, $get_id, 0, array_map('intval', $pageTopLevelIds));

                            if ($pagesCount > 1) {
                                echo '<div class="mt-6 flex justify-center space-x-2">';
                                for ($p = 1; $p <= $pagesCount; $p++) {
                                    $active = $p === $commentsPage ? 'bg-main text-white' : 'bg-white text-gray-700 dark:bg-gray-800 dark:text-gray-200';
                                    $url = '?id=' . (int)$get_id . '&comments_page=' . $p;
                                    echo '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" class="px-3 py-1 rounded-lg border border-gray-300 dark:border-gray-700 ' . $active . '">' . $p . '</a>';
                                }
                                echo '</div>';
                            }
                        } else {
                            echo '<div id="commentEmptyState" class="text-center py-8">';
                            echo '<i class="fas fa-comment-slash text-4xl text-gray-300 dark:text-gray-600 mb-4"></i>';
                            echo '<p class="text-gray-500 dark:text-gray-400">Chưa có bình luận nào. Hãy là người đầu tiên bình luận!</p>';
                            echo '</div>';
                        }
                        ?>
                    </div>

                    <!-- Add Comment Form -->
                    <?php if ($user_id != '' && $fetch_profile) {
                        $select_admin_id = $conn->prepare("SELECT * FROM `posts` WHERE id = ?");
                        $select_admin_id->execute([$get_id]);
                        $fetch_admin_id = $select_admin_id->fetch(PDO::FETCH_ASSOC);
                    ?>
                        <form id="commentFormAjax" action="" method="post" class="order-2 mt-8 p-6 bg-gray-50 dark:bg-gray-700/50 rounded-xl" data-comment-form>
                            <input type="hidden" name="post_id" value="<?= (int)$get_id; ?>">
                            <input type="hidden" name="admin_id" value="<?= $fetch_admin_id['admin_id']; ?>">
                            <input type="hidden" name="user_name" value="<?= $fetch_profile['name']; ?>">
                            <input type="hidden" name="parent_comment_id" id="parentCommentId" value="0">
                            <input type="text" name="comment_hp" value="" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

                            <div class="flex items-center space-x-3 mb-4">
                                <div class="w-10 h-10 bg-main rounded-full flex items-center justify-center text-white font-semibold">
                                    <?= strtoupper(substr($fetch_profile['name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <a href="update.php" class="font-semibold text-gray-900 dark:text-white hover:text-main transition-colors">
                                        <?= $fetch_profile['name']; ?>
                                    </a>
                                </div>
                            </div>

                            <textarea name="comment" maxlength="1000" rows="4"
                                id="commentInput"
                                class="form-textarea mb-4"
                                placeholder="Chia sẻ suy nghĩ của bạn về bài viết này..." required></textarea>
                            <div id="replyIndicator" class="hidden mb-2 text-xs text-main"></div>

                            <button type="submit" name="add_comment" class="btn-primary" data-comment-submit>
                                <i class="fas fa-paper-plane mr-2"></i>Gửi bình luận
                            </button>
                        </form>
                    <?php } else { ?>
                        <div class="order-2 mt-8 p-6 bg-gray-50 dark:bg-gray-700/50 rounded-xl text-center">
                            <i class="fas fa-sign-in-alt text-4xl text-gray-400 mb-4"></i>
                            <p class="text-gray-600 dark:text-gray-400 mb-4">Vui lòng đăng nhập để bình luận</p>
                            <a href="login.php" class="btn-primary">
                                <i class="fas fa-sign-in-alt mr-2"></i>Đăng nhập ngay
                            </a>
                        </div>
                    <?php } ?>

                </div>

                <aside class="lg:col-span-1">
                    <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 bg-gray-50 dark:bg-gray-700/30 lg:sticky lg:top-24">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-3">Đề xuất cho bạn</h3>
                        <?php if (!empty($personalized_posts)): ?>
                            <div class="space-y-3">
                                <?php foreach ($personalized_posts as $rec): ?>
                                    <?php $decoded_rec_title = blog_decode_html_entities_deep((string)($rec['title'] ?? '')); ?>
                                    <a href="<?= post_path((int)$rec['id']); ?>" class="block rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-3 hover:-translate-y-0.5 hover:shadow transition">
                                        <p class="text-[11px] uppercase text-main mb-1"><?= htmlspecialchars($rec['category'] ?? 'Chung', ENT_QUOTES, 'UTF-8'); ?></p>
                                        <h4 class="text-sm font-semibold text-gray-900 dark:text-white line-clamp-2"><?= htmlspecialchars($decoded_rec_title, ENT_QUOTES, 'UTF-8'); ?></h4>

                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Chưa đủ dữ liệu cho bạn.</p>
                        <?php endif; ?>
                    </div>
                </aside>
            </div>
        </section>

        <!-- Related Posts -->
        <section class="mb-12">
            <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4 text-center">
                Bài viết <span class="gradient-text">cùng danh mục</span>
            </h2>
            <p class="text-center text-gray-600 dark:text-gray-300 mb-8">Danh mục: <span class="font-semibold text-gray-900 dark:text-white"><?= htmlspecialchars($fetch_posts['category'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></p>
            <div class="section-divider mb-8"></div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php
                if (!empty($related_posts)) {
                    foreach ($related_posts as $fetch_related_posts) {
                        $related_post_id = $fetch_related_posts['id'];
                        $decoded_related_title = blog_decode_html_entities_deep((string)$fetch_related_posts['title']);
                        $total_post_comments = (int)($post_comment_count_map[(int)$related_post_id] ?? 0);
                        $total_post_likes = (int)($post_like_count_map[(int)$related_post_id] ?? 0);
                        $is_related_liked = !empty($user_liked_post_map[(int)$related_post_id]);
                        $is_related_saved = !empty($user_saved_post_map[(int)$related_post_id]);
                ?>
                        <article class="card dark:bg-gray-700 group blog-card-shared hover:shadow-xl transition-all duration-300 transform hover:-translate-y-2">
                            <form method="post" class="h-full flex flex-col">
                                <input type="hidden" name="post_id" value="<?= $related_post_id; ?>">
                                <input type="hidden" name="admin_id" value="<?= $fetch_related_posts['admin_id']; ?>">

                                <!-- Post Header -->
                                <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-600">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 bg-main rounded-full flex items-center justify-center text-white text-sm font-semibold">
                                            <?= strtoupper(substr($fetch_related_posts['name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <a href="author_posts.php?author=<?= $fetch_related_posts['name']; ?>"
                                                class="font-semibold text-gray-900 dark:text-white hover:text-main transition-colors text-sm">
                                                <?= $fetch_related_posts['name']; ?>
                                            </a>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                <?= date('d/m/Y', strtotime($fetch_related_posts['date'])); ?>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" name="save_post" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                                        <i class="fas fa-bookmark <?= $is_related_saved ? 'text-yellow-500' : 'text-gray-400' ?>"></i>
                                    </button>
                                </div>

                                <!-- Post Image -->
                                <?php if ($fetch_related_posts['image'] != '') : ?>
                                    <div class="relative overflow-hidden h-40 rounded-lg mt-4">
                                        <img src="<?= htmlspecialchars(blog_post_image_src((string)$fetch_related_posts['image'], '../uploaded_img/', '../uploaded_img/default_img.jpg'), ENT_QUOTES, 'UTF-8'); ?>"
                                            alt="<?= htmlspecialchars($decoded_related_title, ENT_QUOTES, 'UTF-8'); ?>"
                                            loading="lazy"
                                            decoding="async"
                                            class="blog-card-image">
                                    </div>
                                <?php endif; ?>

                                <!-- Post Content -->
                                <div class="py-4 flex-1 flex flex-col">
                                    <h3 class="font-bold text-gray-900 dark:text-white hover:text-main transition-colors mb-2 line-clamp-2">
                                        <a href="<?= post_path($related_post_id, $decoded_related_title); ?>">
                                            <?= htmlspecialchars($decoded_related_title, ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                    </h3>

                                    <div class="text-gray-600 dark:text-gray-300 text-sm mb-3 line-clamp-2 flex-1">
                                        <?= strip_tags(html_entity_decode((string)$fetch_related_posts['content'], ENT_QUOTES | ENT_HTML5, 'UTF-8')); ?>
                                    </div>

                                    <a href="<?= post_path($related_post_id, $decoded_related_title); ?>"
                                        class="text-main font-semibold hover:text-blue-700 transition-colors text-sm mb-3">
                                        Đọc thêm →
                                    </a>

                                    <!-- Category -->
                                    <a href="category.php?category=<?= $fetch_related_posts['category']; ?>"
                                        class="inline-flex items-center space-x-1 bg-main/10 text-main px-2 py-1 rounded-full text-xs font-medium hover:bg-main/20 transition-colors w-fit">
                                        <i class="fas fa-tag"></i>
                                        <span><?= $fetch_related_posts['category']; ?></span>
                                    </a>
                                </div>

                                <!-- Post Actions -->
                                <div class="py-4 border-t border-gray-200 dark:border-gray-600 flex items-center justify-between">
                                    <a href="<?= post_path($related_post_id, $decoded_related_title); ?>"
                                        class="flex items-center space-x-1 text-gray-500 dark:text-gray-400 hover:text-main transition-colors text-sm">
                                        <i class="fas fa-comment"></i>
                                        <span><?= $total_post_comments; ?></span>
                                    </a>

                                    <button type="submit" name="like_post"
                                        class="flex items-center space-x-1 text-gray-500 dark:text-gray-400 hover:text-red-500 transition-colors text-sm">
                                        <i class="fas fa-heart <?= $is_related_liked ? 'text-red-500' : '' ?>"></i>
                                        <span><?= $total_post_likes; ?></span>
                                    </button>
                                </div>
                            </form>
                        </article>
                <?php
                    }
                } else {
                    echo '<div class="col-span-full text-center py-8">';
                    echo '<i class="fas fa-search text-4xl text-gray-300 dark:text-gray-600 mb-4"></i>';
                    echo '<p class="text-gray-500 dark:text-gray-400">Không có bài viết liên quan!</p>';
                    echo '</div>';
                }
                ?>
            </div>
        </section>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="fixed inset-0 bg-black bg-opacity-90 hidden items-center justify-center z-50">
        <div class="relative max-w-4xl max-h-full p-4">
            <button id="closeModal" class="absolute -top-12 right-0 text-white text-2xl hover:text-gray-300 transition-colors">
                <i class="fas fa-times"></i>
            </button>
            <img id="modalImage" class="max-w-full max-h-full object-contain rounded-lg shadow-2xl">
            <button id="saveImage" class="absolute -bottom-12 right-0 bg-main text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                <i class="fas fa-download mr-2"></i>Tải về
            </button>
        </div>
    </div>
</main>

<!-- Enhanced JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const readStart = Date.now();
        const postId = <?= (int)$get_id; ?>;
        const aiSummaryBtn = document.getElementById('aiSummaryBtn');
        const aiSummaryText = document.getElementById('aiSummaryText');
        const aiSummaryMeta = document.getElementById('aiSummaryMeta');
        const aiSummaryDebugWrap = document.getElementById('aiSummaryDebugWrap');
        const aiSummaryDebug = document.getElementById('aiSummaryDebug');
        const aiSummaryModeButtons = Array.from(document.querySelectorAll('.ai-summary-mode-btn'));
        let selectedSummaryMode = 'short';

        function escapeHtml(text) {
            return String(text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function formatAiSummary(rawSummary) {
            const text = String(rawSummary || '').trim();
            if (text === '') {
                return '<p class="text-sm text-gray-700 dark:text-gray-300 leading-6">Chưa có nội dung tóm tắt.</p>';
            }

            const lines = text.split(/\r?\n/)
                .map((line) => line.trim())
                .filter((line) => line !== '');

            const items = [];
            let conclusion = null;

            lines.forEach((line) => {
                const lower = line.toLowerCase();
                if (/^(\d+)[\.\)]\s*/.test(line)) {
                    items.push(line.replace(/^(\d+)[\.\)]\s*/, ''));
                } else if (lower.startsWith('kết luận:') || lower.startsWith('kết luận')) {
                    conclusion = line.replace(/^(kết luận:\s*)/i, '');
                } else if (/^[\-\*•]\s*/.test(line)) {
                    items.push(line.replace(/^[\-\*•]\s*/, ''));
                } else {
                    items.push(line);
                }
            });

            if (items.length > 0) {
                const listItems = items
                    .map((item) => '<li class="my-1 text-sm text-gray-700 dark:text-gray-300">' + escapeHtml(item) + '</li>')
                    .join('');

                let html = '<ol class="list-decimal list-inside space-y-2 mb-3">' + listItems + '</ol>';
                if (conclusion !== null) {
                    html += '<p class="text-sm font-semibold text-gray-800 dark:text-gray-200">Kết luận: ' + escapeHtml(conclusion) + '</p>';
                }
                return html;
            }

            return '<p class="text-sm text-gray-700 dark:text-gray-300 leading-6">' + escapeHtml(text) + '</p>';
        }

        if (aiSummaryText) {
            aiSummaryText.innerHTML = formatAiSummary(aiSummaryText.textContent);
        }

        function updateSummaryModeUi() {
            aiSummaryModeButtons.forEach((btn) => {
                const mode = btn.getAttribute('data-summary-mode');
                const isActive = mode === selectedSummaryMode;
                btn.classList.toggle('bg-main', isActive);
                btn.classList.toggle('text-white', isActive);
                btn.classList.toggle('bg-white', !isActive);
                btn.classList.toggle('dark:bg-gray-700', !isActive);
                btn.classList.toggle('text-gray-700', !isActive);
                btn.classList.toggle('dark:text-gray-200', !isActive);
            });

            if (aiSummaryBtn) {
                aiSummaryBtn.innerHTML = '<i class="fas fa-bolt"></i> Tóm tắt AI (' + (selectedSummaryMode === 'detailed' ? 'Chi tiết' : 'Ngắn') + ')';
            }
        }

        aiSummaryModeButtons.forEach((btn) => {
            btn.addEventListener('click', function() {
                selectedSummaryMode = this.getAttribute('data-summary-mode') === 'detailed' ? 'detailed' : 'short';
                updateSummaryModeUi();
            });
        });
        updateSummaryModeUi();

        async function fetchAiSummary() {
            if (!aiSummaryBtn || !aiSummaryText || !aiSummaryMeta) {
                return;
            }

            if (aiSummaryDebugWrap && aiSummaryDebug) {
                aiSummaryDebugWrap.classList.add('hidden');
                aiSummaryDebug.textContent = '';
            }

            const endpoint = (window.BLOG_ENDPOINTS && window.BLOG_ENDPOINTS.aiPostSummary) ?
                window.BLOG_ENDPOINTS.aiPostSummary :
                'post_ai_summary_api.php';

            const originalLabel = aiSummaryBtn.innerHTML;
            aiSummaryBtn.disabled = true;
            aiSummaryBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang tóm tắt...';

            try {
                const body = new URLSearchParams();
                body.set('post_id', String(postId));
                body.set('summary_mode', selectedSummaryMode);

                const res = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: body.toString()
                });

                const text = await res.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch (parseError) {
                    throw new Error('Kết quả trả về không phải JSON: ' + parseError.message + ' | Raw: ' + text.slice(0, 256));
                }
                if (!data || !data.ok) {
                    throw new Error(data && data.message ? data.message : 'Không thể tạo tóm tắt lúc này.');
                }

                aiSummaryText.innerHTML = formatAiSummary(String(data.summary || '').trim() || 'Chưa có nội dung tóm tắt.');
                if (data.source === 'gemini') {
                    aiSummaryMeta.textContent = 'Nguồn: Gemini (server-side API)';
                } else if (data.source === 'gemini-cache') {
                    aiSummaryMeta.textContent = 'Nguồn: Gemini cache (tiết kiệm chi phí, phản hồi nhanh)';
                } else {
                    aiSummaryMeta.textContent = 'Nguồn: tóm tắt nội bộ (fallback)';
                }

                if (data.debug && aiSummaryDebugWrap && aiSummaryDebug) {
                    const lines = [];
                    if (typeof data.debug.http_code !== 'undefined') {
                        lines.push('HTTP: ' + data.debug.http_code);
                    }
                    if (data.debug.error) {
                        lines.push('Error: ' + data.debug.error);
                    }
                    if (data.debug.provider_message) {
                        lines.push('Provider: ' + data.debug.provider_message);
                    }
                    if (data.debug.transport_error) {
                        lines.push('Transport: ' + data.debug.transport_error);
                    }
                    if (Array.isArray(data.debug.tried_models) && data.debug.tried_models.length) {
                        lines.push('Tried models: ' + data.debug.tried_models.join(', '));
                    }
                    if (Array.isArray(data.debug.available_models_for_key) && data.debug.available_models_for_key.length) {
                        lines.push('Available for key: ' + data.debug.available_models_for_key.join(', '));
                    }
                    if (data.debug.suggested_env) {
                        lines.push('Suggestion: ' + data.debug.suggested_env);
                    }

                    aiSummaryDebug.textContent = lines.join('\n');
                    aiSummaryDebugWrap.classList.remove('hidden');
                }

                if (data.message && typeof showNotification === 'function') {
                    showNotification(data.message, 'info');
                }
            } catch (error) {
                if (typeof showNotification === 'function') {
                    showNotification(error.message || 'Đã xảy ra lỗi khi tóm tắt.', 'error');
                }
            } finally {
                aiSummaryBtn.disabled = false;
                aiSummaryBtn.innerHTML = originalLabel || ('<i class="fas fa-bolt"></i> Tóm tắt AI (' + (selectedSummaryMode === 'detailed' ? 'Chi tiết' : 'Ngắn') + ')');
            }
        }

        if (aiSummaryBtn) {
            aiSummaryBtn.addEventListener('click', fetchAiSummary);
        }

        const sendReadEvent = () => {
            const seconds = Math.max(1, Math.round((Date.now() - readStart) / 1000));
            const payload = new URLSearchParams();
            payload.set('post_id', String(postId));
            payload.set('seconds_read', String(seconds));

            if (navigator.sendBeacon) {
                const blob = new Blob([payload.toString()], {
                    type: 'application/x-www-form-urlencoded;charset=UTF-8'
                });
                navigator.sendBeacon('read_event.php', blob);
            } else {
                fetch('read_event.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
                    },
                    body: payload.toString(),
                    keepalive: true
                });
            }
        };

        window.addEventListener('beforeunload', sendReadEvent);

        // Image modal functionality
        const modal = document.getElementById("imageModal");
        const modalImg = document.getElementById("modalImage");
        const imageShow = document.getElementById("image_show");
        const closeModal = document.getElementById("closeModal");
        const saveImage = document.getElementById('saveImage');

        if (imageShow) {
            imageShow.onclick = function() {
                modal.style.display = "flex";
                modalImg.src = this.src;
            }
        }

        if (closeModal) {
            closeModal.onclick = function() {
                modal.style.display = "none";
            }
        }

        if (modal) {
            modal.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = "none";
                }
            }
        }

        if (saveImage && imageShow) {
            saveImage.onclick = function() {
                const link = document.createElement('a');
                link.href = imageShow.src;
                link.download = 'post_image';
                link.click();
                saveImage.classList.add('bg-green-500');
                saveImage.innerHTML = '<i class="fas fa-check mr-2"></i>Đã tải';
            }
        }

        const commentsList = document.getElementById('commentsList');
        const commentForm = document.querySelector('[data-comment-form]');

        function getDirectReplyCount(repliesWrap) {
            if (!repliesWrap) {
                return 0;
            }
            return Array.from(repliesWrap.children).filter((child) => child.hasAttribute('data-comment-id')).length;
        }

        function ensureReplyToggleButton(parentComment, parentId, repliesWrap) {
            if (!parentComment || !repliesWrap) {
                return;
            }

            const count = getDirectReplyCount(repliesWrap);
            const actionRow = parentComment.querySelector('[data-comment-actions]');
            if (!actionRow) {
                return;
            }

            const textEl = document.getElementById('reply-toggle-text-' + parentId);
            if (textEl) {
                const isHidden = repliesWrap.classList.contains('hidden');
                textEl.textContent = (isHidden ? 'Hiện phản hồi (' : 'Ẩn phản hồi (') + count + ')';
                return;
            }

            const toggleBtn = document.createElement('button');
            toggleBtn.type = 'button';
            toggleBtn.className = 'px-2 py-1 rounded bg-blue-100 text-blue-700 text-xs hover:bg-blue-200';
            toggleBtn.innerHTML = '<i class="fas fa-code-branch mr-1"></i><span id="reply-toggle-text-' + parentId + '">Ẩn phản hồi (' + count + ')</span>';
            toggleBtn.addEventListener('click', function() {
                toggleReplies(parentId);
            });
            actionRow.appendChild(toggleBtn);
        }

        function initReplyLoadMore() {
            document.querySelectorAll('[id^="replies-"]').forEach(function(container) {
                const replies = Array.from(container.children).filter(function(child) {
                    return child.nodeType === Node.ELEMENT_NODE;
                });
                if (replies.length <= 2) {
                    return;
                }

                replies.slice(2).forEach(function(child) {
                    child.classList.add('hidden');
                });

                const parentId = container.id.replace('replies-', '');
                const showMoreBtn = document.createElement('button');
                showMoreBtn.type = 'button';
                showMoreBtn.className = 'px-3 py-1 text-xs text-main border border-main rounded-lg hover:bg-main/10 transition';
                showMoreBtn.textContent = 'Xem thêm ' + (replies.length - 2) + ' phản hồi';

                showMoreBtn.addEventListener('click', function() {
                    replies.slice(2).forEach(function(child) {
                        child.classList.remove('hidden');
                    });
                    showMoreBtn.remove();
                });

                container.insertAdjacentElement('afterend', showMoreBtn);
            });
        }

        if (commentForm && commentsList) {
            initReplyLoadMore();
            commentForm.addEventListener('submit', async function(e) {
                e.preventDefault();

                const submitBtn = this.querySelector('[data-comment-submit]');
                const commentInput = this.querySelector('#commentInput');
                const replyInput = this.querySelector('#parentCommentId');
                const emptyState = document.getElementById('commentEmptyState');

                const endpointCandidates = [
                    (window.BLOG_ENDPOINTS && window.BLOG_ENDPOINTS.commentAdd) ? window.BLOG_ENDPOINTS.commentAdd : '',
                    'comment_add.php',
                    '../static/comment_add.php',
                    '/static/comment_add.php',
                    '/project/static/comment_add.php'
                ].filter(Boolean);
                const originalBtn = submitBtn ? submitBtn.innerHTML : '';
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Đang gửi...';
                }

                try {
                    const formData = new FormData(this);
                    if (!formData.has('add_comment')) {
                        formData.append('add_comment', '1');
                    }
                    let payload = null;
                    let lastError = null;
                    for (const endpoint of endpointCandidates) {
                        try {
                            const res = await fetch(endpoint, {
                                method: 'POST',
                                body: formData,
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                credentials: 'same-origin'
                            });

                            if (!res.ok) {
                                lastError = new Error('request-failed:' + endpoint + ':status=' + res.status);
                                continue;
                            }

                            const contentType = (res.headers.get('content-type') || '').toLowerCase();
                            if (!contentType.includes('application/json')) {
                                const invalidText = (await res.text()).slice(0, 180);
                                lastError = new Error('invalid-json-response:' + endpoint + ':' + invalidText);
                                continue;
                            }

                            payload = await res.json();
                            break;
                        } catch (fetchErr) {
                            lastError = fetchErr;
                        }
                    }

                    if (!payload) {
                        throw lastError || new Error('comment-endpoint-unavailable');
                    }

                    if (!payload || payload.ok !== true) {
                        if (payload && payload.login_required && payload.login_url) {
                            showNotification(payload.message || 'Bạn cần đăng nhập để bình luận.', 'warning');
                            setTimeout(() => {
                                window.location.href = payload.login_url;
                            }, 600);
                            return;
                        }
                        const debugSuffix = (window.BLOG_DEBUG_ENDPOINTS && payload && payload.debug) ?
                            ('\n[Debug] ' + payload.debug) :
                            '';
                        showNotification(((payload && payload.message) || 'Không thể gửi bình luận lúc này.') + debugSuffix, 'error');
                        return;
                    }

                    const commentData = payload.comment;
                    if (commentData) {
                        if (emptyState) {
                            emptyState.remove();
                        }

                        const parentId = Number(commentData.parent_comment_id || 0);
                        const commentHtml = String(payload.comment_html || '').trim();
                        if (!commentHtml) {
                            showNotification('Dữ liệu bình luận trả về chưa hợp lệ.', 'error');
                            return;
                        }

                        if (parentId > 0) {
                            let repliesWrap = document.getElementById('replies-' + parentId);
                            if (!repliesWrap) {
                                repliesWrap = document.createElement('div');
                                repliesWrap.id = 'replies-' + parentId;
                                repliesWrap.className = 'mt-3 space-y-3';
                                const parentComment = document.querySelector('[data-comment-id="' + parentId + '"]');
                                if (parentComment) {
                                    parentComment.insertAdjacentElement('afterend', repliesWrap);
                                } else {
                                    commentsList.prepend(repliesWrap);
                                }
                            }
                            repliesWrap.insertAdjacentHTML('afterbegin', commentHtml);
                            repliesWrap.classList.remove('hidden');
                            const parentComment = document.querySelector('[data-comment-id="' + parentId + '"]');
                            ensureReplyToggleButton(parentComment, parentId, repliesWrap);
                        } else {
                            commentsList.insertAdjacentHTML('afterbegin', commentHtml);
                        }
                    }

                    if (commentInput) {
                        commentInput.value = '';
                    }
                    if (replyInput) {
                        replyInput.value = '0';
                    }
                    cancelReply();

                    showNotification(payload.message || 'Đã gửi bình luận!', 'success');
                } catch (err) {
                    const debugSuffix = (window.BLOG_DEBUG_ENDPOINTS && err && err.message) ?
                        ('\n[Debug] ' + String(err.message).slice(0, 220)) :
                        '';
                    if (err && String(err.message || '').startsWith('invalid-json-response')) {
                        showNotification('Endpoint bình luận phản hồi không hợp lệ.' + debugSuffix, 'error');
                    } else {
                        showNotification('Lỗi kết nối khi gửi bình luận.' + debugSuffix, 'error');
                    }
                } finally {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalBtn;
                    }
                }
            });
        }

        // Comment vote via AJAX
        document.querySelectorAll('[data-comment-vote]').forEach((btn) => {
            btn.addEventListener('click', async function() {
                const commentId = this.getAttribute('data-comment-id');
                const voteType = this.getAttribute('data-vote-type');
                const endpoint = (window.BLOG_ENDPOINTS && window.BLOG_ENDPOINTS.commentVote) ? window.BLOG_ENDPOINTS.commentVote : 'comment_vote.php';

                this.disabled = true;
                try {
                    const formData = new URLSearchParams();
                    formData.set('comment_id', commentId || '0');
                    formData.set('vote_type', voteType || '');

                    const res = await fetch(endpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData.toString(),
                        credentials: 'same-origin'
                    });

                    const payload = await res.json();
                    if (!payload || payload.ok !== true) {
                        if (payload && payload.login_required && payload.login_url) {
                            showNotification(payload.message || 'Ban can dang nhap de vote.', 'warning');
                            setTimeout(() => {
                                window.location.href = payload.login_url;
                            }, 500);
                            return;
                        }
                        showNotification((payload && payload.message) || 'Khong the vote luc nay.', 'error');
                        return;
                    }

                    const likeCountEl = document.getElementById('comment-like-count-' + payload.comment_id);
                    if (likeCountEl && typeof payload.up_count === 'number') {
                        likeCountEl.textContent = String(payload.up_count);
                    }
                    const dislikeCountEl = document.getElementById('comment-dislike-count-' + payload.comment_id);
                    if (dislikeCountEl && typeof payload.down_count === 'number') {
                        dislikeCountEl.textContent = String(payload.down_count);
                    }
                    showNotification(payload.message || 'Da cap nhat vote.', 'success');
                } catch (err) {
                    showNotification('Loi ket noi khi vote binh luan.', 'error');
                } finally {
                    this.disabled = false;
                }
            });
        });

        // Enhanced post card interactions
        const postCards = document.querySelectorAll('.blog-card-shared');
        postCards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-8px) scale(1.02)';
            });

            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });
    });

    // Comment menu toggle
    function toggleCommentMenu(commentId) {
        const menu = document.getElementById('comment-menu-' + commentId);
        const allMenus = document.querySelectorAll('[id^="comment-menu-"]');

        allMenus.forEach(m => {
            if (m !== menu) m.classList.add('hidden');
        });

        menu.classList.toggle('hidden');
    }

    function replyToComment(commentId) {
        const input = document.getElementById('commentInput');
        const parentInput = document.getElementById('parentCommentId');
        const indicator = document.getElementById('replyIndicator');
        if (!input || !parentInput || !indicator) return;

        parentInput.value = String(commentId);
        indicator.innerHTML = 'Dang tra loi binh luan #' + commentId + ' <button type="button" class="underline" onclick="cancelReply()">Huy</button>';
        indicator.classList.remove('hidden');
        input.focus();
        input.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });
    }

    function cancelReply() {
        const parentInput = document.getElementById('parentCommentId');
        const indicator = document.getElementById('replyIndicator');
        if (parentInput) parentInput.value = '0';
        if (indicator) {
            indicator.classList.add('hidden');
            indicator.textContent = '';
        }
    }

    function toggleReplies(commentId) {
        const wrap = document.getElementById('replies-' + commentId);
        const text = document.getElementById('reply-toggle-text-' + commentId);
        if (!wrap || !text) return;
        const count = Array.from(wrap.children).filter((child) => child.hasAttribute('data-comment-id')).length;
        const hidden = wrap.classList.toggle('hidden');
        text.textContent = hidden ? ('Hiện phản hồi (' + count + ')') : ('Ẩn phản hồi (' + count + ')');
    }

    // Share functionality
    function sharePost(platform) {
        const url = window.location.href;
        const title = document.querySelector('h1').textContent;

        switch (platform) {
            case 'facebook':
                window.open(`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`, '_blank');
                break;
            case 'twitter':
                window.open(`https://twitter.com/intent/tweet?url=${encodeURIComponent(url)}&text=${encodeURIComponent(title)}`, '_blank');
                break;
            case 'copy':
                navigator.clipboard.writeText(url).then(() => {
                    alert('Đã sao chép liên kết!');
                });
                break;
        }
    }

    // Close comment menus when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('[id^="comment-menu-"]') && !e.target.closest('button[onclick^="toggleCommentMenu"]')) {
            document.querySelectorAll('[id^="comment-menu-"]').forEach(menu => {
                menu.classList.add('hidden');
            });
        }
    });
</script>

<?php include '../components/layout_footer.php'; ?>
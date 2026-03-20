<?php
ob_start();
include_once __DIR__ . '/../components/connect.php';
include_once __DIR__ . '/../components/seo_helpers.php';
include_once __DIR__ . '/../components/feature_engine.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

function json_fail(string $message, array $extra = []): void
{
    if (ob_get_length() > 0) {
        ob_clean();
    }
    echo json_encode(array_merge([
        'ok' => false,
        'message' => $message,
    ], $extra));
    exit;
}

function render_comment_html_fragment(array $commentRow, bool $showReplyButton = true): string
{
    $commentId = (int)($commentRow['id'] ?? 0);
    $parentCommentId = (int)($commentRow['parent_comment_id'] ?? 0);
    $isReply = $parentCommentId > 0;
    $userName = (string)($commentRow['user_name'] ?? '');
    $badge = blog_user_badge((int)($commentRow['interaction_score'] ?? 0));
    $upCount = (int)($commentRow['up_count'] ?? 0);
    $downCount = (int)($commentRow['down_count'] ?? 0);
    $dateRaw = (string)($commentRow['date'] ?? date('Y-m-d H:i:s'));
    $dateDisplay = date('d/m/Y H:i', strtotime($dateRaw));
    $safeName = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');
    $safeInitial = htmlspecialchars(strtoupper(substr($userName, 0, 1)), ENT_QUOTES, 'UTF-8');
    $safeComment = nl2br(htmlspecialchars((string)($commentRow['comment'] ?? ''), ENT_QUOTES, 'UTF-8'));
    $safeDate = htmlspecialchars($dateDisplay, ENT_QUOTES, 'UTF-8');
    $safeBadgeClass = htmlspecialchars((string)($badge['class'] ?? ''), ENT_QUOTES, 'UTF-8');
    $safeBadgeLabel = htmlspecialchars((string)($badge['label'] ?? ''), ENT_QUOTES, 'UTF-8');

    $replyAction = '';
    if ($showReplyButton) {
        $replyAction = '<button type="button" class="px-2 py-1 rounded bg-gray-100 text-gray-700 text-xs hover:bg-gray-200" onclick="replyToComment(' . $commentId . ')"><i class="fas fa-reply mr-1"></i>Trả lời</button>';
    }

    $replyClass = $isReply ? ' comment-reply-item' : '';

    return '<div data-comment-id="' . $commentId . '" class="flex space-x-4 p-4 rounded-xl transition-all duration-300 hover:bg-gray-50 dark:hover:bg-gray-700/50 bg-main/5 border border-main/20' . $replyClass . '">'
        . '<div class="w-10 h-10 bg-main rounded-full flex items-center justify-center text-white font-semibold flex-shrink-0">' . $safeInitial . '</div>'
        . '<div class="flex-1">'
        . '<div class="flex items-center justify-between mb-2">'
        . '<div><span class="font-semibold text-gray-900 dark:text-white">' . $safeName . '</span>'
        . '<span class="ml-2 px-2 py-1 text-[11px] rounded-full ' . $safeBadgeClass . '">' . $safeBadgeLabel . '</span>'
        . '<span class="ml-2 px-2 py-1 text-xs bg-main text-white rounded-full">Bạn</span>'
        . '</div>'
        . '<span class="text-sm text-gray-500 dark:text-gray-400">' . $safeDate . '</span>'
        . '</div>'
        . '<div class="text-gray-700 dark:text-gray-300 leading-relaxed">' . $safeComment . '</div>'
        . '<div class="mt-3 flex items-center gap-2 flex-wrap" data-comment-actions>'
        . '<button type="button" data-comment-vote data-vote-type="up" data-comment-id="' . $commentId . '" class="px-2 py-1 rounded bg-green-100 text-green-700 text-xs hover:bg-green-200"><i class="fas fa-thumbs-up mr-1"></i><span id="comment-like-count-' . $commentId . '">' . $upCount . '</span></button>'
        . '<button type="button" data-comment-vote data-vote-type="down" data-comment-id="' . $commentId . '" class="px-2 py-1 rounded bg-rose-100 text-rose-700 text-xs hover:bg-rose-200"><i class="fas fa-thumbs-down mr-1"></i><span id="comment-dislike-count-' . $commentId . '">' . $downCount . '</span></button>'
        . $replyAction
        . '</div>'
        . '</div>'
        . '</div>';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_fail('Phương thức không hợp lệ.');
}

$is_local_debug = preg_match('/^(localhost|127\\.0\\.0\\.1|::1)(:\\d+)?$/i', (string)($_SERVER['HTTP_HOST'] ?? '')) === 1;

try {
    $user_id = $_SESSION['user_id'] ?? '';
    if ($user_id === '') {
        json_fail('Vui lòng đăng nhập để bình luận.', [
            'login_required' => true,
            'login_url' => site_url('static/login.php')
        ]);
    }

    $post_id = (int)($_POST['post_id'] ?? 0);
    $admin_id = (int)($_POST['admin_id'] ?? 0);
    $parent_comment_id = (int)($_POST['parent_comment_id'] ?? 0);
    $comment = trim((string)($_POST['comment'] ?? ''));
    $comment = filter_var($comment, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    if ($post_id <= 0 || $admin_id <= 0 || $comment === '') {
        json_fail('Dữ liệu bình luận không hợp lệ.', $is_local_debug ? [
            'debug' => 'post_id=' . $post_id . ', admin_id=' . $admin_id . ', comment_len=' . strlen($comment)
        ] : []);
    }

    if ($parent_comment_id < 0) {
        $parent_comment_id = 0;
    }

    $profileStmt = $conn->prepare('SELECT id, name, COALESCE(level_of_interaction, 0) AS interaction_score FROM users WHERE id = ? LIMIT 1');
    $profileStmt->execute([(int)$user_id]);
    $profile = $profileStmt->fetch(PDO::FETCH_ASSOC);
    if (!$profile) {
        json_fail('Không tìm thấy tài khoản người dùng.');
    }

    $user_name = (string)$profile['name'];
    $interaction_score = (int)$profile['interaction_score'];

    $spamReason = blog_detect_spam_or_toxic($comment);
    if ($spamReason !== '') {
        json_fail($spamReason);
    }

    $verify_comment = $conn->prepare('SELECT id FROM comments WHERE post_id = ? AND admin_id = ? AND user_id = ? AND user_name = ? AND comment = ? ORDER BY id DESC LIMIT 1');
    $verify_comment->execute([$post_id, $admin_id, $user_id, $user_name, $comment]);
    $existing = $verify_comment->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $comment_id = (int)$existing['id'];
    } else {
        $commentDate = date('Y-m-d H:i:s');
        $hasCommentDateColumn = function_exists('blog_db_has_column') ? blog_db_has_column($conn, 'comments', 'date') : true;
        if ($hasCommentDateColumn) {
            $insert_comment = $conn->prepare('INSERT INTO comments(post_id, parent_comment_id, admin_id, user_id, user_name, comment, date) VALUES(?,?,?,?,?,?,?)');
            $insert_comment->execute([$post_id, $parent_comment_id > 0 ? $parent_comment_id : null, $admin_id, $user_id, $user_name, $comment, $commentDate]);
        } else {
            $insert_comment = $conn->prepare('INSERT INTO comments(post_id, parent_comment_id, admin_id, user_id, user_name, comment) VALUES(?,?,?,?,?,?)');
            $insert_comment->execute([$post_id, $parent_comment_id > 0 ? $parent_comment_id : null, $admin_id, $user_id, $user_name, $comment]);
        }
        $comment_id = (int)$conn->lastInsertId();

        $ownerSql = 'SELECT id FROM users WHERE id = ? LIMIT 1';
        $ownerParams = [$admin_id];
        if (function_exists('blog_has_legacy_admin_id_column') && blog_has_legacy_admin_id_column($conn)) {
            $ownerSql = 'SELECT id FROM users WHERE legacy_admin_id = ? OR id = ? LIMIT 1';
            $ownerParams = [$admin_id, $admin_id];
        }
        $ownerStmt = $conn->prepare($ownerSql);
        $ownerStmt->execute($ownerParams);
        $ownerUser = $ownerStmt->fetch(PDO::FETCH_ASSOC);
        if ($ownerUser && (int)$ownerUser['id'] !== (int)$user_id) {
            blog_push_notification(
                $conn,
                (int)$ownerUser['id'],
                'comment',
                'Binh luan moi',
                $user_name . ' vua binh luan ve bai viet cua ban.',
                post_path($post_id)
            );
        }

        if ($parent_comment_id > 0) {
            $parentStmt = $conn->prepare('SELECT user_id FROM comments WHERE id = ? LIMIT 1');
            $parentStmt->execute([$parent_comment_id]);
            $parentComment = $parentStmt->fetch(PDO::FETCH_ASSOC);
            $parentUserId = (int)($parentComment['user_id'] ?? 0);
            if ($parentUserId > 0 && $parentUserId !== (int)$user_id) {
                blog_push_notification(
                    $conn,
                    $parentUserId,
                    'comment_reply',
                    'Binh luan duoc tra loi',
                    $user_name . ' da tra loi binh luan cua ban.',
                    post_path($post_id)
                );
            }
        }

        $mentions = blog_extract_mentions($comment);
        if (!empty($mentions)) {
            $placeholders = implode(',', array_fill(0, count($mentions), '?'));
            $mentionStmt = $conn->prepare("SELECT id FROM users WHERE LOWER(name) IN ({$placeholders}) LIMIT 10");
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
                        post_path($post_id)
                    );
                }
            }
        }
    }

    $commentRow = [
        'id' => $comment_id,
        'parent_comment_id' => $parent_comment_id,
        'user_name' => $user_name,
        'comment' => $comment,
        'date' => date('Y-m-d H:i:s'),
        'interaction_score' => $interaction_score,
        'up_count' => 0,
        'down_count' => 0,
    ];

    $comment_html = render_comment_html_fragment($commentRow, true);

    if (ob_get_length() > 0) {
        ob_clean();
    }

    echo json_encode([
        'ok' => true,
        'message' => 'Đã gửi bình luận!',
        'comment' => [
            'id' => $comment_id,
            'parent_comment_id' => $parent_comment_id,
            'user_name' => $user_name,
            'comment' => $comment,
            'date' => date('d/m/Y H:i')
        ],
        'comment_html' => $comment_html
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    json_fail('Không thể gửi bình luận lúc này. Vui lòng thử lại.', $is_local_debug ? [
        'debug' => $e->getMessage() . ' @' . basename((string)$e->getFile()) . ':' . (int)$e->getLine()
    ] : []);
}

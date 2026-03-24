<?php

if (!function_exists('community_fetch_feed_posts_page')) {
    function community_fetch_feed_posts_page($conn, $userId, $page = 1, $limit = 8, $topicSlug = '')
    {
        $userId = (int)$userId;
        $page = max(1, (int)$page);
        $limit = max(1, min(20, (int)$limit));
        $offset = ($page - 1) * $limit;
        $topicSlug = trim((string)$topicSlug);

        $params = [];
        $pinJoin = '';
        $pinSelect = '';
        if ($userId > 0) {
            $where = "(
                (p.status = 'published' AND (
                    p.privacy = 'public'
                        OR p.user_id = :viewer_id_owner
                    OR (
                        p.privacy = 'followers'
                        AND EXISTS (
                            SELECT 1
                            FROM community_user_follows f
                                WHERE f.follower_user_id = :viewer_id_follow
                            AND f.following_user_id = p.user_id
                        )
                    )
                ))
                    OR (p.status = 'draft' AND p.user_id = :viewer_id_draft)
            )";
            $params[':viewer_id_owner'] = $userId;
            $params[':viewer_id_follow'] = $userId;
            $params[':viewer_id_draft'] = $userId;
            $pinJoin = ' LEFT JOIN community_user_pins cup ON cup.post_id = p.id AND cup.user_id = :pin_user_id ';
            $pinSelect = ', CASE WHEN cup.id IS NULL THEN 0 ELSE 1 END AS is_pinned';
            $params[':pin_user_id'] = $userId;
        } else {
            $where = "(p.status = 'published' AND p.privacy = 'public')";
        }

        $topicJoin = '';
        if ($topicSlug !== '') {
            $topicJoin = ' INNER JOIN community_post_topics cpt ON cpt.post_id = p.id INNER JOIN community_topics ct ON ct.id = cpt.topic_id ';
            $where .= ' AND ct.slug = :topic_slug';
            $params[':topic_slug'] = $topicSlug;
        }

        $sql = "SELECT DISTINCT p.*{$pinSelect}
                FROM community_posts p
                {$topicJoin}
                {$pinJoin}
                WHERE {$where}
                ORDER BY " . ($userId > 0 ? 'is_pinned DESC, ' : '') . "p.created_at DESC
                LIMIT :fetch_limit OFFSET :fetch_offset";

        $stmt = $conn->prepare($sql);
        foreach ($params as $k => $v) {
            if (in_array($k, [':viewer_id_owner', ':viewer_id_follow', ':viewer_id_draft', ':pin_user_id'], true)) {
                $stmt->bindValue($k, (int)$v, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($k, (string)$v, PDO::PARAM_STR);
            }
        }
        $stmt->bindValue(':fetch_limit', $limit + 1, PDO::PARAM_INT);
        $stmt->bindValue(':fetch_offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }

        return [
            'posts' => $rows,
            'has_more' => $hasMore,
            'next_page' => $hasMore ? $page + 1 : null,
            'current_page' => $page,
        ];
    }
}

if (!function_exists('community_load_post_maps')) {
    function community_load_post_maps($conn, array $posts, $userId)
    {
        $userId = (int)$userId;
        $postIds = array_map(function ($row) {
            return (int)$row['id'];
        }, $posts);

        $result = [
            'mediaByPost' => [],
            'linksByPost' => [],
            'commentsByPost' => [],
            'reactionByPost' => [],
            'savedByPost' => [],
            'topicsByPost' => [],
            'badgesByUser' => [],
            'pollByPost' => [],
            'followersCountByAuthor' => [],
            'followingByAuthor' => [],
            'followedByAuthor' => [],
        ];

        if (empty($postIds)) {
            return $result;
        }

        $placeholders = implode(',', array_fill(0, count($postIds), '?'));

        $mediaStmt = $conn->prepare("SELECT post_id, file_path FROM community_post_media WHERE post_id IN ({$placeholders}) ORDER BY sort_order ASC, id ASC");
        $mediaStmt->execute($postIds);
        foreach ($mediaStmt->fetchAll(PDO::FETCH_ASSOC) as $mediaRow) {
            $pid = (int)$mediaRow['post_id'];
            if (!isset($result['mediaByPost'][$pid])) {
                $result['mediaByPost'][$pid] = [];
            }
            $result['mediaByPost'][$pid][] = $mediaRow;
        }

        $linkStmt = $conn->prepare("SELECT post_id, url, host, title, description, preview_image FROM community_post_links WHERE post_id IN ({$placeholders}) ORDER BY id ASC");
        $linkStmt->execute($postIds);
        foreach ($linkStmt->fetchAll(PDO::FETCH_ASSOC) as $linkRow) {
            $pid = (int)$linkRow['post_id'];
            if (!isset($result['linksByPost'][$pid])) {
                $result['linksByPost'][$pid] = [];
            }
            $result['linksByPost'][$pid][] = $linkRow;
        }

        $topicStmt = $conn->prepare("SELECT cpt.post_id, ct.slug, ct.name FROM community_post_topics cpt INNER JOIN community_topics ct ON ct.id = cpt.topic_id WHERE cpt.post_id IN ({$placeholders}) ORDER BY ct.name ASC");
        $topicStmt->execute($postIds);
        foreach ($topicStmt->fetchAll(PDO::FETCH_ASSOC) as $topicRow) {
            $pid = (int)$topicRow['post_id'];
            if (!isset($result['topicsByPost'][$pid])) {
                $result['topicsByPost'][$pid] = [];
            }
            $result['topicsByPost'][$pid][] = $topicRow;
        }

        $commentStmt = $conn->prepare("SELECT id, post_id, parent_comment_id, user_id, user_name, comment, created_at FROM community_post_comments WHERE post_id IN ({$placeholders}) AND status = 'active' ORDER BY created_at DESC");
        $commentStmt->execute($postIds);
        foreach ($commentStmt->fetchAll(PDO::FETCH_ASSOC) as $commentRow) {
            $pid = (int)$commentRow['post_id'];
            if (!isset($result['commentsByPost'][$pid])) {
                $result['commentsByPost'][$pid] = [];
            }
            $result['commentsByPost'][$pid][] = $commentRow;
        }

        $pollOptionStmt = $conn->prepare("SELECT o.id, o.post_id, o.option_text, o.sort_order, COALESCE(v.vote_count, 0) AS vote_count
            FROM community_poll_options o
            LEFT JOIN (
                SELECT option_id, COUNT(*) AS vote_count
                FROM community_poll_votes
                WHERE post_id IN ({$placeholders})
                GROUP BY option_id
            ) v ON v.option_id = o.id
            WHERE o.post_id IN ({$placeholders})
            ORDER BY o.post_id ASC, o.sort_order ASC, o.id ASC");
        $pollOptionStmt->execute(array_merge($postIds, $postIds));
        foreach ($pollOptionStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $pid = (int)$row['post_id'];
            if (!isset($result['pollByPost'][$pid])) {
                $result['pollByPost'][$pid] = ['options' => [], 'total_votes' => 0, 'user_option_id' => 0];
            }
            $optionVotes = (int)($row['vote_count'] ?? 0);
            $result['pollByPost'][$pid]['options'][] = [
                'id' => (int)$row['id'],
                'option_text' => (string)($row['option_text'] ?? ''),
                'vote_count' => $optionVotes,
            ];
            $result['pollByPost'][$pid]['total_votes'] += $optionVotes;
        }

        if ($userId > 0) {
            $likeParams = array_merge($postIds, [$userId]);
            $likeStmt = $conn->prepare("SELECT post_id, reaction FROM community_post_reactions WHERE post_id IN ({$placeholders}) AND user_id = ?");
            $likeStmt->execute($likeParams);
            foreach ($likeStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $result['reactionByPost'][(int)$row['post_id']] = (int)($row['reaction'] ?? 0);
            }

            $savedStmt = $conn->prepare("SELECT post_id FROM community_saved_posts WHERE post_id IN ({$placeholders}) AND user_id = ?");
            $savedStmt->execute($likeParams);
            foreach ($savedStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $result['savedByPost'][(int)$row['post_id']] = true;
            }

            $pollVoteStmt = $conn->prepare("SELECT post_id, option_id FROM community_poll_votes WHERE post_id IN ({$placeholders}) AND user_id = ?");
            $pollVoteStmt->execute($likeParams);
            foreach ($pollVoteStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $pid = (int)$row['post_id'];
                if (!isset($result['pollByPost'][$pid])) {
                    $result['pollByPost'][$pid] = ['options' => [], 'total_votes' => 0, 'user_option_id' => 0];
                }
                $result['pollByPost'][$pid]['user_option_id'] = (int)($row['option_id'] ?? 0);
            }
        }

        $authorIds = array_values(array_unique(array_map(function ($row) {
            return (int)($row['user_id'] ?? 0);
        }, $posts)));

        if (!empty($authorIds)) {
            if (function_exists('community_get_follower_counts_by_author_ids')) {
                $result['followersCountByAuthor'] = community_get_follower_counts_by_author_ids($conn, $authorIds);
            }

            if ($userId > 0) {
                if (function_exists('community_get_viewer_following_author_map')) {
                    $result['followingByAuthor'] = community_get_viewer_following_author_map($conn, $userId, $authorIds);
                }
                if (function_exists('community_get_authors_following_viewer_map')) {
                    $result['followedByAuthor'] = community_get_authors_following_viewer_map($conn, $userId, $authorIds);
                }
            }
        }

        if (!empty($authorIds) && function_exists('community_build_user_badges_map')) {
            $result['badgesByUser'] = community_build_user_badges_map($conn, $authorIds);
        }

        return $result;
    }
}

if (!function_exists('community_fetch_saved_posts_page')) {
    function community_fetch_saved_posts_page($conn, $userId, $page = 1, $limit = 8, $topicSlug = '')
    {
        $userId = (int)$userId;
        $page = max(1, (int)$page);
        $limit = max(1, min(20, (int)$limit));
        $offset = ($page - 1) * $limit;
        $topicSlug = trim((string)$topicSlug);

        if ($userId <= 0) {
            return [
                'posts' => [],
                'has_more' => false,
                'next_page' => null,
                'current_page' => $page,
            ];
        }

        $topicJoin = '';
        $topicWhere = '';
        if ($topicSlug !== '') {
            $topicJoin = ' INNER JOIN community_post_topics cpt ON cpt.post_id = p.id INNER JOIN community_topics ct ON ct.id = cpt.topic_id ';
            $topicWhere = ' AND ct.slug = :topic_slug ';
        }

        $sql = "SELECT DISTINCT p.*
            FROM community_saved_posts sp
            INNER JOIN community_posts p ON p.id = sp.post_id
            {$topicJoin}
            WHERE sp.user_id = :user_id
            AND (
                p.user_id = :owner_id_self
                OR (
                    p.status = 'published'
                    AND (
                        p.privacy = 'public'
                        OR (
                            p.privacy = 'followers'
                            AND EXISTS (
                                SELECT 1
                                FROM community_user_follows f
                                WHERE f.follower_user_id = :owner_id_follow
                                AND f.following_user_id = p.user_id
                            )
                        )
                    )
                )
            )
            {$topicWhere}
            ORDER BY sp.created_at DESC
            LIMIT :fetch_limit OFFSET :fetch_offset";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':owner_id_self', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':owner_id_follow', $userId, PDO::PARAM_INT);
        if ($topicSlug !== '') {
            $stmt->bindValue(':topic_slug', $topicSlug, PDO::PARAM_STR);
        }
        $stmt->bindValue(':fetch_limit', $limit + 1, PDO::PARAM_INT);
        $stmt->bindValue(':fetch_offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }

        return [
            'posts' => $rows,
            'has_more' => $hasMore,
            'next_page' => $hasMore ? $page + 1 : null,
            'current_page' => $page,
        ];
    }
}

if (!function_exists('community_render_feed_posts_html')) {
    function community_render_feed_posts_html(array $posts, array $maps, $userId, array $options = [])
    {
        $userId = (int)$userId;
        $isCompact = !empty($options['compact']);
        ob_start();

        foreach ($posts as $post) {
            $postId = (int)$post['id'];
            $authorId = (int)($post['user_id'] ?? 0);
            $author = (string)$post['user_name'];
            $postUrl = function_exists('community_post_path') ? community_post_path($postId, (string)($post['post_title'] ?? '')) : ('community_post.php?slug=' . $postId);
            $authorUrl = function_exists('community_profile_path') ? community_profile_path($authorId, $author) : ('community_profile.php?user=' . $authorId);
            $contentRaw = html_entity_decode((string)$post['content'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $postMedia = $maps['mediaByPost'][$postId] ?? [];
            $postLinks = $maps['linksByPost'][$postId] ?? [];
            $postComments = $maps['commentsByPost'][$postId] ?? [];
            $topics = $maps['topicsByPost'][$postId] ?? [];
            $primaryTopicSlug = (string)($topics[0]['slug'] ?? 'community');
            $subredditName = 'r/' . ($primaryTopicSlug !== '' ? $primaryTopicSlug : 'community');
            $postTitleRaw = trim((string)($post['post_title'] ?? ''));
            $postTitle = $postTitleRaw !== '' ? $postTitleRaw : (function_exists('community_extract_title') ? community_extract_title((string)$post['content']) : 'Bai dang cong dong');
            $postTitle = html_entity_decode((string)$postTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $postBodyRaw = function_exists('community_extract_body') ? community_extract_body((string)$post['content']) : $contentRaw;
            $reaction = (int)($maps['reactionByPost'][$postId] ?? 0);
            $isUpvoted = $reaction === 1;
            $isDownvoted = $reaction === -1;
            $isOwner = $userId > 0 && $authorId === $userId;
            $isSaved = !empty($maps['savedByPost'][$postId]);
            $theme = function_exists('community_resolve_topic_theme') ? community_resolve_topic_theme($primaryTopicSlug) : ['card' => 'bg-gray-100 dark:bg-gray-800/70', 'badge' => 'bg-main/10 text-main', 'accent' => 'text-main'];
            $authorBadges = $maps['badgesByUser'][$authorId] ?? [];
            $authorFollowersCount = (int)($maps['followersCountByAuthor'][$authorId] ?? 0);
            $isFollowingAuthor = !empty($maps['followingByAuthor'][$authorId]);
            $isFollowedByAuthor = !empty($maps['followedByAuthor'][$authorId]);
            $dbPoll = $maps['pollByPost'][$postId] ?? ['options' => [], 'total_votes' => 0, 'user_option_id' => 0];

            $isFeedPage = isset($_SERVER['PHP_SELF']) && strpos((string)$_SERVER['PHP_SELF'], 'community_feed.php') !== false;
            $safeContentHtml = nl2br(htmlspecialchars($postBodyRaw, ENT_QUOTES, 'UTF-8'));
            $safeContentHtml = preg_replace_callback('/(^|[\s>])#([\p{L}\p{N}_-]{2,60})/u', function ($matches) use ($isFeedPage) {
                $prefix = (string)($matches[1] ?? '');
                $topicName = (string)($matches[2] ?? '');
                $topicSlug = function_exists('community_slugify_topic') ? community_slugify_topic($topicName) : '';
                if ($topicSlug === '') {
                    return $matches[0];
                }

                $topicUrl = $isFeedPage ? ('community_feed.php?topic=' . rawurlencode($topicSlug)) : ('community_saved.php?topic=' . rawurlencode($topicSlug));
                return $prefix . '<a href="' . htmlspecialchars($topicUrl, ENT_QUOTES, 'UTF-8') . '" class="text-main hover:underline font-medium">#' . htmlspecialchars($topicName, ENT_QUOTES, 'UTF-8') . '</a>';
            }, $safeContentHtml) ?? $safeContentHtml;

            $safeTitleHtml = htmlspecialchars($postTitle, ENT_QUOTES, 'UTF-8');
            $safeTitleHtml = preg_replace_callback('/(^|\s)#([\p{L}\p{N}_-]{2,60})/u', function ($matches) {
                $prefix = (string)($matches[1] ?? '');
                $topicName = (string)($matches[2] ?? '');
                $topicSlug = function_exists('community_slugify_topic') ? community_slugify_topic($topicName) : '';
                if ($topicSlug === '') {
                    return $matches[0];
                }

                $topicUrl = 'community_saved.php?topic=' . rawurlencode($topicSlug);
                if (isset($_SERVER['PHP_SELF']) && strpos((string)$_SERVER['PHP_SELF'], 'community_feed.php') !== false) {
                    $topicUrl = 'community_feed.php?topic=' . rawurlencode($topicSlug);
                }
                return $prefix . '<a href="' . htmlspecialchars($topicUrl, ENT_QUOTES, 'UTF-8') . '" class="text-main hover:underline">#' . htmlspecialchars($topicName, ENT_QUOTES, 'UTF-8') . '</a>';
            }, $safeTitleHtml) ?? $safeTitleHtml;

            $pollData = null;
            if (preg_match('/\[POLL\](.*?)\[\/POLL\]/is', $postBodyRaw, $pollMatch)) {
                $pollBlock = trim((string)($pollMatch[1] ?? ''));
                $pollLines = preg_split('/\r\n|\r|\n/', $pollBlock);
                $pollQuestion = '';
                $pollOptions = [];
                if (is_array($pollLines)) {
                    foreach ($pollLines as $line) {
                        $line = trim((string)$line);
                        if ($line === '') {
                            continue;
                        }
                        if ($pollQuestion === '') {
                            $pollQuestion = $line;
                            continue;
                        }
                        $line = preg_replace('/^[-*]\s*/', '', $line);
                        if ($line !== '') {
                            $pollOptions[] = $line;
                        }
                    }
                }
                if ($pollQuestion !== '' && count($pollOptions) >= 2) {
                    $pollData = [
                        'question' => $pollQuestion,
                        'options' => array_slice($pollOptions, 0, 6),
                    ];
                }

                $postBodyRaw = trim((string)preg_replace('/\[POLL\].*?\[\/POLL\]/is', '', $postBodyRaw));
                $safeContentHtml = nl2br(htmlspecialchars($postBodyRaw, ENT_QUOTES, 'UTF-8'));
                $safeContentHtml = preg_replace_callback('/(^|[\s>])#([\p{L}\p{N}_-]{2,60})/u', function ($matches) use ($isFeedPage) {
                    $prefix = (string)($matches[1] ?? '');
                    $topicName = (string)($matches[2] ?? '');
                    $topicSlug = function_exists('community_slugify_topic') ? community_slugify_topic($topicName) : '';
                    if ($topicSlug === '') {
                        return $matches[0];
                    }

                    $topicUrl = $isFeedPage ? ('community_feed.php?topic=' . rawurlencode($topicSlug)) : ('community_saved.php?topic=' . rawurlencode($topicSlug));
                    return $prefix . '<a href="' . htmlspecialchars($topicUrl, ENT_QUOTES, 'UTF-8') . '" class="text-main hover:underline font-medium">#' . htmlspecialchars($topicName, ENT_QUOTES, 'UTF-8') . '</a>';
                }, $safeContentHtml) ?? $safeContentHtml;
            }

            $topLevelComments = [];
            $replyByParent = [];
            foreach ($postComments as $commentRow) {
                $parentId = (int)($commentRow['parent_comment_id'] ?? 0);
                if ($parentId > 0) {
                    if (!isset($replyByParent[$parentId])) {
                        $replyByParent[$parentId] = [];
                    }
                    $replyByParent[$parentId][] = $commentRow;
                } else {
                    $topLevelComments[] = $commentRow;
                }
            }
?>
            <?php
            $cardGapClass = $isCompact ? 'my-4' : 'my-5';
            $contentWrapClass = $isCompact ? 'p-3 sm:p-4 space-y-3' : 'p-4 sm:p-5 space-y-4';
            $titleClass = $isCompact
                ? 'text-base sm:text-lg font-semibold leading-snug text-gray-900 dark:text-white break-words'
                : 'text-lg sm:text-xl font-normal leading-tight text-gray-900 dark:text-white break-words';
            $bodyClass = $isCompact
                ? 'text-sm leading-6 text-gray-700 dark:text-gray-200 break-words overflow-hidden max-h-[7.2rem]'
                : 'text-sm leading-7 text-gray-700 dark:text-gray-200 break-words';
            $mediaHeightClass = $isCompact ? 'h-44 sm:h-56' : 'h-56 sm:h-72';
            ?>
            <article id="community-post-<?= $postId; ?>" class="community-post-card <?= htmlspecialchars((string)$theme['card'], ENT_QUOTES, 'UTF-8'); ?> rounded-2xl shadow-md overflow-visible <?= $cardGapClass; ?> hover:shadow-xl transition-shadow duration-300 w-full max-w-[850px] mx-auto">
                <div class="p-4 sm:p-5 border-b border-black/5 dark:border-white/10">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-10 h-10 rounded-full bg-main text-white font-semibold flex items-center justify-center shrink-0">
                                <?= htmlspecialchars(strtoupper(mb_substr($author, 0, 1, 'UTF-8')), ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <div class="min-w-0">

                                <p class="font-semibold text-gray-900 dark:text-white truncate"><a href="<?= htmlspecialchars($authorUrl, ENT_QUOTES, 'UTF-8'); ?>" class="hover:underline"><?= htmlspecialchars($author, ENT_QUOTES, 'UTF-8'); ?></a></p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate"><span class="font-semibold <?= htmlspecialchars((string)$theme['accent'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($subredditName, ENT_QUOTES, 'UTF-8'); ?></span> • <?= htmlspecialchars(community_time_ago((string)$post['created_at']), ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                                    <span data-community-followers-count data-user-id="<?= $authorId; ?>"><?= $authorFollowersCount; ?></span> người theo dõi
                                    <?php if ($isFollowedByAuthor && !$isOwner): ?>
                                        <span class="ml-2 inline-flex items-center rounded-full px-3 py-1 bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200">Theo dõi bạn</span>
                                    <?php endif; ?>
                                </p>
                                <?php if (!empty($authorBadges)): ?>
                                    <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                        <?php foreach ($authorBadges as $badge): ?>
                                            <span class="text-[10px] px-2 py-0.5 rounded-full font-semibold <?= htmlspecialchars((string)($badge['class'] ?? 'bg-main/10 text-main'), ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string)($badge['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <?php if ($userId > 0 && !$isOwner): ?>
                                <?php
                                $followButtonClass = $isFollowingAuthor
                                    ? 'px-4 py-1 rounded-full text-xs font-semibold bg-main text-white hover:bg-main/90 transition-colors'
                                    : 'px-4 py-1 rounded-full text-xs font-semibold bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-main/10 hover:text-main transition-colors';
                                $followButtonLabel = $isFollowingAuthor ? 'Đang theo dõi' : ($isFollowedByAuthor ? 'Theo dõi lại' : 'Theo dõi');
                                ?>
                                <button type="button"
                                    class="<?= htmlspecialchars($followButtonClass, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-community-follow-btn
                                    data-target-user-id="<?= $authorId; ?>"
                                    data-following="<?= $isFollowingAuthor ? '1' : '0'; ?>">
                                    <?= htmlspecialchars($followButtonLabel, ENT_QUOTES, 'UTF-8'); ?>
                                </button>
                            <?php endif; ?>
                            <span class="text-[11px] sm:text-xs px-4 py-1 rounded-full font-semibold whitespace-nowrap <?= htmlspecialchars((string)$theme['badge'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?= htmlspecialchars(community_visibility_badge((string)$post['privacy'], (string)$post['status']), ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                            <?php if ((int)($post['is_pinned'] ?? 0) === 1): ?>
                                <span class="text-[11px] sm:text-xs px-3 py-1 rounded-full font-semibold whitespace-nowrap bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-200">
                                    Da ghim
                                </span>
                            <?php endif; ?>
                            <div class="relative" data-community-action-wrap>
                                <button type="button" class="w-8 h-8 rounded-full hover:bg-black/5 dark:hover:bg-white/10 text-gray-500 hover:text-gray-700 dark:text-gray-300 dark:hover:text-white transition-colors" data-community-action-trigger aria-label="Menu bài viết">
                                    <i class="fas fa-ellipsis"></i>
                                </button>
                                <div class="hidden absolute right-0 top-10 z-30 min-w-[240px] bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-2xl overflow-hidden" style=" min-width: 260px;" data-community-action-menu>
                                    <button type="button" class="w-full text-left px-3 py-2.5 text-sm hover:bg-gray-50 dark:hover:bg-gray-700" data-community-action="save" data-post-id="<?= $postId; ?>" data-saved="<?= $isSaved ? '1' : '0'; ?>"><?= $isSaved ? 'Bỏ lưu bài viết' : 'Lưu bài viết'; ?></button>
                                    <button type="button" class="w-full text-left px-3 py-2.5 text-sm hover:bg-gray-50 dark:hover:bg-gray-700" data-community-action="pin" data-post-id="<?= $postId; ?>" data-pinned="<?= ((int)($post['is_pinned'] ?? 0) === 1) ? '1' : '0'; ?>"><?= ((int)($post['is_pinned'] ?? 0) === 1) ? 'Bỏ ghim trên đầu feed' : 'Ghim lên đầu feed'; ?></button>
                                    <button type="button" class="w-full text-left px-3 py-2.5 text-sm hover:bg-gray-50 dark:hover:bg-gray-700" data-community-action="hide" data-post-id="<?= $postId; ?>">Ẩn bài viết</button>
                                    <button type="button" class="w-full text-left px-3 py-2.5 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20" data-community-action="report" data-post-id="<?= $postId; ?>">Báo cáo bài viết</button>
                                    <?php if ($isOwner): ?>
                                        <div class="border-t border-gray-100 dark:border-gray-700"></div>
                                        <a href="community_manage.php" class="block px-3 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Chỉnh sửa bài viết</a>
                                        <button type="button" class="w-full text-left px-3 py-2.5 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20" data-community-delete-btn data-post-id="<?= $postId; ?>">Xóa bài viết</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="<?= htmlspecialchars($contentWrapClass, ENT_QUOTES, 'UTF-8'); ?>">
                    <h3 class="<?= htmlspecialchars($titleClass, ENT_QUOTES, 'UTF-8'); ?>"><a href="<?= htmlspecialchars($postUrl, ENT_QUOTES, 'UTF-8'); ?>" class="hover:underline"><?= $safeTitleHtml; ?></a></h3>

                    <?php if (trim(strip_tags($postBodyRaw)) !== ''): ?>
                        <div class="<?= htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8'); ?>"><?= $safeContentHtml; ?></div>
                    <?php endif; ?>



                    <?php if (!empty($dbPoll['options'])): ?>
                        <div class="rounded-xl border border-main/25 bg-main/5 p-3" data-community-poll-wrap data-post-id="<?= $postId; ?>">
                            <p class="text-xs uppercase tracking-wide font-semibold text-main mb-1">Poll</p>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white mb-2"><?= htmlspecialchars((string)$postTitle, ENT_QUOTES, 'UTF-8'); ?></p>
                            <div class="space-y-2" data-community-poll-options>
                                <?php foreach ((array)$dbPoll['options'] as $pollOption): ?>
                                    <?php
                                    $optionId = (int)($pollOption['id'] ?? 0);
                                    $optionVotes = (int)($pollOption['vote_count'] ?? 0);
                                    $isSelected = $optionId > 0 && $optionId === (int)($dbPoll['user_option_id'] ?? 0);
                                    $totalVotes = max(1, (int)($dbPoll['total_votes'] ?? 0));
                                    $ratio = (int)round(($optionVotes / $totalVotes) * 100);
                                    ?>
                                    <button type="button" class="w-full text-left px-3 py-2 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:border-main text-sm <?= $isSelected ? 'border-main bg-main/10 font-semibold' : ''; ?>" data-community-poll-option data-post-id="<?= $postId; ?>" data-option-id="<?= $optionId; ?>" data-selected="<?= $isSelected ? '1' : '0'; ?>">
                                        <span class="block text-gray-800 dark:text-gray-100"><?= htmlspecialchars((string)($pollOption['option_text'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="mt-1 block text-[11px] text-gray-500 dark:text-gray-400" data-community-poll-option-meta data-vote-count="<?= $optionVotes; ?>"><?= $optionVotes; ?> vote(s) • <?= $ratio; ?>%</span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            <p class="mt-2 text-[11px] text-gray-500 dark:text-gray-400">Tổng phiếu: <span data-community-poll-total><?= (int)($dbPoll['total_votes'] ?? 0); ?></span></p>
                        </div>
                    <?php elseif (!empty($pollData)): ?>
                        <div class="rounded-xl border border-main/25 bg-main/5 p-3">
                            <p class="text-xs uppercase tracking-wide font-semibold text-main mb-1">Poll nhanh</p>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white mb-2"><?= htmlspecialchars((string)$pollData['question'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <div class="space-y-2">
                                <?php foreach ((array)$pollData['options'] as $pollIdx => $pollOption): ?>
                                    <button type="button" class="w-full text-left px-3 py-2 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:border-main text-sm" data-community-poll-option data-post-id="<?= $postId; ?>" data-poll-option="<?= (int)$pollIdx; ?>">
                                        <?= htmlspecialchars((string)$pollOption, ENT_QUOTES, 'UTF-8'); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            <p class="mt-2 text-[11px] text-gray-500 dark:text-gray-400">Bình chọn nhanh trên thiết bị này để tăng tương tác.</p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($postMedia)): ?>
                        <?php
                        $allMediaUrls = [];
                        foreach ($postMedia as $mediaRow) {
                            $mediaPath = (string)($mediaRow['file_path'] ?? '');
                            if (blog_is_external_url($mediaPath)) {
                                $allMediaUrls[] = $mediaPath;
                            } else {
                                $normalized = str_replace('\\', '/', trim($mediaPath));
                                $normalized = preg_replace('#^(?:\./|\.\./)+#', '', $normalized);
                                $normalized = ltrim((string)$normalized, '/');

                                $uploadedPos = stripos($normalized, 'uploaded_img/');
                                if ($uploadedPos !== false) {
                                    $normalized = substr($normalized, $uploadedPos);
                                } elseif ($normalized !== '' && strpos($normalized, 'static/uploaded_img/') === 0) {
                                    $normalized = substr($normalized, strlen('static/'));
                                } else {
                                    $normalized = 'uploaded_img/' . ltrim($normalized, '/');
                                }

                                $allMediaUrls[] = site_url($normalized);
                            }
                        }
                        $allMediaJson = htmlspecialchars(json_encode($allMediaUrls, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
                        ?>

                        <div class="community-carousel rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden" data-community-carousel data-post-id="<?= $postId; ?>" data-gallery-images="<?= $allMediaJson; ?>">
                            <div class="community-carousel-track" data-community-carousel-track>
                                <?php foreach ($allMediaUrls as $imageIndex => $imageUrl): ?>
                                    <div class="community-carousel-slide media-item" data-gallery-index="<?= (int)$imageIndex; ?>">
                                        <div class="community-image-spinner absolute inset-0 flex items-center justify-center bg-gray-100 dark:bg-gray-700">
                                            <i class="fas fa-spinner fa-spin text-gray-400"></i>
                                        </div>
                                        <img src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="community media" loading="lazy" class="w-full <?= htmlspecialchars($mediaHeightClass, ENT_QUOTES, 'UTF-8'); ?> object-cover bg-gray-100 dark:bg-gray-700 cursor-zoom-in community-gallery-image opacity-0 transition-opacity duration-300" data-community-lazy-image>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($allMediaUrls) > 1): ?>
                                <button type="button" class="community-carousel-nav is-prev" data-community-carousel-prev aria-label="Previous image"><i class="fas fa-chevron-left"></i></button>
                                <button type="button" class="community-carousel-nav is-next" data-community-carousel-next aria-label="Next image"><i class="fas fa-chevron-right"></i></button>
                                <div class="community-carousel-dots" data-community-carousel-dots>
                                    <?php foreach ($allMediaUrls as $imageIndex => $imageUrl): ?>
                                        <button type="button" class="community-carousel-dot <?= $imageIndex === 0 ? 'is-active' : ''; ?>" data-community-carousel-dot data-dot-index="<?= (int)$imageIndex; ?>" aria-label="Den anh <?= (int)$imageIndex + 1; ?>"></button>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($postLinks)): ?>
                        <div class="space-y-2">
                            <?php foreach ($postLinks as $link): ?>
                                <a href="<?= htmlspecialchars((string)$link['url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="block rounded-lg border border-gray-200 dark:border-gray-700 p-3 hover:border-main hover:bg-main/5 transition-colors">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white truncate"><?= htmlspecialchars((string)($link['title'] ?: $link['host']), ENT_QUOTES, 'UTF-8'); ?></p>
                                    <?php if (!empty($link['description'])): ?>
                                        <p class="text-xs text-gray-600 dark:text-gray-300 mt-1 line-clamp-2"><?= htmlspecialchars((string)$link['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    <?php endif; ?>
                                    <p class="text-xs text-main truncate mt-1"><?= htmlspecialchars((string)$link['url'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    <?php if (!empty($link['preview_image'])): ?>
                                        <img src="<?= htmlspecialchars((string)$link['preview_image'], ENT_QUOTES, 'UTF-8'); ?>" loading="lazy" alt="link preview" class="mt-2 w-full h-32 object-cover rounded-lg border border-gray-200 dark:border-gray-700">
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="px-4 sm:px-5 py-3 border-t border-black/5 dark:border-white/10 bg-white/45 dark:bg-gray-900/30 text-sm text-gray-600 dark:text-gray-300 space-y-3 rounded-b-2xl">
                    <?php
                    $voteGroupClass = 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-200 shadow-sm';
                    if ($isUpvoted) {
                        $voteGroupClass = 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-800 dark:text-emerald-200 shadow-lg';
                    } elseif ($isDownvoted) {
                        $voteGroupClass = 'bg-rose-100 dark:bg-rose-900/50 text-rose-800 dark:text-rose-200 shadow-lg';
                    }
                    ?>
                    <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                        <div data-community-vote-group class="inline-flex items-center gap-2 px-5 py-2 rounded-full transition duration-200 <?= $voteGroupClass; ?> text-lg">
                            <button type="button" class="inline-flex items-center gap-2 rounded-md transition duration-200 <?= $isUpvoted ? 'text-xl font-bold' : 'text-sm'; ?>" data-community-vote-btn data-vote="up" data-post-id="<?= $postId; ?>" aria-pressed="<?= $isUpvoted ? 'true' : 'false'; ?>">
                                <i class="fas fa-arrow-up" data-community-upvote-icon></i>
                                <span id="community-upvote-count-<?= $postId; ?>"><?= (int)($post['total_upvotes'] ?? 0); ?></span>
                            </button>
                            <span id="community-score-count-<?= $postId; ?>" class="inline-flex items-center rounded-md  dark:bg-gray-800 text-base font-semibold"><?= (int)($post['vote_score'] ?? 0); ?></span>
                            <button type="button" class="inline-flex items-center gap-2 px-2 py-1 rounded-md transition duration-200 <?= $isDownvoted ? 'text-xl font-bold' : 'text-sm'; ?>" data-community-vote-btn data-vote="down" data-post-id="<?= $postId; ?>" aria-pressed="<?= $isDownvoted ? 'true' : 'false'; ?>">
                                <i class="fas fa-arrow-down" data-community-downvote-icon></i>
                                <span id="community-downvote-count-<?= $postId; ?>"><?= (int)($post['total_downvotes'] ?? 0); ?></span>
                            </button>
                        </div>

                        <div class="inline-flex items-center gap-2 px-5 py-2 bg-white dark:bg-gray-900 shadow-sm rounded-full cursor-pointer text-sm" onclick="toggleCommunityComments(<?= $postId; ?>)">
                            <i class="fas fa-comment"></i>
                            <span id="community-comment-count-<?= $postId; ?>"><?= (int)$post['total_comments']; ?></span>
                        </div>

                        <div class="inline-flex items-center gap-2 px-5 py-2 bg-white dark:bg-gray-900 shadow-sm rounded-full cursor-pointer text-sm" onclick="shareCommunityPost(<?= $postId; ?>)">
                            <i class="fas fa-share-nodes"></i>
                            <span>Chia sẻ</span>
                        </div>
                    </div>

                    <div id="community-comments-panel-<?= $postId; ?>" class="hidden pt-3 border-t border-black/5 dark:border-white/10">
                        <div id="community-comments-list-<?= $postId; ?>" class="space-y-2">
                            <?php if (!empty($topLevelComments)): ?>
                                <?php foreach ($topLevelComments as $commentRow): ?>
                                    <?php
                                    $parentId = (int)$commentRow['id'];
                                    $childrenHtml = '';
                                    if (!empty($replyByParent[$parentId])) {
                                        foreach ($replyByParent[$parentId] as $replyRow) {
                                            $childrenHtml .= community_render_comment_item($replyRow, false, true);
                                        }
                                    }
                                    $replyCount = !empty($replyByParent[$parentId]) ? count($replyByParent[$parentId]) : 0;
                                    echo community_render_comment_item($commentRow, $userId > 0, false, $childrenHtml, $replyCount);
                                    ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p id="community-empty-comments-<?= $postId; ?>" class="text-xs text-gray-500 dark:text-gray-400">Chưa có bình luận nào.</p>
                            <?php endif; ?>
                        </div>

                        <?php if ($userId > 0): ?>
                            <form class="mt-3" data-community-comment-form data-post-id="<?= $postId; ?>">
                                <input type="hidden" name="post_id" value="<?= $postId; ?>">
                                <input type="hidden" name="parent_comment_id" id="community-parent-comment-<?= $postId; ?>" value="0">
                                <input type="text" name="comment_hp" value="" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
                                <div id="community-reply-indicator-<?= $postId; ?>" class="hidden text-xs text-main mb-1"></div>
                                <textarea name="comment" rows="3" maxlength="1200" class="form-textarea text-sm" placeholder="Viết bình luận..." required></textarea>
                                <div class="mt-2 flex items-center gap-2">
                                    <button type="submit" class="px-3 py-2 rounded-lg bg-main text-white text-sm font-semibold hover:bg-main/90">Gửi bình luận</button>
                                    <button type="button" class="px-3 py-2 rounded-lg bg-gray-100 text-gray-700 text-sm hover:bg-gray-200" onclick="cancelCommunityReply(<?= $postId; ?>)">Hủy trả lời</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="mt-3">
                                <a href="login.php" class="text-sm text-main hover:underline">Đăng nhập để bình luận</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
<?php
        }

        return (string)ob_get_clean();
    }
}

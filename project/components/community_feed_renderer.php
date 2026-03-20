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
        $where = "(p.status = 'published'";
        if ($userId > 0) {
            $where .= " OR (p.status = 'draft' AND p.user_id = :viewer_id)";
            $params[':viewer_id'] = $userId;
        }
        $where .= ')';

        $topicJoin = '';
        if ($topicSlug !== '') {
            $topicJoin = ' INNER JOIN community_post_topics cpt ON cpt.post_id = p.id INNER JOIN community_topics ct ON ct.id = cpt.topic_id ';
            $where .= ' AND ct.slug = :topic_slug';
            $params[':topic_slug'] = $topicSlug;
        }

        $sql = "SELECT DISTINCT p.*
                FROM community_posts p
                {$topicJoin}
                WHERE {$where}
                ORDER BY p.created_at DESC
                LIMIT :fetch_limit OFFSET :fetch_offset";

        $stmt = $conn->prepare($sql);
        foreach ($params as $k => $v) {
            if ($k === ':viewer_id') {
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
            'likedPostMap' => [],
            'topicsByPost' => [],
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

        if ($userId > 0) {
            $likeParams = array_merge($postIds, [$userId]);
            $likeStmt = $conn->prepare("SELECT post_id FROM community_post_reactions WHERE post_id IN ({$placeholders}) AND user_id = ?");
            $likeStmt->execute($likeParams);
            foreach ($likeStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $result['likedPostMap'][(int)$row['post_id']] = true;
            }
        }

        return $result;
    }
}

if (!function_exists('community_render_feed_posts_html')) {
    function community_render_feed_posts_html(array $posts, array $maps, $userId)
    {
        $userId = (int)$userId;
        ob_start();

        foreach ($posts as $post) {
            $postId = (int)$post['id'];
            $author = (string)$post['user_name'];
            $contentRaw = html_entity_decode((string)$post['content'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $postMedia = $maps['mediaByPost'][$postId] ?? [];
            $postLinks = $maps['linksByPost'][$postId] ?? [];
            $postComments = $maps['commentsByPost'][$postId] ?? [];
            $isLiked = !empty($maps['likedPostMap'][$postId]);
            $isOwner = $userId > 0 && (int)$post['user_id'] === $userId;

            $safeContentHtml = nl2br(htmlspecialchars($contentRaw, ENT_QUOTES, 'UTF-8'));
            $safeContentHtml = preg_replace_callback('/(^|[\s>])#([\p{L}\p{N}_-]{2,60})/u', function ($matches) {
                $prefix = (string)($matches[1] ?? '');
                $topicName = (string)($matches[2] ?? '');
                $topicSlug = function_exists('community_slugify_topic') ? community_slugify_topic($topicName) : '';
                if ($topicSlug === '') {
                    return $matches[0];
                }

                $topicUrl = 'community_feed.php?topic=' . rawurlencode($topicSlug);
                return $prefix . '<a href="' . htmlspecialchars($topicUrl, ENT_QUOTES, 'UTF-8') . '" class="text-main hover:underline font-medium">#' . htmlspecialchars($topicName, ENT_QUOTES, 'UTF-8') . '</a>';
            }, $safeContentHtml) ?? $safeContentHtml;

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
            <article id="community-post-<?= $postId; ?>" class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden my-5">
                <div class="p-4 sm:p-5 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-10 h-10 rounded-full bg-main text-white font-semibold flex items-center justify-center shrink-0">
                                <?= htmlspecialchars(strtoupper(mb_substr($author, 0, 1, 'UTF-8')), ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-900 dark:text-white truncate"><?= htmlspecialchars($author, ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="text-xs text-gray-500 dark:text-gray-400"><?= htmlspecialchars(community_time_ago((string)$post['created_at']), ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-[11px] sm:text-xs px-4 py-1 rounded-full bg-main/10 text-main font-semibold whitespace-nowrap">
                                <?= htmlspecialchars(community_visibility_badge((string)$post['privacy'], (string)$post['status']), ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                            <?php if ($isOwner): ?>
                                <div class="relative" data-community-owner-wrap>
                                    <button type="button" class="w-8 h-8 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500 hover:text-gray-700 dark:text-gray-300 dark:hover:text-white transition-colors" data-community-owner-trigger aria-label="Tuy chon bai viet">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div class="hidden absolute right-0 top-10 z-20 w-40 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-xl overflow-hidden" data-community-owner-menu>
                                        <a href="community_manage.php" class="flex items-center gap-2 px-3 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                            <i class="fas fa-pen text-main"></i>
                                            <span>Chỉnh sửa</span>
                                        </a>
                                        <button type="button" class="w-full flex items-center gap-2 px-3 py-2.5 text-sm text-left text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors" data-community-delete-btn data-post-id="<?= $postId; ?>">
                                            <i class="fas fa-trash"></i>
                                            <span>Xóa bài viết</span>
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="p-4 sm:p-5 space-y-4">
                    <div class="text-gray-800 dark:text-gray-200 leading-relaxed whitespace-pre-line break-words text-sm sm:text-base"><?= $safeContentHtml; ?></div>

                    <?php if (!empty($postMedia)): ?>
                        <?php
                        $allMediaUrls = [];
                        foreach ($postMedia as $mediaRow) {
                            $allMediaUrls[] = site_url('uploaded_img/' . ltrim((string)$mediaRow['file_path'], '/'));
                        }
                        $visibleMedia = array_slice($allMediaUrls, 0, 4);
                        $remainingMediaCount = max(0, count($allMediaUrls) - count($visibleMedia));
                        $allMediaJson = htmlspecialchars(json_encode($allMediaUrls, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
                        $totalVisible = count($visibleMedia);
                        $gridClass = $totalVisible === 1
                            ? 'community-media-preview grid grid-cols-1 max-w-3xl mx-auto gap-2 sm:gap-3'
                            : 'community-media-preview grid grid-cols-2 gap-2 sm:gap-3';
                        ?>

                        <div class="<?= $gridClass; ?>" data-community-gallery data-gallery-post-id="<?= $postId; ?>" data-gallery-images="<?= $allMediaJson; ?>">
                            <?php foreach ($visibleMedia as $imageIndex => $imageUrl): ?>
                                <?php
                                $itemClass = 'h-32 sm:h-40';
                                if ($totalVisible === 1) {
                                    $itemClass = 'h-64 sm:h-72';
                                } elseif ($totalVisible >= 3 && $imageIndex === 0) {
                                    $itemClass = 'col-span-2 h-44 sm:h-52';
                                }
                                ?>
                                <div class="media-item rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700 relative <?= $itemClass; ?>" data-gallery-index="<?= (int)$imageIndex; ?>">
                                    <div class="community-image-spinner absolute inset-0 flex items-center justify-center bg-gray-100 dark:bg-gray-700">
                                        <i class="fas fa-spinner fa-spin text-gray-400"></i>
                                    </div>
                                    <img src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="community media" loading="lazy" class="w-full h-full object-cover bg-gray-100 dark:bg-gray-700 cursor-zoom-in community-gallery-image opacity-0 transition-opacity duration-300" data-community-lazy-image>
                                    <?php if ($imageIndex === 3 && $remainingMediaCount > 0): ?>
                                        <button type="button" class="absolute inset-0 bg-black/45 hover:bg-black/55 text-white text-sm sm:text-base font-bold flex items-center justify-center transition-colors" data-community-open-gallery data-gallery-images="<?= $allMediaJson; ?>" data-gallery-start-index="3">
                                            +<?= (int)$remainingMediaCount; ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
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

                <div class="px-4 sm:px-5 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/40 text-sm text-gray-600 dark:text-gray-300 space-y-3">
                    <div class="flex items-center gap-4 sm:gap-5 flex-wrap">
                        <button type="button" class="inline-flex items-center gap-1 hover:text-rose-500 transition-colors <?= $isLiked ? 'text-rose-500' : ''; ?>" data-community-like-btn data-post-id="<?= $postId; ?>" data-liked="<?= $isLiked ? '1' : '0'; ?>" aria-pressed="<?= $isLiked ? 'true' : 'false'; ?>">
                            <i class="fas fa-heart <?= $isLiked ? 'text-rose-500' : ''; ?> transition-colors" data-community-like-icon></i>
                            <span id="community-like-count-<?= $postId; ?>"><?= (int)$post['total_reactions']; ?></span>
                        </button>
                        <button type="button" class="inline-flex items-center gap-1 hover:text-main transition-colors" onclick="toggleCommunityComments(<?= $postId; ?>)">
                            <i class="fas fa-comment"></i>
                            <span id="community-comment-count-<?= $postId; ?>"><?= (int)$post['total_comments']; ?></span>
                        </button>
                        <button type="button" class="inline-flex items-center gap-1 hover:text-sky-600 transition-colors" onclick="shareCommunityPost(<?= $postId; ?>)">
                            <i class="fas fa-share-nodes"></i>
                            <span>Chia se</span>
                        </button>
                    </div>

                    <div id="community-comments-panel-<?= $postId; ?>" class="hidden pt-3 border-t border-gray-200 dark:border-gray-700">
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
                                <p id="community-empty-comments-<?= $postId; ?>" class="text-xs text-gray-500 dark:text-gray-400">Chua co binh luan nao.</p>
                            <?php endif; ?>
                        </div>

                        <?php if ($userId > 0): ?>
                            <form class="mt-3" data-community-comment-form data-post-id="<?= $postId; ?>">
                                <input type="hidden" name="post_id" value="<?= $postId; ?>">
                                <input type="hidden" name="parent_comment_id" id="community-parent-comment-<?= $postId; ?>" value="0">
                                <div id="community-reply-indicator-<?= $postId; ?>" class="hidden text-xs text-main mb-1"></div>
                                <textarea name="comment" rows="3" maxlength="1200" class="form-textarea text-sm" placeholder="Viet binh luan..." required></textarea>
                                <div class="mt-2 flex items-center gap-2">
                                    <button type="submit" class="px-3 py-2 rounded-lg bg-main text-white text-sm font-semibold hover:bg-main/90">Gui binh luan</button>
                                    <button type="button" class="px-3 py-2 rounded-lg bg-gray-100 text-gray-700 text-sm hover:bg-gray-200" onclick="cancelCommunityReply(<?= $postId; ?>)">Huy tra loi</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="mt-3">
                                <a href="login.php" class="text-sm text-main hover:underline">Dang nhap de binh luan</a>
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

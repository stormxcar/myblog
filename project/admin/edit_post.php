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
    $_SESSION['message'] = 'Post ID không hợp lệ.';
    header('location:view_posts.php');
    exit;
}

$categories_stmt = $conn->prepare('SELECT category_id, name FROM cart ORDER BY name ASC');
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

$tag_names_stmt = $conn->prepare('SELECT name FROM tags ORDER BY name ASC LIMIT 300');
$tag_names_stmt->execute();
$tag_names = $tag_names_stmt->fetchAll(PDO::FETCH_COLUMN);

if (isset($_POST['save'])) {
    $title = trim((string)($_POST['title'] ?? ''));
    $content = (string)($_POST['content'] ?? '');
    $category = trim((string)($_POST['category'] ?? ''));
    $status = trim((string)($_POST['status'] ?? 'deactive'));
    $tags_input = (string)($_POST['tags'] ?? '');

    if ($title === '' || $content === '' || $category === '') {
        $message[] = 'Vui lòng nhập đầy đủ thông tin bắt buộc.';
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

        blog_sync_post_tags($conn, $post_id, blog_parse_tags_input($tags_input));

        $message[] = 'Đã cập nhật bài viết thành công.';

        $old_image = (string)($_POST['old_image'] ?? '');
        $image_name = $_FILES['image']['name'] ?? '';
        $image_size = (int)($_FILES['image']['size'] ?? 0);
        $image_type = (string)($_FILES['image']['type'] ?? '');

        if (!empty($image_name)) {
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            if (!in_array($image_type, $allowedTypes, true)) {
                $message[] = 'File ảnh không hợp lệ. Chỉ hỗ trợ JPG/PNG/WebP.';
            } elseif ($image_size > 2000000) {
                $message[] = 'Kích thước ảnh quá lớn (tối đa 2MB).';
            } else {
                $uploadResult = blog_cloudinary_upload($_FILES['image'], blog_cloudinary_default_folder() . '/posts');
                if (!($uploadResult['ok'] ?? false)) {
                    $message[] = (string)($uploadResult['error'] ?? 'Không thể upload ảnh lên Cloudinary.');
                } else {
                    $newImageUrl = (string)$uploadResult['secure_url'];
                    $update_image = $conn->prepare('UPDATE posts SET image = ? WHERE id = ? AND admin_id = ?');
                    $update_image->execute([$newImageUrl, $post_id, (int)$admin_id]);

                    if ($old_image !== '' && $old_image !== $newImageUrl) {
                        blog_delete_image_resource($old_image);
                    }

                    $message[] = 'Đã cập nhật ảnh bài viết.';
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
        blog_delete_image_resource((string)$fetch_delete_image['image']);
    }

    $delete_post = $conn->prepare('DELETE FROM posts WHERE id = ? AND admin_id = ?');
    $delete_post->execute([$post_id, (int)$admin_id]);

    $delete_comments = $conn->prepare('DELETE FROM comments WHERE post_id = ?');
    $delete_comments->execute([$post_id]);

    $_SESSION['message'] = 'Đã xóa bài viết thành công.';
    header('location:view_posts.php');
    exit;
}

if (isset($_POST['delete_image'])) {
    $old_image = (string)($_POST['old_image'] ?? '');

    if ($old_image !== '') {
        blog_delete_image_resource($old_image);
    }

    $unset_image = $conn->prepare('UPDATE posts SET image = ? WHERE id = ? AND admin_id = ?');
    $unset_image->execute(['', $post_id, (int)$admin_id]);

    $message[] = 'Đã xóa ảnh bài viết.';
}

$select_post = $conn->prepare('SELECT * FROM posts WHERE id = ? AND admin_id = ? LIMIT 1');
$select_post->execute([$post_id, (int)$admin_id]);
$post = $select_post->fetch(PDO::FETCH_ASSOC);
$post_tags_csv = $post ? blog_get_post_tag_names_csv($conn, $post_id) : '';

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Chỉnh sửa bài viết</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <script src="../plugin/ckeditor/ckeditor.js"></script>
    <style>
        /* Tag picker polished UI */
        #tagPicker {
            border-radius: 0.75rem;
            box-shadow: 0 0 0 1px rgba(79, 70, 229, 0.25);
            transition: box-shadow 0.2s ease;
        }

        #tagPicker:focus-within {
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.45);
            border-color: #3b82f6;
        }

        #tagChips {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            min-height: 2rem;
        }

        .tag-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background-color: #eff6ff;
            border: 1px solid #60a5fa;
            color: #1d4ed8;
            padding: 0.25rem 0.55rem;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .tag-chip button {
            border: none;
            margin: 0;
            background: transparent;
            color: #1d4ed8;
            font-weight: 700;
            cursor: pointer;
        }

        .tag-chip button:hover {
            color: #dc2626;
        }

        .tag-chip:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
        }

        .tag-chip-conflict {
            border-color: #dc2626;
            background-color: #fee2e2;
            animation: pulse-conflict 0.3s ease;
        }

        @keyframes pulse-conflict {
            0% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-2px);
            }

            100% {
                transform: translateY(0);
            }
        }

        .tag-chip[title]::after {
            content: attr(title);
            position: absolute;
            background: rgba(15, 23, 42, 0.85);
            color: #fff;
            font-size: 11px;
            padding: 3px 6px;
            border-radius: 4px;
            white-space: nowrap;
            transform: translateY(-34px);
            z-index: 30;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.15s ease;
        }

        .tag-chip:hover::after {
            opacity: 1;
        }

        #tagSuggestionList li {
            cursor: pointer;
            padding: 0.5rem 0.75rem;
            font-size: 0.9rem;
            color: #1e293b;
        }

        #tagSuggestionList li:hover,
        #tagSuggestionList li.active {
            background-color: #eff6ff;
            color: #1d4ed8;
        }

        #tagInput {
            width: 100%;
            border: none;
            outline: none;
            min-height: 2rem;
            background: transparent;
        }
    </style>
</head>

<body class="ui-page">

    <?php include '../components/admin_header.php'; ?>

    <section class="post-editor ui-container">
        <h1 class="heading ui-title">Chỉnh sửa bài viết</h1>

        <?php if (!$post): ?>
            <p class="empty">Không tìm thấy bài viết hoặc bạn không có quyền truy cập.</p>
            <div class="flex-btn">
                <a href="view_posts.php" class="option-btn ui-btn-warning">Quay lại danh sách</a>
                <a href="add_posts.php" class="btn ui-btn">Thêm bài mới</a>
            </div>
        <?php else: ?>
            <form action="" method="post" enctype="multipart/form-data" class="ui-card" data-no-submit-lock="true">
                <input type="hidden" name="old_image" value="<?= htmlspecialchars((string)$post['image'], ENT_QUOTES, 'UTF-8'); ?>">

                <p>Trạng thái bài viết <span>*</span></p>
                <select name="status" class="box ui-select" required>
                    <option value="active" <?= $post['status'] === 'active' ? 'selected' : ''; ?>>active</option>
                    <option value="deactive" <?= $post['status'] === 'deactive' ? 'selected' : ''; ?>>deactive</option>
                </select>

                <p>Tiêu đề <span>*</span></p>
                <input
                    type="text"
                    name="title"
                    maxlength="150"
                    required
                    class="box ui-input"
                    value="<?= htmlspecialchars((string)$post['title'], ENT_QUOTES, 'UTF-8'); ?>">

                <p>Nội dung <span>*</span></p>
                <textarea
                    name="content"
                    id="content"
                    class="box ui-textarea"
                    required
                    rows="12"><?= htmlspecialchars((string)$post['content'], ENT_QUOTES, 'UTF-8'); ?></textarea>

                <p>Danh mục <span>*</span></p>
                <select name="category" class="box ui-select" required>
                    <option value="" disabled>-- Chọn danh mục --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?>" <?= $post['category'] === $cat['name'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <p>Tag bài viết</p>
                <div id="tagPicker" class="mt-2 rounded-lg border border-gray-300 dark:border-gray-600 p-3 bg-white dark:bg-gray-800">
                    <div id="tagChips" class="flex flex-wrap gap-2 mb-2"></div>
                    <div class="relative">
                        <input type="text" id="tagInput" class="box ui-input" placeholder="Nhập tag rồi nhấn Enter hoặc ,...">
                        <ul id="tagSuggestionList" class="hidden absolute left-0 right-0 mt-1 z-20 max-h-44 overflow-auto rounded-md bg-white border border-gray-200 dark:bg-gray-700 dark:border-gray-600 shadow-lg"></ul>
                    </div>
                    <datalist id="tagSuggestions">
                        <?php foreach ($tag_names as $tag_name): ?>
                            <option value="<?= htmlspecialchars((string)$tag_name, ENT_QUOTES, 'UTF-8'); ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                    <input type="hidden" name="tags" id="tagsHidden" value="<?= htmlspecialchars((string)$post_tags_csv, ENT_QUOTES, 'UTF-8'); ?>">
                    <p id="tagStatus" class="mt-2 text-xs text-red-500 hidden"></p>
                    <small class="text-gray-500">Nhập tối đa 20 tag; Enter/dấu phẩy/paste để thêm; chuột hover chip để xóa; Esc đóng gợi ý; # sẽ gợi ý.</small>
                </div>

                <p>Cập nhật ảnh</p>
                <input type="file" name="image" class="box ui-input" accept="image/jpg,image/jpeg,image/png,image/webp">

                <?php if (!empty($post['image'])): ?>
                    <img src="<?= htmlspecialchars(blog_post_image_src((string)$post['image'], '../uploaded_img/', '../uploaded_img/default_img.jpg'), ENT_QUOTES, 'UTF-8'); ?>" class="image" alt="Post image">
                    <div class="flex-btn" style="margin-top:.8rem; margin-bottom:1rem;">
                        <button type="submit" name="delete_image" class="inline-delete-btn ui-btn-danger">Xóa ảnh hiện tại</button>
                    </div>
                <?php endif; ?>

                <div class="flex-btn">
                    <button type="submit" name="save" class="btn ui-btn">Lưu thay đổi</button>
                    <a href="view_posts.php" class="option-btn ui-btn-warning">Quay lại</a>
                    <button type="submit" name="delete_post" class="delete-btn ui-btn-danger" onclick="return confirm('Bạn có chắc muốn xóa bài viết này không?');">Xóa bài viết</button>
                </div>
            </form>
        <?php endif; ?>
    </section>

    <script>
        if (document.getElementById('content')) {
            CKEDITOR.replace('content', {
                filebrowserBrowseUrl: '../plugin/ckfinder/ckfinder.html',
                filebrowserUploadUrl: '../plugin/ckfinder/core/connector/php/connector.php?command=QuickUpload&type=Files',
                height: '55vh'
            });
        }

        const existingTags = <?= json_encode(array_values(array_filter(array_map(function ($t) {
                                    return trim((string)$t);
                                }, $tag_names))), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const tagInput = document.getElementById('tagInput');
        const tagChips = document.getElementById('tagChips');
        const tagsHidden = document.getElementById('tagsHidden');
        const tagSuggestionList = document.getElementById('tagSuggestionList');
        const tagStatus = document.getElementById('tagStatus');
        let tagsState = String(tagsHidden.value || '')
            .split(',')
            .map((t) => t.trim())
            .filter(Boolean)
            .slice(0, 20);
        let suggestionIndex = -1;

        function normalizeTag(text) {
            return String(text || '').trim().replace(/^#+/, '').replace(/\s+/g, ' ').slice(0, 80);
        }

        function setTagStatus(message, isError = true) {
            if (!tagStatus) return;
            tagStatus.textContent = message || '';
            if (message) {
                tagStatus.classList.remove('hidden');
                tagStatus.classList.toggle('text-red-500', isError);
                tagStatus.classList.toggle('text-green-500', !isError);
            } else {
                tagStatus.classList.add('hidden');
            }
        }

        function escapeHtml(text) {
            return String(text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function renderSuggestions() {
            if (!tagSuggestionList) return;
            const query = normalizeTag(tagInput.value).toLowerCase();
            if (!query) {
                tagSuggestionList.innerHTML = '';
                tagSuggestionList.classList.add('hidden');
                return;
            }

            const filtered = existingTags
                .filter((t) => t.toLowerCase().includes(query) && !tagsState.some((tag) => tag.toLowerCase() === t.toLowerCase()))
                .slice(0, 8);

            if (!filtered.length) {
                tagSuggestionList.innerHTML = '';
                tagSuggestionList.classList.add('hidden');
                return;
            }

            tagSuggestionList.innerHTML = filtered
                .map((t, index) => `<li class="${index === suggestionIndex ? 'active' : ''}" data-value="${escapeHtml(t)}">${escapeHtml(t)}</li>`)
                .join('');
            tagSuggestionList.classList.remove('hidden');
        }

        function hideSuggestions() {
            if (!tagSuggestionList) return;
            suggestionIndex = -1;
            tagSuggestionList.classList.add('hidden');
        }

        function validateTagState() {
            if (tagsState.length > 20) {
                setTagStatus('Tối đa 20 tag.', true);
                return false;
            }
            if (tagsState.length < 1) {
                setTagStatus('Cần tối thiểu 1 tag.', true);
                return false;
            }
            setTagStatus('');
            return true;
        }


        function syncTagsHidden() {
            tagsHidden.value = tagsState.join(', ');
        }

        function renderTagChips() {
            tagChips.innerHTML = '';
            tagsState.forEach((tag) => {
                const chip = document.createElement('span');
                chip.className = 'tag-chip';
                chip.textContent = '#' + tag;
                chip.title = 'Nhấn để xóa tag này';

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'tag-chip-close';
                removeBtn.setAttribute('aria-label', 'Xóa tag');
                removeBtn.textContent = '×';
                removeBtn.addEventListener('click', function() {
                    tagsState = tagsState.filter((t) => t !== tag);
                    renderTagChips();
                    syncTagsHidden();
                    validateTagState();
                });

                chip.appendChild(removeBtn);
                tagChips.appendChild(chip);
            });
        }

        function addTag(raw) {
            const tag = normalizeTag(raw);
            if (!tag) return;
            const key = tag.toLowerCase();
            const exists = tagsState.some((t) => t.toLowerCase() === key);
            if (exists) {
                setTagStatus('Tag đã tồn tại.', true);
                const existingChip = [...tagChips.children].find((c) => c.textContent.trim().toLowerCase().startsWith('#' + key));
                if (existingChip) {
                    existingChip.classList.add('tag-chip-conflict');
                    setTimeout(() => existingChip.classList.remove('tag-chip-conflict'), 600);
                }
                return;
            }
            if (tagsState.length >= 20) {
                setTagStatus('Tối đa 20 tag.', true);
                return;
            }
            tagsState.push(tag);
            renderTagChips();
            syncTagsHidden();
            setTagStatus('Đã thêm tag: ' + tag, false);
            validateTagState();
        }

        tagInput.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                hideSuggestions();
                return;
            }
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                const items = tagSuggestionList.querySelectorAll('li');
                if (!items.length) return;
                suggestionIndex = (suggestionIndex + 1) % items.length;
                renderSuggestions();
                return;
            }
            if (event.key === 'ArrowUp') {
                event.preventDefault();
                const items = tagSuggestionList.querySelectorAll('li');
                if (!items.length) return;
                suggestionIndex = (suggestionIndex - 1 + items.length) % items.length;
                renderSuggestions();
                return;
            }
            if (event.key === 'Enter') {
                event.preventDefault();
                const items = tagSuggestionList.querySelectorAll('li');
                if (items.length && suggestionIndex >= 0) {
                    addTag(items[suggestionIndex].dataset.value);
                } else {
                    addTag(tagInput.value);
                }
                tagInput.value = '';
                hideSuggestions();
                return;
            }
            if (event.key === ',') {
                event.preventDefault();
                addTag(tagInput.value);
                tagInput.value = '';
                hideSuggestions();
                return;
            }
            if (event.key === 'Backspace' && tagInput.value === '' && tagsState.length > 0) {
                tagsState.pop();
                renderTagChips();
                syncTagsHidden();
                validateTagState();
            }
        });

        tagInput.addEventListener('input', function() {
            renderSuggestions();
        });

        tagInput.addEventListener('paste', function(event) {
            event.preventDefault();
            const pasteText = event.clipboardData.getData('text') || '';
            const parts = pasteText.split(/[\s,;\n\r]+/).map((item) => normalizeTag(item)).filter((item) => item);
            if (!parts.length) return;
            parts.forEach((part) => addTag(part));
            tagInput.value = '';
            hideSuggestions();
        });

        tagInput.addEventListener('blur', function() {
            setTimeout(hideSuggestions, 150);
        });

        if (tagSuggestionList) {
            tagSuggestionList.addEventListener('mousedown', function(event) {
                const item = event.target.closest('li');
                if (item && item.dataset.value) {
                    addTag(item.dataset.value);
                    tagInput.value = '';
                    hideSuggestions();
                }
            });
        }

        document.querySelector('form').addEventListener('submit', function(event) {
            if (!validateTagState()) {
                event.preventDefault();
                return false;
            }
            return true;
        });

        renderTagChips();
        syncTagsHidden();
        validateTagState();
    </script>

    <script src="../js/admin_script.js"></script>
</body>

</html>
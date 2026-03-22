<?php

include '../components/connect.php';
include '../components/feature_engine.php';
include '../components/seo_helpers.php';

session_start();

$admin_id = $_SESSION['admin_id'] ?? null;
if (!$admin_id) {
    header('location:admin_login.php');
    exit;
}

$fetch_profile = blog_fetch_admin_profile($conn, $admin_id);

if (isset($_POST['publish']) || isset($_POST['draft'])) {
    $content = (string)($_POST['content'] ?? '');
    $name = trim((string)($_POST['name'] ?? ''));
    $title = trim((string)($_POST['title'] ?? ''));
    $category = trim((string)($_POST['category'] ?? ''));

    $image_file_name = $_FILES['image_file']['name'] ?? '';
    $image_file_name = htmlspecialchars($image_file_name, ENT_QUOTES, 'UTF-8');
    $image_file_size = (int)($_FILES['image_file']['size'] ?? 0);
    $image_file_type = (string)($_FILES['image_file']['type'] ?? '');

    $image_url = trim((string)($_POST['image_url'] ?? ''));
    $tags_input = (string)($_POST['tags'] ?? '');

    $image = '';
    if ($image_file_name !== '') {
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        if (!in_array($image_file_type, $allowedTypes, true)) {
            $message[] = 'File ảnh không hợp lệ. Chỉ hỗ trợ JPG/PNG/WebP.';
        } elseif ($image_file_size > 2000000) {
            $message[] = 'Kích thước ảnh quá lớn (tối đa 2MB).';
        } else {
            $uploadResult = blog_cloudinary_upload($_FILES['image_file'], blog_cloudinary_default_folder() . '/posts');
            if (!($uploadResult['ok'] ?? false)) {
                $message[] = (string)($uploadResult['error'] ?? 'Không thể upload ảnh lên Cloudinary.');
            } else {
                $image = (string)$uploadResult['secure_url'];
            }
        }
    } elseif ($image_url !== '') {
        if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
            $message[] = 'URL ảnh không hợp lệ.';
        } else {
            $uploadResult = blog_cloudinary_upload($image_url, blog_cloudinary_default_folder() . '/posts');
            if (!($uploadResult['ok'] ?? false)) {
                $message[] = (string)($uploadResult['error'] ?? 'Không thể đồng bộ URL ảnh lên Cloudinary.');
            } else {
                $image = (string)$uploadResult['secure_url'];
            }
        }
    }

    $status = isset($_POST['publish']) ? 'active' : 'deactive';

    if ($title === '' || $content === '' || $category === '' || $name === '') {
        $message[] = 'Vui lòng nhập đầy đủ thông tin bắt buộc.';
    } else {
        $select_tag_id = $conn->prepare('SELECT category_id FROM cart WHERE name = ? LIMIT 1');
        $select_tag_id->execute([$category]);
        $tag = $select_tag_id->fetch(PDO::FETCH_ASSOC);
        $tag_id = isset($tag['category_id']) ? (int)$tag['category_id'] : null;

        $date = date('Y-m-d');
        $insert_post = $conn->prepare('INSERT INTO posts (admin_id, name, title, content, category, image, status, tag_id, date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $insert_post->execute([(int)$admin_id, $name, $title, $content, $category, $image, $status, $tag_id, $date]);

        $newPostId = (int)$conn->lastInsertId();
        blog_sync_post_tags($conn, $newPostId, blog_parse_tags_input($tags_input));

        if ($status === 'active' && $newPostId > 0) {
            blog_ensure_feature_tables($conn);
            $postLink = site_url('static/view_post.php?post_id=' . $newPostId);
            blog_notify_all_users(
                $conn,
                'new_post',
                'Bài viết mới',
                '"' . mb_substr($title, 0, 80, 'UTF-8') . '" vừa được đăng.',
                $postLink,
                0
            );
        }

        $_SESSION['message'] = isset($_POST['publish']) ? 'Bài viết đã được đăng thành công.' : 'Đã lưu bản nháp.';
        header('location:view_posts.php');
        exit;
    }
}

$select_categories = $conn->prepare('SELECT * FROM cart ORDER BY name ASC');
$select_categories->execute();
$categories = $select_categories->fetchAll(PDO::FETCH_ASSOC);

$tag_names_stmt = $conn->prepare('SELECT name FROM tags ORDER BY name ASC LIMIT 300');
$tag_names_stmt->execute();
$tag_names = $tag_names_stmt->fetchAll(PDO::FETCH_COLUMN);

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm bài đăng</title>

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


    <?php include '../components/admin_header.php' ?>

    <section class="post-editor ui-container">

        <h1 class="heading ui-title">Thêm bài đăng mới</h1>

        <form action="" method="post" enctype="multipart/form-data" onsubmit="return submitForm()" class="ui-card" data-no-submit-lock="true">
            <input type="hidden" name="name" value="<?= htmlspecialchars((string)($fetch_profile['name'] ?? 'admin'), ENT_QUOTES, 'UTF-8'); ?>">

            <p>Tiêu đề bài viết <span>*</span></p>
            <input type="text" name="title" maxlength="100" required placeholder="Thêm tiêu đề" class="box ui-input">

            <p>Nội dung bài viết <span>*</span></p>
            <textarea name="content" id="content" class="box content_add ui-textarea" required maxlength="10000" placeholder="Viết nội dung..." cols="30" rows="10"></textarea>

            <p>Thể loại bài viết <span>*</span></p>
            <select name="category" class="box ui-select" required>
                <option value="" selected disabled>-- Chọn thể loại --</option>
                <?php foreach ($categories as $category) : ?>
                    <option value="<?= htmlspecialchars($category['name']); ?>"><?= htmlspecialchars($category['name']); ?></option>
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
                <input type="hidden" name="tags" id="tagsHidden" value="">
                <p id="tagStatus" class="mt-2 text-xs text-red-500 hidden"></p>
            </div>
            <small class="text-gray-500">Nhập tối đa 20 tag; Enter/dấu phẩy/paste để thêm; chuột hover chip để xóa; Esc đóng gợi ý; # sẽ gợi ý.</small>

            <p>Ảnh đại diện bài viết</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Upload file</label>
                    <input type="file" id="imageFileInput" name="image_file" class="box ui-input" accept="image/jpg, image/jpeg, image/png, image/webp">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Hoặc URL</label>
                    <input type="url" id="imageUrlInput" name="image_url" class="box ui-input" placeholder="https://example.com/path/to/image.jpg">
                </div>
            </div>
            <div class="mt-3">
                <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">Xem trước ảnh:</p>
                <img id="imagePreview" src="#" alt="Preview" class="w-48 h-48 object-cover rounded-lg border border-gray-300 dark:border-gray-600 hidden">
                <div class="mt-2 flex items-center gap-2">
                    <button type="button" id="clearImage" class="px-3 py-1 rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-100 hover:bg-gray-300 dark:hover:bg-gray-600 transition">Xóa thumbnail</button>
                    <span id="imageFeedback" class="text-sm text-red-500 hidden"></span>
                </div>
            </div>

            <div class="flex-btn mt-3">
                <input type="submit" value="Lưu công khai" name="publish" class="btn ui-btn">
                <input type="submit" value="Lưu bản nháp" name="draft" class="option-btn ui-btn-warning">
            </div>
        </form>
    </section>

    <script>
        CKEDITOR.replace('content', {
            filebrowserBrowseUrl: '../plugin/ckfinder/ckfinder.html',
            filebrowserUploadUrl: '../plugin/ckfinder/core/connector/php/connector.php?command=QuickUpload&type=Files',
            height: '55vh'
        });

        function submitForm() {
            for (let instance in CKEDITOR.instances) {
                CKEDITOR.instances[instance].updateElement();
            }
            return true;
        }

        const imageFileInput = document.getElementById('imageFileInput');
        const imageUrlInput = document.getElementById('imageUrlInput');
        const imagePreview = document.getElementById('imagePreview');
        const clearImageBtn = document.getElementById('clearImage');
        const imageFeedback = document.getElementById('imageFeedback');

        function showImageFeedback(text) {
            if (!text) {
                imageFeedback.textContent = '';
                imageFeedback.classList.add('hidden');
                return;
            }
            imageFeedback.textContent = text;
            imageFeedback.classList.remove('hidden');
        }

        function updateImagePreview(src) {
            if (src) {
                imagePreview.src = src;
                imagePreview.classList.remove('hidden');
            } else {
                imagePreview.src = '#';
                imagePreview.classList.add('hidden');
            }
        }

        imageFileInput.addEventListener('change', function() {
            const file = this.files[0];
            if (!file) {
                if (imageUrlInput.value.trim()) {
                    updateImagePreview(imageUrlInput.value.trim());
                    showImageFeedback('');
                } else {
                    updateImagePreview('');
                }
                return;
            }

            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                showImageFeedback('File không hợp lệ. Chỉ hỗ trợ JPG/PNG/WebP.');
                imageFileInput.value = '';
                updateImagePreview('');
                return;
            }

            if (file.size > 2 * 1024 * 1024) {
                showImageFeedback('Kích thước ảnh quá lớn. Tối đa 2MB.');
                imageFileInput.value = '';
                updateImagePreview('');
                return;
            }

            showImageFeedback('');
            const reader = new FileReader();
            reader.onload = function(e) {
                updateImagePreview(e.target.result);
                imageUrlInput.value = '';
            };
            reader.readAsDataURL(file);
        });

        imageUrlInput.addEventListener('input', function() {
            const url = this.value.trim();
            if (url) {
                updateImagePreview(url);
                imageFileInput.value = '';
            } else {
                updateImagePreview('');
            }
        });

        clearImageBtn.addEventListener('click', function() {
            imageFileInput.value = '';
            imageUrlInput.value = '';
            showImageFeedback('');
            updateImagePreview('');
        });

        const existingTags = <?= json_encode(array_values(array_filter(array_map(function ($t) {
                                    return trim((string)$t);
                                }, $tag_names))), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const tagInput = document.getElementById('tagInput');
        const tagChips = document.getElementById('tagChips');
        const tagsHidden = document.getElementById('tagsHidden');
        const tagSuggestionList = document.getElementById('tagSuggestionList');
        const tagStatus = document.getElementById('tagStatus');
        let tagsState = [];
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

        function escapeHtml(text) {
            return String(text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
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
                chip.title = 'Nhấn để xóa';

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

        tagSuggestionList.addEventListener('mousedown', function(event) {
            const item = event.target.closest('li');
            if (item && item.dataset.value) {
                addTag(item.dataset.value);
                tagInput.value = '';
                hideSuggestions();
            }
        });

        document.querySelector('form').addEventListener('submit', function(event) {
            if (!validateTagState()) {
                event.preventDefault();
                return false;
            }
            return true;
        });

        renderSuggestions();
    </script>

    <script src="../js/admin_script.js"></script>

</body>

</html>
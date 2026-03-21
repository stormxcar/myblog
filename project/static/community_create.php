<?php
include '../components/connect.php';
include '../components/seo_helpers.php';
include '../components/community_engine.php';

session_start();

community_ensure_tables($conn);

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if ($user_id <= 0) {
    $_SESSION['flash_message'] = 'Vui lòng đăng nhập để đăng bài cộng đồng.';
    $_SESSION['flash_type'] = 'warning';
    header('Location: login.php');
    exit;
}

$page_title = 'Tạo bài cộng đồng - My Blog';
$page_description = 'Đăng bài cộng đồng với nội dung, hình ảnh và liên kết.';
$page_canonical = site_url('static/community_create.php');
?>

<?php include '../components/layout_header.php'; ?>

<?php
include '../components/breadcrumb.php';
$breadcrumb_items = auto_breadcrumb('Tạo bài cộng đồng');
render_breadcrumb($breadcrumb_items);
?>

<main class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <style>
        .community-create-tab[aria-selected="true"] {
            background: rgba(59, 130, 246, 0.12);
            color: #2563eb;
            border-color: rgba(59, 130, 246, 0.35);
        }

        .community-editor-wrap .cke_chrome {
            border-radius: 0.75rem;
            border-color: rgba(148, 163, 184, 0.45);
        }
    </style>
    <div class="container-custom py-8">
        <section class="max-w-4xl mx-auto bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-6 md:p-8 border border-gray-200 dark:border-gray-700">
            <div class="mb-8">
                <h1 class="text-3xl md:text-4xl font-bold text-gray-900 dark:text-white">Tạo bài đăng cộng đồng</h1>
                <p class="text-gray-600 dark:text-gray-300 mt-2">Chia sẻ ý tưởng, kèm ảnh và các liên kết hữu ích cho mọi người.</p>
            </div>

            <form id="communityCreateForm" class="space-y-6" enctype="multipart/form-data">
                <input type="hidden" name="post_type" id="communityPostType" value="text">
                <input type="hidden" name="content" id="communityContentHidden" value="">

                <div role="tablist" class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <button type="button" role="tab" class="community-create-tab border border-gray-300 dark:border-gray-600 rounded-xl px-4 py-3 text-left font-semibold" data-tab="text" aria-selected="true">Văn bản</button>
                    <button type="button" role="tab" class="community-create-tab border border-gray-300 dark:border-gray-600 rounded-xl px-4 py-3 text-left font-semibold" data-tab="media" aria-selected="false">Hình ảnh và video</button>
                    <button type="button" role="tab" class="community-create-tab border border-gray-300 dark:border-gray-600 rounded-xl px-4 py-3 text-left font-semibold" data-tab="link" aria-selected="false">Liên kết</button>
                </div>

                <div>
                    <label for="communityTitle" id="communityTitleLabel" class="block text-sm font-semibold text-gray-800 dark:text-gray-100 mb-2">Tiêu đề bài văn bản</label>
                    <input id="communityTitle" name="title" maxlength="300" required class="form-input" placeholder="Nhập tiêu đề (tối đa 300 ký tự)">
                    <p class="text-xs text-gray-500 mt-1">Tối đa 300 ký tự.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="communityPrivacy" class="block text-sm font-semibold text-gray-800 dark:text-gray-100 mb-2">Phạm vi bài đăng</label>
                        <select id="communityPrivacy" name="privacy" class="form-input">
                            <option value="public">Công khai</option>
                            <option value="followers">Chỉ người theo dõi</option>
                            <option value="private">Chỉ mình tôi (Bản nháp)</option>
                        </select>
                    </div>
                    <div>
                        <label for="communityImages" class="block text-sm font-semibold text-gray-800 dark:text-gray-100 mb-2">Ảnh đính kèm</label>
                        <input id="communityImages" type="file" name="images[]" accept="image/*" multiple class="form-input">
                        <p class="text-xs text-gray-500 mt-1">Tối đa 12 ảnh, mỗi ảnh <= 5MB.</p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-800 dark:text-gray-100 mb-2">Phần văn bản chính (không bắt buộc)</label>
                    <div class="community-editor-wrap">
                        <textarea id="communityContentEditor"></textarea>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Nội dung tối đa 5000 ký tự sau khi bỏ định dạng.</p>
                </div>

                <div id="communityLinkWrap" class="hidden">
                    <label for="communityLinks" class="block text-sm font-semibold text-gray-800 dark:text-gray-100 mb-2">Liên kết (mỗi dòng 1 link)</label>
                    <textarea id="communityLinks" name="links" rows="4" class="form-textarea" placeholder="https://example.com"></textarea>
                </div>

                <div id="communityImagePreview" class="grid grid-cols-2 md:grid-cols-4 gap-3"></div>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" id="communityDraftBtn" class="btn-secondary">
                        <i class="fas fa-floppy-disk mr-2"></i>Lưu bản nháp
                    </button>
                    <button type="submit" id="communitySubmitBtn" class="btn-primary">
                        <i class="fas fa-paper-plane mr-2"></i>Đăng bài
                    </button>
                    <a href="community_feed.php" class="btn-secondary">Xem bảng tin cộng đồng</a>
                </div>
            </form>
        </section>
    </div>
</main>

<script src="../plugin/ckeditor/ckeditor.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('communityCreateForm');
        const submitBtn = document.getElementById('communitySubmitBtn');
        const draftBtn = document.getElementById('communityDraftBtn');
        const imageInput = document.getElementById('communityImages');
        const previewWrap = document.getElementById('communityImagePreview');
        const contentHidden = document.getElementById('communityContentHidden');
        const editorEl = document.getElementById('communityContentEditor');
        let editorInstance = null;
        const postTypeInput = document.getElementById('communityPostType');
        const titleLabel = document.getElementById('communityTitleLabel');
        const linkWrap = document.getElementById('communityLinkWrap');
        let submitIntent = 'publish';

        const tabLabelMap = {
            text: 'Tiêu đề bài văn bản',
            media: 'Tiêu đề bài hình ảnh và video',
            link: 'Tiêu đề bài liên kết'
        };

        function applyTab(nextType) {
            const currentType = ['text', 'media', 'link'].includes(nextType) ? nextType : 'text';
            if (postTypeInput) {
                postTypeInput.value = currentType;
            }

            document.querySelectorAll('.community-create-tab').forEach(function(tab) {
                const active = tab.getAttribute('data-tab') === currentType;
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
            });

            if (titleLabel) {
                titleLabel.textContent = tabLabelMap[currentType] || 'Tiêu đề bài đăng';
            }

            if (linkWrap) {
                linkWrap.classList.toggle('hidden', currentType !== 'link');
            }
        }

        document.querySelectorAll('.community-create-tab').forEach(function(tab) {
            tab.addEventListener('click', function() {
                applyTab(String(tab.getAttribute('data-tab') || 'text'));
            });
        });

        if (window.CKEDITOR && editorEl) {
            editorInstance = window.CKEDITOR.replace('communityContentEditor', {
                height: 220,
                removePlugins: 'elementspath',
                resize_enabled: true,
                toolbar: [{
                        name: 'basicstyles',
                        items: ['Bold', 'Italic', 'Underline', 'Strike', '-', 'RemoveFormat']
                    },
                    {
                        name: 'paragraph',
                        items: ['NumberedList', 'BulletedList', '-', 'Blockquote']
                    },
                    {
                        name: 'links',
                        items: ['Link', 'Unlink']
                    },
                    {
                        name: 'insert',
                        items: ['Image', 'Table', 'HorizontalRule']
                    },
                    {
                        name: 'styles',
                        items: ['Format', 'FontSize']
                    },
                    {
                        name: 'colors',
                        items: ['TextColor', 'BGColor']
                    },
                    {
                        name: 'tools',
                        items: ['Maximize']
                    }
                ]
            });
        }

        function renderImagePreview(files) {
            if (!previewWrap) {
                return;
            }
            previewWrap.innerHTML = '';
            const fileList = Array.from(files || []);
            fileList.slice(0, 3).forEach(function(file) {
                const reader = new FileReader();
                reader.onload = function(evt) {
                    const card = document.createElement('div');
                    card.className = 'rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700';
                    card.innerHTML = '<img class="w-full h-28 object-cover" alt="preview" src="' + String(evt.target && evt.target.result ? evt.target.result : '') + '">';
                    previewWrap.appendChild(card);
                };
                reader.readAsDataURL(file);
            });

            if (fileList.length > 3) {
                const extra = document.createElement('div');
                extra.className = 'rounded-xl border border-dashed border-main/40 bg-main/5 text-main text-xs font-semibold h-28 flex items-center justify-center';
                extra.textContent = '+' + (fileList.length - 3) + ' anh nua';
                previewWrap.appendChild(extra);
            }
        }

        async function compressImageFile(file) {
            if (!(file instanceof File)) {
                return file;
            }
            if (!file.type.startsWith('image/')) {
                return file;
            }
            if (file.type === 'image/gif' || file.type === 'image/avif') {
                return file;
            }

            const maxSide = 1600;
            const quality = 0.82;

            const loadImage = () => new Promise((resolve, reject) => {
                const img = new Image();
                img.onload = () => resolve(img);
                img.onerror = reject;
                img.src = URL.createObjectURL(file);
            });

            try {
                const source = await loadImage();
                const sourceWidth = source.naturalWidth || source.width;
                const sourceHeight = source.naturalHeight || source.height;
                const ratio = Math.min(1, maxSide / Math.max(sourceWidth, sourceHeight));
                const targetWidth = Math.max(1, Math.round(sourceWidth * ratio));
                const targetHeight = Math.max(1, Math.round(sourceHeight * ratio));

                const canvas = document.createElement('canvas');
                canvas.width = targetWidth;
                canvas.height = targetHeight;

                const ctx = canvas.getContext('2d');
                if (!ctx) {
                    return file;
                }
                ctx.drawImage(source, 0, 0, targetWidth, targetHeight);

                const blob = await new Promise((resolve) => {
                    canvas.toBlob(resolve, 'image/webp', quality);
                });

                if (!(blob instanceof Blob) || blob.size <= 0) {
                    return file;
                }

                if (blob.size >= file.size * 0.95) {
                    return file;
                }

                const compressedName = file.name.replace(/\.[^.]+$/, '') + '.webp';
                return new File([blob], compressedName, {
                    type: 'image/webp',
                    lastModified: Date.now()
                });
            } catch (err) {
                return file;
            }
        }

        if (imageInput) {
            imageInput.addEventListener('change', function() {
                renderImagePreview(this.files);
            });
        }

        if (!form) {
            return;
        }

        if (draftBtn) {
            draftBtn.addEventListener('click', function() {
                submitIntent = 'draft';
                if (form) {
                    form.requestSubmit(submitBtn);
                }
            });
        }
        if (submitBtn) {
            submitBtn.addEventListener('click', function() {
                submitIntent = 'publish';
            });
        }

        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            const originalText = submitBtn ? submitBtn.innerHTML : '';
            const originalDraftText = draftBtn ? draftBtn.innerHTML : '';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Dang dang...';
            }
            if (draftBtn) {
                draftBtn.disabled = true;
            }

            try {
                const endpoint = (window.BLOG_ENDPOINTS && window.BLOG_ENDPOINTS.communityCreate) ?
                    window.BLOG_ENDPOINTS.communityCreate :
                    'community_create_api.php';

                const formData = new FormData(form);
                if (contentHidden && editor) {
                    const html = editorInstance ? editorInstance.getData() : '';
                    contentHidden.value = html;
                    formData.set('content', html);
                }

                const privacyForSubmit = submitIntent === 'draft' ? 'private' : String(formData.get('privacy') || 'public');
                formData.set('privacy', privacyForSubmit);
                formData.delete('images[]');

                const selectedImages = Array.from((imageInput && imageInput.files) ? imageInput.files : []).slice(0, 12);
                if (selectedImages.length > 0) {
                    const processedImages = await Promise.all(selectedImages.map(compressImageFile));
                    processedImages.forEach(function(processed) {
                        formData.append('images[]', processed, processed.name);
                    });
                }
                const response = await fetch(endpoint, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                });

                const contentType = (response.headers.get('content-type') || '').toLowerCase();
                if (!contentType.includes('application/json')) {
                    throw new Error('invalid-json-response');
                }

                const payload = await response.json();
                if (!payload || payload.ok !== true) {
                    if (payload && payload.login_required && payload.login_url) {
                        showNotification(payload.message || 'Ban can dang nhap.', 'warning');
                        setTimeout(function() {
                            window.location.href = payload.login_url;
                        }, 500);
                        return;
                    }

                    showNotification((payload && payload.message) || 'Khong the dang bai luc nay.', 'error');
                    return;
                }

                showNotification(payload.message || 'Dang bai thanh cong.', 'success');
                setTimeout(function() {
                    window.location.href = payload.redirect_url || 'community_feed.php';
                }, 500);
            } catch (err) {
                showNotification('Loi ket noi khi dang bai cong dong.', 'error');
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
                if (draftBtn) {
                    draftBtn.disabled = false;
                    draftBtn.innerHTML = originalDraftText;
                }
                submitIntent = 'publish';
            }
        });

        applyTab('text');
    });
</script>

<?php include '../components/layout_footer.php'; ?>
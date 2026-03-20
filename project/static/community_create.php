<?php
include '../components/connect.php';
include '../components/seo_helpers.php';
include '../components/community_engine.php';

session_start();

community_ensure_tables($conn);

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if ($user_id <= 0) {
    $_SESSION['flash_message'] = 'Vui long dang nhap de dang bai cong dong.';
    $_SESSION['flash_type'] = 'warning';
    header('Location: login.php');
    exit;
}

$page_title = 'Tao bai cong dong - My Blog';
$page_description = 'Dang bai cong dong voi noi dung, hinh anh va lien ket.';
$page_canonical = site_url('static/community_create.php');
?>

<?php include '../components/layout_header.php'; ?>

<?php
include '../components/breadcrumb.php';
$breadcrumb_items = auto_breadcrumb('Tao bai cong dong');
render_breadcrumb($breadcrumb_items);
?>

<main class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <div class="container-custom py-8">
        <section class="max-w-4xl mx-auto bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-6 md:p-8 border border-gray-200 dark:border-gray-700">
            <div class="mb-8">
                <h1 class="text-3xl md:text-4xl font-bold text-gray-900 dark:text-white">Tao bai dang cong dong</h1>
                <p class="text-gray-600 dark:text-gray-300 mt-2">Chia se y tuong, kem anh va cac lien ket huu ich cho moi nguoi.</p>
            </div>

            <form id="communityCreateForm" class="space-y-6" enctype="multipart/form-data">
                <div>
                    <label for="communityContent" class="block text-sm font-semibold text-gray-800 dark:text-gray-100 mb-2">Noi dung bai viet</label>
                    <textarea id="communityContent" name="content" rows="7" maxlength="5000" required class="form-textarea" placeholder="Ban dang nghi gi?"></textarea>
                    <p class="text-xs text-gray-500 mt-1">Toi da 5000 ky tu.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="communityPrivacy" class="block text-sm font-semibold text-gray-800 dark:text-gray-100 mb-2">Pham vi bai dang</label>
                        <select id="communityPrivacy" name="privacy" class="form-input">
                            <option value="public">Cong khai</option>
                            <option value="followers">Chi nguoi theo doi</option>
                            <option value="private">Chi minh toi (Ban nhap)</option>
                        </select>
                    </div>
                    <div>
                        <label for="communityImages" class="block text-sm font-semibold text-gray-800 dark:text-gray-100 mb-2">Anh dinh kem</label>
                        <input id="communityImages" type="file" name="images[]" accept="image/*" multiple class="form-input">
                        <p class="text-xs text-gray-500 mt-1">Toi da 12 anh, moi anh <= 5MB (xem truoc 8 anh dau).</p>
                    </div>
                </div>

                <div>
                    <label for="communityLinks" class="block text-sm font-semibold text-gray-800 dark:text-gray-100 mb-2">Lien ket (moi dong 1 link)</label>
                    <textarea id="communityLinks" name="links" rows="4" class="form-textarea" placeholder="https://example.com"></textarea>
                </div>

                <div id="communityImagePreview" class="grid grid-cols-2 md:grid-cols-4 gap-3"></div>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" id="communitySubmitBtn" class="btn-primary">
                        <i class="fas fa-paper-plane mr-2"></i>Dang bai
                    </button>
                    <a href="community_feed.php" class="btn-secondary">Xem bang tin cong dong</a>
                </div>
            </form>
        </section>
    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('communityCreateForm');
        const submitBtn = document.getElementById('communitySubmitBtn');
        const imageInput = document.getElementById('communityImages');
        const previewWrap = document.getElementById('communityImagePreview');

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

        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            const originalText = submitBtn ? submitBtn.innerHTML : '';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Dang dang...';
            }

            try {
                const endpoint = (window.BLOG_ENDPOINTS && window.BLOG_ENDPOINTS.communityCreate) ?
                    window.BLOG_ENDPOINTS.communityCreate :
                    'community_create_api.php';

                const formData = new FormData(form);
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
            }
        });
    });
</script>

<?php include '../components/layout_footer.php'; ?>
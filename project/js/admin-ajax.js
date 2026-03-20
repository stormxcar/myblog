(function () {
  const showToast = (text, type) => {
    const color = type === "error" ? "#dc2626" : "#0f766e";
    if (window.Toastify) {
      Toastify({
        text,
        duration: 3800,
        gravity: "top",
        position: "right",
        close: true,
        stopOnFocus: true,
        style: {
          background: color,
        },
      }).showToast();
      return;
    }
    alert(text);
  };

  const toQueryString = (params) =>
    Object.keys(params)
      .map(
        (key) =>
          `${encodeURIComponent(key)}=${encodeURIComponent(params[key])}`,
      )
      .join("&");

  const postForm = async (formDataObj) => {
    const res = await fetch("admin_api.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams(formDataObj),
    });
    return parseJsonResponse(res);
  };

  const parseJsonResponse = async (res) => {
    const raw = await res.text();
    try {
      return JSON.parse(raw);
    } catch (err) {
      console.error("Invalid JSON response from admin_api.php", {
        status: res.status,
        body: raw,
      });
      return {
        ok: false,
        message: "Phan hoi may chu khong hop le. Vui long tai lai trang.",
      };
    }
  };

  const bindPagination = (container, onPage) => {
    if (!container) return;
    container.addEventListener("click", (event) => {
      const button = event.target.closest(".admin-page-btn");
      if (!button || button.disabled) return;
      onPage(Number(button.dataset.page || "1"));
    });
  };

  const collectCheckedIds = (selector) =>
    Array.from(document.querySelectorAll(selector))
      .filter((input) => input.checked)
      .map((input) => input.value)
      .filter(Boolean);

  const bindSelectAll = (masterSelector, itemSelector) => {
    const master = document.querySelector(masterSelector);
    if (!master) return;
    master.addEventListener("change", () => {
      document.querySelectorAll(itemSelector).forEach((item) => {
        item.checked = master.checked;
      });
    });
  };

  const postPageRoot = document.getElementById("admin-posts-page");
  if (postPageRoot) {
    const filtersForm = document.getElementById("admin-posts-filters");
    const tbody = document.getElementById("admin-posts-tbody");
    const pager = document.getElementById("admin-posts-pager");
    const summary = document.getElementById("admin-posts-summary");
    const bulkBtn = document.getElementById("admin-bulk-delete-posts");
    let page = 1;

    const loadPosts = async () => {
      const formData = new FormData(filtersForm);
      const params = {
        action: "posts_list",
        page,
        search: formData.get("search") || "",
        status: formData.get("status") || "all",
        category: formData.get("category") || "all",
        sort: formData.get("sort") || "newest",
        limit: formData.get("limit") || "10",
      };

      tbody.innerHTML = '<tr><td colspan="12">Dang tai...</td></tr>';
      const res = await fetch(`admin_api.php?${toQueryString(params)}`);
      const data = await parseJsonResponse(res);

      if (!data.ok) {
        showToast(data.message || "Khong the tai danh sach bai viet", "error");
        return;
      }

      tbody.innerHTML = data.html;
      pager.innerHTML = data.pagination;
      summary.textContent = `Tong ${data.summary.total} bai viet - Trang ${data.summary.page}/${data.summary.total_pages}`;
      const selectAll = document.getElementById("admin-posts-select-all");
      if (selectAll) selectAll.checked = false;
    };

    filtersForm.addEventListener("submit", (event) => {
      event.preventDefault();
      page = 1;
      loadPosts();
    });

    filtersForm.addEventListener("change", () => {
      page = 1;
      loadPosts();
    });

    bindPagination(pager, (nextPage) => {
      page = nextPage;
      loadPosts();
    });

    bindSelectAll("#admin-posts-select-all", ".admin-bulk-post-check");

    bulkBtn?.addEventListener("click", async () => {
      const ids = collectCheckedIds(".admin-bulk-post-check");
      if (!ids.length) {
        showToast("Bạn chưa chọn bài viết nào", "error");
        return;
      }
      if (!confirm(`Xóa ${ids.length} bài viết đã chọn?`)) return;

      const data = await postForm({
        action: "bulk_delete_posts",
        ids: ids.join(","),
      });
      if (!data.ok) {
        showToast(data.message || "Xóa hàng loạt thất bại", "error");
        return;
      }
      showToast(data.message || "Đã xóa hàng loạt");
      loadPosts();
    });

    tbody.addEventListener("click", async (event) => {
      const btn = event.target.closest(".admin-delete-post");
      if (!btn) return;

      const postId = btn.dataset.postId;
      if (!postId || !confirm("Bạn có chắc chắn muốn xóa bài viết này?"))
        return;
      btn.disabled = true;

      const data = await postForm({ action: "delete_post", post_id: postId });
      if (!data.ok) {
        btn.disabled = false;
        showToast(data.message || "Xóa bài viết thất bại", "error");
        return;
      }

      showToast(data.message || "Đã xóa bài viết");
      loadPosts();
    });

    loadPosts();
  }

  const commentsPageRoot = document.getElementById("admin-comments-page");
  if (commentsPageRoot) {
    const filtersForm = document.getElementById("admin-comments-filters");
    const list = document.getElementById("admin-comments-list");
    const pager = document.getElementById("admin-comments-pager");
    const summary = document.getElementById("admin-comments-summary");
    const bulkBtn = document.getElementById("admin-bulk-delete-comments");
    let page = 1;

    const loadComments = async () => {
      const formData = new FormData(filtersForm);
      const params = {
        action: "comments_list",
        page,
        search: formData.get("search") || "",
        sort: formData.get("sort") || "newest",
        limit: formData.get("limit") || "10",
      };

      list.innerHTML = '<div class="empty">Dang tai binh luan...</div>';
      const res = await fetch(`admin_api.php?${toQueryString(params)}`);
      const data = await parseJsonResponse(res);

      if (!data.ok) {
        showToast(data.message || "Khong the tai danh sach binh luan", "error");
        return;
      }

      list.innerHTML = data.html;
      pager.innerHTML = data.pagination;
      summary.textContent = `Tong ${data.summary.total} binh luan - Trang ${data.summary.page}/${data.summary.total_pages}`;
    };

    filtersForm.addEventListener("submit", (event) => {
      event.preventDefault();
      page = 1;
      loadComments();
    });

    filtersForm.addEventListener("change", () => {
      page = 1;
      loadComments();
    });

    bindPagination(pager, (nextPage) => {
      page = nextPage;
      loadComments();
    });

    bulkBtn?.addEventListener("click", async () => {
      const ids = collectCheckedIds(".admin-bulk-comment-check");
      if (!ids.length) {
        showToast("Bạn chưa chọn bình luận nào", "error");
        return;
      }
      if (!confirm(`Xóa ${ids.length} bình luận đã chọn?`)) return;

      const data = await postForm({
        action: "bulk_delete_comments",
        ids: ids.join(","),
      });
      if (!data.ok) {
        showToast(data.message || "Xóa hàng loạt thất bại", "error");
        return;
      }
      showToast(data.message || "Đã xóa hàng loạt bình luận");
      loadComments();
    });

    list.addEventListener("click", async (event) => {
      const btn = event.target.closest(".admin-delete-comment");
      if (!btn) return;

      const commentId = btn.dataset.commentId;
      if (!commentId || !confirm("Bạn có chắc chắn muốn xóa bình luận này?"))
        return;
      btn.disabled = true;

      const data = await postForm({
        action: "delete_comment",
        comment_id: commentId,
      });
      if (!data.ok) {
        btn.disabled = false;
        showToast(data.message || "Xóa bình luận thất bại", "error");
        return;
      }

      showToast(data.message || "Đã xóa bình luận");
      loadComments();
    });

    loadComments();
  }

  const usersPageRoot = document.getElementById("admin-users-page");
  if (usersPageRoot) {
    const filtersForm = document.getElementById("admin-users-filters");
    const tbody = document.getElementById("admin-users-tbody");
    const pager = document.getElementById("admin-users-pager");
    const summary = document.getElementById("admin-users-summary");
    const bulkBtn = document.getElementById("admin-bulk-delete-users");
    let page = 1;

    const loadUsers = async () => {
      const formData = new FormData(filtersForm);
      const params = {
        action: "users_list",
        page,
        search: formData.get("search") || "",
        ban_filter: formData.get("ban_filter") || "all",
        sort: formData.get("sort") || "newest",
        limit: formData.get("limit") || "10",
      };

      tbody.innerHTML = '<tr><td colspan="11">Dang tai...</td></tr>';
      const res = await fetch(`admin_api.php?${toQueryString(params)}`);
      const data = await parseJsonResponse(res);

      if (!data.ok) {
        showToast(
          data.message || "Khong the tai danh sach nguoi dung",
          "error",
        );
        return;
      }

      tbody.innerHTML = data.html;
      pager.innerHTML = data.pagination;
      summary.textContent = `Tong ${data.summary.total} nguoi dung - Trang ${data.summary.page}/${data.summary.total_pages}`;
      const selectAll = document.getElementById("admin-users-select-all");
      if (selectAll) selectAll.checked = false;
    };

    filtersForm.addEventListener("submit", (event) => {
      event.preventDefault();
      page = 1;
      loadUsers();
    });

    filtersForm.addEventListener("change", () => {
      page = 1;
      loadUsers();
    });

    bindPagination(pager, (nextPage) => {
      page = nextPage;
      loadUsers();
    });

    bindSelectAll("#admin-users-select-all", ".admin-bulk-user-check");

    bulkBtn?.addEventListener("click", async () => {
      const ids = collectCheckedIds(".admin-bulk-user-check");
      if (!ids.length) {
        showToast("Bạn chưa chọn user nào", "error");
        return;
      }
      if (!confirm(`Xóa ${ids.length} user đã chọn?`)) return;

      const data = await postForm({
        action: "bulk_delete_users",
        ids: ids.join(","),
      });
      if (!data.ok) {
        showToast(data.message || "Xóa hàng loạt thất bại", "error");
        return;
      }
      showToast(data.message || "Đã xóa hàng loạt user");
      loadUsers();
    });

    tbody.addEventListener("change", async (event) => {
      const checkbox = event.target.closest(".admin-toggle-ban");
      if (!checkbox) return;

      const userId = checkbox.dataset.userId;
      const banned = checkbox.checked ? "1" : "0";
      const data = await postForm({
        action: "toggle_user_ban",
        user_id: userId,
        banned,
      });

      if (!data.ok) {
        checkbox.checked = !checkbox.checked;
        showToast(data.message || "Cap nhat trang thai that bai", "error");
        return;
      }

      showToast(data.message || "Da cap nhat trang thai");
    });

    tbody.addEventListener("click", async (event) => {
      const btn = event.target.closest(".admin-delete-user");
      if (!btn) return;

      const userId = btn.dataset.userId;
      if (!userId || !confirm("Bạn có chắc chắn muốn xóa người dùng này?"))
        return;
      btn.disabled = true;

      const data = await postForm({ action: "delete_user", user_id: userId });
      if (!data.ok) {
        btn.disabled = false;
        showToast(data.message || "Xoa user that bai", "error");
        return;
      }

      showToast(data.message || "Đã xóa user");
      loadUsers();
    });

    loadUsers();
  }

  const cartsPageRoot = document.getElementById("admin-carts-page");
  if (cartsPageRoot) {
    const createForm = document.getElementById("admin-cart-create-form");
    const filtersForm = document.getElementById("admin-carts-filters");
    const tbody = document.getElementById("admin-carts-tbody");
    const pager = document.getElementById("admin-carts-pager");
    const summary = document.getElementById("admin-carts-summary");
    let page = 1;

    const loadCarts = async () => {
      const formData = new FormData(filtersForm);
      const params = {
        action: "carts_list",
        page,
        search: formData.get("search") || "",
        sort: formData.get("sort") || "newest",
        limit: formData.get("limit") || "10",
      };

      tbody.innerHTML = '<tr><td colspan="5">Dang tai...</td></tr>';
      const res = await fetch(`admin_api.php?${toQueryString(params)}`);
      const data = await parseJsonResponse(res);

      if (!data.ok) {
        showToast(data.message || "Khong the tai danh muc", "error");
        return;
      }

      tbody.innerHTML = data.html;
      pager.innerHTML = data.pagination;
      summary.textContent = `Tong ${data.summary.total} danh muc - Trang ${data.summary.page}/${data.summary.total_pages}`;
    };

    createForm?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const formData = new FormData(createForm);
      const name = (formData.get("name") || "").toString().trim();
      if (!name) {
        showToast("Vui lòng nhập tên danh mục", "error");
        return;
      }

      const data = await postForm({ action: "add_cart", name });
      if (!data.ok) {
        showToast(data.message || "Them danh muc that bai", "error");
        return;
      }

      showToast(data.message || "Đã thêm danh mục");
      createForm.reset();
      loadCarts();
    });

    filtersForm?.addEventListener("submit", (event) => {
      event.preventDefault();
      page = 1;
      loadCarts();
    });

    filtersForm?.addEventListener("change", () => {
      page = 1;
      loadCarts();
    });

    bindPagination(pager, (nextPage) => {
      page = nextPage;
      loadCarts();
    });

    tbody.addEventListener("click", async (event) => {
      const editBtn = event.target.closest(".admin-edit-cart");
      if (editBtn) {
        const cartId = editBtn.dataset.cartId;
        const currentName = editBtn.dataset.cartName || "";
        const nextName = prompt("Nhập tên danh mục mới:", currentName);
        if (nextName === null) return;
        const trimmedName = nextName.trim();
        if (!trimmedName) {
          showToast("Tên danh mục không được để trống", "error");
          return;
        }

        const data = await postForm({
          action: "edit_cart",
          cart_id: cartId,
          name: trimmedName,
        });
        if (!data.ok) {
          showToast(data.message || "Cap nhat danh muc that bai", "error");
          return;
        }

        showToast(data.message || "Đã cập nhật danh mục");
        loadCarts();
        return;
      }

      const deleteBtn = event.target.closest(".admin-delete-cart");
      if (!deleteBtn) return;

      const cartId = deleteBtn.dataset.cartId;
      if (!cartId || !confirm("Bạn có chắc chắn muốn xóa danh mục này?"))
        return;
      deleteBtn.disabled = true;

      const data = await postForm({ action: "delete_cart", cart_id: cartId });
      if (!data.ok) {
        deleteBtn.disabled = false;
        showToast(data.message || "Xoa danh muc that bai", "error");
        return;
      }

      showToast(data.message || "Đã xóa danh mục");
      loadCarts();
    });

    loadCarts();
  }

  const adminsPageRoot = document.getElementById("admin-admins-page");
  if (adminsPageRoot) {
    const createForm = document.getElementById("admin-create-form");
    const filtersForm = document.getElementById("admin-admins-filters");
    const tbody = document.getElementById("admin-admins-tbody");
    const pager = document.getElementById("admin-admins-pager");
    const summary = document.getElementById("admin-admins-summary");
    const bulkBtn = document.getElementById("admin-bulk-delete-admins");
    let page = 1;

    const loadAdmins = async () => {
      const formData = new FormData(filtersForm);
      const params = {
        action: "admins_list",
        page,
        search: formData.get("search") || "",
        sort: formData.get("sort") || "newest",
        limit: formData.get("limit") || "10",
      };

      tbody.innerHTML = '<tr><td colspan="7">Dang tai...</td></tr>';
      const res = await fetch(`admin_api.php?${toQueryString(params)}`);
      const data = await parseJsonResponse(res);
      if (!data.ok) {
        showToast(data.message || "Khong the tai danh sach admin", "error");
        return;
      }

      tbody.innerHTML = data.html;
      pager.innerHTML = data.pagination;
      summary.textContent = `Tong ${data.summary.total} admin - Trang ${data.summary.page}/${data.summary.total_pages}`;
      const selectAll = document.getElementById("admin-admins-select-all");
      if (selectAll) selectAll.checked = false;
    };

    createForm?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const formData = new FormData(createForm);
      const name = (formData.get("name") || "").toString().trim();
      const password = (formData.get("password") || "").toString().trim();
      if (!name || !password) {
        showToast("Vui lòng nhập đủ thông tin", "error");
        return;
      }

      const data = await postForm({ action: "create_admin", name, password });
      if (!data.ok) {
        showToast(data.message || "Tao admin that bai", "error");
        return;
      }

      showToast(data.message || "Đã tạo admin");
      createForm.reset();
      loadAdmins();
    });

    filtersForm?.addEventListener("submit", (event) => {
      event.preventDefault();
      page = 1;
      loadAdmins();
    });

    filtersForm?.addEventListener("change", () => {
      page = 1;
      loadAdmins();
    });

    bindPagination(pager, (nextPage) => {
      page = nextPage;
      loadAdmins();
    });

    bindSelectAll("#admin-admins-select-all", ".admin-bulk-admin-check");

    bulkBtn?.addEventListener("click", async () => {
      const ids = collectCheckedIds(".admin-bulk-admin-check");
      if (!ids.length) {
        showToast("Bạn chưa chọn admin nào", "error");
        return;
      }
      if (!confirm(`Xóa ${ids.length} admin đã chọn?`)) return;

      const data = await postForm({
        action: "bulk_delete_admins",
        ids: ids.join(","),
      });
      if (!data.ok) {
        showToast(data.message || "Xóa hàng loạt thất bại", "error");
        return;
      }

      showToast(data.message || "Đã xóa admin đã chọn");
      loadAdmins();
    });

    tbody.addEventListener("click", async (event) => {
      const editBtn = event.target.closest(".admin-edit-admin");
      if (editBtn) {
        const adminId = editBtn.dataset.adminId;
        const currentName = editBtn.dataset.adminName || "";
        const newName = prompt("Tên admin mới:", currentName);
        if (newName === null) return;
        const trimmedName = newName.trim();
        if (!trimmedName) {
          showToast("Tên admin không được để trống", "error");
          return;
        }
        const newPass = prompt("Mật khẩu mới (để trống nếu không đổi):", "");
        if (newPass === null) return;

        const data = await postForm({
          action: "update_admin",
          admin_id: adminId,
          name: trimmedName,
          password: newPass,
        });
        if (!data.ok) {
          showToast(data.message || "Cập nhật admin thất bại", "error");
          return;
        }

        showToast(data.message || "Đã cập nhật admin");
        loadAdmins();
        return;
      }

      const deleteBtn = event.target.closest(".admin-delete-admin");
      if (!deleteBtn) return;
      const adminId = deleteBtn.dataset.adminId;
      if (!adminId || !confirm("Bạn có chắc chắn muốn xóa admin này?")) return;
      deleteBtn.disabled = true;

      const data = await postForm({
        action: "delete_admin",
        admin_id: adminId,
      });
      if (!data.ok) {
        deleteBtn.disabled = false;
        showToast(data.message || "Xóa admin thất bại", "error");
        return;
      }

      showToast(data.message || "Đã xóa admin");
      loadAdmins();
    });

    loadAdmins();
  }

  const settingForms = document.querySelectorAll(".admin-ajax-settings-form");
  if (settingForms.length) {
    settingForms.forEach((form) => {
      form.addEventListener("submit", async (event) => {
        event.preventDefault();
        const submitBtn = form.querySelector('[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;

        try {
          const fd = new FormData(form);
          const res = await fetch(form.action || window.location.href, {
            method: "POST",
            headers: {
              Accept: "application/json",
              "X-Requested-With": "XMLHttpRequest",
            },
            body: fd,
          });
          const data = await parseJsonResponse(res);
          if (!data.ok) {
            showToast(data.message || "Lưu cấu hình thất bại", "error");
            return;
          }
          showToast(data.message || "Đã lưu cấu hình");
        } catch (err) {
          showToast("Lưu cấu hình thất bại", "error");
        } finally {
          if (submitBtn) submitBtn.disabled = false;
        }
      });
    });
  }
})();

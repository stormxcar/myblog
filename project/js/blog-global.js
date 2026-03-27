/**
 * Blog Global JavaScript
 * Xử lý các chức năng chung cho toàn bộ website
 */

document.addEventListener("DOMContentLoaded", function () {
  // Initialize all components
  initDarkMode();
  initMobileMenu();
  initUserMenu();
  initLoader();
  initNotification();
  initRealtimePostUpdates();
  initScrollToTop();
  initPostInteractionAjax();
  initSearchAutocomplete();
  // Prevent duplicate submissions on non-AJAX forms by locking editable controls.
  document.querySelectorAll("form").forEach((form) => {
    if (form.dataset.noSubmitLock === "true") {
      return;
    }

    form.addEventListener("submit", (event) => {
      if (event.defaultPrevented || !form.checkValidity()) {
        return;
      }

      const submitter = event.submitter;
      if (
        submitter instanceof HTMLElement &&
        ["like_post", "save_post"].includes(
          submitter.getAttribute("name") || "",
        )
      ) {
        return;
      }

      const submitButtons = form.querySelectorAll(
        "button[type='submit'], input[type='submit']",
      );

      submitButtons.forEach((button) => {
        button.disabled = true;
      });

      form.classList.add("is-submitting");
    });
  });
});

/**
 * Dark Mode Toggle
 */
function initDarkMode() {
  const darkModeToggle = document.querySelector(".dark-mode-toggle");
  const updateThemeIcon = () => {
    if (!darkModeToggle) return;
    const moonIcon = darkModeToggle.querySelector(".icon-theme-moon");
    const sunIcon = darkModeToggle.querySelector(".icon-theme-sun");
    const isDark = document.documentElement.classList.contains("dark");
    if (moonIcon) moonIcon.classList.toggle("hidden", isDark);
    if (sunIcon) sunIcon.classList.toggle("hidden", !isDark);
  };

  if (darkModeToggle) {
    // Load saved dark mode preference
    if (localStorage.getItem("darkMode") === "enabled") {
      document.documentElement.classList.add("dark");
    }
    updateThemeIcon();

    // Toggle dark mode
    darkModeToggle.addEventListener("click", function () {
      document.documentElement.classList.toggle("dark");

      // Save preference
      const isDark = document.documentElement.classList.contains("dark");
      localStorage.setItem("darkMode", isDark ? "enabled" : "disabled");
      updateThemeIcon();
    });
  }
}

function initRealtimePostUpdates() {
  if (typeof window.Pusher === "undefined" || !window.BLOG_PUSHER) {
    return;
  }

  const cfg = window.BLOG_PUSHER;
  if (!cfg.key || !cfg.cluster) {
    return;
  }

  try {
    const pusher = new window.Pusher(cfg.key, {
      cluster: cfg.cluster,
      forceTLS: true,
      authEndpoint: cfg.authEndpoint,
    });

    const channel = pusher.subscribe("public-site-events");
    channel.bind("post:published", function (payload) {
      const title =
        payload && payload.title ? String(payload.title) : "Bài viết mới";
      const url = payload && payload.url ? String(payload.url) : "";

      showNotification(`Có bài viết mới: ${title}`, "info");

      if (!url) return;
      const barId = "live-post-bar";
      let bar = document.getElementById(barId);

      if (!bar) {
        bar = document.createElement("div");
        bar.id = barId;
        bar.style.position = "fixed";
        bar.style.left = "50%";
        bar.style.bottom = "16px";
        bar.style.transform = "translateX(-50%)";
        bar.style.zIndex = "9999";
        bar.style.background = "#0f172a";
        bar.style.color = "#fff";
        bar.style.padding = "10px 14px";
        bar.style.borderRadius = "999px";
        bar.style.boxShadow = "0 10px 30px rgba(2,6,23,.35)";
        bar.style.fontSize = "13px";
        document.body.appendChild(bar);
      }

      bar.innerHTML = `Bài viết mới đã đăng <a href="${url}" style="color:#67e8f9;font-weight:700;margin-left:8px;text-decoration:none;">Xem ngay</a>`;
    });
  } catch (error) {
    // Best effort only.
  }
}

/**
 * Mobile Menu Toggle
 */
function initMobileMenu() {
  const mobileMenuToggle = document.querySelector(".mobile-menu-toggle");
  const mobileMenu = document.querySelector(".mobile-menu");

  if (mobileMenuToggle && mobileMenu) {
    mobileMenuToggle.addEventListener("click", function () {
      mobileMenu.classList.toggle("hidden");
    });

    // Close mobile menu when clicking outside
    document.addEventListener("click", function (event) {
      if (
        !mobileMenuToggle.contains(event.target) &&
        !mobileMenu.contains(event.target)
      ) {
        mobileMenu.classList.add("hidden");
      }
    });
  }
}

/**
 * User Dropdown Menu
 */
function initUserMenu() {
  const userMenuTrigger = document.querySelector(".user-menu button");
  const userDropdown = document.querySelector(".user-dropdown");

  if (userMenuTrigger && userDropdown) {
    userMenuTrigger.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      userDropdown.classList.toggle("hidden");
    });

    // Close dropdown when clicking outside
    document.addEventListener("click", function (event) {
      const userMenu = document.querySelector(".user-menu");
      if (userMenu && !userMenu.contains(event.target)) {
        userDropdown.classList.add("hidden");
      }
    });

    // Close dropdown when pressing Escape
    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape") {
        userDropdown.classList.add("hidden");
      }
    });
  }
}

/**
 * Page Loader
 */
function initLoader() {
  const loader = document.getElementById("loader-wrapper");

  if (loader) {
    // Hide loader when page is loaded
    window.addEventListener("load", function () {
      setTimeout(function () {
        loader.classList.add("is-hidden");
        setTimeout(function () {
          loader.setAttribute("aria-hidden", "true");
          loader.style.display = "none";
        }, 360);
      }, 420);
    });

    // Fallback: never keep loader forever when external assets are slow.
    setTimeout(function () {
      if (loader.style.display !== "none") {
        loader.classList.add("is-hidden");
        setTimeout(function () {
          loader.setAttribute("aria-hidden", "true");
          loader.style.display = "none";
        }, 360);
      }
    }, 4500);
  }
}

/**
 * Notification System
 */
function initNotification() {
  // Show notifications from PHP sessions
  const urlParams = new URLSearchParams(window.location.search);
  const message = urlParams.get("message");

  if (message) {
    showNotification(decodeURIComponent(message), "success");
    return;
  }

  if (window.BLOG_FLASH_MESSAGE) {
    showNotification(
      window.BLOG_FLASH_MESSAGE,
      window.BLOG_FLASH_TYPE || "info",
    );
    window.BLOG_FLASH_MESSAGE = "";
    window.BLOG_FLASH_TYPE = "";
  }
}

function showNotification(message, type = "info") {
  if (window.GooeyToast && typeof window.GooeyToast.show === "function") {
    window.GooeyToast.show(String(message || ""), type || "info", 4400);
  } else {
    const colors = {
      success: "#10B981",
      error: "#EF4444",
      warning: "#F59E0B",
      info: "#3B82F6",
    };

    let box = document.getElementById("global-toast-fallback");
    if (!box) {
      box = document.createElement("div");
      box.id = "global-toast-fallback";
      box.style.position = "fixed";
      box.style.top = "16px";
      box.style.right = "16px";
      box.style.zIndex = "9999";
      box.style.padding = "12px 16px";
      box.style.borderRadius = "10px";
      box.style.color = "#fff";
      box.style.boxShadow = "0 10px 24px rgba(0,0,0,0.18)";
      box.style.maxWidth = "320px";
      box.style.fontSize = "14px";
      document.body.appendChild(box);
    }

    box.textContent = message;
    box.style.background = colors[type] || colors.info;
    box.style.display = "block";

    clearTimeout(window.__fallbackToastTimer);
    window.__fallbackToastTimer = setTimeout(() => {
      if (box) box.style.display = "none";
    }, 2600);
  }
}

function getNotificationClass(type) {
  const classes = {
    success: "bg-green-500 text-white",
    error: "bg-red-500 text-white",
    warning: "bg-yellow-500 text-white",
    info: "bg-blue-500 text-white",
  };

  return classes[type] || classes["info"];
}

/**
 * Scroll to Top Button
 */
function initScrollToTop() {
  const scrollBtn = document.createElement("button");
  scrollBtn.innerHTML = '<i class="fas fa-arrow-up"></i>';
  scrollBtn.className =
    "fixed bottom-6 right-6 w-12 h-12 bg-main text-white rounded-full shadow-lg hover:bg-main/80 transition-all duration-300 z-30 hidden";
  scrollBtn.id = "scrollToTop";

  document.body.appendChild(scrollBtn);

  // Show/hide scroll button
  window.addEventListener("scroll", function () {
    if (window.pageYOffset > 300) {
      scrollBtn.classList.remove("hidden");
    } else {
      scrollBtn.classList.add("hidden");
    }
  });

  // Scroll to top when clicked
  scrollBtn.addEventListener("click", function () {
    window.scrollTo({
      top: 0,
      behavior: "smooth",
    });
  });
}

/**
 * Form Validation Helpers
 */
function validateEmail(email) {
  const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return re.test(email);
}

function validateRequired(value) {
  return value.trim() !== "";
}

/**
 * Image Lazy Loading
 */
function initLazyLoading() {
  const images = document.querySelectorAll("img[data-src]");
  const imageObserver = new IntersectionObserver((entries, observer) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        const img = entry.target;
        img.src = img.dataset.src;
        img.removeAttribute("data-src");
        imageObserver.unobserve(img);
      }
    });
  });

  images.forEach((img) => imageObserver.observe(img));
}

/**
 * Smooth Scrolling for Anchor Links
 */
function initSmoothScrolling() {
  const links = document.querySelectorAll('a[href^="#"]');

  links.forEach((link) => {
    link.addEventListener("click", function (e) {
      e.preventDefault();

      const targetId = this.getAttribute("href").substring(1);
      const targetElement = document.getElementById(targetId);

      if (targetElement) {
        targetElement.scrollIntoView({
          behavior: "smooth",
          block: "start",
        });
      }
    });
  });
}

/**
 * Initialize additional features
 */
document.addEventListener("DOMContentLoaded", function () {
  initLazyLoading();
  initSmoothScrolling();
});

function initSearchAutocomplete() {
  const forms = document.querySelectorAll("[data-search-autocomplete]");
  if (!forms.length) return;

  const storageKey = "recentSearches";
  const suggestUrl = window.SEARCH_SUGGEST_URL || "search_suggest.php";

  const getRecentSearches = () => {
    try {
      const raw = localStorage.getItem(storageKey);
      const parsed = raw ? JSON.parse(raw) : [];
      return Array.isArray(parsed) ? parsed : [];
    } catch {
      return [];
    }
  };

  const saveRecentSearch = (query) => {
    const q = (query || "").trim();
    if (q.length < 2) return;
    const existing = getRecentSearches().filter(
      (x) => x.toLowerCase() !== q.toLowerCase(),
    );
    existing.unshift(q);
    localStorage.setItem(storageKey, JSON.stringify(existing.slice(0, 8)));
  };

  const debounce = (fn, delay = 260) => {
    let t;
    return (...args) => {
      clearTimeout(t);
      t = setTimeout(() => fn(...args), delay);
    };
  };

  const escapeHtml = (str) =>
    String(str || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;")
      .replace(/'/g, "&#39;");

  const escapeRegExp = (str) =>
    String(str || "").replace(/[.*+?^${}()|[\]\\]/g, "\\$&");

  const highlightKeyword = (text, keyword) => {
    const safeText = escapeHtml(text);
    const q = String(keyword || "").trim();
    if (q.length < 2) return safeText;
    const re = new RegExp(`(${escapeRegExp(q)})`, "ig");
    return safeText.replace(
      re,
      '<mark class="bg-yellow-200/90 dark:bg-yellow-600/80 text-inherit rounded px-1">$1</mark>',
    );
  };

  forms.forEach((form) => {
    const input = form.querySelector("[data-search-input]");
    const dropdown = form.querySelector("[data-search-dropdown]");
    if (!input || !dropdown) return;

    let activeIndex = -1;

    const setActiveItem = (nextIndex) => {
      const items = Array.from(
        dropdown.querySelectorAll("[data-suggest-item]"),
      );
      if (!items.length) {
        activeIndex = -1;
        return;
      }

      activeIndex = nextIndex;
      if (activeIndex < 0) activeIndex = items.length - 1;
      if (activeIndex >= items.length) activeIndex = 0;

      items.forEach((item, idx) => {
        if (idx === activeIndex) {
          item.classList.add(
            "bg-blue-50",
            "dark:bg-gray-700",
            "ring-2",
            "ring-main",
            "ring-inset",
          );
          item.scrollIntoView({ block: "nearest" });
        } else {
          item.classList.remove(
            "bg-blue-50",
            "dark:bg-gray-700",
            "ring-2",
            "ring-main",
            "ring-inset",
          );
        }
      });
    };

    const openDropdown = () => {
      dropdown.classList.remove("hidden");
      dropdown.classList.add("block");
      dropdown.style.display = "block";
    };

    const closeDropdown = () => {
      dropdown.classList.add("hidden");
      dropdown.classList.remove("block");
      dropdown.style.display = "";
      dropdown.innerHTML = "";
      activeIndex = -1;
    };

    const renderRecent = () => {
      const recent = getRecentSearches();
      if (!recent.length) {
        closeDropdown();
        return;
      }

      dropdown.innerHTML = `
        <div class="px-3 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">Tìm kiếm gần đây</div>
        ${recent
          .map(
            (item) => `
          <button type="button" data-recent-item="${encodeURIComponent(item)}" data-suggest-item="recent" class="w-full text-left px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
            <i class="fas fa-history mr-2 text-gray-400"></i>${item}
          </button>
        `,
          )
          .join("")}
      `;
      openDropdown();
      activeIndex = -1;
    };

    const renderSuggestions = (items, query) => {
      if (!items.length) {
        dropdown.innerHTML =
          '<div class="px-3 py-3 text-sm text-gray-500 dark:text-gray-400">Không có gợi ý phù hợp</div>';
        openDropdown();
        return;
      }

      dropdown.innerHTML = items
        .map(
          (item) => `
          <a href="${item.url}" data-suggest-item="suggest" class="flex items-center gap-3 px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-700 border-b border-gray-100 dark:border-gray-700/40 last:border-b-0">
            <img src="${item.image}" alt="${escapeHtml(item.title)}" class="w-10 h-10 rounded object-cover" />
            <span class="text-sm text-gray-700 dark:text-gray-200 line-clamp-2">${highlightKeyword(item.title, query)}</span>
          </a>
        `,
        )
        .join("");
      openDropdown();
      activeIndex = -1;
    };

    const fetchSuggestions = debounce(async (query) => {
      const q = (query || "").trim();
      if (q.length < 2) {
        renderRecent();
        return;
      }

      try {
        const encodedBaseUrl = encodeURI(suggestUrl);
        const url = `${encodedBaseUrl}${encodedBaseUrl.includes("?") ? "&" : "?"}q=${encodeURIComponent(q)}`;
        const res = await fetch(url, {
          headers: { "X-Requested-With": "XMLHttpRequest" },
        });
        if (!res.ok) {
          throw new Error("suggest endpoint failed");
        }
        const data = await res.json();
        renderSuggestions(
          Array.isArray(data.items) ? data.items : [],
          data.query || q,
        );
      } catch {
        try {
          const fallbackUrl = `search_suggest.php?q=${encodeURIComponent(q)}`;
          const fallbackRes = await fetch(fallbackUrl, {
            headers: { "X-Requested-With": "XMLHttpRequest" },
          });
          const fallbackData = await fallbackRes.json();
          renderSuggestions(
            Array.isArray(fallbackData.items) ? fallbackData.items : [],
            fallbackData.query || q,
          );
        } catch {
          dropdown.innerHTML =
            '<div class="px-3 py-3 text-sm text-gray-500 dark:text-gray-400">Không thể tải gợi ý lúc này</div>';
          openDropdown();
        }
      }
    }, 280);

    input.addEventListener("focus", () => {
      if (!input.value.trim()) renderRecent();
    });

    input.addEventListener("input", (e) => {
      fetchSuggestions(e.target.value);
    });

    input.addEventListener("keydown", (e) => {
      if (dropdown.classList.contains("hidden")) return;

      const items = Array.from(
        dropdown.querySelectorAll("[data-suggest-item]"),
      );
      if (!items.length) return;

      if (e.key === "ArrowDown") {
        e.preventDefault();
        setActiveItem(activeIndex + 1);
        return;
      }

      if (e.key === "ArrowUp") {
        e.preventDefault();
        setActiveItem(activeIndex - 1);
        return;
      }

      if (e.key === "Escape") {
        closeDropdown();
        return;
      }

      if (e.key === "Enter" && activeIndex >= 0) {
        e.preventDefault();
        items[activeIndex].click();
      }
    });

    form.addEventListener("submit", () => {
      saveRecentSearch(input.value);
      closeDropdown();
    });

    dropdown.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-recent-item]");
      if (!btn) return;
      const keyword = decodeURIComponent(
        btn.getAttribute("data-recent-item") || "",
      );
      input.value = keyword;
      form.submit();
    });

    document.addEventListener("click", (e) => {
      if (!form.contains(e.target)) closeDropdown();
    });
  });
}

function initPostInteractionAjax() {
  let lastSubmitter = null;

  const endpointCandidates = (type) => {
    const candidates = [];

    if (window.BLOG_ENDPOINTS && window.BLOG_ENDPOINTS[type]) {
      candidates.push(window.BLOG_ENDPOINTS[type]);
    }

    const file = type === "like" ? "like_post.php" : "save_post.php";
    candidates.push(
      `../components/${file}`,
      `components/${file}`,
      `/components/${file}`,
      `/project/components/${file}`,
    );

    return [...new Set(candidates.filter(Boolean))];
  };

  const postWithFallback = async (type, formData) => {
    const urls = endpointCandidates(type);
    let lastError = null;

    for (const url of urls) {
      try {
        const response = await fetch(url, {
          method: "POST",
          body: formData,
          headers: {
            "X-Requested-With": "XMLHttpRequest",
          },
        });

        if (!response.ok) {
          lastError = new Error("request failed");
          continue;
        }

        const contentType = (
          response.headers.get("content-type") || ""
        ).toLowerCase();
        if (!contentType.includes("application/json")) {
          const text = await response.text();
          lastError = new Error(
            `invalid-json-response:${url}:${text.slice(0, 160)}`,
          );
          continue;
        }

        const payload = await response.json();
        return payload;
      } catch (error) {
        lastError = error;
      }
    }

    throw lastError || new Error("no endpoint available");
  };

  const setLoadingState = (button, loading) => {
    if (!button) return;
    const icon = button.querySelector("i");

    if (loading) {
      button.dataset.originalIcon = icon ? icon.className : "";
      button.disabled = true;
      if (icon) icon.className = "fas fa-spinner fa-spin";
      return;
    }

    button.disabled = false;
    if (icon && button.dataset.originalIcon) {
      icon.className = button.dataset.originalIcon;
      delete button.dataset.originalIcon;
    }
  };

  const replaceFirstNumber = (text, nextNumber) => {
    if (/^\s*\d+\s*$/.test(text)) return String(nextNumber);
    if (/\d+/.test(text)) return text.replace(/\d+/, String(nextNumber));
    return String(nextNumber);
  };

  const updateLikeUI = (button, payload) => {
    const liked = !!payload.liked;
    const icon = button.querySelector("i");
    const countNode = button.querySelector("span");

    if (icon) {
      icon.classList.toggle("text-red-500", liked);
    }

    if (countNode && typeof payload.like_count === "number") {
      countNode.textContent = replaceFirstNumber(
        countNode.textContent || "",
        payload.like_count,
      );
    }

    button.classList.toggle("text-red-500", liked);
  };

  const updateSaveUI = (button, payload) => {
    const saved = !!payload.saved;
    const icon = button.querySelector("i");
    if (!icon) return;

    icon.classList.toggle("text-yellow-500", saved);
    icon.classList.toggle("text-gray-400", !saved);
    icon.classList.toggle("group-hover:text-yellow-500", !saved);

    if (!saved) {
      icon.classList.remove("text-main");
    }

    // Some legacy templates still set inline colors; keep UI in sync explicitly.
    icon.style.color = saved ? "#eab308" : "";
  };

  document.addEventListener("submit", async (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;

    const submitter =
      e.submitter ||
      form.__lastSubmitter ||
      lastSubmitter ||
      document.activeElement;
    if (!(submitter instanceof HTMLElement)) return;

    const isLike = submitter.getAttribute("name") === "like_post";
    const isSave = submitter.getAttribute("name") === "save_post";
    if (!isLike && !isSave) return;

    e.preventDefault();

    const button = submitter;
    const type = isLike ? "like" : "save";
    const formData = new FormData(form);
    if (isLike && !formData.has("like_post")) formData.append("like_post", "1");
    if (isSave && !formData.has("save_post")) formData.append("save_post", "1");

    setLoadingState(button, true);

    try {
      const payload = await postWithFallback(type, formData);
      if (!payload || payload.ok !== true) {
        if (payload && payload.login_required && payload.login_url) {
          showNotification(payload.message || "Bạn cần đăng nhập", "warning");
          setTimeout(() => {
            window.location.href = payload.login_url;
          }, 500);
          return;
        }

        const debugSuffix =
          window.BLOG_DEBUG_ENDPOINTS && payload && payload.debug
            ? `\n[Debug] ${payload.debug}`
            : "";

        showNotification(
          ((payload && payload.message) || "Không thể xử lý yêu cầu") +
            debugSuffix,
          "error",
        );
        return;
      }

      if (isLike) {
        updateLikeUI(button, payload);
      } else {
        updateSaveUI(button, payload);
      }

      showNotification(payload.message || "Thao tác thành công", "success");
    } catch (error) {
      const details = (error && error.message) || "";
      const isInvalidJson = details.includes("invalid-json-response:");
      const debugSuffix =
        window.BLOG_DEBUG_ENDPOINTS && details
          ? `\n[Debug] ${details.slice(0, 220)}`
          : "";
      showNotification(
        isInvalidJson
          ? "Endpoint phản hồi không hợp lệ (không phải JSON)." + debugSuffix
          : "Có lỗi kết nối, vui lòng thử lại" + debugSuffix,
        "error",
      );
    } finally {
      setLoadingState(button, false);
    }
  });

  document.addEventListener(
    "click",
    (e) => {
      const btn = e.target.closest(
        'button[name="like_post"], button[name="save_post"]',
      );
      if (!(btn instanceof HTMLElement)) return;
      lastSubmitter = btn;
      const ownerForm = btn.closest("form");
      if (ownerForm) {
        ownerForm.__lastSubmitter = btn;
      }
    },
    true,
  );
}

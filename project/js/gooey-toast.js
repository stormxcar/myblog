(function () {
  if (window.GooeyToast) {
    return;
  }

  const rootId = "gooey-toast-root";

  function getRoot() {
    let root = document.getElementById(rootId);
    if (!root) {
      root = document.createElement("div");
      root.id = rootId;
      root.className = "gooey-toast-root";
      document.body.appendChild(root);
    }
    return root;
  }

  function removeToast(el) {
    if (!el) return;
    el.classList.add("is-leaving");
    setTimeout(() => {
      if (el && el.parentNode) {
        el.parentNode.removeChild(el);
      }
    }, 240);
  }

  function show(message, type, duration) {
    const root = getRoot();
    const safeType = ["success", "error", "warning", "info"].includes(type)
      ? type
      : "info";

    const toast = document.createElement("div");
    toast.className = "gooey-toast gooey-toast--" + safeType;

    const row = document.createElement("div");
    row.className = "gooey-toast__row";

    const text = document.createElement("div");
    text.textContent = String(message || "");

    const closeBtn = document.createElement("button");
    closeBtn.type = "button";
    closeBtn.className = "gooey-toast__close";
    closeBtn.innerHTML = "&times;";
    closeBtn.setAttribute("aria-label", "Close toast");
    closeBtn.addEventListener("click", function () {
      removeToast(toast);
    });

    row.appendChild(text);
    row.appendChild(closeBtn);
    toast.appendChild(row);
    root.appendChild(toast);

    const timeout = Number(duration || 4200);
    setTimeout(() => removeToast(toast), timeout);
  }

  window.GooeyToast = { show };

  // Unified global API for all existing pages
  window.showNotification = function (message, type) {
    show(message, type || "info", 4400);
  };

  // Backward compatibility for existing Toastify/toastify usage.
  window.Toastify = function (opts) {
    const options = opts || {};
    const text = options.text || "";
    const duration = Number(options.duration || 4200);

    let type = "info";
    const styleBg = (
      options.style && options.style.background
        ? String(options.style.background)
        : ""
    ).toLowerCase();

    if (
      styleBg.includes("#10b981") ||
      styleBg.includes("16a34a") ||
      styleBg.includes("00b09b")
    ) {
      type = "success";
    } else if (
      styleBg.includes("#ef4444") ||
      styleBg.includes("dc2626") ||
      styleBg.includes("ff5f6d")
    ) {
      type = "error";
    } else if (
      styleBg.includes("#f59e0b") ||
      styleBg.includes("d97706") ||
      styleBg.includes("ffc371")
    ) {
      type = "warning";
    }

    return {
      showToast: function () {
        show(text, type, duration);
      },
    };
  };

  window.toastify = window.Toastify;
})();

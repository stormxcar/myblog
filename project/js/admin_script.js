document.addEventListener("DOMContentLoaded", function () {
  const body = document.body;
  const header = document.querySelector(".header");
  const sidebarToggleBtn = document.querySelector("#sidebar-toggle-btn");
  const sidebarToggleIcon = document.querySelector("#sidebar-toggle-icon");
  const btnAddModal = document.querySelector(".add_tag_btn");
  const addTagModal = document.querySelector("#modal_add_tag");
  const btnEditModals = document.querySelectorAll(".edit_btn");
  const cancelBtnEdits = document.querySelectorAll(".cancel_edit");
  const cancelBtnAdd = document.querySelector(".cancel_add");
  let currentModalId = null;

  function applyAdminTableLabels(scope = document) {
    const tables = scope.querySelectorAll(".admin-table");
    tables.forEach((table) => {
      const headerCells = Array.from(table.querySelectorAll("thead th"));
      if (!headerCells.length) {
        return;
      }

      const labels = headerCells.map((cell, index) => {
        const text = (cell.textContent || "").replace(/\s+/g, " ").trim();
        return text !== "" ? text : `Cot ${index + 1}`;
      });

      table.querySelectorAll("tbody tr").forEach((row) => {
        const cells = row.querySelectorAll("td");
        cells.forEach((td, idx) => {
          if (td.hasAttribute("colspan")) {
            td.setAttribute("data-label", "Thong bao");
          } else {
            td.setAttribute("data-label", labels[idx] || `Cot ${idx + 1}`);
          }
        });
      });
    });
  }

  function bindBulkCheckAll(scope = document) {
    const wrappers = scope.querySelectorAll("[data-bulk-check-all]");
    wrappers.forEach((checkAll) => {
      const itemSelector = checkAll.getAttribute("data-target-selector");
      if (!itemSelector) {
        return;
      }

      if (checkAll.dataset.boundChange === "1") {
        return;
      }

      checkAll.dataset.boundChange = "1";
      checkAll.addEventListener("change", () => {
        const rows = scope.querySelectorAll(itemSelector);
        rows.forEach((row) => {
          row.checked = checkAll.checked;
        });
      });
    });
  }

  applyAdminTableLabels();
  bindBulkCheckAll();

  // Navbar toggle
  const menuBtn = document.querySelector("#menu-btn");

  function applySidebarCollapsedState(collapsed) {
    body.classList.toggle("sidebar-collapsed", collapsed);
    if (header) {
      header.classList.toggle("sidebar-collapsed", collapsed);
    }
    if (sidebarToggleBtn) {
      sidebarToggleBtn.setAttribute(
        "aria-pressed",
        collapsed ? "true" : "false",
      );
    }
    if (sidebarToggleIcon) {
      sidebarToggleIcon.classList.toggle("fa-arrow-left", !collapsed);
      sidebarToggleIcon.classList.toggle("fa-arrow-right", collapsed);
    }
  }

  if (sidebarToggleBtn) {
    const savedState =
      window.localStorage.getItem("admin_sidebar_collapsed") === "1";
    if (window.innerWidth > 991) {
      applySidebarCollapsedState(savedState);
    }

    sidebarToggleBtn.addEventListener("click", () => {
      if (window.innerWidth <= 991) {
        if (menuBtn) {
          menuBtn.click();
        }
        return;
      }

      const next = !body.classList.contains("sidebar-collapsed");
      applySidebarCollapsedState(next);
      window.localStorage.setItem("admin_sidebar_collapsed", next ? "1" : "0");
    });
  }

  window.addEventListener("resize", () => {
    if (window.innerWidth <= 991) {
      applySidebarCollapsedState(false);
      return;
    }

    if (header) {
      header.classList.remove("active");
    }
    body.classList.remove("padding-left-35rem");

    const savedState =
      window.localStorage.getItem("admin_sidebar_collapsed") === "1";
    applySidebarCollapsedState(savedState);
  });

  if (window.innerWidth > 991) {
    if (header) {
      header.classList.remove("active");
    }
    body.classList.remove("padding-left-35rem");
  }

  if (menuBtn && header) {
    menuBtn.onclick = () => {
      header.classList.toggle("active");
      body.classList.toggle("padding-left-35rem");
    };
  }

  // Limit content length
  document.querySelectorAll(".posts-content").forEach((content) => {
    if (content.innerHTML.length > 100) {
      content.innerHTML = content.innerHTML.slice(0, 100);
    }
  });

  // Modal handling
  if (btnAddModal && addTagModal) {
    btnAddModal.addEventListener("click", (e) => {
      e.preventDefault();
      toggleModal(addTagModal, true);
    });
  }

  if (cancelBtnAdd && addTagModal) {
    cancelBtnAdd.addEventListener("click", () => {
      toggleModal(addTagModal, false);
    });
  }

  btnEditModals.forEach((btnEditModal) => {
    btnEditModal.addEventListener("click", (e) => {
      e.preventDefault();
      const cartId = e.target.getAttribute("data-cart-id");
      const cartName = e.target.getAttribute("data-cart-name");
      const editTagModal = document.querySelector("#modal_edit_tag_" + cartId);
      if (!editTagModal) {
        return;
      }
      const nameInput = editTagModal.querySelector("input[name='name']");
      if (!nameInput) {
        return;
      }
      nameInput.value = cartName;
      toggleModal(editTagModal, true);
      currentModalId = cartId;
    });
  });

  cancelBtnEdits.forEach((cancelBtn) => {
    cancelBtn.addEventListener("click", () => {
      if (currentModalId !== null) {
        const currentModal = document.querySelector(
          "#modal_edit_tag_" + currentModalId,
        );
        if (currentModal) {
          toggleModal(currentModal, false);
        }
      }
    });
  });

  function toggleModal(modal, show) {
    if (!modal) {
      return;
    }

    if (show) {
      modal.classList.add("showTag");
      body.style.background = "rgba(0, 0, 0, 0.3)";
    } else {
      modal.classList.remove("showTag");
      body.style.background = "initial";
    }
  }

  async function fetchAdminSection(url, replaceHistory = true) {
    const currentContainer = document.querySelector(".ui-container");
    if (!currentContainer) {
      window.location.href = url;
      return;
    }

    const urlObj = new URL(url, window.location.origin);
    urlObj.searchParams.set("admin_ajax", "1");

    try {
      const response = await fetch(urlObj.toString(), {
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      });

      if (!response.ok) {
        throw new Error("request failed");
      }

      const html = await response.text();
      const parser = new DOMParser();
      const nextDoc = parser.parseFromString(html, "text/html");
      const nextContainer = nextDoc.querySelector(".ui-container");

      if (!nextContainer) {
        throw new Error("container not found");
      }

      currentContainer.replaceWith(nextContainer);

      if (replaceHistory) {
        const cleanUrl = new URL(url, window.location.origin);
        cleanUrl.searchParams.delete("admin_ajax");
        window.history.pushState({ adminAjax: true }, "", cleanUrl.toString());
      }

      applyAdminTableLabels(document);
      bindBulkCheckAll(document);
      window.scrollTo({ top: 0, behavior: "smooth" });
    } catch (error) {
      window.location.href = url;
    }
  }

  async function postAdminSection(form) {
    const currentContainer = document.querySelector(".ui-container");
    if (!currentContainer) {
      form.submit();
      return;
    }

    const action = form.getAttribute("action") || window.location.pathname;
    const target = new URL(action, window.location.origin);
    target.searchParams.set("admin_ajax", "1");

    try {
      const response = await fetch(target.toString(), {
        method: "POST",
        body: new FormData(form),
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      });

      if (!response.ok) {
        throw new Error("request failed");
      }

      const html = await response.text();
      const parser = new DOMParser();
      const nextDoc = parser.parseFromString(html, "text/html");
      const nextContainer = nextDoc.querySelector(".ui-container");

      if (!nextContainer) {
        throw new Error("container not found");
      }

      currentContainer.replaceWith(nextContainer);
      const cleanUrl = new URL(window.location.href);
      cleanUrl.searchParams.delete("admin_ajax");
      window.history.replaceState({ adminAjax: true }, "", cleanUrl.toString());

      applyAdminTableLabels(document);
      bindBulkCheckAll(document);
      window.scrollTo({ top: 0, behavior: "smooth" });
    } catch (error) {
      form.submit();
    }
  }

  document.addEventListener("submit", (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) {
      return;
    }

    if (!form.matches("[data-admin-ajax-form]")) {
      if (!form.matches("[data-admin-ajax-post-form]")) {
        return;
      }

      event.preventDefault();
      postAdminSection(form);
      return;
    }

    event.preventDefault();
    const action = form.getAttribute("action") || window.location.pathname;
    const query = new URLSearchParams(new FormData(form));
    const target = `${action}?${query.toString()}`;
    fetchAdminSection(target, true);
  });

  document.addEventListener("click", (event) => {
    const link = event.target.closest("a[data-admin-ajax-link]");
    if (link instanceof HTMLAnchorElement) {
      const href = link.getAttribute("href");
      if (!href || href.startsWith("#") || href.startsWith("javascript:")) {
        return;
      }

      event.preventDefault();
      fetchAdminSection(href, true);
      return;
    }

    const refreshBtn = event.target.closest("[data-admin-refresh]");
    if (!refreshBtn) {
      return;
    }

    event.preventDefault();
    fetchAdminSection(window.location.href, false);
  });

  window.addEventListener("popstate", () => {
    const activeForm = document.querySelector("form[data-admin-ajax-form]");
    if (!activeForm) {
      return;
    }
    fetchAdminSection(window.location.href, false);
  });

  // Tab handling
  function openPage(pageName, elmnt, color) {
    const tabcontent = document.getElementsByClassName("tabcontent");
    for (let i = 0; i < tabcontent.length; i++) {
      tabcontent[i].style.display = "none";
    }

    const tablinks = document.getElementsByClassName("tablink");
    for (let i = 0; i < tablinks.length; i++) {
      tablinks[i].style.backgroundColor = "";
    }

    document.getElementById(pageName).style.display = "block";
    elmnt.style.backgroundColor = color;
  }

  const defaultTab = document.getElementById("defaultOpen");
  if (defaultTab) {
    defaultTab.click();
  }

  // Lock form controls on valid submit to avoid duplicate writes in admin pages.
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
        if (submitter && button === submitter) {
          return;
        }
        button.disabled = true;
      });

      form.classList.add("is-submitting");
    });
  });
});

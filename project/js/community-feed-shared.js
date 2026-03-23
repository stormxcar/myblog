(function () {
  if (window.CommunityFeedShared) {
    return;
  }

  const REASONS = [
    "Spam hoặc quảng cáo",
    "Nội dung quấy rối / thù ghét",
    "Thông tin sai lệch",
    "Nội dung bạo lực / phản cảm",
    "Vi phạm bản quyền",
    "Lừa đảo hoặc mạo danh",
  ];

  // Add animation styles for vote buttons and vote group
  (function addVoteAnimationStyles() {
    if (document.getElementById("community-vote-animation-style")) {
      return;
    }
    const style = document.createElement("style");
    style.id = "community-vote-animation-style";
    style.textContent = `
      @keyframes community-vote-pop {
        0% { transform: scale(1); }
        50% { transform: scale(1.2); }
        100% { transform: scale(1); }
      }
      .community-vote-pop {
        animation: community-vote-pop 0.35s ease-out;
      }
      @keyframes community-vote-glow {
        0% { box-shadow: 0 0 0 0 rgba(34,197,94,0.5); }
        50% { box-shadow: 0 0 12px 5px rgba(34,197,94,0.35); }
        100% { box-shadow: 0 0 0 0 rgba(34,197,94,0); }
      }
      .community-vote-glow {
        animation: community-vote-glow 0.7s ease-out;
      }
      .community-vote-group-active{
        box-shadow: 0 0 16px rgba(34,197,94,0.35);
      }
    `;
    document.head.appendChild(style);
  })();

  (function addCommunityCarouselStyles() {
    if (document.getElementById("community-carousel-shared-style")) {
      return;
    }
    const style = document.createElement("style");
    style.id = "community-carousel-shared-style";
    style.textContent = `
      .community-carousel{position:relative}
      .community-carousel-track{display:flex;width:100%;transition:transform .35s ease}
      .community-carousel-slide{position:relative;min-width:100%}
      .community-carousel-nav{position:absolute;top:50%;transform:translateY(-50%);width:36px;height:36px;border-radius:999px;border:1px solid rgba(255,255,255,.55);background:rgba(15,23,42,.55);color:#fff;z-index:4;transition:background-color .2s ease}
      .community-carousel-nav:hover{background:rgba(15,23,42,.85)}
      .community-carousel-nav.is-prev{left:10px}
      .community-carousel-nav.is-next{right:10px}
      .community-carousel-dots{position:absolute;left:50%;bottom:10px;transform:translateX(-50%);display:inline-flex;align-items:center;gap:6px;z-index:4;padding:4px 8px;border-radius:999px;background:rgba(2,6,23,.45)}
      .community-carousel-dot{width:8px;height:8px;border-radius:999px;background:rgba(255,255,255,.6);transition:transform .2s ease,background-color .2s ease}
      .community-carousel-dot.is-active{background:#fff;transform:scale(1.18)}
    `;
    document.head.appendChild(style);
  })();

  function ensureReportModal() {
    let modal = document.getElementById("community-report-modal");
    if (modal) {
      return modal;
    }

    const style = document.createElement("style");
    style.textContent =
      ".community-report-tag{border:1px solid #cbd5e1;border-radius:9999px;padding:.45rem .8rem;font-size:.84rem;}" +
      ".community-report-tag.is-active{background:#e0f2fe;border-color:#38bdf8;color:#0c4a6e;}";
    document.head.appendChild(style);

    modal = document.createElement("div");
    modal.id = "community-report-modal";
    modal.className =
      "hidden fixed inset-0 z-[10060] bg-black/50 p-4 flex items-center justify-center overflow-auto";
    modal.style.padding = "1.5rem";
    modal.innerHTML =
      '<div class="w-full max-w-xl max-h-[85vh] bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-2xl p-5 sm:p-6 overflow-y-auto">' +
      '<h3 class="text-lg font-bold text-gray-900 dark:text-white">Chọn lý do báo cáo</h3>' +
      '<p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Hãy chọn lý do phù hợp nhất cho bài viết này.</p>' +
      '<div id="community-report-reason-tags" class="mt-4 flex flex-wrap gap-2"></div>' +
      '<label class="mt-4 block text-sm font-semibold text-gray-800 dark:text-gray-200">Hoặc nhập lý do khác</label>' +
      '<textarea id="community-report-custom-reason" rows="3" maxlength="1000" class="form-textarea mt-2" placeholder="Nhập lý do bạn muốn báo cáo..."></textarea>' +
      '<div class="mt-5 flex items-center justify-end gap-2">' +
      '<button type="button" id="community-report-cancel" class="px-4 py-2 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">Hủy</button>' +
      '<button type="button" id="community-report-confirm" class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700">Gửi báo cáo</button>' +
      "</div>";

    document.body.appendChild(modal);
    return modal;
  }

  function openReportReasonPicker() {
    const modal = ensureReportModal();
    const tagsWrap = modal.querySelector("#community-report-reason-tags");
    const customInput = modal.querySelector("#community-report-custom-reason");
    const cancelBtn = modal.querySelector("#community-report-cancel");
    const confirmBtn = modal.querySelector("#community-report-confirm");

    tagsWrap.innerHTML = "";
    customInput.value = "";
    let activeReason = "";

    REASONS.forEach(function (reason) {
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = "community-report-tag";
      btn.textContent = reason;
      btn.addEventListener("click", function () {
        activeReason = reason;
        customInput.value = "";
        tagsWrap
          .querySelectorAll(".community-report-tag")
          .forEach(function (tagBtn) {
            tagBtn.classList.remove("is-active");
          });
        btn.classList.add("is-active");
      });
      tagsWrap.appendChild(btn);
    });

    modal.classList.remove("hidden");

    return new Promise(function (resolve) {
      const cleanup = function () {
        modal.classList.add("hidden");
        confirmBtn.disabled = false;
        confirmBtn.textContent = "Gửi báo cáo";
        cancelBtn.removeEventListener("click", onCancel);
        confirmBtn.removeEventListener("click", onConfirm);
      };

      const onCancel = function () {
        cleanup();
        resolve(null);
      };

      const onConfirm = function () {
        const customReason = String(customInput.value || "").trim();
        const reason = customReason || activeReason;
        if (!reason) {
          if (typeof showNotification === "function") {
            showNotification(
              "Vui lòng chọn hoặc nhập lý do báo cáo.",
              "warning",
            );
          }
          return;
        }
        cleanup();
        resolve(reason);
      };

      cancelBtn.addEventListener("click", onCancel);
      confirmBtn.addEventListener("click", onConfirm);
    });
  }

  function create(options) {
    const opts = options || {};

    const getActionEndpoint = function () {
      if (typeof opts.getActionEndpoint === "function") {
        return opts.getActionEndpoint();
      }
      if (window.BLOG_ENDPOINTS && window.BLOG_ENDPOINTS.communityAction) {
        return window.BLOG_ENDPOINTS.communityAction;
      }
      return "community_action_api.php";
    };

    const getReactEndpoint = function () {
      if (typeof opts.getReactEndpoint === "function") {
        return opts.getReactEndpoint();
      }
      if (window.BLOG_ENDPOINTS && window.BLOG_ENDPOINTS.communityReact) {
        return window.BLOG_ENDPOINTS.communityReact;
      }
      return "community_react.php";
    };

    const onPostRemoved = function (postId) {
      if (typeof opts.onPostRemoved === "function") {
        opts.onPostRemoved(postId);
      }
    };

    function initCarousels(root) {
      const scope = root || document;
      scope
        .querySelectorAll("[data-community-lazy-image]")
        .forEach(function (img) {
          if (img.dataset.communityLazyBound === "1") {
            return;
          }
          img.dataset.communityLazyBound = "1";

          const reveal = function () {
            img.classList.remove("opacity-0");
            img.classList.add("opacity-100");
            const spinner = img
              .closest(".media-item")
              ?.querySelector(".community-image-spinner");
            if (spinner) {
              spinner.style.display = "none";
            }
          };

          if (img.complete && img.naturalWidth > 0) {
            reveal();
          } else {
            img.addEventListener("load", reveal, { once: true });
            img.addEventListener(
              "error",
              function () {
                const spinner = img
                  .closest(".media-item")
                  ?.querySelector(".community-image-spinner");
                if (spinner) {
                  spinner.innerHTML =
                    '<i class="fas fa-image text-gray-400"></i>';
                }
                img.classList.remove("opacity-0");
                img.classList.add("opacity-100");
              },
              { once: true },
            );
          }
        });

      scope
        .querySelectorAll("[data-community-carousel]")
        .forEach(function (carousel) {
          if (carousel.dataset.carouselBound === "1") {
            return;
          }
          carousel.dataset.carouselBound = "1";

          const track = carousel.querySelector(
            "[data-community-carousel-track]",
          );
          if (!track) {
            return;
          }

          const slides = Array.from(track.children);
          if (!slides.length) {
            return;
          }

          let index = 0;
          const dots = Array.from(
            carousel.querySelectorAll("[data-community-carousel-dot]"),
          );
          const update = function () {
            track.style.transform = "translateX(-" + index * 100 + "%)";
            dots.forEach(function (dot, dotIndex) {
              dot.classList.toggle("is-active", dotIndex === index);
            });
          };

          const prevBtn = carousel.querySelector(
            "[data-community-carousel-prev]",
          );
          const nextBtn = carousel.querySelector(
            "[data-community-carousel-next]",
          );

          if (prevBtn) {
            prevBtn.addEventListener("click", function (event) {
              event.preventDefault();
              event.stopPropagation();
              index = (index - 1 + slides.length) % slides.length;
              update();
            });
          }

          if (nextBtn) {
            nextBtn.addEventListener("click", function (event) {
              event.preventDefault();
              event.stopPropagation();
              index = (index + 1) % slides.length;
              update();
            });
          }

          dots.forEach(function (dot) {
            dot.addEventListener("click", function (event) {
              event.preventDefault();
              event.stopPropagation();
              const nextIndex = Number(
                dot.getAttribute("data-dot-index") || "0",
              );
              index = Math.max(0, Math.min(nextIndex, slides.length - 1));
              update();
            });
          });

          update();
        });
    }

    function applyPinnedPlacement(postId, pinned) {
      const postEl = document.getElementById(
        "community-post-" + String(postId || ""),
      );
      if (!postEl) {
        return;
      }

      const listRoot = postEl.closest("[data-community-feed-list]");
      if (!listRoot) {
        return;
      }

      if (pinned) {
        const firstCard = listRoot.querySelector(".community-post-card");
        if (firstCard && firstCard !== postEl) {
          listRoot.insertBefore(postEl, firstCard);
        }
      }

      postEl.classList.add("ring-2", "ring-main/40");
      setTimeout(function () {
        postEl.classList.remove("ring-2", "ring-main/40");
      }, 1200);
    }

    async function submitVote(button) {
      const postId = Number(button.getAttribute("data-post-id") || "0");
      const voteType = String(button.getAttribute("data-vote") || "up");
      if (!postId) {
        return;
      }

      button.disabled = true;
      try {
        const fd = new FormData();
        fd.set("post_id", String(postId));
        fd.set("vote", voteType);

        const res = await fetch(getReactEndpoint(), {
          method: "POST",
          body: fd,
          headers: {
            "X-Requested-With": "XMLHttpRequest",
          },
          credentials: "same-origin",
        });

        const payload = await res.json();
        if (!payload || payload.ok !== true) {
          if (payload && payload.login_required && payload.login_url) {
            if (typeof showNotification === "function") {
              showNotification(
                payload.message || "Bạn cần đăng nhập.",
                "warning",
              );
            }
            setTimeout(function () {
              window.location.href = payload.login_url;
            }, 500);
            return;
          }
          if (typeof showNotification === "function") {
            showNotification(
              (payload && payload.message) || "Không thể vote bài viết.",
              "error",
            );
          }
          return;
        }

        const upBtn = document.querySelector(
          '[data-community-vote-btn][data-vote="up"][data-post-id="' +
            postId +
            '"]',
        );
        const downBtn = document.querySelector(
          '[data-community-vote-btn][data-vote="down"][data-post-id="' +
            postId +
            '"]',
        );
        const upCountEl = document.getElementById(
          "community-upvote-count-" + postId,
        );
        const downCountEl = document.getElementById(
          "community-downvote-count-" + postId,
        );
        const scoreEl = document.getElementById(
          "community-score-count-" + postId,
        );

        if (upCountEl) {
          upCountEl.textContent = String(payload.total_upvotes || 0);
        }
        if (downCountEl) {
          downCountEl.textContent = String(payload.total_downvotes || 0);
        }
        if (scoreEl) {
          scoreEl.textContent = String(payload.vote_score || 0);
        }

        const voteGroup = upBtn
          ? upBtn.closest("[data-community-vote-group]")
          : null;
        const reaction = Number(payload.reaction || 0);

        if (voteGroup) {
          voteGroup.classList.remove(
            "bg-emerald-100",
            "dark:bg-emerald-900\/50",
            "text-emerald-800",
            "dark:text-emerald-200",
            "bg-rose-100",
            "dark:bg-rose-900\/50",
            "text-rose-800",
            "dark:text-rose-200",
            "bg-white",
            "dark:bg-gray-900",
            "shadow-lg",
            "shadow-sm",
            "ring",
            "ring-emerald-300",
            "ring-rose-300",
            "community-vote-go",
            "community-vote-glow",
          );
          voteGroup.classList.add("community-vote-glow");
          setTimeout(() => {
            voteGroup.classList.remove("community-vote-glow");
          }, 700);
          if (reaction === 1) {
            voteGroup.classList.add(
              "bg-emerald-100",
              "dark:bg-emerald-900\/50",
              "text-emerald-800",
              "dark:text-emerald-200",
              "shadow-lg",
            );
          } else if (reaction === -1) {
            voteGroup.classList.add(
              "bg-rose-100",
              "dark:bg-rose-900\/50",
              "text-rose-800",
              "dark:text-rose-200",
              "shadow-lg",
            );
          } else {
            voteGroup.classList.add(
              "bg-white",
              "dark:bg-gray-900",
              "shadow-sm",
            );
          }
        }

        if (upBtn) {
          upBtn.classList.toggle("text-2xl", reaction === 1);
          upBtn.classList.toggle("font-bold", reaction === 1);
          upBtn.classList.toggle("text-xl", reaction !== 1);
          upBtn.classList.toggle("ring", reaction === 1);
          upBtn.classList.toggle("ring-emerald-300", reaction === 1);
          upBtn.classList.toggle("ring-rose-300", reaction !== 1);
          upBtn.classList.add("community-vote-pop");
          setTimeout(() => upBtn.classList.remove("community-vote-pop"), 350);
          upBtn.setAttribute("aria-pressed", reaction === 1 ? "true" : "false");
        }
        if (downBtn) {
          downBtn.classList.toggle("text-2xl", reaction === -1);
          downBtn.classList.toggle("font-bold", reaction === -1);
          downBtn.classList.toggle("text-xl", reaction !== -1);
          downBtn.classList.toggle("ring", reaction === -1);
          downBtn.classList.toggle("ring-rose-300", reaction === -1);
          downBtn.classList.toggle("ring-emerald-300", reaction !== -1);
          downBtn.classList.add("community-vote-pop");
          setTimeout(() => downBtn.classList.remove("community-vote-pop"), 350);
          downBtn.setAttribute(
            "aria-pressed",
            reaction === -1 ? "true" : "false",
          );
        }
      } catch (err) {
        if (typeof showNotification === "function") {
          showNotification("Lỗi kết nối khi vote bài viết.", "error");
        }
      } finally {
        button.disabled = false;
      }
    }

    async function handleCardAction(action, postId, actionButton) {
      if (!action || !postId) {
        return;
      }

      if (action === "hide") {
        const postEl = document.getElementById("community-post-" + postId);
        if (postEl) {
          postEl.remove();
          onPostRemoved(postId);
        }
        if (typeof showNotification === "function") {
          showNotification("Đã ẩn bài viết.", "info");
        }
        return;
      }

      if (action === "save") {
        try {
          const fd = new FormData();
          fd.set("action", "save");
          fd.set("post_id", String(postId));

          const res = await fetch(getActionEndpoint(), {
            method: "POST",
            body: fd,
            headers: {
              "X-Requested-With": "XMLHttpRequest",
            },
            credentials: "same-origin",
          });
          const payload = await res.json();

          if (!payload || payload.ok !== true) {
            if (typeof showNotification === "function") {
              showNotification(
                (payload && payload.message) || "Không thể lưu bài viết.",
                "error",
              );
            }
            return;
          }

          if (actionButton) {
            const isSaved = Number(payload.saved || 0) === 1;
            actionButton.setAttribute("data-saved", isSaved ? "1" : "0");
            actionButton.textContent = isSaved
              ? "Bỏ lưu bài viết"
              : "Lưu bài viết";
          }

          if (typeof showNotification === "function") {
            showNotification(
              payload.message || "Đã cập nhật bài viết đã lưu.",
              "success",
            );
          }
        } catch (err) {
          if (typeof showNotification === "function") {
            showNotification("Lỗi kết nối khi lưu bài viết.", "error");
          }
        }
        return;
      }

      if (action === "pin") {
        try {
          const fd = new FormData();
          fd.set("action", "pin");
          fd.set("post_id", String(postId));

          const res = await fetch(getActionEndpoint(), {
            method: "POST",
            body: fd,
            headers: {
              "X-Requested-With": "XMLHttpRequest",
            },
            credentials: "same-origin",
          });
          const payload = await res.json();

          if (!payload || payload.ok !== true) {
            if (typeof showNotification === "function") {
              showNotification(
                (payload && payload.message) ||
                  "Khong the cap nhat ghim bai viet.",
                "error",
              );
            }
            return;
          }

          if (actionButton) {
            const pinned = Number(payload.pinned || 0) === 1;
            actionButton.setAttribute("data-pinned", pinned ? "1" : "0");
            actionButton.textContent = pinned
              ? "Bo ghim tren dau feed"
              : "Ghim len dau feed";
            applyPinnedPlacement(postId, pinned);
          }

          if (typeof showNotification === "function") {
            showNotification(
              payload.message || "Da cap nhat ghim bai viet.",
              "success",
            );
          }
        } catch (err) {
          if (typeof showNotification === "function") {
            showNotification(
              "Loi ket noi khi cap nhat ghim bai viet.",
              "error",
            );
          }
        }
        return;
      }

      if (action === "report") {
        const reason = await openReportReasonPicker();
        if (!reason) {
          return;
        }

        try {
          const fd = new FormData();
          fd.set("action", "report");
          fd.set("post_id", String(postId));
          fd.set("reason", reason);

          const res = await fetch(getActionEndpoint(), {
            method: "POST",
            body: fd,
            headers: {
              "X-Requested-With": "XMLHttpRequest",
            },
            credentials: "same-origin",
          });
          const payload = await res.json();

          if (!payload || payload.ok !== true) {
            if (typeof showNotification === "function") {
              showNotification(
                (payload && payload.message) || "Không thể báo cáo bài viết.",
                "error",
              );
            }
            return;
          }

          if (typeof showNotification === "function") {
            showNotification(payload.message || "Đã gửi báo cáo.", "warning");
          }
        } catch (err) {
          if (typeof showNotification === "function") {
            showNotification("Lỗi kết nối khi báo cáo bài viết.", "error");
          }
        }
      }
    }

    function updateFollowButtons(
      targetUserId,
      following,
      followersCount,
      followedByTarget,
    ) {
      const normalizedTarget = String(targetUserId || "");
      if (!normalizedTarget) {
        return;
      }

      const btnSelector =
        '[data-community-follow-btn][data-target-user-id="' +
        normalizedTarget +
        '"]';
      document.querySelectorAll(btnSelector).forEach(function (button) {
        button.setAttribute("data-following", following ? "1" : "0");
        if (following) {
          button.textContent = "Dang theo doi";
          button.classList.remove(
            "bg-gray-100",
            "dark:bg-gray-700",
            "text-gray-700",
            "dark:text-gray-200",
            "hover:bg-main/10",
            "hover:text-main",
          );
          button.classList.add("bg-main", "text-white", "hover:bg-main/90");
        } else {
          button.textContent = followedByTarget ? "Theo doi lai" : "Theo doi";
          button.classList.remove("bg-main", "text-white", "hover:bg-main/90");
          button.classList.add(
            "bg-gray-100",
            "dark:bg-gray-700",
            "text-gray-700",
            "dark:text-gray-200",
            "hover:bg-main/10",
            "hover:text-main",
          );
        }
      });

      const countSelector =
        '[data-community-followers-count][data-user-id="' +
        normalizedTarget +
        '"]';
      document.querySelectorAll(countSelector).forEach(function (countEl) {
        countEl.textContent = String(Math.max(0, Number(followersCount || 0)));
      });
    }

    async function submitFollow(button) {
      const targetUserId = Number(
        button.getAttribute("data-target-user-id") || "0",
      );
      if (!targetUserId) {
        return;
      }

      button.disabled = true;
      try {
        const fd = new FormData();
        fd.set("action", "follow");
        fd.set("target_user_id", String(targetUserId));

        const res = await fetch(getActionEndpoint(), {
          method: "POST",
          body: fd,
          headers: {
            "X-Requested-With": "XMLHttpRequest",
          },
          credentials: "same-origin",
        });

        const payload = await res.json();
        if (!payload || payload.ok !== true) {
          if (payload && payload.login_required && payload.login_url) {
            if (typeof showNotification === "function") {
              showNotification(
                payload.message || "Vui long dang nhap.",
                "warning",
              );
            }
            setTimeout(function () {
              window.location.href = payload.login_url;
            }, 500);
            return;
          }

          if (typeof showNotification === "function") {
            showNotification(
              (payload && payload.message) || "Khong the cap nhat theo doi.",
              "error",
            );
          }
          return;
        }

        updateFollowButtons(
          Number(payload.target_user_id || targetUserId),
          Number(payload.following || 0) === 1,
          Number(payload.followers_count || 0),
          Boolean(payload.followed_by_target),
        );

        if (typeof showNotification === "function") {
          showNotification(
            payload.message || "Da cap nhat trang thai theo doi.",
            "success",
          );
        }
      } catch (err) {
        if (typeof showNotification === "function") {
          showNotification("Loi ket noi khi theo doi tac gia.", "error");
        }
      } finally {
        button.disabled = false;
      }
    }

    function handleClick(event) {
      const actionTrigger = event.target.closest(
        "[data-community-action-trigger]",
      );
      if (actionTrigger) {
        event.preventDefault();
        const wrap = actionTrigger.closest("[data-community-action-wrap]");
        const menu = wrap
          ? wrap.querySelector("[data-community-action-menu]")
          : null;
        if (!menu) {
          return false;
        }
        document
          .querySelectorAll("[data-community-action-menu]")
          .forEach(function (otherMenu) {
            if (otherMenu !== menu) {
              otherMenu.classList.add("hidden");
            }
          });
        menu.classList.toggle("hidden");
        return true;
      }

      if (!event.target.closest("[data-community-action-wrap]")) {
        document
          .querySelectorAll("[data-community-action-menu]")
          .forEach(function (menu) {
            menu.classList.add("hidden");
          });
      }

      const actionButton = event.target.closest("[data-community-action]");
      if (actionButton) {
        event.preventDefault();
        const action = String(
          actionButton.getAttribute("data-community-action") || "",
        );
        const postId = Number(actionButton.getAttribute("data-post-id") || "0");
        handleCardAction(action, postId, actionButton);
        return true;
      }

      const followButton = event.target.closest("[data-community-follow-btn]");
      if (followButton) {
        event.preventDefault();
        submitFollow(followButton);
        return true;
      }

      const voteButton = event.target.closest("[data-community-vote-btn]");
      if (voteButton) {
        event.preventDefault();
        submitVote(voteButton);
        return true;
      }

      return false;
    }

    function bindDelegation(root) {
      const target = root || document;
      if (target.dataset && target.dataset.communitySharedBound === "1") {
        return;
      }
      if (target.dataset) {
        target.dataset.communitySharedBound = "1";
      }

      target.addEventListener("click", function (event) {
        handleClick(event);
      });
    }

    return {
      initCarousels: initCarousels,
      submitVote: submitVote,
      submitFollow: submitFollow,
      handleCardAction: handleCardAction,
      handleClick: handleClick,
      bindDelegation: bindDelegation,
    };
  }

  window.CommunityFeedShared = {
    create: create,
  };
})();

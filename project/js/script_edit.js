document.addEventListener("DOMContentLoaded", () => {
  const navbar = document.querySelector(".header .flex .navbar");
  const menuBtn = document.querySelector("#menu-btn");
  const profile = document.querySelector(".header .flex .profile");
  const userBtn = document.querySelector("#user-btn");
  const searchForm = document.querySelector(".header .flex .search-form");
  const searchBtn = document.querySelector("#search-btn");
  const closeBtn = document.querySelector(".close-btn");
  const header = document.querySelector(".header");
  const prevBtn = document.getElementById("prevBtn");
  const nextBtn = document.getElementById("nextBtn");
  const boxes = document.querySelectorAll(".box_byPost");
  const message = document.getElementById("message");
  const progressBar = document.getElementById("progressBar");

  let lastScrollTop = 0;
  let currentBoxIndex = 0;

  if (
    menuBtn &&
    navbar &&
    searchForm &&
    profile &&
    userBtn &&
    searchBtn &&
    closeBtn
  ) {
    // thực hiện thao tác đóng/mở header
    menuBtn.onclick = () => {
      navbar.classList.toggle("active");
      searchForm.classList.remove("active");
      profile.classList.remove("active");
    };

    userBtn.onclick = () => {
      profile.classList.toggle("active");
      searchForm.classList.remove("active");
      navbar.classList.remove("active");
    };

    searchBtn.onclick = () => {
      searchForm.classList.toggle("active");
      navbar.classList.remove("active");
      profile.classList.remove("active");
    };

    window.onscroll = () => {
      profile.classList.remove("active");
      navbar.classList.remove("active");
      searchForm.classList.remove("active");
    };

    closeBtn.onclick = () => {
      navbar.classList.remove("active");
    };
  }

  // giới hạn hiển thị text lên giao diện
  document.querySelectorAll(".content-30").forEach((content) => {
    if (content.innerHTML.length > 30) {
      content.innerHTML = content.innerHTML.slice(0, 30);
    }
  });

  document.querySelectorAll(".content-150").forEach((content) => {
    if (content.innerHTML.length > 150)
      content.innerHTML = content.innerHTML.slice(0, 150);
  });

  document.querySelectorAll(".content-200").forEach((content) => {
    if (content.innerHTML.length > 200)
      content.innerHTML = content.innerHTML.slice(0, 200);
  });

  document.querySelectorAll(".content-220").forEach((content) => {
    if (content.innerHTML.length > 220)
      content.innerHTML = content.innerHTML.slice(0, 220);
  });

  // mở phone
  document
    .querySelector('a[href^="tel:"]')
    .addEventListener("click", function (event) {
      event.preventDefault();
      var phoneNumber = this.getAttribute("href").replace("tel:", "");
      callPhoneNumber(phoneNumber);
    });

  function callPhoneNumber(phoneNumber) {
    if (
      "function" === typeof window.open &&
      window.open("", "_self", "") &&
      window.open("tel:" + phoneNumber)
    ) {
      window.open("tel:" + phoneNumber);
    } else {
      alert("Trình duyệt của bạn không hỗ trợ chức năng này.");
    }
  }

  // handle nút trước / sau của slide
  if (prevBtn) {
    prevBtn.addEventListener("click", function () {
      if (currentBoxIndex > 0) {
        currentBoxIndex--;
        boxes[currentBoxIndex].scrollIntoView({
          behavior: "smooth",
          block: "nearest",
          inline: "start",
        });
      }
    });
  }

  if (nextBtn) {
    nextBtn.addEventListener("click", function () {
      if (currentBoxIndex < boxes.length - 1) {
        currentBoxIndex++;
        boxes[currentBoxIndex].scrollIntoView({
          behavior: "smooth",
          block: "nearest",
          inline: "start",
        });
      }
    });
  }

  // ẩn thanh header khi cuộn
  window.addEventListener("scroll", function () {
    let scrollTop = window.pageYOffset || document.documentElement.scrollTop;
    if (scrollTop > lastScrollTop) {
      header.classList.add("hide");
    } else {
      header.classList.remove("hide");
    }
    lastScrollTop = scrollTop;
  });

  if (message) {
    progressBar.style.width = "100%";
    setTimeout(function () {
      message.style.display = "none";
    }, 4000);
  }

  // theme dark / light mode
  const body = document.body;
  const toggleButton = document.querySelector(".light_dark_btn span");
  const currentTheme = localStorage.getItem("theme");

  if (localStorage.getItem("dark-mode") === "enabled") {
    body.classList.add("dark-mode");
  }

  toggleButton.addEventListener("click", () => {
    body.classList.toggle("dark-mode");

    // Lưu trạng thái dark mode vào localStorage
    if (body.classList.contains("dark-mode")) {
      localStorage.setItem("dark-mode", "enabled");
    } else {
      localStorage.setItem("dark-mode", "disabled");
    }
  });

  tippy('#searchToolTip', {
    content: 'Hãy thử tìm kiếm một thứ gì đó !',
      placement: 'bottom',
      animation: 'scale',
      delay: [100, 200],
      theme: '',
  });
});

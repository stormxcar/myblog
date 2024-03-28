let navbar = document.querySelector(".header .flex .navbar");

document.querySelector("#menu-btn").onclick = () => {
  navbar.classList.toggle("active");
  searchForm.classList.remove("active");
  profile.classList.remove("active");
};

let profile = document.querySelector(".header .flex .profile");

document.querySelector("#user-btn").onclick = () => {
  profile.classList.toggle("active");
  searchForm.classList.remove("active");
  navbar.classList.remove("active");
};

let searchForm = document.querySelector(".header .flex .search-form");

document.querySelector("#search-btn").onclick = () => {
  searchForm.classList.toggle("active");
  navbar.classList.remove("active");
  profile.classList.remove("active");
};

window.onscroll = () => {
  profile.classList.remove("active");
  navbar.classList.remove("active");
  searchForm.classList.remove("active");
};

document.querySelectorAll(".content-30").forEach((content) => {
  if (content.innerHTML.length > 30)
    content.innerHTML = content.innerHTML.slice(0, 30);
});

document.querySelectorAll(".content-200").forEach((content) => {
  if (content.innerHTML.length > 200)
    content.innerHTML = content.innerHTML.slice(0, 200);
});

function scrollToTop() {
  window.scrollTo({
    top: 0,
    behavior: "smooth",
  });
}

//
document
  .querySelector('a[href^="tel:"]')
  .addEventListener("click", function (event) {
    event.preventDefault();

    // Lấy số điện thoại từ thuộc tính href
    var phoneNumber = this.getAttribute("href").replace("tel:", "");

    // Gọi hàm để thực hiện cuộc gọi
    callPhoneNumber(phoneNumber);
  });

// Hàm thực hiện cuộc gọi
function callPhoneNumber(phoneNumber) {
  // Kiểm tra xem trình duyệt hỗ trợ hàm gọi điện thoại không
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


// xử lý 
const prevBtn = document.getElementById('prevBtn');
const nextBtn = document.getElementById('nextBtn');
const boxes = document.querySelectorAll('.box');

let currentBoxIndex = 0; // gán vi tri của box ban dau

prevBtn.addEventListener('click', function() {
    if (currentBoxIndex > 0) { // Kiểm tra xem có box trước đó không
        currentBoxIndex--; // Giảm chỉ số box hiện tại
        boxes[currentBoxIndex].scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'start' });
    }
});

nextBtn.addEventListener('click', function() {
    if (currentBoxIndex < boxes.length - 1) { // Kiểm tra xem có box tiếp theo không
        currentBoxIndex++; // Tăng chỉ số box hiện tại
        boxes[currentBoxIndex].scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'start' });
    }
});
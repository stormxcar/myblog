let header = document.querySelector(".header");

document.querySelector("#menu-btn").onclick = () => {
  header.classList.toggle("active");
};

window.onscroll = () => {
  header.classList.remove("active");
};

document.querySelectorAll(".posts-content").forEach((content) => {
  if (content.innerHTML.length > 100)
    content.innerHTML = content.innerHTML.slice(0, 100);
});

// xử lý mở modal thêm tag
// const btn_add_modal = document.querySelector(".add_tag_btn");
// const add_tag_modal = document.querySelector("#modal_add_tag");
// const bodyLayer = document.querySelector("body");
// let isModalVisible = false;

// btn_add_modal.addEventListener("click", (e) => {
//   e.preventDefault();
//   // Toggle trạng thái của modal
//   isModalVisible = !isModalVisible;
//   // Thay đổi hiển thị của modal dựa trên trạng thái mới
//   if (isModalVisible) {
//     add_tag_modal.classList.add("showTag");
//     bodyLayer.style.background = "rgba(0, 0, 0, 0.3)";
//   } else {
//     add_tag_modal.classList.remove("showTag");
//     bodyLayer.style.background = "initial";
//   }
// });

// const btn_edit_modal = document.getElementById("edit_tag_btn");
// const edit_tag_modal = document.querySelector("#modal_edit_tag");

// btn_edit_modal.addEventListener("click", (e) => {
//   e.preventDefault();
//   // Toggle trạng thái của modal
//   isModalVisible = !isModalVisible;
//   // Thay đổi hiển thị của modal dựa trên trạng thái mới
//   if (isModalVisible) {
//     edit_tag_modal.classList.add("showTag2");
//     bodyLayer.style.background = "rgba(0, 0, 0, 0.3)";
//   } else {
//     edit_tag_modal.classList.remove("showTag2");
//     bodyLayer.style.background = "initial";
//   }
// });

// const cancel_btn = document.querySelector('.cancel');
// cancel_btn.addEventListener('click', function(){
//   edit_tag_modal.classList.remove("showTag2");
//   bodyLayer.style.background = "initial";
// })

function openPage(pageName, elmnt, color) {
  // Hide all elements with class="tabcontent" by default */
  var i, tabcontent, tablinks;
  tabcontent = document.getElementsByClassName("tabcontent");
  for (i = 0; i < tabcontent.length; i++) {
    tabcontent[i].style.display = "none";
  }

  // Remove the background color of all tablinks/buttons
  tablinks = document.getElementsByClassName("tablink");
  for (i = 0; i < tablinks.length; i++) {
    tablinks[i].style.backgroundColor = "";
  }

  // Show the specific tab content
  document.getElementById(pageName).style.display = "block";

  // Add the specific color to the button used to open the tab content
  elmnt.style.backgroundColor = color;
}

// Get the element with id="defaultOpen" and click on it
document.getElementById("defaultOpen").click();

document.addEventListener("DOMContentLoaded", function () {
  const body = document.body;
  const header = document.querySelector(".header");
  const btnAddModal = document.querySelector(".add_tag_btn");
  const addTagModal = document.querySelector("#modal_add_tag");
  const btnEditModals = document.querySelectorAll(".edit_btn");
  const cancelBtnEdits = document.querySelectorAll(".cancel_edit");
  const cancelBtnAdd = document.querySelector(".cancel_add");
  let currentModalId = null;

  // Navbar toggle
  document.querySelector("#menu-btn").onclick = () => {
    header.classList.toggle("active");
    body.classList.toggle("padding-left-35rem");
  };

  // Limit content length
  document.querySelectorAll(".posts-content").forEach((content) => {
    if (content.innerHTML.length > 100) {
      content.innerHTML = content.innerHTML.slice(0, 100);
    }
  });

  // Modal handling
  btnAddModal.addEventListener("click", (e) => {
    e.preventDefault();
    toggleModal(addTagModal, true);
  });

  cancelBtnAdd.addEventListener("click", () => {
    toggleModal(addTagModal, false);
  });

  btnEditModals.forEach((btnEditModal) => {
    btnEditModal.addEventListener("click", (e) => {
      e.preventDefault();
      const cartId = e.target.getAttribute("data-cart-id");
      const cartName = e.target.getAttribute("data-cart-name");
      const editTagModal = document.querySelector("#modal_edit_tag_" + cartId);
      const nameInput = editTagModal.querySelector("input[name='name']");
      nameInput.value = cartName;
      toggleModal(editTagModal, true);
      currentModalId = cartId;
    });
  });

  cancelBtnEdits.forEach((cancelBtn) => {
    cancelBtn.addEventListener("click", () => {
      if (currentModalId !== null) {
        const currentModal = document.querySelector(
          "#modal_edit_tag_" + currentModalId
        );
        toggleModal(currentModal, false);
      }
    });
  });

  function toggleModal(modal, show) {
    if (show) {
      modal.classList.add("showTag");
      body.style.background = "rgba(0, 0, 0, 0.3)";
    } else {
      modal.classList.remove("showTag");
      body.style.background = "initial";
    }
  }

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

  document.getElementById("defaultOpen").click();
});

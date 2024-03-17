let navbar = document.querySelector('.header .flex .navbar');

document.querySelector('#menu-btn').onclick = () =>{
   navbar.classList.toggle('active');
   searchForm.classList.remove('active');
   profile.classList.remove('active');
}

let profile = document.querySelector('.header .flex .profile');

document.querySelector('#user-btn').onclick = () =>{
   profile.classList.toggle('active');
   searchForm.classList.remove('active');
   navbar.classList.remove('active');
}

let searchForm = document.querySelector('.header .flex .search-form');

document.querySelector('#search-btn').onclick = () =>{
   searchForm.classList.toggle('active');
   navbar.classList.remove('active');
   profile.classList.remove('active');
}

window.onscroll = () =>{
   profile.classList.remove('active');
   navbar.classList.remove('active');
   searchForm.classList.remove('active');
}


document.querySelectorAll('.content-150').forEach(content => {
   if(content.innerHTML.length > 30) content.innerHTML = content.innerHTML.slice(0, 30);
});

//
document.querySelector('a[href^="tel:"]').addEventListener('click', function(event) {
   // Ngăn chặn hành vi mặc định của liên kết
   event.preventDefault();
   
   // Lấy số điện thoại từ thuộc tính href
   var phoneNumber = this.getAttribute('href').replace('tel:', '');
   
   // Gọi hàm để thực hiện cuộc gọi
   callPhoneNumber(phoneNumber);
});

// Hàm thực hiện cuộc gọi
function callPhoneNumber(phoneNumber) {
   // Kiểm tra xem trình duyệt hỗ trợ hàm gọi điện thoại không
   if ('function' === typeof window.open && window.open('', '_self', '') && window.open('tel:' + phoneNumber)) {
       // Nếu trình duyệt hỗ trợ, mở giao diện gọi điện thoại
       window.open('tel:' + phoneNumber);
   } else {
       // Nếu trình duyệt không hỗ trợ, hiển thị thông báo
       alert('Trình duyệt của bạn không hỗ trợ chức năng này.');
   }
}



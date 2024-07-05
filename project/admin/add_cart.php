<?php
include '../components/connect.php';

session_start();
$admin_id = $_SESSION['admin_id'];

// yêu cầu đăng nhập admin 
if (!isset($admin_id)) {
   header('location:admin_login.php');
}
$message = [];

if (isset($_POST['add_to_cart'])) {
   // Kiểm tra nếu người dùng chưa đăng nhập
   if (empty($admin_id)) {
      header('location:login.php');
      exit(); // Dừng việc thực thi tiếp tục nếu chưa đăng nhập
   } else {
      // Lấy tên tag từ form
      $name = $_POST['name'];
      $name = filter_var($name, FILTER_SANITIZE_STRING);

      // Thêm cart và admin_id tương ứng vào bảng cart
      $insert_cart = $conn->prepare("INSERT INTO `cart`(admin_id, name) VALUES(?, ?)");
      if ($insert_cart->execute([$admin_id, $name])) {
         $message[] = 'Thẻ đã được thêm !';
         header('Location: add_cart.php');
         exit();
      } else {
         $message[] = 'Có lỗi xảy ra khi thêm thẻ!';
      }

      // Reset biến session
      unset($_SESSION['message']);
   }
}

if (isset($_POST['delete_cart'])) {
   $cart_id = $_POST['cart_id'];

   // Xóa thẻ khỏi cơ sở dữ liệu
   $delete_cart = $conn->prepare("DELETE FROM `cart` WHERE category_id = ?");
   if ($delete_cart->execute([$cart_id])) {
      $message[] = 'Thẻ đã được xóa !';
      // Điều hướng người dùng sau khi xóa thẻ
      header('Location: add_cart.php');
      exit();
   } else {
      $message[] = 'Có lỗi xảy ra khi xóa thẻ!';
   }
}

if (isset($_POST['edit_cart'])) {
   $cart_id = filter_var($_POST['cart_id'], FILTER_SANITIZE_NUMBER_INT);
   $new_name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);

   if (!empty($cart_id) && !empty($new_name)) {
      // Cập nhật tên thẻ trong cơ sở dữ liệu
      $update_cart = $conn->prepare("UPDATE `cart` SET name = ? WHERE category_id = ?");
      if ($update_cart->execute([$new_name, $cart_id])) {
         $message[] = 'Thẻ đã được cập nhật!';
         // Điều hướng người dùng sau khi cập nhật thẻ
         header('Location: add_cart.php');
         exit();
      } else {
         $message[] = 'Có lỗi xảy ra khi cập nhật thẻ!';
      }
   } else {
      $message[] = 'Vui lòng điền đầy đủ thông tin!';
   }
}



if (isset($_POST['count_post_byCart'])) {
   // Kiểm tra nếu người dùng chưa đăng nhập
   if (empty($admin_id)) {
      header('location:login.php');
      exit(); // Dừng việc thực thi tiếp tục nếu chưa đăng nhập
   } else {
      $cart_id = $_POST['cart_id'];

      // Truy vấn SQL để đếm số lượng bài viết sử dụng thẻ tag cụ thể
      /*
       SELECT c.category_id, c.name, COUNT(p.post_id) AS num_posts 
       FROM `cart` c 
       LEFT JOIN `posts` p ON c.category_id = p.tag_id 
       WHERE c.admin_id = ? 
       GROUP BY c.category_id, c.name
       */

      $count_post_byCart = $conn->prepare("SELECT COUNT(*) AS num_posts FROM `posts` WHERE tag_id = ?");
      $count_post_byCart->execute([$cart_id]);
      $count = $count_post_byCart->fetch(PDO::FETCH_ASSOC);
      echo $count['num_posts'];
      exit(); // Dừng việc thực thi tiếp tục sau khi đã hiển thị số lượng bài viết
   }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Quản lý carts</title>

   <!-- font awesome cdn link  -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
   <!-- custom css file link  -->
   <link rel="stylesheet" href="../css/admin_style_edit.css">

</head>

<body>
   <?php include '../components/admin_header.php' ?>

   <section class="category_edit">

      <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
         <table>
            <thead>
               <tr>
                  <th>STT</th>
                  <th>cate id</th>
                  <th>Tên cate</th>
                  <th>Số bài viết đang sử dụng</th>
                  <th>Quản lý thẻ</th>
               </tr>
            </thead>
            <tbody>
               <?php
               $select_carts = $conn->prepare("
         SELECT c.category_id, c.name, COUNT(p.id) AS num_posts 
         FROM `cart` c 
         LEFT JOIN `posts` p ON c.category_id = p.tag_id 
         WHERE c.admin_id = ? 
         GROUP BY c.category_id, c.name
         ");
               $select_carts->execute([$admin_id]);
               $carts = $select_carts->fetchAll(PDO::FETCH_ASSOC);
               ?>
               <?php if (isset($carts) && !empty($carts)) : ?>
                  <?php foreach ($carts as $index => $cart) : ?>
                     <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><?php echo $cart['category_id']; ?></td>
                        <td><?php echo $cart['name']; ?></td>
                        <td><?php echo $cart['num_posts']; ?></td>
                        <td>
                           <form class="manage_cart" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                              <input type="hidden" name="cart_id" value="<?php echo $cart['category_id']; ?>">
                              <button type="submit" class="edit_btn" data-cart-id="<?php echo $cart['category_id']; ?>" data-cart-name="<?php echo $cart['name']; ?>">Sửa</button>
                              <button type="submit" name="delete_cart" class="delete_btn" style="margin-top:0">Xóa</button>
                           </form>
                        </td>
                     </tr>
                  <?php endforeach; ?>
               <?php else : ?>
                  <tr>
                     <td colspan="5">Không có dữ liệu cart để hiển thị.</td>
                  </tr>
               <?php endif; ?>
            </tbody>
         </table>
      </form>


      <button class="add_tag_btn">
         <i class="fas fa-plus"></i>
         <span>Thêm tag</span>
      </button>

      <div class="add_cart" id="modal_add_tag">
         <div class="modal">
            <div class="modal_content">
               <div class="modal_title">
                  <h3>Thêm cart</h3>
               </div>
               <form method="POST" action="add_cart.php">
                  <div class="modal_details">
                     <span>Nhập tên thẻ mới:</span>
                     <input type="text" name="name" placeholder="hạnh phúc" class="cart_box" required>
                  </div>
                  <div class="modal_footer">
                     <button type="button" class="cancel_add">Hủy</button>
                     <button type="submit" name="add_to_cart" class="submit">Thêm</button>
                  </div>
               </form>
            </div>
         </div>
      </div>

      <?php foreach ($carts as $cart) : ?>
         <div class="edit_cart" id="modal_edit_tag_<?php echo $cart['category_id']; ?>">
            <div class="modal_edit">
               <div class="modal_content">
                  <div class="modal_title">
                     <h3>Cập nhật cart</h3>
                  </div>
                  <form method="POST" action="add_cart.php">
                     <div class="modal_details">
                        <input type="hidden" name="cart_id" value="<?php echo $cart['category_id']; ?>">
                        <span>Cập nhật tên thẻ: </span>
                        <input type="text" name="name" value="<?php echo $cart['name']; ?>" class="cart_box" required>
                     </div>
                     <div class="modal_footer">
                        <button type="button" class="cancel_edit">Hủy</button>
                        <button type="submit" name="edit_cart" class="submit">Cập nhật</button>
                     </div>
                  </form>
               </div>
            </div>
         </div>
      <?php endforeach; ?>


   </section>


   <!-- đảm bảo file script được tải song song trong quá trình tải trang -->
   <script async src="../js/admin_script.js"></script>
</body>

<script>
   document.addEventListener("DOMContentLoaded", function() {
      const bodyLayer = document.querySelector("body");

      const btn_add_modal = document.querySelector(".add_tag_btn");
      const add_tag_modal = document.querySelector("#modal_add_tag");

      btn_add_modal.addEventListener("click", (e) => {
         e.preventDefault();
         openModal(add_tag_modal);
      });

      const cancel_btn_add = document.querySelector('.cancel_add');
      cancel_btn_add.addEventListener("click", () => {
         closeModal(add_tag_modal);
      });

      const btn_edit_modals = document.querySelectorAll(".edit_btn");

      let currentModalId = null;

      btn_edit_modals.forEach(btn_edit_modal => {
         btn_edit_modal.addEventListener("click", (e) => {
            e.preventDefault();
            const cartId = e.target.getAttribute('data-cart-id');
            const cartName = e.target.getAttribute('data-cart-name');
            const edit_tag_modal = document.querySelector("#modal_edit_tag_" + cartId);
            const name_input = edit_tag_modal.querySelector("input[name='name']");
            name_input.value = cartName;
            openModal(edit_tag_modal);
            currentModalId = cartId;
         });
      });

      const cancel_btn_edit = document.querySelectorAll('.cancel_edit');
      cancel_btn_edit.forEach(cancel_btn => {
         cancel_btn.addEventListener("click", () => {
            if (currentModalId !== null) {
               const currentModal = document.querySelector("#modal_edit_tag_" + currentModalId);
               closeModal(currentModal);
            }
         });
      });

      function openModal(modal) {
         modal.classList.add("showTag");
         bodyLayer.style.background = "rgba(0, 0, 0, 0.3)";
      }

      function closeModal(modal) {
         modal.classList.remove("showTag");
         bodyLayer.style.background = "initial";
      }
   });
</script>

</html>
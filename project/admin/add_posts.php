<?php

include '../components/connect.php';



session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
   header('location:admin_login.php');
}


if (isset($_POST['publish'])) {

   $name = $_POST['name'];
   $name = filter_var($name, FILTER_SANITIZE_STRING);
   $title = $_POST['title'];
   $title = filter_var($title, FILTER_SANITIZE_STRING);
   $content = $_POST['content'];
   $content = filter_var($content, FILTER_SANITIZE_STRING);
   $category = $_POST['category'];
   $category = filter_var($category, FILTER_SANITIZE_STRING);
   $status = 'active';

   $image = $_FILES['image']['name'];
   $image = filter_var($image, FILTER_SANITIZE_STRING);
   $image_size = $_FILES['image']['size'];
   $image_tmp_name = $_FILES['image']['tmp_name'];
   $image_folder = '../uploaded_img/' . $image;

   $select_image = $conn->prepare("SELECT * FROM `posts` WHERE image = ? AND admin_id = ?");
   $select_image->execute([$image, $admin_id]);

   if (isset($image)) {
      if ($select_image->rowCount() > 0 and $image != '') {
         $message[] = 'Tên ảnh đã tồn tại!';
      } elseif ($image_size > 2000000) {
         $message[] = 'Kích thước ảnh quá lớn!';
      } else {
         move_uploaded_file($image_tmp_name, $image_folder);
      }
   } else {
      $image = '';
   }

   if ($select_image->rowCount() > 0 and $image != '') {
      $message[] = 'Vui lòng đổi tên ảnh!';
   } else {
      // Truy vấn để lấy tag_id tương ứng với category
      $select_tag_id = $conn->prepare("SELECT category_id FROM cart WHERE name = ?");
      $select_tag_id->execute([$category]);
      $tag = $select_tag_id->fetch(PDO::FETCH_ASSOC);
      $tag_id = $tag['category_id'];

      $insert_post = $conn->prepare("INSERT INTO `posts` (admin_id, name, title, content, category, image, status, tag_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
      $insert_post->execute([$admin_id, $name, $title, $content, $category, $image, $status, $tag_id]);
      $message[] = 'Bài viết đã được đăng thành công!';
   }
}

if (isset($_POST['draft'])) {

   $name = $_POST['name'];
   $name = filter_var($name, FILTER_SANITIZE_STRING);
   $title = $_POST['title'];
   $title = filter_var($title, FILTER_SANITIZE_STRING);
   $content = $_POST['content'];
   $content = filter_var($content, FILTER_SANITIZE_STRING);
   $category = $_POST['category'];
   $category = filter_var($category, FILTER_SANITIZE_STRING);
   $status = 'deactive';

   $image = $_FILES['image']['name'];
   $image = filter_var($image, FILTER_SANITIZE_STRING);
   $image_size = $_FILES['image']['size'];
   $image_tmp_name = $_FILES['image']['tmp_name'];
   $image_folder = '../uploaded_img/' . $image;

   $select_image = $conn->prepare("SELECT * FROM `posts` WHERE image = ? AND admin_id = ?");
   $select_image->execute([$image, $admin_id]);

   if (isset($image)) {
      if ($select_image->rowCount() > 0 and $image != '') {
         $message[] = 'image name repeated!';
      } elseif ($image_size > 2000000) {
         $message[] = 'images size is too large!';
      } else {
         move_uploaded_file($image_tmp_name, $image_folder);
      }
   } else {
      $image = '';
   }

   if ($select_image->rowCount() > 0 and $image != '') {
      $message[] = 'please rename your image!';
   } else {
      $insert_post = $conn->prepare("INSERT INTO `posts`(admin_id, name, title, content, category, image, status) VALUES(?,?,?,?,?,?,?)");
      $insert_post->execute([$admin_id, $name, $title, $content, $category, $image, $status]);
      $message[] = 'draft saved!';
   }
}


$select_categories = $conn->prepare("SELECT * FROM cart");
$select_categories->execute();
$categories = $select_categories->fetchAll(PDO::FETCH_ASSOC);



?>

<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Bài Đăng</title>

   <!-- font awesome cdn link  -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

   <!-- custom css file link  -->
   <link rel="stylesheet" href="../css/admin_style_edit.css">

   <script src="../plugin/ckeditor/ckeditor.js"></script>
   <script src="../plugin/ckfinder/ckfinder.js"></script>
   <script src="https://cdn.ckeditor.com/4.22.1/full/ckeditor.js"></script>


</head>

<body>


   <?php include '../components/admin_header.php' ?>

   <section class="post-editor">

      <h1 class="heading">Thêm Bài Đăng Mới</h1>

      <form action="" method="post" enctype="multipart/form-data" onsubmit="return submitForm()">
         <input type="hidden" name="name" value="<?= $fetch_profile['name']; ?>">
         <p>Tiêu đề bài viết <span>*</span></p>
         <input type="text" name="title" maxlength="100" required placeholder="Thêm tiêu đề" class="box">
         <p>Nội dung bài viết <span>*</span></p>
         <textarea name="content" id="content" class="box content_add" required maxlength="10000" placeholder="Viết nội dung..." cols="30" rows="10"></textarea>
         <script>
            CKEDITOR.replace('content', {
               filebrowserBrowseUrl: '../plugin/ckfinder/ckfinder.html',
               // filebrowserImageBrowseUrl: '../plugin/ckfinder/ckfinder.html?type=Images',
               filebrowserUploadUrl: '../plugin/ckfinder/core/connector/php/connector.php?command=QuickUpload&type=Files'
               // filebrowserImageUploadUrl: '../plugin/ckfinder/core/connector/php/connector.php?command=QuickUpload&type=Images'
            });
         </script>

         <p>Thể loại bài viết <span>*</span></p>
         <select name="category" class="box" required>
            <option value="" selected disabled>-- Chọn Thể Loại* </option>
            <?php foreach ($categories as $category) : ?>
               <option value="<?= htmlspecialchars($category['name']); ?>"><?= htmlspecialchars($category['name']); ?></option>
            <?php endforeach; ?>
         </select>
         <p>Chọn ảnh bài viết</p>
         <input type="file" name="image" class="box" accept="image/jpg, image/jpeg, image/png, image/webp">
         <div class="flex-btn">
            <input type="submit" value="Lưu công khai" name="publish" class="btn">
            <input type="submit" value="Lưu Bản Nháp" name="draft" class="option-btn">
         </div>
      </form>
   </section>



   <script>
      ClassicEditor
         .create(document.querySelector('#content'))
         .catch(error => {
            console.error(error);
         });

      function submitForm() {
         // Đồng bộ nội dung CKEditor với textarea trước khi gửi form
         for (instance in CKEDITOR.instances) {
            CKEDITOR.instances[instance].updateElement();
         }
         return true;
      }
   </script>



   <!-- custom js file link  -->
   <script src="../js/admin_script.js"></script>

</body>

</html>
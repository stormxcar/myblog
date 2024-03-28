<?php

include '../components/connect.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if(!isset($admin_id)){
   header('location:admin_login.php');
}

if(isset($_POST['publish'])){

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
   $image_folder = '../uploaded_img/'.$image;

   $select_image = $conn->prepare("SELECT * FROM `posts` WHERE image = ? AND admin_id = ?");
   $select_image->execute([$image, $admin_id]);

   if(isset($image)){
      if($select_image->rowCount() > 0 AND $image != ''){
         $message[] = 'image name repeated!';
      }elseif($image_size > 2000000){
         $message[] = 'images size is too large!';
      }else{
         move_uploaded_file($image_tmp_name, $image_folder);
      }
   }else{
      $image = '';
   }

   if($select_image->rowCount() > 0 AND $image != ''){
      $message[] = 'please rename your image!';
   }else{
      $insert_post = $conn->prepare("INSERT INTO `posts`(admin_id, name, title, content, category, image, status) VALUES(?,?,?,?,?,?,?)");
      $insert_post->execute([$admin_id, $name, $title, $content, $category, $image, $status]);
      $message[] = 'post published!';
   }
   
}

if(isset($_POST['draft'])){

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
   $image_folder = '../uploaded_img/'.$image;

   $select_image = $conn->prepare("SELECT * FROM `posts` WHERE image = ? AND admin_id = ?");
   $select_image->execute([$image, $admin_id]); 

   if(isset($image)){
      if($select_image->rowCount() > 0 AND $image != ''){
         $message[] = 'image name repeated!';
      }elseif($image_size > 2000000){
         $message[] = 'images size is too large!';
      }else{
         move_uploaded_file($image_tmp_name, $image_folder);
      }
   }else{
      $image = '';
   }

   if($select_image->rowCount() > 0 AND $image != ''){
      $message[] = 'please rename your image!';
   }else{
      $insert_post = $conn->prepare("INSERT INTO `posts`(admin_id, name, title, content, category, image, status) VALUES(?,?,?,?,?,?,?)");
      $insert_post->execute([$admin_id, $name, $title, $content, $category, $image, $status]);
      $message[] = 'draft saved!';
   }

}

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

</head>
<body>


<?php include '../components/admin_header.php' ?>

<section class="post-editor">

   <h1 class="heading">Thêm Bài Đăng Mới</h1>

   <form action="" method="post" enctype="multipart/form-data">
      <input type="hidden" name="name" value="<?= $fetch_profile['name']; ?>">
      <p>Tiêu đề bài viết <span>*</span></p>
      <input type="text" name="title" maxlength="100" required placeholder="Thêm tiêu đề" class="box">
      <p>Nội dung bài viết <span>*</span></p>
      <textarea name="content" class="box" required maxlength="10000" placeholder="Viết nội dung..." cols="30" rows="10"></textarea>
      <p>Thể loại bài viết <span>*</span></p>
      <select name="category" class="box" required>
         <option value="" selected disabled>-- Chọn Thể Loại* </option>
         <option value="nature">Tự Nhiên</option>
         <option value="education">Giáo Dục</option>
         <option value="pets and animals">Thú cưng và Động Vật</option>
         <option value="technology">Công Nghệ</option>
         <option value="fashion">Thời Trang</option>
         <option value="entertainment">Giải Trí</option>
         <option value="movies and animations">Phim</option>
         <option value="gaming">Trò Chơi</option>
         <option value="music">Âm Nhạc</option>
         <option value="sports">Thể Thao</option>
         <option value="news">Tin Tức</option>
         <option value="travel">Du Lịch</option>
         <option value="comedy">Hài Hước</option>
         <option value="design and development">Thiết kế</option>
         <option value="food and drinks">Thức ăn Và Đồ uống</option>
         <option value="lifestyle">Phong Cách Sống</option>
         <option value="personal">Cá Nhân</option>
         <option value="health and fitness">Sức Khỏe</option>
         <option value="business">Kinh Doanh</option>
         <option value="shopping">Thời Trang</option>
         <option value="animations">Hoạt Hình</option>
      </select>
      <p>Chọn ảnh bài viết</p>
      <input type="file" name="image" class="box" accept="image/jpg, image/jpeg, image/png, image/webp">
      <div class="flex-btn">
         <input type="submit" value="Bài viết công khai" name="publish" class="btn">
         <input type="submit" value="Lưu Bản Nháp" name="draft" class="option-btn">
      </div>
   </form>

</section>










<!-- custom js file link  -->
<script src="../js/admin_script.js"></script>

</body>
</html>
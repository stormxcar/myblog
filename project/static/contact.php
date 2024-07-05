<?php
// Kết nối CSDL và truy vấn lấy giá trị bản quyền
include '../components/connect.php';

$select_lienhe_image = $conn->prepare("SELECT setting_value FROM `settings` WHERE setting_key = 'lienhe_image'");
$select_lienhe_image->execute();
$image_url = $select_lienhe_image->fetchColumn();

$select_lienhe_tieude = $conn->prepare("SELECT setting_value FROM `settings` WHERE setting_key = 'lienhe_tieude'");
$select_lienhe_tieude->execute();
$tieude_text = $select_lienhe_tieude->fetchColumn();

$select_lienhe_noidung = $conn->prepare("SELECT setting_value FROM `settings` WHERE setting_key = 'lienhe_noidung'");
$select_lienhe_noidung->execute();
$noidung_text = $select_lienhe_noidung->fetchColumn();

$select_email = $conn->prepare("SELECT setting_value FROM `settings` where setting_key = 'lienhe_email'");
$select_email->execute();
$email_text = $select_email->fetchColumn();

$select_name = $conn->prepare("SELECT setting_value FROM `settings` where setting_key = 'lienhe_name'");
$select_name->execute();
$name_text = $select_name->fetchColumn();


?>

<section class="contact" id="contact">
    <div class="contact_left">
        <img src="<?= $image_url?>" alt="">
    </div>
    <div class="contact_right">
        <div class="contact_right-top">
        <h1><?= $tieude_text?></h1>
        
        <p>
        <?= $noidung_text ?>
        </p>
        </div>
        <div class="contact_right-bottom">
        <h1>LIÊN HỆ</h1>
        <form action="" class="book_box" method=post>
            <h2>Email</h2>
            <input type="text" placeholder="<?= $email_text?>">
            <h2>Họ tên</h2>
            <input type="text" placeholder="<?= $name_text?>">
            <h2>Noi dung</h2>
           <textarea class="ckeditor" name="noi_dung" id="noi_dung" cols="20" rows="4"></textarea>
            <a href="mailto:nkha3561@gmail.com" id = "btnGui">Gửi</a>
        </form>
        </div>
    </div>
</section>
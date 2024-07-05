<?php
// Kết nối CSDL và truy vấn lấy giá trị bản quyền
include '../components/connect.php';

$select_footer_text = $conn->prepare("SELECT setting_value FROM `settings` WHERE setting_key = 'footer_text'");
$select_footer_text->execute();
$footer_text = $select_footer_text->fetchColumn();

// $conn->close();

$select_facebook_link = $conn->prepare("SELECT setting_value FROM `settings` WHERE setting_key = 'link_facebook'");
$select_facebook_link->execute();
$link_facebook = $select_facebook_link->fetchColumn();
$select_google_link = $conn->prepare("SELECT setting_value FROM `settings` WHERE setting_key = 'link_google'");
$select_google_link->execute();
$link_google = $select_google_link->fetchColumn();
$select_twitter_link = $conn->prepare("SELECT setting_value FROM `settings` WHERE setting_key = 'link_twitter'");
$select_twitter_link->execute();
$link_twitter = $select_twitter_link->fetchColumn();
$select_youtube_link = $conn->prepare("SELECT setting_value FROM `settings` WHERE setting_key = 'link_youtube'");
$select_youtube_link->execute();
$link_youtube = $select_youtube_link->fetchColumn();


$select_diachi = $conn->prepare("SELECT setting_value FROM `settings` where setting_key = 'lienhe_diachi'");
$select_diachi->execute();
$diachi_text = $select_diachi->fetchColumn();
$select_dienthoai = $conn->prepare("SELECT setting_value FROM `settings` where setting_key = 'lienhe_dienthoai'");
$select_dienthoai->execute();
$dienthoai_text = $select_dienthoai->fetchColumn();
$select_fax = $conn->prepare("SELECT setting_value FROM `settings` where setting_key = 'lienhe_fax'");
$select_fax->execute();
$fax_text = $select_fax->fetchColumn();
$select_email = $conn->prepare("SELECT setting_value FROM `settings` where setting_key = 'lienhe_email'");
$select_email->execute();
$email_text = $select_email->fetchColumn();
$select_zalo = $conn->prepare("SELECT setting_value FROM `settings` where setting_key = 'lienhe_zalo'");
$select_zalo->execute();
$zalo_text = $select_zalo->fetchColumn();
$select_name = $conn->prepare("SELECT setting_value FROM `settings` where setting_key = 'lienhe_name'");
$select_name->execute();
$name_text = $select_name->fetchColumn();
?>


<footer class="footer">
   <div class="footer_top">
      <div class="footer_top-gioithieu">
         <h1>BLOG CỦA TÔI</h1>
         <p>Một hành trình lan tỏa niềm đam mê với một tinh thần lạc quan. Hãy cùng tôi khám phá những trải nghiệm sống đầy màu sắc và tìm thấy cảm hứng trong từng câu chuyện của chính mình. Chúng ta sẽ cùng nhau tạo nên một không gian tràn đầy năng lượng tích cực và niềm vui bất tận.</p>
         <div class="icons">
            <a href="<?= $link_facebook?>" target="_blank"> <i class="fa-brands fa-facebook" style="color:blue";></i></a>
            <a href="<?= $link_google?>" target="_blank"> <i class="fa-brands fa-google" style="color:red";></i></a>
            <a href="<?= $link_twitter?>" target="_blank"> <i class="fa-brands fa-twitter" style="color:cyan";></i></a>
            <a href="<?= $link_youtube?>" target="_blank"> <i class="fa-brands fa-youtube" style="color:red";></i></a>

         </div>
      </div>
      <div class="footer_top-lienhe">
         <h1>THÔNG TIN LIÊN HỆ</h1>
         <ul>
            <li><span>- Địa chỉ: </span><?= $diachi_text?></li>
            <li><span>- Số điện thoại: </span><a href="tel:0584.344.344"><?= $dienthoai_text?></a></li>
            <li><span>- Fax: </span><a href="tel:0584.344.344"><?= $fax_text?></a></li>
            <li><span>- Email: </span><?= $email_text?></li>
            <li><span>- Zalo: </span><a href="<?= $zalo_text?>"><?= $name_text?></a></li>
         </ul>
      </div>
      <div class="footer_top-thongtin">
         <h1>DANH MỤC</h1>
         <ul>
            <li><a href="#home">Trang Chu</a></li>
            <li><a href="#introduce">Gioi Thieu</a></li>
            <li><a href="#news">Tin Tuc</a></li>
            <li><a href="#contact">Lien He</a></li>
         </ul>
      </div>
   </div>

   <div class="footer_bottom">
      &copy; copyright @ <?= date('Y'); ?> by <?= htmlspecialchars($footer_text) ?> | all rights reserved!
      <div class="scroll-to-top" onclick="scrollToTop()">
         <i class="fas fa-chevron-up"></i>
      </div>
   </div>

</footer>
<?php
include '../components/connect.php';

$select_gioithieu_tieude = $conn->prepare("SELECT setting_value FROM `settings` WHERE setting_key = 'gioithieu_tieude'");
$select_gioithieu_tieude->execute();
$tieude_text = $select_gioithieu_tieude->fetchColumn();

$select_gioithieu_slogan = $conn->prepare("SELECT setting_value FROM `settings` WHERE setting_key = 'gioithieu_slogan'");
$select_gioithieu_slogan->execute();
$slogan_text = $select_gioithieu_slogan->fetchColumn();

$select_gioithieu_tieude_1 = $conn->prepare("SELECT setting_value FROM `settings` WHERE setting_key = 'gioithieu_tieude_1'");
$select_gioithieu_tieude_1->execute();
$tieude_text_1 = $select_gioithieu_tieude_1->fetchColumn();
$select_gioithieu_noidung_1 = $conn->prepare("SELECT setting_value FROM `settings` WHERE setting_key = 'gioithieu_noidung_1'");
$select_gioithieu_noidung_1->execute();
$noidung_text_1 = $select_gioithieu_noidung_1->fetchColumn();

$select_gioithieu_tieude_2 = $conn->prepare("SELECT setting_value FROM `settings` WHERE setting_key = 'gioithieu_tieude_2'");
$select_gioithieu_tieude_2->execute();
$tieude_text_2 = $select_gioithieu_tieude_2->fetchColumn();
$select_gioithieu_noidung_2 = $conn->prepare("SELECT setting_value FROM `settings` WHERE setting_key = 'gioithieu_noidung_2'");
$select_gioithieu_noidung_2->execute();
$noidung_text_2 = $select_gioithieu_noidung_2->fetchColumn();

?>

<section class=introduce id="introduce">
    <div class="main_title">
        <h1>Giới Thiệu </h1>
        <span class="title"><?= $tieude_text ?></span>
        <p class="slogan">"<?= $slogan_text ?>"</p>
    </div>
    <div class="sperator"></div>
    <div class="content">
        <p><span><?= $tieude_text_1?>: </span>
           <?= $noidung_text_1?>
        </p>
        <p><span><?= $tieude_text_2?>: </span>
           <?= $noidung_text_2?>
        </p>
    </div>
</section>
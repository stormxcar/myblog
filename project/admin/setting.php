<?php

include '../components/connect.php';

session_start();

$admin_id = $_SESSION['admin_id'];

if (!isset($admin_id)) {
    header('location:admin_login.php');
}

if (isset($_POST['save'])) {

    $post_id = $_GET['id'];
    $title = $_POST['title'];
    $title = filter_var($title, FILTER_SANITIZE_STRING);
    $content = $_POST['content'];
    $content = filter_var($content, FILTER_SANITIZE_STRING);
    $category = $_POST['category'];
    $category = filter_var($category, FILTER_SANITIZE_STRING);
    $status = $_POST['status'];
    $status = filter_var($status, FILTER_SANITIZE_STRING);

    $update_post = $conn->prepare("UPDATE `posts` SET title = ?, content = ?, category = ?, status = ? WHERE id = ?");
    $update_post->execute([$title, $content, $category, $status, $post_id]);

    $message[] = 'post updated!';

    $old_image = $_POST['old_image'];
    $image = $_FILES['image']['name'];
    $image = filter_var($image, FILTER_SANITIZE_STRING);
    $image_size = $_FILES['image']['size'];
    $image_tmp_name = $_FILES['image']['tmp_name'];
    $image_folder = '../uploaded_img/' . $image;

    $select_image = $conn->prepare("SELECT * FROM `posts` WHERE image = ? AND admin_id = ?");
    $select_image->execute([$image, $admin_id]);

    if (!empty($image)) {
        if ($image_size > 2000000) {
            $message[] = 'images size is too large!';
        } elseif ($select_image->rowCount() > 0 and $image != '') {
            $message[] = 'please rename your image!';
        } else {
            $update_image = $conn->prepare("UPDATE `posts` SET image = ? WHERE id = ?");
            move_uploaded_file($image_tmp_name, $image_folder);
            $update_image->execute([$image, $post_id]);
            if ($old_image != $image and $old_image != '') {
                unlink('../uploaded_img/' . $old_image);
            }
            $message[] = 'image updated!';
        }
    }
}

if (isset($_POST['delete_post'])) {

    $post_id = $_POST['post_id'];
    $post_id = filter_var($post_id, FILTER_SANITIZE_STRING);
    $delete_image = $conn->prepare("SELECT * FROM `posts` WHERE id = ?");
    $delete_image->execute([$post_id]);
    $fetch_delete_image = $delete_image->fetch(PDO::FETCH_ASSOC);
    if ($fetch_delete_image['image'] != '') {
        unlink('../uploaded_img/' . $fetch_delete_image['image']);
    }
    $delete_post = $conn->prepare("DELETE FROM `posts` WHERE id = ?");
    $delete_post->execute([$post_id]);
    $delete_comments = $conn->prepare("DELETE FROM `comments` WHERE post_id = ?");
    $delete_comments->execute([$post_id]);
    $message[] = 'post deleted successfully!';
}

if (isset($_POST['delete_image'])) {

    $empty_image = '';
    $post_id = $_POST['post_id'];
    $post_id = filter_var($post_id, FILTER_SANITIZE_STRING);
    $delete_image = $conn->prepare("SELECT * FROM `posts` WHERE id = ?");
    $delete_image->execute([$post_id]);
    $fetch_delete_image = $delete_image->fetch(PDO::FETCH_ASSOC);
    if ($fetch_delete_image['image'] != '') {
        unlink('../uploaded_img/' . $fetch_delete_image['image']);
    }
    $unset_image = $conn->prepare("UPDATE `posts` SET image = ? WHERE id = ?");
    $unset_image->execute([$empty_image, $post_id]);
    $message[] = 'image deleted successfully!';
}

// update logo
if (isset($_POST['save_logo'])) {
    $logo = $_FILES['logo']['name'];
    $logo = filter_var($logo, FILTER_SANITIZE_STRING);
    $logo_size = $_FILES['logo']['size'];
    $logo_tmp_name = $_FILES['logo']['tmp_name'];
    $logo_folder = '../uploaded_img/' . $logo;

    if (!empty($logo)) {
        if ($logo_size > 200000) {
            $message[] = 'image size is too large!';
        } else {
            $update_logo = $conn->prepare("SELECT setting_value FROM `settings` WHERE setting_key = 'logo'");
            $update_logo->execute();
            $fetch_logo = $update_logo->fetch(PDO::FETCH_ASSOC);
            $old_logo = $fetch_logo['setting_value'];

            $update_logo = $conn->prepare("UPDATE `settings` SET setting_value = ? WHERE setting_key = 'logo'");
            $update_logo->execute([$logo_folder]);

            // Move the new logo file to the upload folder
            move_uploaded_file($logo_tmp_name, $logo_folder);

            // Delete the old logo file if it's different from the new one
            if ($old_logo && $old_logo != $logo_folder && file_exists($old_logo)) {
                unlink($old_logo);
            }

            $message[] = 'Logo da duoc cap nhat!';
        }
    } else {
        $message[] = "vui long chon logo muon thay doi";
    }
}

// update licenseif 
if (isset($_POST['save_license'])) {
    $license_text = $_POST['license_text'];
    $license_text = filter_var($license_text, FILTER_SANITIZE_STRING);

    $update_license = $conn->prepare("UPDATE `settings` SET setting_value = ? WHERE setting_key = 'footer_text'");
    $update_license->execute([$license_text]);

    $message[] = 'License updated successfully!';
}

// update image slide banner
if (isset($_POST['save_image_slide'])) {
    // nên khai báo biến là số silde về sau
    for ($i = 1; $i <= 4; $i++) {
        $image_key = 'image_' . $i;
        $image_path = '../uploaded_img/banner_slide_' . $i . '.' . pathinfo($_FILES[$image_key]['name'], PATHINFO_EXTENSION);

        if (move_uploaded_file($_FILES[$image_key]['tmp_name'], $image_path)) {
            $update_image = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
            $update_image->execute([$image_path, 'banner_slide_' . $i]);
        }
    }

    $message[] = 'Slide ảnh đã được cập nhật thành công!';
}

// update title in gioithieu part
if (isset($_POST['save_gioithieu'])) {
    $settings = $conn->query("SELECT setting_key, setting_value FROM `settings`")->fetchAll(PDO::FETCH_KEY_PAIR);

    $gioithieu_tieude = $_POST['tieude'];
    $gioithieu_slogan = $_POST['slogan'];
    $gioithieu_tieude_1 = $_POST['gioithieu_tieude_1'];
    $gioithieu_noidung_1 = $_POST['gioithieu_noidung_1'];
    $gioithieu_tieude_2 = $_POST['gioithieu_tieude_2'];
    $gioithieu_noidung_2 = $_POST['gioithieu_noidung_2'];

    $social_facebook = $_POST['social_facebook'];
    $social_gmail = $_POST['social_gmail'];
    $social_twitter = $_POST['social_twitter'];
    $social_youtube = $_POST['social_youtube'];

    // Update title
    if (!empty($gioithieu_tieude) && $gioithieu_tieude !== $settings['gioithieu_tieude']) {
        $gioithieu_tieude = filter_var($gioithieu_tieude, FILTER_SANITIZE_STRING);
        $update_gioithieu_tieude = $conn->prepare("UPDATE `settings` SET setting_value = ? WHERE setting_key = 'gioithieu_tieude'");
        $update_gioithieu_tieude->execute([$gioithieu_tieude]);
        $message[] = 'Tiêu đề giới thiệu đã được cập nhật!';
    }

    if (!empty($gioithieu_slogan) && $gioithieu_slogan !== $settings['gioithieu_slogan']) {
        $gioithieu_slogan = filter_var($gioithieu_slogan, FILTER_SANITIZE_STRING);
        $update_gioithieu_slogan = $conn->prepare("UPDATE `settings` SET setting_value = ? WHERE setting_key = 'gioithieu_slogan'");
        $update_gioithieu_slogan->execute([$gioithieu_slogan]);
        $message[] = 'Slogan đã được cập nhật!';
    }

    // Update nội dung giới thiệu
    if (!empty($gioithieu_tieude_1) && $gioithieu_tieude_1 !== $settings['gioithieu_tieude_1']) {
        $gioithieu_tieude_1 = filter_var($gioithieu_tieude_1, FILTER_SANITIZE_STRING);
        $update_gioithieu_tieude_1 = $conn->prepare("UPDATE `settings` SET setting_value = ? WHERE setting_key = 'gioithieu_tieude_1'");
        $update_gioithieu_tieude_1->execute([$gioithieu_tieude_1]);
        $message[] = 'Tiêu đề giới thiệu đã được cập nhật!';
    }
    if (!empty($gioithieu_noidung_1) && $gioithieu_noidung_1 !== $settings['gioithieu_noidung_1']) {
        $gioithieu_noidung_1 = filter_var($gioithieu_noidung_1, FILTER_SANITIZE_STRING);
        $update_gioithieu_noidung_1 = $conn->prepare("UPDATE `settings` SET setting_value = ? WHERE setting_key = 'gioithieu_noidung_1'");
        $update_gioithieu_noidung_1->execute([$gioithieu_noidung_1]);
        $message[] = 'Nội dung giới thiệu đã được cập nhật!';
    }
    if (!empty($gioithieu_tieude_2) && $gioithieu_tieude_2 !== $settings['gioithieu_tieude_2']) {
        $gioithieu_tieude_2 = filter_var($gioithieu_tieude_2, FILTER_SANITIZE_STRING);
        $update_gioithieu_tieude_2 = $conn->prepare("UPDATE `settings` SET setting_value = ? WHERE setting_key = 'gioithieu_tieude_2'");
        $update_gioithieu_tieude_2->execute([$gioithieu_tieude_2]);
        $message[] = 'Tiêu đề giới thiệu đã được cập nhật!';
    }
    if (!empty($gioithieu_noidung_2) && $gioithieu_noidung_2 !== $settings['gioithieu_noidung_2']) {
        $gioithieu_noidung_2 = filter_var($gioithieu_noidung_2, FILTER_SANITIZE_STRING);
        $update_gioithieu_noidung_2 = $conn->prepare("UPDATE `settings` SET setting_value = ? WHERE setting_key = 'gioithieu_noidung_2'");
        $update_gioithieu_noidung_2->execute([$gioithieu_noidung_2]);
        $message[] = 'Nội dung giới thiệu đã được cập nhật!';
    }

    // Update socials
    if (!empty($social_facebook) && $social_facebook !== $settings['link_facebook']) {
        $social_facebook = filter_var($social_facebook, FILTER_SANITIZE_URL);
        $update_social_facebook = $conn->prepare("UPDATE `settings` SET setting_value = ? WHERE setting_key = 'link_facebook'");
        $update_social_facebook->execute([$social_facebook]);
        $message[] = 'Link Facebook đã được cập nhật!';
    }

    if (!empty($social_gmail) && $social_gmail !== $settings['link_google']) {
        $social_gmail = filter_var($social_gmail, FILTER_SANITIZE_URL);
        $update_social_gmail = $conn->prepare("UPDATE `settings` SET setting_value = ? WHERE setting_key = 'link_google'");
        $update_social_gmail->execute([$social_gmail]);
        $message[] = 'Link Gmail đã được cập nhật!';
    }

    if (!empty($social_twitter) && $social_twitter !== $settings['link_twitter']) {
        $social_twitter = filter_var($social_twitter, FILTER_SANITIZE_URL);
        $update_social_twitter = $conn->prepare("UPDATE `settings` SET setting_value = ? WHERE setting_key = 'link_twitter'");
        $update_social_twitter->execute([$social_twitter]);
        $message[] = 'Link Twitter đã được cập nhật!';
    }

    if (!empty($social_youtube) && $social_youtube !== $settings['link_youtube']) {
        $social_youtube = filter_var($social_youtube, FILTER_SANITIZE_URL);
        $update_social_youtube = $conn->prepare("UPDATE `settings` SET setting_value = ? WHERE setting_key = 'link_youtube'");
        $update_social_youtube->execute([$social_youtube]);
        $message[] = 'Link Youtube đã được cập nhật!';
    }
}

// update lienhe 
if (isset($_POST['save_lienhe'])) {
    $settings = $conn->query("SELECT setting_key , setting_value FROM `settings`")->fetchAll(PDO::FETCH_KEY_PAIR);

    $lienhe_image = $_POST['lienhe_image'];
    $lienhe_tieude = $_POST['lienhe_tieude'];
    $lienhe_noidung = $_POST['lienhe_noidung'];

    $lienhe_diachi = $_POST['lienhe_diachi'];
    $lienhe_dienthoai = $_POST['lienhe_dienthoai'];
    $lienhe_fax = $_POST['lienhe_fax'];
    $lienhe_email = $_POST['lienhe_email'];
    $lienhe_zalo = $_POST['lienhe_zalo'];
    $lienhe_name = $POST['lienhe_name'];
    if (!empty($lienhe_image) && $lienhe_image !== $settings['lienhe_image']) {
        $lienhe_image = filter_var($lienhe_image, FILTER_SANITIZE_URL);
        $update_lienhe_image = $conn->prepare("UPDATE `settings` SET setting_value = ? WHERE setting_key = 'lienhe_image'");
        $update_lienhe_image->execute([$lienhe_image]);
        $message[] = 'Cập nhật ảnh thành công!';
    }

    if (!empty($lienhe_tieude) && $lienhe_tieude !== $settings['lienhe_tieude']) {
        $lienhe_tieude = filter_var($lienhe_tieude, FILTER_SANITIZE_STRING);
        $update_lienhe_tieude = $conn->prepare("UPDATE `settings` SET setting_value = ? WHERE setting_key = 'lienhe_tieude'");
        $update_lienhe_tieude->execute([$lienhe_tieude]);
        $message[] = 'Cập nhật tiêu đề thành công !';
    }

    if (!empty($lienhe_noidung) && $lienhe_noidung !== $settings['lienhe_noidung']) {
        $lienhe_noidung = filter_var($lienhe_noidung, FILTER_SANITIZE_STRING);
        $update_lienhe_noidung = $conn->prepare("UPDATE `settings` SET setting_value = ? WHERE setting_key = 'lienhe_noidung'");
        $update_lienhe_noidung->execute([$lienhe_noidung]);
        $message[] = 'Cập nhật nội dung giới thiệu này !';
    }

    if (!empty($lienhe_diachi) && $lienhe_diachi !== $settings['lienhe_diachi']) {
        $lienhe_diachi = filter_var($lienhe_diachi, FILTER_SANITIZE_STRING);
        $update_lienhe_diachi = $conn->prepare("UPDATE `settings` SET setting_value = ? WHERE setting_key = 'lienhe_diachi'");
        $update_lienhe_diachi->execute([$lienhe_diachi]);
        $message[] = 'Cập nhật địa chỉ thành công!';
    }

    if (!empty($lienhe_dienthoai) && $lienhe_dienthoai !== $settings['lienhe_dienthoai']) {
        $lienhe_dienthoai = filter_var($lienhe_dienthoai, FILTER_SANITIZE_STRING);
        $update_lienhe_dienthoai = $conn->prepare("UPDATE `settings` SET setting_value = ? WHERE setting_key = 'lienhe_dienthoai'");
        $update_lienhe_dienthoai->execute([$lienhe_dienthoai]);
        $message[] = 'Cập nhật số điện thoại thành công!';
    }

    if (!empty($lienhe_fax) && $lienhe_fax !== $settings['lienhe_fax']) {
        $lienhe_fax = filter_var($lienhe_fax, FILTER_SANITIZE_STRING);
        $update_lienhe_fax = $conn->prepare("UPDATE `settings` SET setting_value = ? WHERE setting_key = 'lienhe_fax'");
        $update_lienhe_fax->execute([$lienhe_fax]);
        $message[] = 'Cập nhật fax bạn được cập nhật!';
    }

    if (!empty($lienhe_email) && $lienhe_email !== $settings['lienhe_email']) {
        $lienhe_email = filter_var($lienhe_email, FILTER_SANITIZE_STRING);
        $update_lienhe_email = $conn->prepare("UPDATE `settings` SET setting_value = ? WHERE setting_key = 'lienhe_email'");
        $update_lienhe_email->execute([$lienhe_email]);
        $message[] = 'Cập nhật email được cập nhật!';
    }

    if (!empty($lienhe_zalo) && $lienhe_zalo !== $settings['lienhe_zalo']) {
        $lienhe_zalo = filter_var($lienhe_zalo, FILTER_SANITIZE_URL);
        $update_lienhe_zalo = $conn->prepare("UPDATE `settings` SET setting_value = ? WHERE setting_key = 'lienhe_zalo'");
        $update_lienhe_zalo->execute([$lienhe_zalo]);
        $message[] = 'Cập nhật zalo được cập nhật!';
    }
    if (!empty($lienhe_name) && $lienhe_name !== $settings['lienhe_name']) {
        $lienhe_name = filter_var($lienhe_email, FILTER_SANITIZE_STRING);
        $update_lienhe_name = $conn->prepare("UPDATE `settings` SET setting_value = ? WHERE setting_key = 'lienhe_name'");
        $update_lienhe_name->execute([$lienhe_email]);
        $message[] = 'Cập nhật tên được cập nhật!';
    }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thiết lập giao diện</title>

    <!-- font awesome cdn link  -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

    <!-- custom css file link  -->
    <link rel="stylesheet" href="../css/admin_style_edit.css">

</head>

<body>

    <?php include '../components/admin_header.php' ?>

    <section class="setting">

        <h1 class="heading">Thiết lập hiển thị giao diện</h1>

        <!-- thêm ảnh banner và upload vào mục ảnh -->

        <form class="banner_edit_form" action="setting.php" method="post" enctype="multipart/form-data">
            <p class="title_banner">Tùy chỉnh banner</p>
            <div class="image-container">
                <div class="banner_img">
                    <div class="banner_img_infor">
                        <span>Ảnh banner 1: </span>
                        <input id="banner_1" type="file" name="image_1" accept="image/jpg, image/jpeg, image/png , image/avif" onchange="previewImage('banner_1')">
                    </div>
                    <img id="banner_1_preview" class="show_img" src="../uploaded_img/default_img.png" alt="image slide show here !">
                </div>
                <div class="banner_img">
                    <div class="banner_img_infor">
                        <span>Ảnh banner 2: </span>
                        <input id="banner_2" type="file" name="image_2" accept="image/jpg, image/jpeg, image/png , image/avif" onchange="previewImage('banner_2')">
                    </div>
                    <img id="banner_2_preview" class="show_img" src="" alt="image slide show here !">
                </div>
                <div class="banner_img">
                    <div class="banner_img_infor">
                        <span>Ảnh banner 3: </span>
                        <input id="banner_3" type="file" name="image_3" accept="image/jpg, image/jpeg, image/png , image/avif" onchange="previewImage('banner_3')">
                    </div>
                    <img id="banner_3_preview" class="show_img" src="../uploaded_img/default_img.png" alt="image slide show here !">
                </div>
                <div class="banner_img">
                    <div class="banner_img_infor">
                        <span>Ảnh banner 4: </span>
                        <input id="banner_4" type="file" name="image_4" accept="image/jpg, image/jpeg, image/png , image/avif" onchange="previewImage('banner_4')">
                    </div>
                    <img id="banner_4_preview" class="show_img" src="../uploaded_img/default_img.png" alt="image slide show here !">
                </div>
            </div>
            <div class="btn_handle">
                <button type="reset" class="reset_btn">Làm mới</button>
                <button type="submit" class="submit_btn" name="save_image_slide">Xác nhận</button>
            </div>
        </form>

        <form class="footer_edit_form" action="setting.php" method="post" enctype="multipart/form-data">
            <p class="title_banner">Tùy chỉnh footer</p>
            <div class="detail_handle">
                <div class="btn_top">
                    <button type="button" class="tablink" onclick="openPage('gioithieu', this, 'var(--main-color)')" id="defaultOpen">Giới thiệu</button>
                    <button type="button" class="tablink" onclick="openPage('lienhe', this, 'var(--main-color)')">Liên hệ</button>
                    <button type="button" class="tablink" onclick="openPage('thongtin', this, 'var(--main-color)')">Thông tin</button>
                </div>
                <div id="gioithieu" class="tabcontent">
                    <div class="detail_gioithieu">
                        <h3>Giới thiệu:</h3>
                        <div class="detail_gioithieu_tieude">
                            <h4>Giới thiệu</h4>
                            <?php
                            $settings = $conn->query("SELECT setting_key, setting_value FROM `settings`")->fetchAll(PDO::FETCH_KEY_PAIR);

                            $gioithieu_tieude = $settings['gioithieu_tieude'] ?? '';
                            $gioithieu_slogan = $settings['gioithieu_slogan'] ?? '';
                            $gioithieu_tieude_1 = $settings['gioithieu_tieude_1'] ?? '';
                            $gioithieu_noidung_1 = $settings['gioithieu_noidung_1'] ?? '';
                            $gioithieu_tieude_2 = $settings['gioithieu_tieude_2'] ?? '';
                            $gioithieu_noidung_2 = $settings['gioithieu_noidung_2'] ?? '';
                            $social_facebook = $settings['link_facebook'] ?? '';
                            $social_gmail = $settings['link_google'] ?? '';
                            $social_twitter = $settings['link_twitter'] ?? '';
                            $social_youtube = $settings['link_youtube'] ?? '';
                            ?>
                            <input type="text" name="tieude" placeholder="<?= $gioithieu_tieude ?>">
                        </div>
                        <div class="detail_gioithieu_slogan">
                            <h4>Slogan</h4>
                            <input type="text" name="slogan" placeholder="<?= $gioithieu_slogan ?>">
                        </div>
                        <div class="sperator"></div>
                        <div class="detail_gioithieu_noidung" id="detail_gioithieu_noidung">
                            <div class="section-container" id="section-1">
                                <h4>Tiêu đề 1</h4>
                                <input type="text" name="gioithieu_tieude_1" placeholder="<?= $gioithieu_tieude_1 ?>" style="text-transform:uppercase">
                                <h4>Nội dung 1</h4>
                                <textarea name="gioithieu_noidung_1" id="gioithieu" cols="30" rows="5" placeholder="<?= $gioithieu_noidung_1 ?>"></textarea>
                            </div>
                            <div class="section-container" id="section-2">
                                <h4>Tiêu đề 2</h4>
                                <input type="text" name="gioithieu_tieude_2" placeholder="<?= $gioithieu_tieude_2 ?>" style="text-transform:uppercase">
                                <h4>Nội dung 2</h4>
                                <textarea name="gioithieu_noidung_2" id="gioithieu_2" cols="30" rows="5" placeholder="<?= $gioithieu_noidung_1 ?>"></textarea>
                            </div>


                        </div>
                        <button class="add_tieude_noidung" style="width:100px" >Thêm</button>
                    </div>
                    <div class="detail_socials">
                        <h3>Socials:</h3>
                        <label for="">
                            <div class="top">
                                <i class="fa-brands fa-facebook"></i>
                                <span>Facebook</span>
                            </div>
                            <input type="text" name="social_facebook" placeholder="<?= $social_facebook ?>">
                        </label>
                        <label for="">
                            <div class="top">
                                <i class="fa-brands fa-facebook"></i>
                                <span>Gmail</span>
                            </div>
                            <input type="text" name="social_gmail" placeholder="<?= $social_gmail ?>">
                        </label>
                        <label for="">
                            <div class="top">
                                <i class="fa-brands fa-facebook"></i>
                                <span>Twitter</span>
                            </div>
                            <input type="text" name="social_twitter" placeholder="<?= $social_twitter ?>">
                        </label>
                        <label for="">
                            <div class="top">
                                <i class="fa-brands fa-facebook"></i>
                                <span>Youtube</span>
                            </div>
                            <input type="text" name="social_youtube" placeholder="<?= $social_youtube ?>">
                        </label>
                        <div class="bottom">
                            <button type="button" class="add_social">Them</button>
                            <div class="btn_handle">
                                <button type="reset" class="reset_btn">Làm mới</button>
                                <button type="submit" class="submit_btn" name="save_gioithieu">Xác nhận</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="lienhe" class="tabcontent">
                    <?php
                    $settings = $conn->query("SELECT setting_key, setting_value FROM `settings`")->fetchAll(PDO::FETCH_KEY_PAIR);

                    $lienhe_image = $settings['lienhe_image'] ?? '';
                    $lienhe_tieude = $settings['lienhe_tieude'] ?? '';
                    $lienhe_noidung = $settings['lienhe_noidung'] ?? '';

                    $lienhe_diachi = $settings['lienhe_diachi'] ?? '';
                    $lienhe_dienthoai = $settings['lienhe_dienthoai'] ?? '';
                    $lienhe_fax = $settings['lienhe_fax'] ?? '';
                    $lienhe_email = $settings['lienhe_email'] ?? '';
                    $lienhe_zalo = $settings['lienhe_zalo'] ?? '';
                    $lienhe_name = $settings['lienhe_name'] ?? '';
                    ?>
                    <div class="lienhe_image">
                        <h4>Chọn ảnh: </h4>
                        <input type="file" name="lienhe_image" id="lienhe_image" onchange="previewImage('lienhe_image')" value="<?= $lienhe_image ?>">
                        <img id="lienhe_image_preview" class="show_img" src="<?= $lienhe_image ?>" alt="image slide show here !">
                    </div>
                    <div class="lienhe_tieude">
                        <h4>Tiêu đề</h4>
                        <input type="text" name="lienhe_tieude" placeholder="<?= $lienhe_tieude ?>" style="text-transform:uppercase" ?>">
                    </div>
                    <div class="lienhe_noidung">
                        <h4>Nội dung</h4>
                        <textarea name="lienhe_noidung" id="" rows="10" placeholder="<?= $lienhe_noidung ?>"></textarea>
                    </div>
                    <label for="">
                        <div class="top">
                            <i class="fa-brands fa-facebook"></i>
                            <span>Địa chỉ:</span>
                        </div>
                        <input type="text" placeholder="<?= $lienhe_diachi ?>" name="lienhe_diachi">
                    </label>
                    <label for="">
                        <div class="top">
                            <i class="fa-brands fa-facebook"></i>
                            <span>Số điện thoại:</span>
                        </div>
                        <input type="text" placeholder="<?= $lienhe_dienthoai ?>" name="lienhe_dienthoai">
                    </label>
                    <label for="">
                        <div class="top">
                            <i class="fa-brands fa-facebook"></i>
                            <span>Fax: </span>
                        </div>
                        <input type="text" placeholder="<?= $lienhe_fax ?>" name="lienhe_fax">
                    </label>
                    <label for="">
                        <div class="top">
                            <i class="fa-brands fa-facebook"></i>
                            <span>Email: </span>
                        </div>
                        <input type="text" placeholder="<?= $lienhe_email ?>" name="lienhe_email">
                    </label>
                    <label for="">
                        <div class="top">
                            <i class="fa-brands fa-facebook"></i>
                            <span>Zalo: </span>
                        </div>
                        <input type="text" placeholder="<?= $lienhe_zalo ?>" name="lienhe_zalo">
                    </label>
                    <label for="">
                        <div class="top">
                            <i class="fa-brands fa-facebook"></i>
                            <span>Name: </span>
                        </div>
                        <input type="text" placeholder="<?= $lienhe_name ?>" name="lienhe_name">
                    </label>
                    <div class="bottom">
                        <button type="button" class="add_social">Them</button>
                        <div class="btn_handle">
                            <button type="reset" class="reset_btn">Làm mới</button>
                            <button type="submit" class="submit_btn" name="save_lienhe">Xác nhận</button>
                        </div>
                    </div>
                </div>

                <div id="thongtin" class="tabcontent">
                    <h3>Thông tin</h3>
                </div>
            </div>
        </form>


        <form action="setting.php" method="post" enctype="multipart/form-data">
            <div class="diff_edit_form">
                <div class="logo_edit">
                    <h3>Tùy chỉnh logo</h3>
                    <div class="detail_logo_edit">
                        <h4>Chọn logo khác thay thế:</h4>
                        <input type="file" name="logo" id="logo" onchange="previewImage('logo')">
                        <img id="logo_preview" src="../uploaded_img/default_img.png" alt="logo show here !">
                    </div>
                    <div class="btn_handle">
                        <button type="reset" class="reset_btn">Làm mới</button>
                        <button type="submit" class="submit_btn" name="save_logo">Xác nhận</button>
                    </div>
                </div>

                <div class="license_edit">
                    <h3>Tùy chỉnh bản quyền</h3>
                    <div class="detail_license_edit">
                    <?php
                            $settings = $conn->query("SELECT setting_key, setting_value FROM `settings`")->fetchAll(PDO::FETCH_KEY_PAIR);

                            $license_text = $settings['footer_text'] ?? '';
                            ?>
                        <h4>Nhập bản quyền:</h4>
                        <input type="text" name="license_text" value="" placeholder="<?= $license_text?>">
                    </div>
                    <div class="btn_handle">
                        <button type="reset" class="reset_btn">Làm mới</button>
                        <button type="submit" class="submit_btn" name="save_license">Xác nhận</button>
                    </div>
                </div>
            </div>
        </form>


    </section>

    <script>
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



/*
        function previewLogo() {
            const file = document.getElementById('logo').files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('logo_preview').src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        }
            */


        function previewImage(inputId) {
            const fileInput = document.getElementById(inputId);
            const previewId = `${inputId}_preview`;
            const previewImage = document.getElementById(previewId);

            const file = fileInput.files[0];
            const reader = new FileReader();

            reader.onload = function(e) {
                previewImage.src = e.target.result;
            };

            if (file) {
                reader.readAsDataURL(file);
            } else {
                previewImage.src = ''; // Nếu không có file được chọn
            }
        }

       
    </script>


    <!-- custom js file link  -->
    <script src="../js/admin_script.js"></script>



</body>

</html>
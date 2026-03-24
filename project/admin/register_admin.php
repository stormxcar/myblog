<?php

include '../components/connect.php';

session_start();

$admin_id = $_SESSION['admin_id'] ?? null;
if (!$admin_id) {
    header('location:admin_login.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $name = trim((string)($_POST['name'] ?? ''));
    $name = filter_var($name, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $rawPass = (string)($_POST['pass'] ?? '');
    $rawConfirmPass = (string)($_POST['cpass'] ?? '');
    $selectedRole = (string)($_POST['role'] ?? 'admin');
    $selectedRole = in_array($selectedRole, ['admin', 'user'], true) ? $selectedRole : 'admin';

    $emailRaw = trim((string)($_POST['email'] ?? ''));
    $email = filter_var($emailRaw, FILTER_SANITIZE_EMAIL);

    if ($name === '' || $email === '' || $rawPass === '' || $rawConfirmPass === '') {
        $message[] = 'Vui lòng nhập đầy đủ tên tài khoản, email và mật khẩu.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message[] = 'Email không hợp lệ.';
    } elseif (strlen($email) > 50) {
        $message[] = 'Email tối đa 50 ký tự theo cấu trúc dữ liệu hiện tại.';
    } else {
        $select_user = $conn->prepare('SELECT id FROM users WHERE name = ? OR email = ? LIMIT 1');
        $select_user->execute([$name, $email]);

        if ($select_user->rowCount() > 0) {
            $message[] = 'Tên tài khoản hoặc email đã tồn tại.';
        } else {
            if ($rawPass !== $rawConfirmPass) {
                $message[] = 'Mật khẩu nhập lại không khớp.';
            } else {
                $passwordHash = password_hash($rawPass, PASSWORD_DEFAULT);
                $conn->beginTransaction();
                try {
                    $legacyAdminId = null;

                    // Optional backward-compatibility: keep legacy admin table in sync only for role=admin.
                    if ($selectedRole === 'admin' && blog_db_has_column($conn, 'admin', 'id') && blog_db_has_column($conn, 'admin', 'name') && blog_db_has_column($conn, 'admin', 'password')) {
                        $insert_legacy = $conn->prepare('INSERT INTO admin(name, password) VALUES(?, ?)');
                        $insert_legacy->execute([$name, $passwordHash]);
                        $legacyAdminId = (int)$conn->lastInsertId();
                    }

                    $insertColumns = ['name', 'email', 'password'];
                    $insertValues = [$name, $email, $passwordHash];

                    if (blog_db_has_column($conn, 'users', 'banned')) {
                        $insertColumns[] = 'banned';
                        $insertValues[] = 0;
                    }

                    if (blog_db_has_column($conn, 'users', 'level_of_interaction')) {
                        $insertColumns[] = 'level_of_interaction';
                        $insertValues[] = 'Thấp';
                    }

                    if (blog_db_has_column($conn, 'users', 'reset_code')) {
                        $insertColumns[] = 'reset_code';
                        $insertValues[] = null;
                    }

                    if (blog_db_has_column($conn, 'users', 'role')) {
                        $insertColumns[] = 'role';
                        $insertValues[] = $selectedRole;
                    }

                    if (blog_db_has_column($conn, 'users', 'legacy_admin_id')) {
                        $insertColumns[] = 'legacy_admin_id';
                        $insertValues[] = $legacyAdminId;
                    }

                    if (blog_db_has_column($conn, 'users', 'is_verified')) {
                        $insertColumns[] = 'is_verified';
                        $insertValues[] = 1;
                    }

                    if (blog_db_has_column($conn, 'users', 'verified_at')) {
                        $insertColumns[] = 'verified_at';
                        $insertValues[] = gmdate('Y-m-d H:i:s');
                    }

                    if (blog_db_has_column($conn, 'users', 'verified_by_admin_id')) {
                        $insertColumns[] = 'verified_by_admin_id';
                        $insertValues[] = (int)$admin_id;
                    }

                    $colsSql = implode(', ', $insertColumns);
                    $holdersSql = implode(', ', array_fill(0, count($insertColumns), '?'));
                    $insert_user = $conn->prepare("INSERT INTO users({$colsSql}) VALUES({$holdersSql})");
                    $insert_user->execute($insertValues);

                    $conn->commit();
                    $_SESSION['message'] = $selectedRole === 'admin'
                        ? 'Tài khoản admin mới đã được thêm.'
                        : 'Tài khoản user mới đã được thêm.';
                    header('location:users_accounts.php');
                    exit;
                } catch (Exception $e) {
                    if ($conn->inTransaction()) {
                        $conn->rollBack();
                    }
                    $message[] = 'Không thể tạo tài khoản mới: ' . $e->getMessage();
                }
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tạo tài khoản</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
</head>

<body>

    <?php include '../components/admin_header.php' ?>

    <section class="form-container">

        <form action="" method="POST">
            <h3>Tạo tài khoản mới</h3>
            <p style="margin-bottom:1rem;color:#334155;font-size:.92rem;">Tài khoản tạo sẽ được xác thực email tự động bởi admin.</p>
            <?php if (!empty($message) && is_array($message)): ?>
                <div style="margin-bottom:1rem;padding:.9rem 1rem;border-radius:.6rem;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;">
                    <?php foreach ($message as $msg): ?>
                        <div><?= htmlspecialchars((string)$msg, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <label for="admin_name">
                <span>Tên tài khoản</span>
                <input type="text" name="name" maxlength="20" required placeholder="Nhập tên tài khoản" class="box" value="<?= htmlspecialchars((string)($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" oninput="this.value = this.value.replace(/\s/g, '')">
            </label>

            <label for="admin_email">
                <span>Email tài khoản</span>
                <input type="email" name="email" maxlength="50" required placeholder="Nhập email tài khoản" class="box" value="<?= htmlspecialchars((string)($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" oninput="this.value = this.value.replace(/\s/g, '')">
            </label>

            <label for="account_role">
                <span>Vai trò tài khoản</span>
                <select name="role" class="box" required>
                    <option value="admin" <?= (($_POST['role'] ?? 'admin') === 'admin') ? 'selected' : ''; ?>>Admin</option>
                    <option value="user" <?= (($_POST['role'] ?? 'admin') === 'user') ? 'selected' : ''; ?>>User</option>
                </select>
            </label>

            <label for="admin_pass">
                <span>Mật khẩu</span>
                <input type="password" name="pass" maxlength="20" required placeholder="Nhập mật khẩu" class="box" oninput="this.value = this.value.replace(/\s/g, '')">
            </label>

            <label for="admin_pass_conf">
                <span>Xác nhận mật khẩu</span>
                <input type="password" name="cpass" maxlength="20" required placeholder="Nhập lại mật khẩu" class="box" oninput="this.value = this.value.replace(/\s/g, '')">
            </label>

            <input type="submit" value="Tạo tài khoản" name="submit" class="btn">
        </form>

    </section>

    <script src="../js/admin_script.js"></script>

</body>

</html>
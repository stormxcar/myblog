<?php
require __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/cloudinary.php';
require_once __DIR__ . '/post_tags.php';

// In production (Heroku), suppress HTML warning output to avoid invalid JSON responses.
if (getenv('DYNO') !== false) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
} else {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

try {
    // Load environment variables
    if (getenv('DYNO') === false) {
        // Method 1: Try Dotenv
        try {
            $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../../');
            $dotenv->load();
        } catch (Exception $e) {
            // Method 2: Manual parsing as fallback
            $envFile = __DIR__ . '/../../.env';
            if (file_exists($envFile)) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos($line, '=') !== false && !str_starts_with(trim($line), '#')) {
                        list($key, $value) = explode('=', $line, 2);
                        $key = trim($key);
                        $value = trim($value);
                        putenv("$key=$value");
                        $_ENV[$key] = $value;
                        $_SERVER[$key] = $value;
                    }
                }
            }
        }
    }

    // Database configuration
    if (getenv('JAWSDB_URL')) {
        // Production: Use Heroku JAWSDB_URL
        $url = parse_url(getenv("JAWSDB_URL"));
        if ($url === false) {
            throw new Exception("JAWSDB_URL environment variable not set or invalid.");
        }

        $servername = $url["host"];
        $username = $url["user"];
        $password = $url["pass"];
        $dbname = substr($url["path"], 1);
        $port = $url["port"] ?? 3306;
    } else {
        // Local Development: Use online database from .env
        $servername = getenv('DB_HOST');
        $username = getenv('DB_USER');
        $password = getenv('DB_PASS');
        $dbname = getenv('DB_NAME');
        $port = getenv('DB_PORT') ?: 3306;
    }

    // Create PDO connection with optimized settings
    $dsn = "mysql:host=$servername;port=$port;dbname=$dbname;charset=utf8mb4";

    // Use the appropriate constant depending on PHP version to support 8.5+ and fallback for older versions.
    $initCommandKey = (class_exists('Pdo\\Mysql') && defined('Pdo\\Mysql::ATTR_INIT_COMMAND'))
        ? \Pdo\Mysql::ATTR_INIT_COMMAND
        : PDO::MYSQL_ATTR_INIT_COMMAND;

    $conn = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        $initCommandKey => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ]);

    // Set timezone to match server
    $conn->exec("SET time_zone = '+00:00'");
    $conn->exec("SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");

    if (!function_exists('blog_db_has_column')) {
        function blog_db_has_column($conn, $table, $column)
        {
            static $cache = [];
            $cacheKey = $table . '.' . $column;
            if (array_key_exists($cacheKey, $cache)) {
                return $cache[$cacheKey];
            }

            $stmt = $conn->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
            $stmt->execute([$table, $column]);
            $cache[$cacheKey] = ((int)$stmt->fetchColumn()) > 0;
            return $cache[$cacheKey];
        }
    }

    if (!function_exists('blog_has_admin_role_column')) {
        function blog_has_admin_role_column($conn)
        {
            return blog_db_has_column($conn, 'users', 'role');
        }
    }

    if (!function_exists('blog_has_legacy_admin_id_column')) {
        function blog_has_legacy_admin_id_column($conn)
        {
            return blog_db_has_column($conn, 'users', 'legacy_admin_id');
        }
    }

    if (!function_exists('blog_build_admin_identity_where')) {
        function blog_build_admin_identity_where($conn, $adminId)
        {
            $adminId = (int)$adminId;
            if (blog_has_legacy_admin_id_column($conn)) {
                return ['(legacy_admin_id = ? OR id = ?)', [$adminId, $adminId]];
            }
            return ['id = ?', [$adminId]];
        }
    }

    if (!function_exists('blog_fetch_admin_profile')) {
        function blog_fetch_admin_profile($conn, $adminId)
        {
            list($identityWhere, $identityParams) = blog_build_admin_identity_where($conn, $adminId);
            $sql = "SELECT * FROM `users` WHERE {$identityWhere}";

            if (blog_has_admin_role_column($conn)) {
                $sql .= " AND role = 'admin'";
            }

            $sql .= ' LIMIT 1';
            $stmt = $conn->prepare($sql);
            $stmt->execute($identityParams);
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($profile) {
                return $profile;
            }

            if (blog_has_legacy_admin_id_column($conn)) {
                $fallback = $conn->prepare('SELECT * FROM `users` WHERE id = ? LIMIT 1');
                $fallback->execute([(int)$adminId]);
                $profile = $fallback->fetch(PDO::FETCH_ASSOC);
                if ($profile) {
                    return $profile;
                }
            }

            return ['id' => (int)$adminId, 'name' => 'Admin'];
        }
    }

    if (!function_exists('blog_count_admin_users')) {
        function blog_count_admin_users($conn)
        {
            if (blog_has_admin_role_column($conn)) {
                $stmt = $conn->prepare("SELECT COUNT(*) FROM `users` WHERE role = 'admin'");
                $stmt->execute();
                return (int)$stmt->fetchColumn();
            }

            if (blog_has_legacy_admin_id_column($conn)) {
                $stmt = $conn->prepare('SELECT COUNT(DISTINCT legacy_admin_id) FROM `users` WHERE legacy_admin_id IS NOT NULL AND legacy_admin_id > 0');
                $stmt->execute();
                $count = (int)$stmt->fetchColumn();
                if ($count > 0) {
                    return $count;
                }
            }

            $stmt = $conn->prepare('SELECT COUNT(*) FROM `users`');
            $stmt->execute();
            return (int)$stmt->fetchColumn();
        }
    }

    if (!function_exists('blog_get_admin_content_ids')) {
        function blog_get_admin_content_ids($conn, $adminId)
        {
            $ids = [(int)$adminId];

            if (blog_has_legacy_admin_id_column($conn)) {
                $stmt = $conn->prepare('SELECT id, legacy_admin_id FROM `users` WHERE id = ? LIMIT 1');
                $stmt->execute([(int)$adminId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($row) {
                    $legacyId = isset($row['legacy_admin_id']) ? (int)$row['legacy_admin_id'] : 0;
                    if ($legacyId > 0) {
                        $ids[] = $legacyId;
                    }
                }

                if (blog_has_admin_role_column($conn)) {
                    $stmt = $conn->prepare("SELECT id FROM `users` WHERE role = 'admin' AND legacy_admin_id = ?");
                    $stmt->execute([(int)$adminId]);
                    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $ids[] = (int)$r['id'];
                    }
                }
            }

            $ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($v) {
                return $v > 0;
            })));

            return $ids ?: [(int)$adminId];
        }
    }

    if (!function_exists('blog_build_admin_content_where')) {
        function blog_build_admin_content_where($conn, $fieldName, $adminId)
        {
            $ids = blog_get_admin_content_ids($conn, $adminId);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            return ["{$fieldName} IN ({$placeholders})", $ids];
        }
    }

    if (!function_exists('blog_user_avatar_src')) {
        function blog_user_avatar_src($avatarValue, $fallback = '../uploaded_img/default_avatar.png')
        {
            if ($avatarValue === null || $avatarValue === '') {
                return $fallback;
            }

            $avatarValue = (string)$avatarValue;
            if (blog_is_external_url($avatarValue)) {
                return $avatarValue;
            }

            $mime = 'image/jpeg';
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo !== false) {
                    $detected = finfo_buffer($finfo, $avatarValue);
                    finfo_close($finfo);
                    if (is_string($detected) && $detected !== '') {
                        $mime = $detected;
                    }
                }
            }

            return 'data:' . $mime . ';base64,' . base64_encode($avatarValue);
        }
    }

    if (!function_exists('blog_inject_lazy_loading_into_html')) {
        function blog_inject_lazy_loading_into_html($html)
        {
            $html = (string)$html;
            if ($html === '' || stripos($html, '<img') === false) {
                return $html;
            }

            return (string)preg_replace_callback('/<img\b[^>]*>/i', function ($matches) {
                $imgTag = (string)($matches[0] ?? '');
                if ($imgTag === '') {
                    return $imgTag;
                }

                if (stripos($imgTag, 'decoding=') === false) {
                    $imgTag = rtrim(substr($imgTag, 0, -1)) . ' decoding="async">';
                }

                if (stripos($imgTag, 'loading=') !== false) {
                    return $imgTag;
                }

                if (stripos($imgTag, 'data-no-lazy') !== false || stripos($imgTag, 'fetchpriority="high"') !== false) {
                    return $imgTag;
                }

                return rtrim(substr($imgTag, 0, -1)) . ' loading="lazy">';
            }, $html);
        }
    }

    if (!function_exists('blog_render_rich_content_html')) {
        function blog_render_rich_content_html($html)
        {
            return blog_inject_lazy_loading_into_html((string)$html);
        }
    }

    blog_ensure_post_tags_tables($conn);

    if (!function_exists('blog_get_foreign_key_reference')) {
        function blog_get_foreign_key_reference($conn, $table, $column)
        {
            static $cache = [];
            $key = $table . '.' . $column;
            if (array_key_exists($key, $cache)) {
                return $cache[$key];
            }

            $sql = "SELECT REFERENCED_TABLE_NAME AS ref_table, REFERENCED_COLUMN_NAME AS ref_column
                    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = ?
                      AND COLUMN_NAME = ?
                      AND REFERENCED_TABLE_NAME IS NOT NULL
                    LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$table, $column]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $cache[$key] = null;
                return null;
            }

            $cache[$key] = [
                'table' => strtolower((string)$row['ref_table']),
                'column' => strtolower((string)$row['ref_column'])
            ];
            return $cache[$key];
        }
    }

    if (!function_exists('blog_resolve_cart_admin_fk_value')) {
        function blog_resolve_cart_admin_fk_value($conn, $adminId)
        {
            $adminId = (int)$adminId;
            $fk = blog_get_foreign_key_reference($conn, 'cart', 'admin_id');

            if (!$fk) {
                return $adminId;
            }

            if ($fk['table'] === 'users' && $fk['column'] === 'legacy_admin_id') {
                $exists = $conn->prepare('SELECT 1 FROM `users` WHERE legacy_admin_id = ? LIMIT 1');
                $exists->execute([$adminId]);
                if ($exists->fetchColumn()) {
                    return $adminId;
                }

                if (blog_has_legacy_admin_id_column($conn)) {
                    $u = $conn->prepare('SELECT legacy_admin_id FROM `users` WHERE id = ? LIMIT 1');
                    $u->execute([$adminId]);
                    $legacy = (int)$u->fetchColumn();
                    if ($legacy > 0) {
                        return $legacy;
                    }
                }

                return $adminId;
            }

            if ($fk['table'] === 'admin' && $fk['column'] === 'id') {
                $exists = $conn->prepare('SELECT 1 FROM `admin` WHERE id = ? LIMIT 1');
                $exists->execute([$adminId]);
                if ($exists->fetchColumn()) {
                    return $adminId;
                }

                $candidates = [];
                if (blog_has_legacy_admin_id_column($conn)) {
                    $u = $conn->prepare('SELECT legacy_admin_id FROM `users` WHERE id = ? LIMIT 1');
                    $u->execute([$adminId]);
                    $legacy = (int)$u->fetchColumn();
                    if ($legacy > 0) {
                        $candidates[] = $legacy;
                    }
                }

                foreach ($candidates as $candidateId) {
                    $exists->execute([$candidateId]);
                    if ($exists->fetchColumn()) {
                        return (int)$candidateId;
                    }
                }

                $fallback = $conn->query('SELECT id FROM `admin` ORDER BY id ASC LIMIT 1');
                $fallbackId = $fallback ? (int)$fallback->fetchColumn() : 0;
                return $fallbackId > 0 ? $fallbackId : $adminId;
            }

            return $adminId;
        }
    }
} catch (Exception $e) {
    // Check if this is an AJAX request
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if ($isAjax) {
        // Return JSON error for AJAX requests
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Không thể kết nối đến cơ sở dữ liệu. Vui lòng thử lại sau!'
        ]);
        exit;
    } else {
        // Show user-friendly error page
        echo "<!DOCTYPE html><html><head><title>Kết nối Database</title>";
        echo "<style>body{font-family:Arial,sans-serif;text-align:center;padding:50px;}";
        echo ".error{color:red;} .info{color:blue;}</style>";
        echo "</head><body>";
        echo "<h1 class='error'>❌ Không thể kết nối Database</h1>";
        echo "<p class='info'>Vui lòng kiểm tra cấu hình và thử lại sau.</p>";
        echo "<p><small>Chi tiết: " . htmlspecialchars($e->getMessage()) . "</small></p>";
        echo "</body></html>";
        exit;
    }
}

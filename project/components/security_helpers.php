<?php

if (!function_exists('blog_security_ensure_tables')) {
    function blog_security_ensure_tables($conn)
    {
        static $initialized = false;
        if ($initialized) {
            return;
        }

        $conn->exec("CREATE TABLE IF NOT EXISTS auth_rate_limits (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            action_name VARCHAR(40) NOT NULL,
            ip_address VARCHAR(64) NOT NULL,
            identifier_key VARCHAR(190) NOT NULL,
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            first_attempt_at DATETIME NOT NULL,
            last_attempt_at DATETIME NOT NULL,
            blocked_until DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_auth_rate_limit (action_name, ip_address, identifier_key),
            KEY idx_auth_rate_limit_blocked_until (blocked_until),
            KEY idx_auth_rate_limit_last_attempt (last_attempt_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $conn->exec("CREATE TABLE IF NOT EXISTS auth_remember_tokens (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            selector VARCHAR(24) NOT NULL,
            validator_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_used_at DATETIME NULL,
            revoked_at DATETIME NULL,
            user_agent VARCHAR(255) NULL,
            ip_address VARCHAR(64) NULL,
            UNIQUE KEY uniq_remember_selector (selector),
            KEY idx_remember_user_id (user_id),
            KEY idx_remember_expires (expires_at),
            KEY idx_remember_revoked (revoked_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        blog_security_ensure_password_column($conn);
        blog_security_ensure_verification_columns($conn);

        $initialized = true;
    }
}

if (!function_exists('blog_security_ensure_verification_columns')) {
    function blog_security_ensure_verification_columns($conn)
    {
        static $checked = false;
        if ($checked) {
            return;
        }

        $ddlMap = [
            'is_verified' => 'ALTER TABLE users ADD COLUMN is_verified TINYINT(1) NOT NULL DEFAULT 0',
            'verification_token_hash' => 'ALTER TABLE users ADD COLUMN verification_token_hash VARCHAR(255) NULL',
            'verification_token_expires_at' => 'ALTER TABLE users ADD COLUMN verification_token_expires_at DATETIME NULL',
            'verification_sent_at' => 'ALTER TABLE users ADD COLUMN verification_sent_at DATETIME NULL',
            'verified_at' => 'ALTER TABLE users ADD COLUMN verified_at DATETIME NULL',
            'verified_by_admin_id' => 'ALTER TABLE users ADD COLUMN verified_by_admin_id BIGINT UNSIGNED NULL',
        ];

        foreach ($ddlMap as $column => $ddl) {
            try {
                if (function_exists('blog_db_has_column') && !blog_db_has_column($conn, 'users', $column)) {
                    $conn->exec($ddl);
                }
            } catch (Throwable $e) {
                error_log('Verification column ensure skipped for ' . $column . ': ' . $e->getMessage());
            }
        }

        try {
            $conn->exec('CREATE INDEX idx_users_verification_token_hash ON users(verification_token_hash)');
        } catch (Throwable $e) {
            // ignore duplicate index errors
        }

        $checked = true;
    }
}

if (!function_exists('blog_security_ensure_password_column')) {
    function blog_security_ensure_password_column($conn)
    {
        static $checked = false;
        if ($checked) {
            return;
        }

        try {
            $stmt = $conn->prepare("SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'password' LIMIT 1");
            $stmt->execute();
            $column = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$column) {
                $checked = true;
                return;
            }

            $dataType = strtolower((string)($column['DATA_TYPE'] ?? ''));
            $maxLen = (int)($column['CHARACTER_MAXIMUM_LENGTH'] ?? 0);

            if (($dataType === 'varchar' || $dataType === 'char') && $maxLen > 0 && $maxLen < 255) {
                $conn->exec('ALTER TABLE users MODIFY password VARCHAR(255) NOT NULL');
            }
        } catch (Throwable $e) {
            error_log('Password column widen skipped: ' . $e->getMessage());
        }

        $checked = true;
    }
}

if (!function_exists('blog_csrf_token')) {
    function blog_csrf_token($formName)
    {
        $formName = (string)$formName;
        if (!isset($_SESSION['_csrf_tokens']) || !is_array($_SESSION['_csrf_tokens'])) {
            $_SESSION['_csrf_tokens'] = [];
        }

        if (empty($_SESSION['_csrf_tokens'][$formName])) {
            $_SESSION['_csrf_tokens'][$formName] = bin2hex(random_bytes(32));
        }

        return (string)$_SESSION['_csrf_tokens'][$formName];
    }
}

if (!function_exists('blog_csrf_input')) {
    function blog_csrf_input($formName)
    {
        $token = blog_csrf_token($formName);
        return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('blog_csrf_validate')) {
    function blog_csrf_validate($formName, $submittedToken)
    {
        $formName = (string)$formName;
        $submittedToken = (string)$submittedToken;

        $stored = '';
        if (isset($_SESSION['_csrf_tokens']) && is_array($_SESSION['_csrf_tokens']) && isset($_SESSION['_csrf_tokens'][$formName])) {
            $stored = (string)$_SESSION['_csrf_tokens'][$formName];
        }

        if ($stored === '' || $submittedToken === '') {
            return false;
        }

        $valid = hash_equals($stored, $submittedToken);
        if ($valid) {
            unset($_SESSION['_csrf_tokens'][$formName]);
        }

        return $valid;
    }
}

if (!function_exists('blog_client_ip')) {
    function blog_client_ip()
    {
        $forwardedFor = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($forwardedFor !== '') {
            $parts = explode(',', $forwardedFor);
            $candidate = trim((string)($parts[0] ?? ''));
            if ($candidate !== '') {
                return $candidate;
            }
        }

        $realIp = trim((string)($_SERVER['HTTP_X_REAL_IP'] ?? ''));
        if ($realIp !== '') {
            return $realIp;
        }

        return trim((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
    }
}

if (!function_exists('blog_rate_limit_identifier_key')) {
    function blog_rate_limit_identifier_key($identifier)
    {
        $identifier = trim((string)$identifier);
        if ($identifier === '') {
            $identifier = 'all';
        }

        if (function_exists('mb_strtolower')) {
            $identifier = mb_strtolower($identifier, 'UTF-8');
        } else {
            $identifier = strtolower($identifier);
        }

        return hash('sha256', $identifier);
    }
}

if (!function_exists('blog_rate_limit_state')) {
    function blog_rate_limit_state($conn, $actionName, $identifier, $maxAttempts = 5, $windowSeconds = 900, $lockSeconds = 900)
    {
        blog_security_ensure_tables($conn);

        $actionName = trim((string)$actionName);
        $ipAddress = blog_client_ip();
        $identifierKey = blog_rate_limit_identifier_key($identifier);
        $nowTs = time();
        $windowStartTs = $nowTs - max(1, (int)$windowSeconds);

        $stmt = $conn->prepare('SELECT attempts, first_attempt_at, blocked_until FROM auth_rate_limits WHERE action_name = ? AND ip_address = ? AND identifier_key = ? LIMIT 1');
        $stmt->execute([$actionName, $ipAddress, $identifierKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return [
                'blocked' => false,
                'retry_after' => 0,
                'remaining' => (int)$maxAttempts,
            ];
        }

        $firstAttemptTs = strtotime((string)($row['first_attempt_at'] ?? '')) ?: $nowTs;
        if ($firstAttemptTs < $windowStartTs) {
            $reset = $conn->prepare('UPDATE auth_rate_limits SET attempts = 0, first_attempt_at = UTC_TIMESTAMP(), last_attempt_at = UTC_TIMESTAMP(), blocked_until = NULL WHERE action_name = ? AND ip_address = ? AND identifier_key = ?');
            $reset->execute([$actionName, $ipAddress, $identifierKey]);

            return [
                'blocked' => false,
                'retry_after' => 0,
                'remaining' => (int)$maxAttempts,
            ];
        }

        $blockedUntilTs = strtotime((string)($row['blocked_until'] ?? '')) ?: 0;
        if ($blockedUntilTs > $nowTs) {
            return [
                'blocked' => true,
                'retry_after' => $blockedUntilTs - $nowTs,
                'remaining' => 0,
            ];
        }

        $attempts = (int)($row['attempts'] ?? 0);
        if ($attempts >= (int)$maxAttempts) {
            $newBlockedUntil = gmdate('Y-m-d H:i:s', $nowTs + max(1, (int)$lockSeconds));
            $block = $conn->prepare('UPDATE auth_rate_limits SET blocked_until = ?, last_attempt_at = UTC_TIMESTAMP() WHERE action_name = ? AND ip_address = ? AND identifier_key = ?');
            $block->execute([$newBlockedUntil, $actionName, $ipAddress, $identifierKey]);

            return [
                'blocked' => true,
                'retry_after' => max(1, (int)$lockSeconds),
                'remaining' => 0,
            ];
        }

        return [
            'blocked' => false,
            'retry_after' => 0,
            'remaining' => max(0, (int)$maxAttempts - $attempts),
        ];
    }
}

if (!function_exists('blog_rate_limit_record_failure')) {
    function blog_rate_limit_record_failure($conn, $actionName, $identifier, $maxAttempts = 5, $windowSeconds = 900, $lockSeconds = 900)
    {
        blog_security_ensure_tables($conn);

        $actionName = trim((string)$actionName);
        $ipAddress = blog_client_ip();
        $identifierKey = blog_rate_limit_identifier_key($identifier);
        $nowTs = time();
        $windowStartTs = $nowTs - max(1, (int)$windowSeconds);

        $stmt = $conn->prepare('SELECT attempts, first_attempt_at FROM auth_rate_limits WHERE action_name = ? AND ip_address = ? AND identifier_key = ? LIMIT 1');
        $stmt->execute([$actionName, $ipAddress, $identifierKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $attempts = 1;
            $blockedUntil = $attempts >= (int)$maxAttempts ? gmdate('Y-m-d H:i:s', $nowTs + max(1, (int)$lockSeconds)) : null;
            $insert = $conn->prepare('INSERT INTO auth_rate_limits (action_name, ip_address, identifier_key, attempts, first_attempt_at, last_attempt_at, blocked_until) VALUES (?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP(), ?)');
            $insert->execute([$actionName, $ipAddress, $identifierKey, $attempts, $blockedUntil]);
            return;
        }

        $firstAttemptTs = strtotime((string)($row['first_attempt_at'] ?? '')) ?: $nowTs;
        $attempts = (int)($row['attempts'] ?? 0);

        if ($firstAttemptTs < $windowStartTs) {
            $attempts = 1;
            $blockedUntil = null;
            if ($attempts >= (int)$maxAttempts) {
                $blockedUntil = gmdate('Y-m-d H:i:s', $nowTs + max(1, (int)$lockSeconds));
            }

            $reset = $conn->prepare('UPDATE auth_rate_limits SET attempts = ?, first_attempt_at = UTC_TIMESTAMP(), last_attempt_at = UTC_TIMESTAMP(), blocked_until = ? WHERE action_name = ? AND ip_address = ? AND identifier_key = ?');
            $reset->execute([$attempts, $blockedUntil, $actionName, $ipAddress, $identifierKey]);
            return;
        }

        $attempts++;
        $blockedUntil = null;
        if ($attempts >= (int)$maxAttempts) {
            $blockedUntil = gmdate('Y-m-d H:i:s', $nowTs + max(1, (int)$lockSeconds));
        }

        $update = $conn->prepare('UPDATE auth_rate_limits SET attempts = ?, last_attempt_at = UTC_TIMESTAMP(), blocked_until = ? WHERE action_name = ? AND ip_address = ? AND identifier_key = ?');
        $update->execute([$attempts, $blockedUntil, $actionName, $ipAddress, $identifierKey]);
    }
}

if (!function_exists('blog_rate_limit_record_success')) {
    function blog_rate_limit_record_success($conn, $actionName, $identifier)
    {
        blog_security_ensure_tables($conn);

        $actionName = trim((string)$actionName);
        $ipAddress = blog_client_ip();
        $identifierKey = blog_rate_limit_identifier_key($identifier);

        $delete = $conn->prepare('DELETE FROM auth_rate_limits WHERE action_name = ? AND ip_address = ? AND identifier_key = ?');
        $delete->execute([$actionName, $ipAddress, $identifierKey]);
    }
}

if (!function_exists('blog_rate_limit_attempts')) {
    function blog_rate_limit_attempts($conn, $actionName, $identifier, $windowSeconds = 900)
    {
        blog_security_ensure_tables($conn);

        $actionName = trim((string)$actionName);
        $ipAddress = blog_client_ip();
        $identifierKey = blog_rate_limit_identifier_key($identifier);
        $nowTs = time();
        $windowStartTs = $nowTs - max(1, (int)$windowSeconds);

        $stmt = $conn->prepare('SELECT attempts, first_attempt_at FROM auth_rate_limits WHERE action_name = ? AND ip_address = ? AND identifier_key = ? LIMIT 1');
        $stmt->execute([$actionName, $ipAddress, $identifierKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return 0;
        }

        $firstAttemptTs = strtotime((string)($row['first_attempt_at'] ?? '')) ?: $nowTs;
        if ($firstAttemptTs < $windowStartTs) {
            return 0;
        }

        return max(0, (int)($row['attempts'] ?? 0));
    }
}

if (!function_exists('blog_captcha_should_show')) {
    function blog_captcha_should_show($conn, $actionName, $identifier, $threshold = 3, $windowSeconds = 900)
    {
        $attempts = blog_rate_limit_attempts($conn, $actionName, $identifier, $windowSeconds);
        return $attempts >= max(1, (int)$threshold);
    }
}

if (!function_exists('blog_human_challenge_provider')) {
    function blog_human_challenge_provider()
    {
        $preferredProvider = strtolower(trim((string)(getenv('CAPTCHA_PROVIDER') ?: '')));

        $recaptchaSiteKey = trim((string)(getenv('RECAPTCHA_SITE_KEY') ?: ''));
        $recaptchaSecretKey = trim((string)(getenv('RECAPTCHA_SECRET_KEY') ?: ''));
        $recaptchaReady = ($recaptchaSiteKey !== '' && $recaptchaSecretKey !== '');

        $turnstileSiteKey = trim((string)(getenv('TURNSTILE_SITE_KEY') ?: ''));
        $turnstileSecretKey = trim((string)(getenv('TURNSTILE_SECRET_KEY') ?: ''));
        $turnstileReady = ($turnstileSiteKey !== '' && $turnstileSecretKey !== '');

        if ($preferredProvider === 'recaptcha' && $recaptchaReady) {
            return 'recaptcha';
        }

        if ($preferredProvider === 'turnstile' && $turnstileReady) {
            return 'turnstile';
        }

        if ($recaptchaReady) {
            return 'recaptcha';
        }

        if ($turnstileReady) {
            return 'turnstile';
        }

        return '';
    }
}

if (!function_exists('blog_human_challenge_site_key')) {
    function blog_human_challenge_site_key()
    {
        $provider = blog_human_challenge_provider();
        if ($provider === 'turnstile') {
            return trim((string)(getenv('TURNSTILE_SITE_KEY') ?: ''));
        }
        if ($provider === 'recaptcha') {
            return trim((string)(getenv('RECAPTCHA_SITE_KEY') ?: ''));
        }
        return '';
    }
}

if (!function_exists('blog_human_challenge_token_field')) {
    function blog_human_challenge_token_field()
    {
        return blog_human_challenge_provider() === 'turnstile'
            ? 'cf-turnstile-response'
            : 'g-recaptcha-response';
    }
}

if (!function_exists('blog_human_challenge_verify')) {
    function blog_human_challenge_verify($token)
    {
        $provider = blog_human_challenge_provider();
        $token = trim((string)$token);
        if ($token === '') {
            return false;
        }

        if ($provider === 'turnstile') {
            $secret = trim((string)(getenv('TURNSTILE_SECRET_KEY') ?: ''));
            if ($secret === '') {
                return false;
            }

            $payload = http_build_query([
                'secret' => $secret,
                'response' => $token,
                'remoteip' => blog_client_ip(),
            ]);

            $response = blog_http_post_form('https://challenges.cloudflare.com/turnstile/v0/siteverify', $payload);
            if ($response === null) {
                return false;
            }

            $decoded = json_decode($response, true);
            return is_array($decoded) && !empty($decoded['success']);
        }

        if ($provider === 'recaptcha') {
            $secret = trim((string)(getenv('RECAPTCHA_SECRET_KEY') ?: ''));
            if ($secret === '') {
                return false;
            }

            $payload = http_build_query([
                'secret' => $secret,
                'response' => $token,
                'remoteip' => blog_client_ip(),
            ]);

            $response = blog_http_post_form('https://www.google.com/recaptcha/api/siteverify', $payload);
            if ($response === null) {
                return false;
            }

            $decoded = json_decode($response, true);
            return is_array($decoded) && !empty($decoded['success']);
        }

        return false;
    }
}

if (!function_exists('blog_http_post_form')) {
    function blog_http_post_form($url, $payload)
    {
        $url = trim((string)$url);
        if ($url === '') {
            return null;
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, (string)$payload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
            $result = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if (!is_string($result) || $status < 200 || $status >= 300) {
                return null;
            }
            return $result;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => (string)$payload,
                'timeout' => 8,
            ],
        ]);
        $result = @file_get_contents($url, false, $context);
        return is_string($result) ? $result : null;
    }
}

if (!function_exists('blog_captcha_get_challenge')) {
    function blog_captcha_get_challenge($formName)
    {
        $formName = trim((string)$formName);
        if ($formName === '') {
            return ['question' => '', 'answer' => ''];
        }

        if (!isset($_SESSION['_captcha']) || !is_array($_SESSION['_captcha'])) {
            $_SESSION['_captcha'] = [];
        }

        if (!isset($_SESSION['_captcha'][$formName]) || !is_array($_SESSION['_captcha'][$formName])) {
            $a = random_int(1, 9);
            $b = random_int(1, 9);
            $_SESSION['_captcha'][$formName] = [
                'question' => $a . ' + ' . $b . ' = ?',
                'answer' => (string)($a + $b),
                'created_at' => time(),
            ];
        }

        $captcha = $_SESSION['_captcha'][$formName];
        return [
            'question' => (string)($captcha['question'] ?? ''),
            'answer' => (string)($captcha['answer'] ?? ''),
        ];
    }
}

if (!function_exists('blog_captcha_clear')) {
    function blog_captcha_clear($formName)
    {
        $formName = trim((string)$formName);
        if ($formName === '') {
            return;
        }

        if (isset($_SESSION['_captcha']) && is_array($_SESSION['_captcha']) && isset($_SESSION['_captcha'][$formName])) {
            unset($_SESSION['_captcha'][$formName]);
        }
    }
}

if (!function_exists('blog_captcha_validate')) {
    function blog_captcha_validate($formName, $submittedValue)
    {
        $formName = trim((string)$formName);
        $submittedValue = trim((string)$submittedValue);
        if ($formName === '' || $submittedValue === '') {
            return false;
        }

        if (!isset($_SESSION['_captcha']) || !is_array($_SESSION['_captcha']) || !isset($_SESSION['_captcha'][$formName])) {
            return false;
        }

        $answer = (string)($_SESSION['_captcha'][$formName]['answer'] ?? '');
        if ($answer === '') {
            return false;
        }

        $valid = hash_equals($answer, $submittedValue);
        if ($valid) {
            blog_captcha_clear($formName);
        }

        return $valid;
    }
}

if (!function_exists('blog_password_matches')) {
    function blog_password_matches($rawPassword, $storedPassword)
    {
        $stored = (string)$storedPassword;
        $rawPassword = (string)$rawPassword;
        if ($stored === '' || $rawPassword === '') {
            return false;
        }

        $info = password_get_info($stored);
        if (!empty($info['algo'])) {
            return password_verify($rawPassword, $stored);
        }

        $sha1Pass = sha1($rawPassword);
        if (hash_equals($stored, $sha1Pass) || hash_equals($stored, $rawPassword)) {
            return true;
        }

        return false;
    }
}

if (!function_exists('blog_password_needs_migration')) {
    function blog_password_needs_migration($storedPassword)
    {
        $stored = (string)$storedPassword;
        if ($stored === '') {
            return false;
        }

        $info = password_get_info($stored);
        return empty($info['algo']);
    }
}

if (!function_exists('blog_mask_secret')) {
    function blog_mask_secret($value, $showPrefix = 4, $showSuffix = 4)
    {
        $value = (string)$value;
        $len = strlen($value);
        if ($len <= ($showPrefix + $showSuffix)) {
            return str_repeat('*', max(4, $len));
        }

        $prefix = substr($value, 0, max(0, (int)$showPrefix));
        $suffix = substr($value, -max(0, (int)$showSuffix));
        $middle = str_repeat('*', max(4, $len - strlen($prefix) - strlen($suffix)));
        return $prefix . $middle . $suffix;
    }
}

if (!function_exists('blog_user_verification_state')) {
    function blog_user_verification_state($conn, $userId)
    {
        blog_security_ensure_tables($conn);
        $userId = (int)$userId;
        if ($userId <= 0) {
            return ['exists' => false, 'is_verified' => false, 'email' => ''];
        }

        if (function_exists('blog_db_has_column') && !blog_db_has_column($conn, 'users', 'is_verified')) {
            return ['exists' => true, 'is_verified' => true, 'email' => ''];
        }

        $stmt = $conn->prepare('SELECT email, is_verified FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['exists' => false, 'is_verified' => false, 'email' => ''];
        }

        return [
            'exists' => true,
            'is_verified' => ((int)($row['is_verified'] ?? 0) === 1),
            'email' => (string)($row['email'] ?? ''),
        ];
    }
}

if (!function_exists('blog_require_verified_user')) {
    function blog_require_verified_user($conn, $userId, $redirectTo = '../static/resend_verification.php')
    {
        $state = blog_user_verification_state($conn, $userId);
        if (!$state['exists']) {
            return false;
        }

        if (!empty($state['is_verified'])) {
            return true;
        }

        if (!headers_sent()) {
            $target = (string)$redirectTo;
            $separator = strpos($target, '?') === false ? '?' : '&';
            $target .= $separator . 'email=' . rawurlencode((string)$state['email']) . '&blocked=1';
            header('Location: ' . $target);
        }
        exit;
    }
}

if (!function_exists('blog_generate_verification_token')) {
    function blog_generate_verification_token($conn, $userId, $ttlSeconds = 86400)
    {
        blog_security_ensure_tables($conn);
        $userId = (int)$userId;
        if ($userId <= 0) {
            return ['ok' => false, 'token' => '', 'expires_at' => ''];
        }

        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expiresAt = gmdate('Y-m-d H:i:s', time() + max(300, (int)$ttlSeconds));

        $stmt = $conn->prepare('UPDATE users SET verification_token_hash = ?, verification_token_expires_at = ?, verification_sent_at = UTC_TIMESTAMP() WHERE id = ? LIMIT 1');
        $stmt->execute([$hash, $expiresAt, $userId]);

        return ['ok' => true, 'token' => $token, 'expires_at' => $expiresAt];
    }
}

if (!function_exists('blog_mark_user_verified')) {
    function blog_mark_user_verified($conn, $userId, $verifiedByAdminId = null)
    {
        blog_security_ensure_tables($conn);
        $userId = (int)$userId;
        if ($userId <= 0) {
            return false;
        }

        $adminId = $verifiedByAdminId === null ? null : (int)$verifiedByAdminId;
        $stmt = $conn->prepare('UPDATE users SET is_verified = 1, verified_at = UTC_TIMESTAMP(), verified_by_admin_id = ?, verification_token_hash = NULL, verification_token_expires_at = NULL, verification_sent_at = NULL WHERE id = ? LIMIT 1');
        $stmt->execute([$adminId, $userId]);
        return $stmt->rowCount() > 0;
    }
}

if (!function_exists('blog_send_verification_email')) {
    function blog_send_verification_email($email, $displayName, $verificationUrl)
    {
        $email = trim((string)$email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Email không hợp lệ'];
        }

        $displayName = trim((string)$displayName);
        if ($displayName === '') {
            $displayName = 'bạn';
        }

        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = (string)($_ENV['SMTP_HOST'] ?? getenv('SMTP_HOST') ?: '');
            $mail->SMTPAuth = true;
            $mail->Username = (string)($_ENV['SMTP_USER'] ?? getenv('SMTP_USER') ?: '');
            $mail->Password = (string)($_ENV['SMTP_PASS'] ?? getenv('SMTP_PASS') ?: '');
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = (int)((string)($_ENV['SMTP_PORT'] ?? getenv('SMTP_PORT') ?: '587'));
            $mail->CharSet = 'UTF-8';

            $fromEmail = (string)($_ENV['SMTP_FROM'] ?? getenv('SMTP_FROM') ?: $mail->Username);
            $fromName = (string)($_ENV['SMTP_FROM_NAME'] ?? getenv('SMTP_FROM_NAME') ?: 'My Blog');
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = 'Xác minh email tài khoản';
            $safeName = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
            $safeLink = htmlspecialchars((string)$verificationUrl, ENT_QUOTES, 'UTF-8');
            $mail->Body = "
                <div style='font-family:Arial,sans-serif;max-width:640px;margin:0 auto;padding:24px;background:#f8fafc'>
                    <div style='background:#0f172a;color:#fff;padding:18px 20px;border-radius:10px 10px 0 0'>
                        <h2 style='margin:0;font-size:20px'>Xác minh email</h2>
                    </div>
                    <div style='background:#fff;padding:24px;border:1px solid #e2e8f0;border-top:0;border-radius:0 0 10px 10px'>
                        <p>Xin chào <strong>{$safeName}</strong>,</p>
                        <p>Vui lòng xác minh email để kích hoạt đầy đủ chức năng tài khoản.</p>
                        <p style='margin:24px 0'>
                            <a href='{$safeLink}' style='display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:12px 18px;border-radius:8px;font-weight:700'>Xác minh email ngay</a>
                        </p>
                        <p>Nếu nút không hoạt động, bạn có thể sao chép đường dẫn sau:</p>
                        <p style='word-break:break-all;color:#334155'>{$safeLink}</p>
                        <p style='color:#64748b;font-size:13px'>Liên kết có hiệu lực trong 24 giờ.</p>
                    </div>
                </div>
            ";

            $mail->send();
            return ['ok' => true, 'error' => ''];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => (string)$e->getMessage()];
        }
    }
}

if (!function_exists('blog_issue_and_send_verification')) {
    function blog_issue_and_send_verification($conn, $userId, $email, $displayName)
    {
        $issued = blog_generate_verification_token($conn, $userId, 86400);
        if (empty($issued['ok'])) {
            return ['ok' => false, 'error' => 'Không thể tạo token xác minh'];
        }

        $url = site_url('static/verify_email.php')
            . '?email=' . rawurlencode((string)$email)
            . '&token=' . rawurlencode((string)$issued['token']);

        $mailResult = blog_send_verification_email($email, $displayName, $url);
        if (!empty($mailResult['ok'])) {
            return ['ok' => true, 'error' => ''];
        }

        return ['ok' => false, 'error' => (string)($mailResult['error'] ?? 'Không gửi được email xác minh')];
    }
}

if (!function_exists('blog_comment_link_count')) {
    function blog_comment_link_count($text)
    {
        $text = (string)$text;
        if ($text === '') {
            return 0;
        }

        preg_match_all('/(?:https?:\/\/|www\.)[^\s<]+/iu', $text, $matches);
        return isset($matches[0]) ? count($matches[0]) : 0;
    }
}

if (!function_exists('blog_comment_normalize_text')) {
    function blog_comment_normalize_text($text)
    {
        $text = trim((string)$text);
        $text = preg_replace('/\s+/u', ' ', $text);
        if (function_exists('mb_strtolower')) {
            return mb_strtolower((string)$text, 'UTF-8');
        }
        return strtolower((string)$text);
    }
}

if (!function_exists('blog_remember_cookie_name')) {
    function blog_remember_cookie_name()
    {
        return 'blog_remember';
    }
}

if (!function_exists('blog_has_remember_cookie')) {
    function blog_has_remember_cookie()
    {
        $name = blog_remember_cookie_name();
        return isset($_COOKIE[$name]) && trim((string)$_COOKIE[$name]) !== '';
    }
}

if (!function_exists('blog_set_remember_cookie')) {
    function blog_set_remember_cookie($tokenValue, $expiresAtTs)
    {
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie(blog_remember_cookie_name(), (string)$tokenValue, [
            'expires' => (int)$expiresAtTs,
            'path' => '/',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

if (!function_exists('blog_clear_remember_cookie')) {
    function blog_clear_remember_cookie()
    {
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie(blog_remember_cookie_name(), '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE[blog_remember_cookie_name()]);
    }
}

if (!function_exists('blog_parse_remember_cookie')) {
    function blog_parse_remember_cookie()
    {
        $raw = trim((string)($_COOKIE[blog_remember_cookie_name()] ?? ''));
        if ($raw === '' || strpos($raw, ':') === false) {
            return [null, null];
        }

        [$selector, $validator] = explode(':', $raw, 2);
        $selector = trim((string)$selector);
        $validator = trim((string)$validator);

        if ($selector === '' || $validator === '' || !preg_match('/^[a-f0-9]{24}$/i', $selector) || !preg_match('/^[a-f0-9]{64}$/i', $validator)) {
            return [null, null];
        }

        return [$selector, $validator];
    }
}

if (!function_exists('blog_issue_remember_login')) {
    function blog_issue_remember_login($conn, $userId, $days = 30)
    {
        blog_security_ensure_tables($conn);

        $userId = (int)$userId;
        if ($userId <= 0) {
            return false;
        }

        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $validatorHash = hash('sha256', $validator);
        $expiresAtTs = time() + (max(1, (int)$days) * 86400);
        $expiresAt = gmdate('Y-m-d H:i:s', $expiresAtTs);

        // Limit active remember tokens per user to reduce token sprawl.
        $cleanup = $conn->prepare('DELETE FROM auth_remember_tokens WHERE user_id = ? AND (revoked_at IS NOT NULL OR expires_at < UTC_TIMESTAMP())');
        $cleanup->execute([$userId]);

        $stmt = $conn->prepare('INSERT INTO auth_remember_tokens (user_id, selector, validator_hash, expires_at, user_agent, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $userId,
            $selector,
            $validatorHash,
            $expiresAt,
            substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            blog_client_ip(),
        ]);

        blog_set_remember_cookie($selector . ':' . $validator, $expiresAtTs);
        return true;
    }
}

if (!function_exists('blog_forget_remember_login')) {
    function blog_forget_remember_login($conn)
    {
        blog_security_ensure_tables($conn);

        [$selector] = blog_parse_remember_cookie();
        if ($selector !== null) {
            $stmt = $conn->prepare('UPDATE auth_remember_tokens SET revoked_at = UTC_TIMESTAMP() WHERE selector = ? AND revoked_at IS NULL');
            $stmt->execute([$selector]);
        }

        blog_clear_remember_cookie();
    }
}

if (!function_exists('blog_restore_login_from_remember_cookie')) {
    function blog_restore_login_from_remember_cookie($conn)
    {
        blog_security_ensure_tables($conn);

        if (!empty($_SESSION['user_id'])) {
            return true;
        }

        [$selector, $validator] = blog_parse_remember_cookie();
        if ($selector === null || $validator === null) {
            return false;
        }

        $stmt = $conn->prepare('SELECT * FROM auth_remember_tokens WHERE selector = ? LIMIT 1');
        $stmt->execute([$selector]);
        $tokenRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$tokenRow) {
            blog_clear_remember_cookie();
            return false;
        }

        if (!empty($tokenRow['revoked_at'])) {
            blog_clear_remember_cookie();
            return false;
        }

        $expiresTs = strtotime((string)($tokenRow['expires_at'] ?? '')) ?: 0;
        if ($expiresTs <= time()) {
            $revoke = $conn->prepare('UPDATE auth_remember_tokens SET revoked_at = UTC_TIMESTAMP() WHERE id = ?');
            $revoke->execute([(int)$tokenRow['id']]);
            blog_clear_remember_cookie();
            return false;
        }

        $validatorHash = hash('sha256', $validator);
        if (!hash_equals((string)($tokenRow['validator_hash'] ?? ''), $validatorHash)) {
            $revoke = $conn->prepare('UPDATE auth_remember_tokens SET revoked_at = UTC_TIMESTAMP() WHERE id = ?');
            $revoke->execute([(int)$tokenRow['id']]);
            blog_clear_remember_cookie();
            return false;
        }

        $userStmt = $conn->prepare('SELECT id, email, role, legacy_admin_id, banned, is_verified FROM users WHERE id = ? LIMIT 1');
        $userStmt->execute([(int)$tokenRow['user_id']]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            blog_forget_remember_login($conn);
            return false;
        }

        if (isset($user['banned']) && (int)$user['banned'] === 1) {
            blog_forget_remember_login($conn);
            return false;
        }

        if (isset($user['is_verified']) && (int)$user['is_verified'] !== 1) {
            blog_forget_remember_login($conn);
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];

        $roleRaw = strtolower(trim((string)($user['role'] ?? '')));
        $isAdminRole = ($roleRaw === 'admin') || ((int)($user['legacy_admin_id'] ?? 0) > 0);
        if ($isAdminRole) {
            $_SESSION['admin_id'] = (int)(((int)($user['legacy_admin_id'] ?? 0) > 0) ? $user['legacy_admin_id'] : $user['id']);
        }

        // Rotate validator on each auto-login to reduce replay risk.
        $newValidator = bin2hex(random_bytes(32));
        $newValidatorHash = hash('sha256', $newValidator);
        $newExpiresTs = time() + (30 * 86400);
        $newExpiresAt = gmdate('Y-m-d H:i:s', $newExpiresTs);
        $rotate = $conn->prepare('UPDATE auth_remember_tokens SET validator_hash = ?, expires_at = ?, last_used_at = UTC_TIMESTAMP(), user_agent = ?, ip_address = ? WHERE id = ?');
        $rotate->execute([
            $newValidatorHash,
            $newExpiresAt,
            substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            blog_client_ip(),
            (int)$tokenRow['id'],
        ]);

        blog_set_remember_cookie($selector . ':' . $newValidator, $newExpiresTs);
        return true;
    }
}

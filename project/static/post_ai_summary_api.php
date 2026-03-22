<?php
// Ensure production API responses are JSON-only; suppress all HTML warnings or notices.
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
ob_start();

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});
set_exception_handler(function ($e) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Lỗi máy chủ: ' . $e->getMessage(),
        'debug' => [
            'line' => $e->getLine(),
            'file' => $e->getFile()
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

include '../components/connect.php';
include '../components/feature_engine.php';

session_start();

header('Content-Type: application/json; charset=utf-8');

function ai_summary_json_response(array $payload, int $statusCode = 200): void
{
    if (ob_get_length()) {
        ob_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function ai_summary_client_ip(): string
{
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? ''
    ];

    foreach ($candidates as $candidate) {
        if (!$candidate) {
            continue;
        }
        $first = trim(explode(',', $candidate)[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) {
            return $first;
        }
    }

    return '0.0.0.0';
}

function ai_summary_rate_limit(string $identity, int $limit, int $windowSeconds = 60): array
{
    $dir = __DIR__ . '/../data/ai_rate_limit';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    if (!is_dir($dir) || !is_writable($dir)) {
        return ['allowed' => true, 'retry_after' => 0];
    }

    $file = $dir . '/' . sha1($identity) . '.json';
    $now = time();
    $timestamps = [];

    if (is_file($file)) {
        $raw = file_get_contents($file);
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && isset($decoded['timestamps']) && is_array($decoded['timestamps'])) {
                $timestamps = $decoded['timestamps'];
            }
        }
    }

    $timestamps = array_values(array_filter($timestamps, static function ($ts) use ($now, $windowSeconds) {
        return is_numeric($ts) && ((int)$ts > ($now - $windowSeconds));
    }));

    if (count($timestamps) >= $limit) {
        $oldest = (int)min($timestamps);
        $retryAfter = max(1, ($oldest + $windowSeconds) - $now);
        return ['allowed' => false, 'retry_after' => $retryAfter];
    }

    $timestamps[] = $now;
    file_put_contents($file, json_encode(['timestamps' => $timestamps], JSON_UNESCAPED_UNICODE), LOCK_EX);

    return ['allowed' => true, 'retry_after' => 0];
}

function ai_summary_read_cache(int $postId, string $mode, string $postHash, int $ttlSeconds = 21600): ?array
{
    $dir = __DIR__ . '/../data/ai_summary_cache';
    $file = $dir . '/' . sha1($postId . '|' . $mode . '|' . $postHash) . '.json';

    if (!is_file($file)) {
        return null;
    }

    $raw = file_get_contents($file);
    if (!is_string($raw) || $raw === '') {
        return null;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return null;
    }

    $createdAt = isset($decoded['created_at']) ? (int)$decoded['created_at'] : 0;
    $summary = isset($decoded['summary']) ? trim((string)$decoded['summary']) : '';
    if ($summary === '') {
        return null;
    }

    if ($createdAt < (time() - $ttlSeconds)) {
        return null;
    }

    return [
        'summary' => $summary,
        'source' => (string)($decoded['source'] ?? 'gemini-cache')
    ];
}

function ai_summary_write_cache(int $postId, string $mode, string $postHash, string $summary): void
{
    $dir = __DIR__ . '/../data/ai_summary_cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    if (!is_dir($dir) || !is_writable($dir)) {
        return;
    }

    $file = $dir . '/' . sha1($postId . '|' . $mode . '|' . $postHash) . '.json';
    $payload = [
        'summary' => $summary,
        'source' => 'gemini-cache',
        'created_at' => time(),
        'mode' => $mode,
        'post_hash' => $postHash,
    ];
    file_put_contents($file, json_encode($payload, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function ai_summary_request_gemini(string $model, string $geminiKey, array $requestBody): array
{
    $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/'
        . rawurlencode($model)
        . ':generateContent?key='
        . rawurlencode($geminiKey);

    $payload = json_encode($requestBody, JSON_UNESCAPED_UNICODE);
    $httpCode = 0;
    $responseText = '';
    $transportError = '';
    $apiErrorMessage = '';

    if (function_exists('curl_init')) {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $curlResult = curl_exec($ch);
        $responseText = is_string($curlResult) ? $curlResult : '';
        if ($responseText === '' || $responseText === false) {
            $transportError = (string)curl_error($ch);
        }
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    }

    if (($responseText === '' || $httpCode === 0) && $transportError !== '') {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 25,
            ]
        ]);
        $streamResponse = @file_get_contents($endpoint, false, $context);
        if (is_string($streamResponse) && $streamResponse !== '') {
            $responseText = $streamResponse;
            $transportError = '';
            if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', (string)$http_response_header[0], $m)) {
                $httpCode = (int)$m[1];
            }
        }
    }

    if (!function_exists('curl_init')) {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 25,
            ]
        ]);
        $responseText = (string)@file_get_contents($endpoint, false, $context);
        if ($responseText === '') {
            $transportError = (string)($GLOBALS['php_errormsg'] ?? 'request failed');
        }
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', (string)$http_response_header[0], $m)) {
            $httpCode = (int)$m[1];
        }
    }

    if ($responseText !== '') {
        $decoded = json_decode($responseText, true);
        if (is_array($decoded) && isset($decoded['error']['message'])) {
            $apiErrorMessage = trim((string)$decoded['error']['message']);
        }
    }

    if ($responseText === '' || $httpCode >= 400) {
        $errorCode = 'model_error';
        if ($httpCode === 404) {
            $errorCode = 'model_not_found';
        } elseif ($httpCode === 429) {
            $errorCode = 'quota_limited';
        } elseif ($httpCode === 403) {
            $errorCode = 'permission_denied';
        } elseif ($httpCode === 401) {
            $errorCode = 'invalid_api_key';
        } elseif ($httpCode >= 500) {
            $errorCode = 'provider_server_error';
        }

        if ($transportError !== '') {
            $errorCode = 'transport_error';
        }

        return [
            'ok' => false,
            'summary' => '',
            'http_code' => $httpCode,
            'error' => $errorCode,
            'api_message' => $apiErrorMessage,
            'transport_error' => $transportError,
        ];
    }

    $data = json_decode($responseText, true);
    $summary = '';
    if (isset($data['candidates'][0]['content']['parts']) && is_array($data['candidates'][0]['content']['parts'])) {
        foreach ($data['candidates'][0]['content']['parts'] as $part) {
            if (isset($part['text'])) {
                $summary .= (string)$part['text'];
            }
        }
    }

    $summary = trim($summary);
    if ($summary === '') {
        return [
            'ok' => false,
            'summary' => '',
            'http_code' => $httpCode,
            'error' => 'empty_summary',
        ];
    }

    return [
        'ok' => true,
        'summary' => $summary,
        'http_code' => $httpCode,
        'error' => '',
        'api_message' => '',
        'transport_error' => '',
    ];
}

function ai_summary_discover_models(string $geminiKey, int $ttlSeconds = 21600): array
{
    $dir = __DIR__ . '/../data/ai_model_discovery';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $cacheFile = $dir . '/' . sha1($geminiKey) . '.json';
    if (is_file($cacheFile)) {
        $raw = file_get_contents($cacheFile);
        if (is_string($raw) && $raw !== '') {
            $cached = json_decode($raw, true);
            if (is_array($cached) && isset($cached['created_at']) && isset($cached['models']) && is_array($cached['models'])) {
                if ((int)$cached['created_at'] >= (time() - $ttlSeconds)) {
                    return [
                        'models' => array_values($cached['models']),
                        'source' => 'cache',
                    ];
                }
            }
        }
    }

    $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . rawurlencode($geminiKey);
    $httpCode = 0;
    $responseText = '';

    if (function_exists('curl_init')) {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);
        $curlResult = curl_exec($ch);
        $responseText = is_string($curlResult) ? $curlResult : '';
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = (string)curl_error($ch);

        if (($responseText === '' || $httpCode === 0) && $curlError !== '') {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 15,
                ]
            ]);
            $streamResponse = @file_get_contents($endpoint, false, $context);
            if (is_string($streamResponse) && $streamResponse !== '') {
                $responseText = $streamResponse;
                if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', (string)$http_response_header[0], $m)) {
                    $httpCode = (int)$m[1];
                }
            }
        }
    }

    if (!function_exists('curl_init')) {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 15,
            ]
        ]);
        $responseText = (string)@file_get_contents($endpoint, false, $context);
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', (string)$http_response_header[0], $m)) {
            $httpCode = (int)$m[1];
        }
    }

    if ($responseText === '' || $httpCode >= 400) {
        return [
            'models' => [],
            'source' => 'unavailable',
        ];
    }

    $decoded = json_decode($responseText, true);
    $models = [];
    if (is_array($decoded) && isset($decoded['models']) && is_array($decoded['models'])) {
        foreach ($decoded['models'] as $model) {
            $name = isset($model['name']) ? (string)$model['name'] : '';
            $methods = isset($model['supportedGenerationMethods']) && is_array($model['supportedGenerationMethods'])
                ? $model['supportedGenerationMethods']
                : [];

            if ($name === '' || !in_array('generateContent', $methods, true)) {
                continue;
            }

            $normalized = str_replace('models/', '', $name);
            $models[] = $normalized;
        }
    }

    $models = array_values(array_unique($models));

    if (!empty($models) && is_dir($dir) && is_writable($dir)) {
        file_put_contents($cacheFile, json_encode([
            'created_at' => time(),
            'models' => $models,
        ], JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    return [
        'models' => $models,
        'source' => 'api',
    ];
}

function ai_summary_build_model_candidates(string $configuredModel, array $discoveredModels): array
{
    $candidates = [];

    if ($configuredModel !== '') {
        $candidates[] = $configuredModel;
    }

    if (!empty($discoveredModels)) {
        $preferredDiscovered = [];
        foreach ($discoveredModels as $model) {
            $model = (string)$model;
            if (stripos($model, 'gemini-2.5') === 0 || stripos($model, 'gemini-2.0') === 0 || stripos($model, 'gemini-flash-latest') === 0) {
                $preferredDiscovered[] = $model;
            }
        }

        if (!empty($preferredDiscovered)) {
            $candidates = array_merge($candidates, $preferredDiscovered);
        }

        $candidates = array_merge($candidates, $discoveredModels);
    }

    // Last-resort defaults (avoid 1.5 to prevent v1beta model_not_found loops).
    $candidates = array_merge($candidates, [
        'gemini-2.5-flash',
        'gemini-2.0-flash',
        'gemini-flash-latest'
    ]);

    return array_values(array_unique(array_filter($candidates, static function ($item) {
        return is_string($item) && trim($item) !== '';
    })));
}

function ai_summary_count_list_lines(string $summary): int
{
    $lines = preg_split('/\R/u', $summary) ?: [];
    $count = 0;
    foreach ($lines as $line) {
        $line = trim((string)$line);
        if ($line === '') {
            continue;
        }
        if (preg_match('/^(?:[-*•]|\d+[.)])\s+/u', $line)) {
            $count++;
        }
    }
    return $count;
}

function ai_summary_is_quality_ok(string $summary, string $mode): bool
{
    $text = trim($summary);
    if ($text === '') {
        return false;
    }

    $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    $listLines = ai_summary_count_list_lines($text);
    $hasConclusion = stripos($text, 'Kết luận:') !== false;

    if ($mode === 'detailed') {
        return $length >= 320 && $listLines >= 6 && $hasConclusion;
    }

    return $length >= 120 && $listLines >= 4 && $hasConclusion;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ai_summary_json_response([
        'ok' => false,
        'message' => 'Method not allowed.'
    ], 405);
}

$postId = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
$summaryMode = isset($_POST['summary_mode']) ? trim((string)$_POST['summary_mode']) : 'short';
if (!in_array($summaryMode, ['short', 'detailed'], true)) {
    $summaryMode = 'short';
}

if ($postId <= 0) {
    ai_summary_json_response([
        'ok' => false,
        'message' => 'Thiếu mã bài viết.'
    ]);
}

$stmt = $conn->prepare('SELECT id, title, content FROM posts WHERE id = ? AND status = ? LIMIT 1');
$stmt->execute([$postId, 'active']);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    ai_summary_json_response([
        'ok' => false,
        'message' => 'Không tìm thấy bài viết phù hợp.'
    ]);
}

$geminiKey = trim((string)(getenv('GEMINI_API_KEY') ?: ''));
$decodedTitle = html_entity_decode((string)$post['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
$decodedContent = html_entity_decode((string)$post['content'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
$contentPlain = trim((string)preg_replace('/\s+/u', ' ', strip_tags($decodedContent)));
$fallbackSummary = blog_generate_quick_summary($decodedContent);
$postHash = sha1((string)$post['title'] . '|' . (string)$post['content']);

if ($contentPlain === '') {
    ai_summary_json_response([
        'ok' => false,
        'message' => 'Bài viết chưa có nội dung để tóm tắt.'
    ]);
}

$cached = ai_summary_read_cache($postId, $summaryMode, $postHash, 21600);
if ($cached !== null && ai_summary_is_quality_ok((string)$cached['summary'], $summaryMode)) {
    ai_summary_json_response([
        'ok' => true,
        'summary' => $cached['summary'],
        'source' => 'gemini-cache',
        'mode' => $summaryMode,
        'message' => 'Đã dùng bản tóm tắt cache để phản hồi nhanh.'
    ]);
}

if ($geminiKey === '') {
    ai_summary_json_response([
        'ok' => true,
        'summary' => $fallbackSummary,
        'source' => 'fallback',
        'mode' => $summaryMode,
        'message' => 'Chưa cấu hình GEMINI_API_KEY, hệ thống dùng tóm tắt nội bộ.'
    ]);
}

$identity = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0
    ? ('user:' . (int)$_SESSION['user_id'])
    : ('ip:' . ai_summary_client_ip());
$limitPerMinute = strpos($identity, 'user:') === 0 ? 12 : 6;
$rateResult = ai_summary_rate_limit($identity, $limitPerMinute, 60);
if (!$rateResult['allowed']) {
    ai_summary_json_response([
        'ok' => true,
        'summary' => $fallbackSummary,
        'source' => 'fallback',
        'mode' => $summaryMode,
        'message' => 'Bạn đang thao tác quá nhanh. Vui lòng thử lại sau ' . (int)$rateResult['retry_after'] . ' giây.'
    ]);
}

$promptGuide = $summaryMode === 'detailed'
    ? 'Hãy tóm tắt theo dạng CHI TIẾT: 8-12 ý được đánh số 1., 2., 3...; mỗi ý 1-2 câu; nêu rõ dữ kiện quan trọng, bối cảnh và ý nghĩa. Sau cùng ghi đúng 1 dòng "Kết luận:" gồm 2 câu ngắn.'
    : 'Hãy tóm tắt theo dạng NGẮN: 4-6 ý được đánh số 1., 2., 3...; mỗi ý tối đa 20 từ; tập trung ý chính. Sau cùng ghi đúng 1 dòng "Kết luận:" gồm 1 câu ngắn.';

$prompt = "Bạn là trợ lý biên tập tiếng Việt. "
    . $promptGuide
    . " Không bịa thông tin, chỉ dùng dữ kiện trong bài. Không mở đầu bằng câu dẫn kiểu 'Dưới đây là...'. Không dùng markdown tiêu đề.\n\nTiêu đề: "
    . $decodedTitle
    . "\n\nNội dung:\n"
    . $contentPlain;

$requestBody = [
    'contents' => [
        [
            'parts' => [
                ['text' => $prompt]
            ]
        ]
    ],
    'generationConfig' => [
        'temperature' => 0.3,
        'topK' => 32,
        'topP' => 0.9,
        'maxOutputTokens' => $summaryMode === 'detailed' ? 900 : 420,
    ]
];

$configuredModel = trim((string)(getenv('GEMINI_MODEL') ?: ''));
$discovery = ai_summary_discover_models($geminiKey, 21600);
$availableModels = isset($discovery['models']) && is_array($discovery['models']) ? $discovery['models'] : [];
$models = ai_summary_build_model_candidates($configuredModel, $availableModels);

$lastError = '';
$lastHttpCode = 0;
$geminiSummary = '';
$lastApiMessage = '';
$lastTransportError = '';
$qualityWarning = '';

foreach ($models as $model) {
    $result = ai_summary_request_gemini($model, $geminiKey, $requestBody);
    if (!empty($result['ok']) && !empty($result['summary'])) {
        $candidate = trim((string)$result['summary']);

        if (!ai_summary_is_quality_ok($candidate, $summaryMode)) {
            $retryPrompt = $prompt
                . "\n\nYêu cầu bắt buộc lại: câu trả lời phải ĐỦ ý, không bị cụt, đúng định dạng danh sách đánh số và có dòng 'Kết luận:'.";
            $retryBody = $requestBody;
            $retryBody['contents'][0]['parts'][0]['text'] = $retryPrompt;
            $retryBody['generationConfig']['maxOutputTokens'] = $summaryMode === 'detailed' ? 1400 : 520;
            $retryBody['generationConfig']['temperature'] = 0.2;

            $retryResult = ai_summary_request_gemini($model, $geminiKey, $retryBody);
            if (!empty($retryResult['ok']) && !empty($retryResult['summary'])) {
                $retryCandidate = trim((string)$retryResult['summary']);
                if (ai_summary_is_quality_ok($retryCandidate, $summaryMode)) {
                    $geminiSummary = $retryCandidate;
                    break;
                }
            }

            if (ai_summary_is_quality_ok($candidate, $summaryMode)) {
                $geminiSummary = $candidate;
                break;
            }

            $qualityWarning = 'Gemini phản hồi chưa đủ chi tiết theo định dạng yêu cầu.';
            continue;
        }

        $geminiSummary = $candidate;
        break;
    }
    $lastError = (string)($result['error'] ?? 'unknown_error');
    $lastHttpCode = (int)($result['http_code'] ?? 0);
    $lastApiMessage = (string)($result['api_message'] ?? '');
    $lastTransportError = (string)($result['transport_error'] ?? '');
}

if ($geminiSummary === '') {
    $recommendedModel = !empty($availableModels) ? $availableModels[0] : '';

    $reason = $lastHttpCode > 0
        ? ('(HTTP ' . $lastHttpCode . ', ' . $lastError . ')')
        : '(' . $lastError . ')';

    if ($lastApiMessage !== '') {
        $reason .= ' - ' . $lastApiMessage;
    }

    if ($configuredModel !== '' && !empty($availableModels) && !in_array($configuredModel, $availableModels, true)) {
        $reason .= ' - Model cấu hình hiện tại không có trong danh sách model khả dụng của key.';
    }

    $displayMessage = 'Gemini chưa phản hồi, hệ thống dùng tóm tắt nội bộ. ' . $reason;
    if ($lastHttpCode === 404 && $recommendedModel !== '') {
        $displayMessage .= ' Gợi ý: đặt GEMINI_MODEL=' . $recommendedModel . '.';
    }

    ai_summary_json_response([
        'ok' => true,
        'summary' => $fallbackSummary,
        'source' => 'fallback',
        'mode' => $summaryMode,
        'message' => $displayMessage,
        'debug' => [
            'http_code' => $lastHttpCode,
            'error' => $lastError,
            'provider_message' => $lastApiMessage,
            'transport_error' => $lastTransportError,
            'tried_models' => $models,
            'available_models_for_key' => $availableModels,
            'model_discovery_source' => $discovery['source'],
            'suggested_env' => $recommendedModel !== '' ? ('GEMINI_MODEL=' . $recommendedModel) : '',
        ],
    ]);
}

ai_summary_write_cache($postId, $summaryMode, $postHash, $geminiSummary);

ai_summary_json_response([
    'ok' => true,
    'summary' => $geminiSummary,
    'source' => 'gemini',
    'mode' => $summaryMode,
    'message' => $qualityWarning,
]);

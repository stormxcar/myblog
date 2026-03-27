<?php
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    echo json_encode([
        'ok' => false,
        'message' => 'Endpoint này chỉ nhận POST từ trang admin/add_posts.php',
        'hint' => 'Hãy dùng nút "AI tạo tiêu đề + nội dung" trong trang thêm bài viết',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

include '../components/connect.php';
include '../components/security_helpers.php';

$debugMode = (string)($_POST['debug'] ?? '') === '1';
$debugTrace = [];
$debugTrace[] = 'Init request';

// ====================== HÀM CŨ - GIỮ NGUYÊN 100% ======================
if (!function_exists('ai_extract_json_payload')) {
    function ai_extract_json_payload(string $text): ?array
    {
        $raw = trim($text);
        if ($raw === '') {
            return null;
        }
        $direct = json_decode($raw, true);
        if (is_array($direct)) {
            return $direct;
        }
        // Remove markdown fences like ```json ... ```
        $unfenced = preg_replace('/^```(?:json)?\s*|\s*```$/mi', '', $raw);
        if (is_string($unfenced) && trim($unfenced) !== '') {
            $parsed = json_decode(trim($unfenced), true);
            if (is_array($parsed)) {
                return $parsed;
            }
        }
        // Try extracting the first JSON object region.
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $slice = substr($raw, $start, $end - $start + 1);
            $parsed = json_decode($slice, true);
            if (is_array($parsed)) {
                return $parsed;
            }
        }
        return null;
    }
}

if (!function_exists('ai_extract_fallback_payload')) {
    function ai_extract_fallback_payload(string $text): array
    {
        $raw = trim((string)$text);
        if ($raw === '') {
            return [
                'titles' => [],
                'chosen_title' => '',
                'meta_description' => '',
                'keywords' => [],
                'content_html' => '',
            ];
        }
        $raw = preg_replace('/^```(?:html|markdown|json)?\s*|\s*```$/mi', '', $raw);
        $raw = trim((string)$raw);
        $chosenTitle = '';
        $meta = '';
        $keywords = [];
        $titles = [];
        $contentHtml = '';
        if (preg_match('/^\s*TITLE\s*:\s*(.+)$/mi', $raw, $m)) {
            $chosenTitle = trim((string)$m[1]);
        }
        if (preg_match('/^\s*TITLES\s*:\s*(.+)$/mi', $raw, $m)) {
            $titlesRaw = trim((string)$m[1]);
            $titles = array_values(array_filter(array_map('trim', preg_split('/[|;,]/', $titlesRaw ?: ''))));
        }
        if (preg_match('/^\s*META\s*:\s*(.+)$/mi', $raw, $m)) {
            $meta = trim((string)$m[1]);
        }
        if (preg_match('/^\s*KEYWORDS\s*:\s*(.+)$/mi', $raw, $m)) {
            $keywordsRaw = trim((string)$m[1]);
            $keywords = array_values(array_filter(array_map('trim', preg_split('/[,;|]/', $keywordsRaw ?: ''))));
        }
        if (preg_match('/CONTENT_HTML\s*:\s*(.*)$/is', $raw, $m)) {
            $contentHtml = trim((string)$m[1]);
        }
        if ($contentHtml === '') {
            $contentHtml = $raw;
        }
        if ($chosenTitle === '') {
            $chosenTitle = 'Bài viết về ' . mb_substr((string)($GLOBALS['topic'] ?? 'chủ đề'), 0, 80, 'UTF-8');
        }
        if (!$titles) {
            $titles = [$chosenTitle];
        }
        if (strip_tags($contentHtml) === $contentHtml) {
            $lines = array_values(array_filter(array_map('trim', preg_split('/\R+/', $contentHtml))));
            $paragraphs = array_map(static function ($line) {
                return '<p>' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</p>';
            }, $lines);
            $contentHtml = implode("\n", $paragraphs);
        }
        return [
            'titles' => $titles,
            'chosen_title' => $chosenTitle,
            'meta_description' => $meta,
            'keywords' => $keywords,
            'content_html' => $contentHtml,
        ];
    }
}

if (!function_exists('ai_extract_json_like_payload')) {
    function ai_extract_json_like_payload(string $text): ?array
    {
        $raw = trim((string)$text);
        if ($raw === '') {
            return null;
        }

        $raw = preg_replace('/^```(?:json)?\s*|\s*```$/mi', '', $raw);
        $raw = trim((string)$raw);

        $extractString = static function (string $field) use ($raw): string {
            $pattern = '/"' . preg_quote($field, '/') . '"\s*:\s*"((?:\\\\.|[^"\\\\])*)"/s';
            if (preg_match($pattern, $raw, $m)) {
                return trim(stripcslashes((string)$m[1]));
            }
            return '';
        };

        $extractArrayStrings = static function (string $field) use ($raw): array {
            $pattern = '/"' . preg_quote($field, '/') . '"\s*:\s*\[([\s\S]*?)\]/s';
            if (!preg_match($pattern, $raw, $m)) {
                return [];
            }
            $items = [];
            if (preg_match_all('/"((?:\\\\.|[^"\\\\])*)"/s', (string)$m[1], $matches)) {
                foreach ($matches[1] as $item) {
                    $decoded = trim(stripcslashes((string)$item));
                    if ($decoded !== '') {
                        $items[] = $decoded;
                    }
                }
            }
            return array_values(array_unique($items));
        };

        $contentHtml = '';
        if (preg_match('/"content_html"\s*:\s*"([\s\S]*?)"\s*(?:,\s*"[a-zA-Z0-9_]+"\s*:|\}\s*$)/s', $raw, $m)) {
            $contentHtml = trim(stripcslashes((string)$m[1]));
        }

        $chosen = $extractString('chosen_title');
        $meta = $extractString('meta_description');
        $titles = $extractArrayStrings('titles');
        $keywords = $extractArrayStrings('keywords');

        if ($chosen === '' && $contentHtml === '' && !$titles) {
            return null;
        }
        if ($chosen === '' && $titles) {
            $chosen = (string)$titles[0];
        }

        return [
            'titles' => $titles,
            'chosen_title' => $chosen,
            'meta_description' => $meta,
            'keywords' => $keywords,
            'content_html' => $contentHtml,
        ];
    }
}

if (!function_exists('ai_build_title_suggestions')) {
    function ai_build_title_suggestions(string $chosen, string $topic): array
    {
        $base = trim($chosen) !== '' ? trim($chosen) : ('Khám phá ' . trim($topic));
        $variants = [
            $base,
            $base . ' - Kinh nghiệm chi tiết từ A đến Z',
            'Review ' . trim($topic) . ': Lịch trình, chi phí và mẹo thực tế',
            trim($topic) . ': Nên đi khi nào, ăn gì, chơi gì?',
            'Cẩm nang ' . trim($topic) . ' cho người đi lần đầu',
        ];

        $clean = [];
        foreach ($variants as $v) {
            $t = trim((string)$v);
            if ($t !== '' && !in_array(mb_strtolower($t, 'UTF-8'), array_map(static fn($x) => mb_strtolower($x, 'UTF-8'), $clean), true)) {
                $clean[] = $t;
            }
            if (count($clean) >= 5) {
                break;
            }
        }
        return array_slice($clean, 0, 5);
    }
}

if (!function_exists('ai_normalize_content_html')) {
    function ai_normalize_content_html(string $content): string
    {
        $value = trim((string)$content);
        if ($value === '') {
            return '';
        }

        if (strpos($value, '"content_html"') !== false || strpos($value, '"chosen_title"') !== false) {
            $jsonLike = ai_extract_json_like_payload($value);
            if (is_array($jsonLike) && !empty($jsonLike['content_html'])) {
                $value = trim((string)$jsonLike['content_html']);
            }
        }

        if (strlen($value) >= 2 && $value[0] === '"' && $value[strlen($value) - 1] === '"') {
            $value = substr($value, 1, -1);
        }

        $value = stripcslashes($value);
        $value = str_replace(["\\r\\n", "\\n", "\\r", "\\t"], ["\n", "\n", "\n", "    "], $value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = trim($value);

        if (strip_tags($value) === $value) {
            $lines = array_values(array_filter(array_map('trim', preg_split('/\R+/', $value))));
            $value = implode("\n", array_map(static function ($line) {
                return '<p>' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</p>';
            }, $lines));
        }

        return $value;
    }
}

if (!function_exists('ai_call_gemini_text')) {
    function ai_call_gemini_text(string $endpoint, array $body, string $step = '', int $timeoutSec = 90): array
    {
        $payload = json_encode($body, JSON_UNESCAPED_UNICODE);
        $responseText = '';
        $httpCode = 0;
        $curlError = '';
        $streamError = '';
        $transport = 'none';

        if (function_exists('curl_init')) {
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_TIMEOUT => $timeoutSec,
                CURLOPT_CONNECTTIMEOUT => 10,
            ]);
            $result = curl_exec($ch);
            $responseText = is_string($result) ? $result : '';
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            $transport = 'curl';

            if ($responseText === '' && $curlError !== '') {
                // Fall through to stream fallback when cURL transport fails.
            }
        }

        if ($responseText === '') {
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => $payload,
                    'timeout' => $timeoutSec,
                    'ignore_errors' => true,
                ],
            ]);

            $result = @file_get_contents($endpoint, false, $ctx);
            if (is_string($result)) {
                $responseText = $result;
            }
            $transport = 'stream';
            $lastErr = error_get_last();
            if (is_array($lastErr) && !empty($lastErr['message'])) {
                $streamError = (string)$lastErr['message'];
            }

            if (isset($http_response_header) && is_array($http_response_header)) {
                foreach ($http_response_header as $headerLine) {
                    if (preg_match('/^HTTP\/\S+\s+(\d{3})/i', (string)$headerLine, $m)) {
                        $httpCode = (int)$m[1];
                        break;
                    }
                }
            }
        }

        if ($responseText === '') {
            return [
                'ok' => false,
                'message' => 'Không nhận được phản hồi từ Gemini.',
                'text' => '',
                'http_code' => $httpCode,
                'transport' => $transport,
                'curl_error' => $curlError,
                'stream_error' => $streamError,
                'step' => $step,
            ];
        }

        $decoded = json_decode($responseText, true);
        if (!is_array($decoded)) {
            return [
                'ok' => false,
                'message' => 'Phản hồi AI không hợp lệ.',
                'text' => '',
                'http_code' => $httpCode,
                'transport' => $transport,
                'curl_error' => $curlError,
                'stream_error' => $streamError,
                'step' => $step,
            ];
        }

        if (!empty($decoded['promptFeedback']['blockReason'])) {
            return [
                'ok' => false,
                'message' => 'Gemini chặn nội dung yêu cầu. Vui lòng đổi chủ đề.',
                'text' => '',
                'http_code' => $httpCode,
                'transport' => $transport,
                'curl_error' => $curlError,
                'stream_error' => $streamError,
                'step' => $step,
            ];
        }

        if ($httpCode >= 400 || isset($decoded['error'])) {
            $message = (string)($decoded['error']['message'] ?? 'Gemini request failed');
            return [
                'ok' => false,
                'message' => 'Gemini lỗi: ' . $message,
                'text' => '',
                'http_code' => $httpCode,
                'transport' => $transport,
                'curl_error' => $curlError,
                'stream_error' => $streamError,
                'step' => $step,
            ];
        }

        $text = trim((string)($decoded['candidates'][0]['content']['parts'][0]['text'] ?? ''));
        if ($text === '') {
            return [
                'ok' => false,
                'message' => 'AI không trả về nội dung.',
                'text' => '',
                'http_code' => $httpCode,
                'transport' => $transport,
                'curl_error' => $curlError,
                'stream_error' => $streamError,
                'step' => $step,
            ];
        }

        return [
            'ok' => true,
            'message' => '',
            'text' => $text,
            'http_code' => $httpCode,
            'transport' => $transport,
            'curl_error' => $curlError,
            'stream_error' => $streamError,
            'step' => $step,
        ];
    }
}

if (!function_exists('ai_extract_outline_fallback_payload')) {
    function ai_extract_outline_fallback_payload(string $text, array $templates): ?array
    {
        $raw = trim((string)$text);
        if ($raw === '') {
            return null;
        }

        $raw = preg_replace('/^```(?:json|markdown|html)?\s*|\s*```$/mi', '', $raw);
        $raw = trim((string)$raw);

        $chosenTemplate = '';
        if (preg_match('/^\s*CHOSEN_TEMPLATE\s*:\s*([a-z_\-]+)/mi', $raw, $m)) {
            $candidate = strtolower(trim((string)$m[1]));
            if (isset($templates[$candidate])) {
                $chosenTemplate = $candidate;
            }
        }
        if ($chosenTemplate === '') {
            $chosenTemplate = 'how_to';
        }

        $primaryKeyword = '';
        if (preg_match('/^\s*PRIMARY_KEYWORD\s*:\s*(.+)$/mi', $raw, $m)) {
            $primaryKeyword = trim((string)$m[1]);
        }

        $entities = [];
        if (preg_match('/^\s*ENTITIES\s*:\s*(.+)$/mi', $raw, $m)) {
            $entities = array_values(array_filter(array_map('trim', preg_split('/[,;|]/', (string)$m[1]))));
        }

        $lsi = [];
        if (preg_match('/^\s*LSI_KEYWORDS\s*:\s*(.+)$/mi', $raw, $m)) {
            $lsi = array_values(array_filter(array_map('trim', preg_split('/[,;|]/', (string)$m[1]))));
        }

        $toc = '';
        if (preg_match('/TABLE_OF_CONTENTS_HTML\s*:\s*(.*?)(?:\n\s*OUTLINE\s*:|$)/is', $raw, $m)) {
            $toc = trim((string)$m[1]);
        }

        $outline = '';
        if (preg_match('/OUTLINE\s*:\s*(.*)$/is', $raw, $m)) {
            $outline = trim((string)$m[1]);
        }

        if ($primaryKeyword === '' && $outline === '' && $toc === '') {
            return null;
        }

        return [
            'chosen_template' => $chosenTemplate,
            'primary_keyword' => $primaryKeyword,
            'entities' => $entities,
            'lsi_keywords' => $lsi,
            'table_of_contents' => $toc,
            'outline' => $outline,
        ];
    }
}

// ====================== PHẦN MỚI - MULTI-STEP (chuẩn claude-blog) ======================
session_start();
if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Phiên đăng nhập quản trị đã hết hạn.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$topic = trim((string)($_POST['topic'] ?? ''));
$style = trim((string)($_POST['style'] ?? 'storytelling'));

if ($topic === '' || mb_strlen($topic, 'UTF-8') < 3) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Vui lòng nhập chủ đề tối thiểu 3 ký tự.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$geminiKey = trim((string)(getenv('GEMINI_API_KEY') ?: getenv('GOOGLE_API_KEY') ?: ''));
if ($geminiKey === '') {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Chưa cấu hình GEMINI_API_KEY trên server.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$model = trim((string)(getenv('GEMINI_WRITER_MODEL') ?: getenv('GEMINI_MODEL') ?: 'gemini-2.5-flash'));
$debugTrace[] = 'Model: ' . $model;

// ================== 1. TEMPLATES & SYSTEM INSTRUCTION ==================
$templates = [
    'ultimate_guide' => 'Cẩm nang toàn tập, chuyên sâu, giải thích từ khái niệm đến nâng cao.',
    'how_to'        => 'Hướng dẫn từng bước (Step-by-step), tập trung vào hành động và kết quả.',
    'listicle'      => 'Danh sách tổng hợp (Top 10+, Best of...), định dạng ngắn gọn, dễ đọc lướt.',
    'comparison'    => 'So sánh đối đầu, phân tích ưu nhược điểm của 2 hoặc nhiều đối tượng.',
    'case_study'    => 'Phân tích một trường hợp thực tế, có số liệu và bài học kinh nghiệm.',
    'faq'           => 'Giải đáp các thắc mắc phổ biến, cấu trúc câu hỏi và câu trả lời ngắn gọn.'
];

$systemInstruction = "Bạn là chuyên gia chiến lược nội dung SEO. Dựa trên TOPIC: '{$topic}', hãy đóng vai một chuyên gia trong ngành đó. Sử dụng cấu trúc bài viết chuẩn E-E-A-T và tối ưu hóa GEO (Generative Engine Optimization).";

// ================== CALL 1: TẠO OUTLINE + CHỌN TEMPLATE ==================
$promptOutline = $systemInstruction . "\n\n"
    . "Nhiệm vụ 1: Phân tích chủ đề \"{$topic}\" và chọn 1 template phù hợp nhất từ danh sách sau:\n"
    . json_encode($templates, JSON_UNESCAPED_UNICODE) . "\n\n"
    . "Nhiệm vụ 2: Tạo DETAILED OUTLINE theo đúng cấu trúc chuẩn claude-blog + GEO 2026:\n"
    . "- Table of Contents (Mục lục với link anchor)\n"
    . "- Primary keyword + Semantic entities (thực thể quan trọng)\n"
    . "- LSI keywords (từ khóa liên quan ngữ nghĩa)\n"
    . "- Các H2/H3 phải chứa entities\n"
    . "- Gợi ý số liệu, ví dụ thực tế hợp lý\n\n"
    . "Trả về JSON đúng schema sau (không thêm text thừa):\n"
    . "{\n"
    . "  \"chosen_template\": \"ultimate_guide hoặc how_to hoặc ...\",\n"
    . "  \"primary_keyword\": \"...\",\n"
    . "  \"entities\": [\"entity1\", \"entity2\", ...],\n"
    . "  \"lsi_keywords\": [\"lsi1\", \"lsi2\", ...],\n"
    . "  \"table_of_contents\": \"<ul>...</ul>\",\n"
    . "  \"outline\": \"H2 1: ...\\nH2 2: ... (chi tiết từng phần)\"\n"
    . "}";

$endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($geminiKey);

$body1 = [
    'contents' => [[ 'role' => 'user', 'parts' => [['text' => $promptOutline]] ]],
    'generationConfig' => [
        'temperature' => 0.7,
        'topP' => 0.95,
        'maxOutputTokens' => 8192,
    ]
];

$call1 = ai_call_gemini_text($endpoint, $body1, 'call1-outline-json', 90);
$debugTrace[] = 'CALL1 status=' . (($call1['ok'] ?? false) ? 'ok' : 'fail')
    . ' transport=' . (string)($call1['transport'] ?? 'n/a')
    . ' http=' . (string)($call1['http_code'] ?? '0')
    . ' curl_error=' . (string)($call1['curl_error'] ?? '')
    . ' stream_error=' . (string)($call1['stream_error'] ?? '');
if (!$call1['ok']) {
    http_response_code(502);
    echo json_encode([
        'ok' => false,
        'message' => $call1['message'],
        'debug_trace' => $debugMode ? $debugTrace : [],
        'transport' => $debugMode ? ($call1['transport'] ?? '') : null,
        'curl_error' => $debugMode ? ($call1['curl_error'] ?? '') : null,
        'stream_error' => $debugMode ? ($call1['stream_error'] ?? '') : null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$outlineData = ai_extract_json_payload($call1['text']);
if (!is_array($outlineData)) {
    $debugTrace[] = 'CALL1 JSON parse fail -> try fallback text format';
    $promptOutlineFallback = $systemInstruction . "\n\n"
        . "Hãy trả THUẦN TEXT theo đúng định dạng sau, không markdown, không JSON:\n"
        . "CHOSEN_TEMPLATE: <one of: ultimate_guide, how_to, listicle, comparison, case_study, faq>\n"
        . "PRIMARY_KEYWORD: <keyword chính>\n"
        . "ENTITIES: <entity1, entity2, entity3>\n"
        . "LSI_KEYWORDS: <lsi1, lsi2, lsi3>\n"
        . "TABLE_OF_CONTENTS_HTML: <ul><li>...</li></ul>\n"
        . "OUTLINE: <H2/H3 chi tiết nhiều dòng>\n";

    $body1Fallback = [
        'contents' => [[
            'role' => 'user',
            'parts' => [['text' => $promptOutlineFallback]],
        ]],
        'generationConfig' => [
            'temperature' => 0.6,
            'topP' => 0.9,
            'maxOutputTokens' => 4096,
        ],
    ];

    $call1Fallback = ai_call_gemini_text($endpoint, $body1Fallback, 'call1-outline-text-fallback', 90);
    $debugTrace[] = 'CALL1-FB status=' . (($call1Fallback['ok'] ?? false) ? 'ok' : 'fail')
        . ' transport=' . (string)($call1Fallback['transport'] ?? 'n/a')
        . ' http=' . (string)($call1Fallback['http_code'] ?? '0')
        . ' curl_error=' . (string)($call1Fallback['curl_error'] ?? '')
        . ' stream_error=' . (string)($call1Fallback['stream_error'] ?? '');
    if (!($call1Fallback['ok'] ?? false)) {
        http_response_code(502);
        echo json_encode([
            'ok' => false,
            'message' => 'Không lấy được Outline từ AI: ' . (string)($call1Fallback['message'] ?? 'Lỗi không xác định'),
            'debug_trace' => $debugMode ? $debugTrace : [],
            'transport' => $debugMode ? ($call1Fallback['transport'] ?? '') : null,
            'curl_error' => $debugMode ? ($call1Fallback['curl_error'] ?? '') : null,
            'stream_error' => $debugMode ? ($call1Fallback['stream_error'] ?? '') : null,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $outlineData = ai_extract_outline_fallback_payload((string)($call1Fallback['text'] ?? ''), $templates);
    if (!is_array($outlineData)) {
        http_response_code(502);
        echo json_encode([
            'ok' => false,
            'message' => 'Không lấy được Outline từ AI',
            'raw_preview' => mb_substr((string)($call1Fallback['text'] ?? ''), 0, 260, 'UTF-8'),
            'debug_trace' => $debugMode ? $debugTrace : [],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ================== CALL 2: VIẾT FULL CONTENT (dùng Outline) ==================
$promptContent = $systemInstruction . "\n\n"
    . "Bạn đã chọn template: " . ($outlineData['chosen_template'] ?? 'how_to') . "\n"
    . "Primary keyword: " . ($outlineData['primary_keyword'] ?? $topic) . "\n"
    . "Semantic entities: " . implode(', ', $outlineData['entities'] ?? []) . "\n"
    . "LSI keywords: " . implode(', ', $outlineData['lsi_keywords'] ?? []) . "\n\n"
    . "Dùng Table of Contents sau:\n" . ($outlineData['table_of_contents'] ?? '') . "\n\n"
    . "Viết bài blog hoàn chỉnh 2500-4500 từ bằng tiếng Việt, phong cách {$style}.\n\n"
    . "CẤU TRÚC BẮT BUỘC (chuẩn claude-blog + GEO 2026):\n"
    . "1. <h1>Tiêu đề chứa primary keyword</h1>\n"
    . "2. Table of Contents (dùng đúng outline trên)\n"
    . "3. Sapô (Intro): 120-180 từ, chứa primary keyword trong 50 từ đầu tiên + ANSWER-FIRST\n"
    . "4. Thân bài: Mỗi đoạn văn tối đa 3-4 dòng (mobile-first). H2/H3 phải chứa entities. Dùng Bold, Bullet, bảng khi cần.\n"
    . "5. FAQ Zone: Ít nhất 5 câu hỏi thường gặp + trả lời chi tiết (để chiếm vị trí 0)\n"
    . "6. Kết bài: Key takeaways + CTA mạnh mẽ\n"
    . "7. Cuối bài: Chèn JSON-LD Schema (Article + FAQPage) dạng comment HTML\n"
    . "8. Thêm Author byline gợi ý để tăng E-E-A-T\n\n"
    . "Yêu cầu GEO:\n"
    . "- Tập trung semantic entities thay vì nhồi từ khóa\n"
    . "- Số liệu, ví dụ thực tế hợp lý\n"
    . "- Cấu trúc rõ ràng để AI dễ trích dẫn\n\n"
    . "Trả về JSON:\n"
    . "{\n"
    . "  \"titles\": [\"title1\", \"title2\", \"title3\"],\n"
    . "  \"chosen_title\": \"...\",\n"
    . "  \"meta_description\": \"... (<=160 ký tự)\",\n"
    . "  \"keywords\": [\"...\"],\n"
    . "  \"content_html\": \"<h1>...</h1><p>...</p> ... (toàn bộ HTML)\"\n"
    . "}";

$body2 = [
    'contents' => [[ 'role' => 'user', 'parts' => [['text' => $promptContent]] ]],
    'generationConfig' => [
        'temperature' => 0.7,
        'topP' => 0.95,
        'maxOutputTokens' => 16384,
    ]
];

$call2 = ai_call_gemini_text($endpoint, $body2, 'call2-content-json', 120);
$debugTrace[] = 'CALL2 status=' . (($call2['ok'] ?? false) ? 'ok' : 'fail')
    . ' transport=' . (string)($call2['transport'] ?? 'n/a')
    . ' http=' . (string)($call2['http_code'] ?? '0')
    . ' curl_error=' . (string)($call2['curl_error'] ?? '')
    . ' stream_error=' . (string)($call2['stream_error'] ?? '');

if (!($call2['ok'] ?? false)) {
    $debugTrace[] = 'CALL2 fail -> retry with lighter prompt';
    $promptContentRetry = $systemInstruction . "\n\n"
        . "Dựa trên dữ liệu sau, hãy viết bài chất lượng cao nhưng ngắn hơn để đảm bảo phản hồi nhanh.\n"
        . "Template: " . ($outlineData['chosen_template'] ?? 'how_to') . "\n"
        . "Primary keyword: " . ($outlineData['primary_keyword'] ?? $topic) . "\n"
        . "Entities: " . implode(', ', $outlineData['entities'] ?? []) . "\n"
        . "Table of contents: " . ($outlineData['table_of_contents'] ?? '') . "\n\n"
        . "Yêu cầu: 1400-2200 từ, có H1/H2/H3, FAQ >= 4 câu, kết bài có CTA.\n"
        . "Trả về đúng JSON schema:\n"
        . "{\n"
        . "  \"titles\": [\"title1\", \"title2\", \"title3\"],\n"
        . "  \"chosen_title\": \"...\",\n"
        . "  \"meta_description\": \"...\",\n"
        . "  \"keywords\": [\"...\"],\n"
        . "  \"content_html\": \"<h1>...</h1><p>...</p>...\"\n"
        . "}";

    $body2Retry = [
        'contents' => [[
            'role' => 'user',
            'parts' => [['text' => $promptContentRetry]],
        ]],
        'generationConfig' => [
            'temperature' => 0.65,
            'topP' => 0.9,
            'maxOutputTokens' => 8192,
        ],
    ];

    $call2Retry = ai_call_gemini_text($endpoint, $body2Retry, 'call2-content-json-retry-light', 120);
    $debugTrace[] = 'CALL2-RETRY status=' . (($call2Retry['ok'] ?? false) ? 'ok' : 'fail')
        . ' transport=' . (string)($call2Retry['transport'] ?? 'n/a')
        . ' http=' . (string)($call2Retry['http_code'] ?? '0')
        . ' curl_error=' . (string)($call2Retry['curl_error'] ?? '')
        . ' stream_error=' . (string)($call2Retry['stream_error'] ?? '');

    if ($call2Retry['ok'] ?? false) {
        $call2 = $call2Retry;
    }
}

if (!$call2['ok']) {
    http_response_code(502);
    echo json_encode([
        'ok' => false,
        'message' => $call2['message'],
        'debug_trace' => $debugMode ? $debugTrace : [],
        'transport' => $debugMode ? ($call2['transport'] ?? '') : null,
        'curl_error' => $debugMode ? ($call2['curl_error'] ?? '') : null,
        'stream_error' => $debugMode ? ($call2['stream_error'] ?? '') : null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$text = $call2['text'];
$data = ai_extract_json_payload($text);

if (!is_array($data)) {
    $debugTrace[] = 'CALL2 JSON parse fail -> try json-like parser';
    $data = ai_extract_json_like_payload($text);
}

if (!is_array($data)) {
    $debugTrace[] = 'CALL2 JSON parse fail -> fallback text parser';
    $data = ai_extract_fallback_payload($text);
}

// ================== TRẢ VỀ KẾT QUẢ ==================
$titles = [];
if (isset($data['titles']) && is_array($data['titles'])) {
    $titles = array_values(array_filter(array_map(static fn($v) => trim((string)$v), $data['titles'])));
}

$chosen = trim((string)($data['chosen_title'] ?? ''));
if ($chosen === '' && !empty($titles)) {
    $chosen = (string)$titles[0];
}

$titles[] = $chosen;
$titles = array_values(array_filter(array_unique(array_map(static fn($v) => trim((string)$v), $titles))));
if (count($titles) < 2) {
    $titles = ai_build_title_suggestions($chosen, $topic);
}
$titles = array_slice($titles, 0, 5);
if ($chosen === '' && !empty($titles)) {
    $chosen = (string)$titles[0];
}

$meta = $data['meta_description'] ?? '';
$keywords = $data['keywords'] ?? [];
$contentHtml = ai_normalize_content_html((string)($data['content_html'] ?? ''));

$debugTrace[] = 'Titles count=' . count($titles) . ' content_length=' . strlen($contentHtml);

if (trim((string)$chosen) === '' || trim((string)$contentHtml) === '') {
    http_response_code(502);
    echo json_encode([
        'ok' => false,
        'message' => 'AI chưa trả đủ tiêu đề hoặc nội dung bài viết.',
        'debug_trace' => $debugMode ? $debugTrace : [],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$debugTrace[] = 'DONE success';

echo json_encode([
    'ok' => true,
    'titles' => $titles,
    'chosen_title' => $chosen,
    'meta_description' => $meta,
    'keywords' => $keywords,
    'content_html' => $contentHtml,
    'debug_outline' => $outlineData,
    'debug_trace' => $debugMode ? $debugTrace : [],
], JSON_UNESCAPED_UNICODE);

exit;
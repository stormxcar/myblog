<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

$envPath = __DIR__ . '/../../';
if (class_exists('Dotenv\\Dotenv')) {
    try {
        Dotenv\Dotenv::createImmutable($envPath)->safeLoad();
    } catch (Throwable $e) {
    }
}

$key = trim((string)(getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? '')));
if ($key === '') {
    fwrite(STDERR, "Missing GEMINI_API_KEY in environment.\n");
    exit(1);
}

$url = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . rawurlencode($key);
$httpCode = 0;
$responseText = '';

if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
    ]);
    $curlResult = curl_exec($ch);
    $responseText = is_string($curlResult) ? $curlResult : '';
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = (string)curl_error($ch);
    curl_close($ch);

    if (($responseText === '' || $httpCode === 0) && $curlError !== '') {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 20,
            ]
        ]);
        $streamResponse = @file_get_contents($url, false, $context);
        if (is_string($streamResponse) && $streamResponse !== '') {
            $responseText = $streamResponse;
            if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', (string)$http_response_header[0], $m)) {
                $httpCode = (int)$m[1];
            }
        } else {
            fwrite(STDERR, "Request failed: {$curlError}\n");
            exit(2);
        }
    }
} else {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 20,
        ]
    ]);
    $responseText = (string)@file_get_contents($url, false, $context);
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', (string)$http_response_header[0], $m)) {
        $httpCode = (int)$m[1];
    }
}

if ($responseText === '') {
    fwrite(STDERR, "No response from Gemini models API.\n");
    exit(3);
}

$data = json_decode($responseText, true);
if (!is_array($data)) {
    fwrite(STDERR, "Invalid JSON response. HTTP={$httpCode}\n");
    exit(4);
}

if ($httpCode >= 400) {
    $message = '';
    if (isset($data['error']['message'])) {
        $message = (string)$data['error']['message'];
    }
    fwrite(STDERR, "Gemini API error. HTTP={$httpCode}" . ($message !== '' ? ": {$message}" : '') . "\n");
    exit(5);
}

$models = [];
if (isset($data['models']) && is_array($data['models'])) {
    foreach ($data['models'] as $model) {
        $name = isset($model['name']) ? (string)$model['name'] : '';
        $methods = isset($model['supportedGenerationMethods']) && is_array($model['supportedGenerationMethods'])
            ? $model['supportedGenerationMethods']
            : [];

        if ($name === '' || !in_array('generateContent', $methods, true)) {
            continue;
        }

        $models[] = str_replace('models/', '', $name);
    }
}

$models = array_values(array_unique($models));
if (empty($models)) {
    echo "No generateContent models available for this key.\n";
    exit(0);
}

echo "Available generateContent models for this key:\n";
foreach ($models as $index => $model) {
    echo sprintf("%2d. %s\n", $index + 1, $model);
}

echo "\nSuggested .env:\n";
echo "GEMINI_MODEL={$models[0]}\n";

<?php

if (!function_exists('blog_is_external_url')) {
    function blog_is_external_url($value)
    {
        if (!is_string($value) || trim($value) === '') {
            return false;
        }
        return (bool)preg_match('/^(https?:)?\/\//i', trim($value));
    }
}

if (!function_exists('blog_post_image_src')) {
    function blog_post_image_src($image, $localPrefix = '../uploaded_img/', $fallback = '')
    {
        $image = trim((string)$image);
        if ($image === '') {
            return $fallback;
        }

        if (blog_is_external_url($image)) {
            return $image;
        }

        return $localPrefix . ltrim($image, '/');
    }
}

if (!function_exists('blog_cloudinary_is_configured')) {
    function blog_cloudinary_is_configured()
    {
        return (bool)(getenv('CLOUDINARY_CLOUD_NAME') && getenv('CLOUDINARY_API_KEY') && getenv('CLOUDINARY_API_SECRET'));
    }
}

if (!function_exists('blog_cloudinary_default_folder')) {
    function blog_cloudinary_default_folder()
    {
        $folder = trim((string)getenv('CLOUDINARY_FOLDER'));
        return $folder !== '' ? $folder : 'myblog';
    }
}

if (!function_exists('blog_cloudinary_sign_params')) {
    function blog_cloudinary_sign_params(array $params, $apiSecret)
    {
        ksort($params);
        $parts = [];
        foreach ($params as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $parts[] = $key . '=' . $value;
        }
        $toSign = implode('&', $parts) . $apiSecret;
        return sha1($toSign);
    }
}

if (!function_exists('blog_cloudinary_upload')) {
    function blog_cloudinary_upload($fileInput, $targetFolder = null)
    {
        if (!blog_cloudinary_is_configured()) {
            return ['ok' => false, 'error' => 'Thiếu cấu hình Cloudinary trong .env'];
        }

        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error' => 'Máy chủ chưa bật cURL để upload Cloudinary'];
        }

        $cloudName = (string)getenv('CLOUDINARY_CLOUD_NAME');
        $apiKey = (string)getenv('CLOUDINARY_API_KEY');
        $apiSecret = (string)getenv('CLOUDINARY_API_SECRET');
        $folder = trim((string)$targetFolder);
        if ($folder === '') {
            $folder = blog_cloudinary_default_folder();
        }

        $endpoint = 'https://api.cloudinary.com/v1_1/' . rawurlencode($cloudName) . '/image/upload';
        $timestamp = time();

        $signPayload = [
            'folder' => $folder,
            'timestamp' => $timestamp,
        ];
        $signature = blog_cloudinary_sign_params($signPayload, $apiSecret);

        $postFields = [
            'api_key' => $apiKey,
            'timestamp' => $timestamp,
            'signature' => $signature,
            'folder' => $folder,
        ];

        if (is_array($fileInput) && isset($fileInput['tmp_name'])) {
            $tmpName = (string)$fileInput['tmp_name'];
            if ($tmpName === '' || !is_file($tmpName)) {
                return ['ok' => false, 'error' => 'Không tìm thấy file tạm để upload'];
            }
            $mimeType = (string)($fileInput['type'] ?? 'image/jpeg');
            $originalName = (string)($fileInput['name'] ?? ('upload_' . $timestamp . '.jpg'));
            $postFields['file'] = new CURLFile($tmpName, $mimeType, $originalName);
        } elseif (is_string($fileInput) && trim($fileInput) !== '') {
            $postFields['file'] = trim($fileInput);
        } else {
            return ['ok' => false, 'error' => 'Dữ liệu upload không hợp lệ'];
        }

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45);

        $raw = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'error' => 'Upload Cloudinary thất bại: ' . $curlErr];
        }

        $decoded = json_decode($raw, true);
        if ($httpCode < 200 || $httpCode >= 300 || !is_array($decoded) || empty($decoded['secure_url'])) {
            $errorText = 'Upload Cloudinary thất bại';
            if (is_array($decoded) && isset($decoded['error']['message'])) {
                $errorText .= ': ' . $decoded['error']['message'];
            }
            return ['ok' => false, 'error' => $errorText];
        }

        return [
            'ok' => true,
            'secure_url' => (string)$decoded['secure_url'],
            'public_id' => (string)($decoded['public_id'] ?? ''),
        ];
    }
}

if (!function_exists('blog_cloudinary_extract_public_id')) {
    function blog_cloudinary_extract_public_id($url)
    {
        $url = trim((string)$url);
        if ($url === '' || !blog_is_external_url($url)) {
            return '';
        }

        $parts = parse_url($url);
        if (!$parts || empty($parts['path'])) {
            return '';
        }

        $path = (string)$parts['path'];
        $uploadPos = strpos($path, '/upload/');
        if ($uploadPos === false) {
            return '';
        }

        $tail = substr($path, $uploadPos + 8);
        $segments = array_values(array_filter(explode('/', $tail), 'strlen'));
        if (empty($segments)) {
            return '';
        }

        while (!empty($segments) && preg_match('/^v\d+$/', $segments[0])) {
            array_shift($segments);
        }

        if (empty($segments)) {
            return '';
        }

        $filename = array_pop($segments);
        $filenameNoExt = preg_replace('/\.[A-Za-z0-9]+$/', '', $filename);
        $segments[] = $filenameNoExt;
        $publicId = implode('/', $segments);
        return trim(urldecode($publicId), '/');
    }
}

if (!function_exists('blog_cloudinary_delete_by_public_id')) {
    function blog_cloudinary_delete_by_public_id($publicId)
    {
        $publicId = trim((string)$publicId);
        if ($publicId === '' || !blog_cloudinary_is_configured() || !function_exists('curl_init')) {
            return false;
        }

        $cloudName = (string)getenv('CLOUDINARY_CLOUD_NAME');
        $apiKey = (string)getenv('CLOUDINARY_API_KEY');
        $apiSecret = (string)getenv('CLOUDINARY_API_SECRET');
        $timestamp = time();

        $signPayload = [
            'public_id' => $publicId,
            'timestamp' => $timestamp,
        ];
        $signature = blog_cloudinary_sign_params($signPayload, $apiSecret);

        $endpoint = 'https://api.cloudinary.com/v1_1/' . rawurlencode($cloudName) . '/image/destroy';
        $postFields = [
            'public_id' => $publicId,
            'api_key' => $apiKey,
            'timestamp' => $timestamp,
            'signature' => $signature,
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $raw = curl_exec($ch);
        curl_close($ch);

        if (!is_string($raw) || $raw === '') {
            return false;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) && (($decoded['result'] ?? '') === 'ok' || ($decoded['result'] ?? '') === 'not found');
    }
}

if (!function_exists('blog_delete_image_resource')) {
    function blog_delete_image_resource($imageValue)
    {
        $imageValue = trim((string)$imageValue);
        if ($imageValue === '') {
            return true;
        }

        if (blog_is_external_url($imageValue)) {
            $publicId = blog_cloudinary_extract_public_id($imageValue);
            if ($publicId !== '') {
                return blog_cloudinary_delete_by_public_id($publicId);
            }
            return false;
        }

        $legacyPath = __DIR__ . '/../uploaded_img/' . ltrim($imageValue, '/');
        if (is_file($legacyPath)) {
            return @unlink($legacyPath);
        }

        return true;
    }
}

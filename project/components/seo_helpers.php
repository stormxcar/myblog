<?php

if (!function_exists('site_base_path')) {
    function site_base_path()
    {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $script = str_replace('\\', '/', $script);

        $markers = ['/static/', '/admin/', '/components/'];
        foreach ($markers as $marker) {
            $pos = strpos($script, $marker);
            if ($pos !== false) {
                return rtrim(substr($script, 0, $pos), '/');
            }
        }

        return rtrim(dirname($script), '/');
    }
}

if (!function_exists('site_url')) {
    function site_url($path = '')
    {
        $base = site_base_path();
        $path = ltrim($path, '/');

        if ($path === '') {
            return $base === '' ? '/' : $base . '/';
        }

        return ($base === '' ? '' : $base) . '/' . $path;
    }
}

if (!function_exists('slugify')) {
    function slugify($text)
    {
        $text = trim((string)$text);
        $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);

        $map = [
            'a' => 'à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ',
            'e' => 'è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ',
            'i' => 'ì|í|ị|ỉ|ĩ',
            'o' => 'ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ',
            'u' => 'ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ',
            'y' => 'ỳ|ý|ỵ|ỷ|ỹ',
            'd' => 'đ',
        ];

        foreach ($map as $ascii => $regex) {
            $text = preg_replace('/(' . $regex . ')/u', $ascii, $text);
        }

        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s-]+/', '-', $text);
        $text = trim($text, '-');

        return $text === '' ? 'post' : $text;
    }
}

if (!function_exists('post_slug')) {
    function post_slug($title, $id)
    {
        return slugify($title) . '-' . (int)$id;
    }
}

if (!function_exists('post_path')) {
    function post_path($id, $title = null)
    {
        $id = (int)$id;
        $slug = $title !== null ? post_slug($title, $id) : ('post-' . $id);
        return site_url('post/' . $slug);
    }
}

if (!function_exists('extract_post_id_from_slug')) {
    function extract_post_id_from_slug($slug)
    {
        if (!is_string($slug)) {
            return 0;
        }

        if (preg_match('/^(\d+)$/', $slug, $m)) {
            return (int)$m[1];
        }

        if (preg_match('/-(\d+)$/', $slug, $m)) {
            return (int)$m[1];
        }

        return 0;
    }
}

if (!function_exists('canonical_current_url')) {
    function canonical_current_url()
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        parse_str(parse_url($uri, PHP_URL_QUERY) ?? '', $params);
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $utm) {
            unset($params[$utm]);
        }

        $path = parse_url($uri, PHP_URL_PATH) ?? '/';
        $query = http_build_query($params);
        $clean = $path . ($query ? ('?' . $query) : '');

        return $scheme . '://' . $host . $clean;
    }
}

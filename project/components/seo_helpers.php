<?php

if (!function_exists('blog_brand_name')) {
    function blog_brand_name()
    {
        return 'Blog';
    }
}

if (!function_exists('blog_brand_logo_url')) {
    function blog_brand_logo_url()
    {
        $configured = trim((string)(getenv('SITE_LOGO_URL') ?: ''));
        if ($configured !== '') {
            return $configured;
        }

        return 'https://res.cloudinary.com/dzwjgfd7t/image/upload/c_thumb,w_200,g_face/v1774397898/myblog/LOGO-removebg-preview_ykzzmy.png';
    }
}

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

if (!function_exists('blog_is_local_host')) {
    function blog_is_local_host($host)
    {
        $host = strtolower(trim((string)$host));
        if ($host === '') {
            return false;
        }

        $hostNoPort = preg_replace('/:\\d+$/', '', $host);
        return in_array($hostNoPort, ['localhost', '127.0.0.1', '::1'], true);
    }
}

if (!function_exists('blog_request_scheme')) {
    function blog_request_scheme()
    {
        $forwardedProto = trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        if ($forwardedProto !== '') {
            $parts = explode(',', $forwardedProto);
            $proto = strtolower(trim((string)($parts[0] ?? '')));
            if ($proto === 'https' || $proto === 'http') {
                return $proto;
            }
        }

        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    }
}

if (!function_exists('site_origin_url')) {
    function site_origin_url()
    {
        $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));

        // Always prefer detected localhost domain while testing locally.
        if ($host !== '' && blog_is_local_host($host)) {
            return blog_request_scheme() . '://' . $host;
        }

        // For deployed env, allow explicit canonical domain from env.
        $configured = trim((string)(getenv('APP_URL') ?: getenv('SITE_URL') ?: ''));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        if ($host !== '') {
            return blog_request_scheme() . '://' . $host;
        }

        return 'http://localhost';
    }
}

if (!function_exists('blog_decode_html_entities_deep')) {
    function blog_decode_html_entities_deep($input, $maxDepth = 3)
    {
        $decoded = (string)$input;
        $depth = max(1, (int)$maxDepth);
        for ($i = 0; $i < $depth; $i++) {
            $next = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($next === $decoded) {
                break;
            }
            $decoded = $next;
        }
        return $decoded;
    }
}

if (!function_exists('site_url')) {
    function site_url($path = '')
    {
        $path = (string)$path;
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $origin = rtrim(site_origin_url(), '/');
        $basePath = trim(site_base_path(), '/');
        $prefix = $origin . ($basePath !== '' ? '/' . $basePath : '');

        $path = ltrim($path, '/');

        if ($path === '') {
            return $prefix . '/';
        }

        return $prefix . '/' . $path;
    }
}

if (!function_exists('blog_site_origin_host')) {
    function blog_site_origin_host()
    {
        $origin = (string)site_origin_url();
        $host = parse_url($origin, PHP_URL_HOST);
        return strtolower(trim((string)$host));
    }
}

if (!function_exists('blog_extract_internal_path')) {
    function blog_extract_internal_path($path)
    {
        $rawPath = trim((string)$path);
        if ($rawPath === '') {
            return '';
        }

        $decoded = rawurldecode($rawPath);
        $decoded = preg_replace('#/+#', '/', $decoded);

        $basePath = '/' . trim(rawurldecode((string)site_base_path()), '/');
        if ($basePath !== '/' && stripos($decoded, $basePath . '/') === 0) {
            return ltrim(substr($decoded, strlen($basePath) + 1), '/');
        }
        if ($basePath !== '/' && strcasecmp($decoded, $basePath) === 0) {
            return '';
        }

        if (preg_match('#/(?:[^/]+/)?project/(.+)$#i', $decoded, $m)) {
            return ltrim((string)$m[1], '/');
        }

        if (preg_match('#/(post|community|static|admin|components|uploaded_img)/(.+)$#i', $decoded, $m)) {
            return strtolower((string)$m[1]) . '/' . ltrim((string)$m[2], '/');
        }

        return ltrim($decoded, '/');
    }
}

if (!function_exists('blog_normalize_site_link')) {
    function blog_normalize_site_link($url)
    {
        $url = trim((string)$url);
        if ($url === '') {
            return null;
        }

        if (preg_match('#^(javascript|data):#i', $url)) {
            return null;
        }

        $appendQueryAndFragment = static function ($base, array $parts) {
            $result = (string)$base;
            if (!empty($parts['query'])) {
                $result .= '?' . $parts['query'];
            }
            if (!empty($parts['fragment'])) {
                $result .= '#' . $parts['fragment'];
            }
            return $result;
        };

        if (preg_match('#^https?://#i', $url)) {
            $parts = parse_url($url);
            if (!is_array($parts)) {
                return $url;
            }

            $host = strtolower(trim((string)($parts['host'] ?? '')));
            if ($host === '') {
                return $url;
            }

            $originHost = blog_site_origin_host();
            if (blog_is_local_host($host) || ($originHost !== '' && $host === $originHost)) {
                $normalizedPath = blog_extract_internal_path((string)($parts['path'] ?? ''));
                $base = site_url($normalizedPath);
                return $appendQueryAndFragment($base, $parts);
            }

            return $url;
        }

        if (str_starts_with($url, '//')) {
            return blog_request_scheme() . ':' . $url;
        }

        if (str_starts_with($url, '/')) {
            return site_url(blog_extract_internal_path($url));
        }

        return site_url($url);
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
        $origin = rtrim(site_origin_url(), '/');
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        parse_str(parse_url($uri, PHP_URL_QUERY) ?? '', $params);
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $utm) {
            unset($params[$utm]);
        }

        $path = parse_url($uri, PHP_URL_PATH) ?? '/';
        $query = http_build_query($params);
        $clean = $path . ($query ? ('?' . $query) : '');

        return $origin . $clean;
    }
}

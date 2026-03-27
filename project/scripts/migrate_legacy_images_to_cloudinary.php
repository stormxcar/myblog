<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from CLI.\n");
    exit(1);
}

require __DIR__ . '/../components/connect.php';

$argv = $_SERVER['argv'] ?? [];
$apply = in_array('--apply', $argv, true);
$dryRun = !$apply;
$limit = 0;
$types = ['posts', 'avatars', 'community', 'settings'];

foreach ($argv as $arg) {
    if (strpos($arg, '--limit=') === 0) {
        $limit = max(0, (int)substr($arg, strlen('--limit=')));
    }
    if (strpos($arg, '--types=') === 0) {
        $raw = trim((string)substr($arg, strlen('--types=')));
        if ($raw !== '') {
            $requested = array_values(array_filter(array_map('trim', explode(',', $raw))));
            if ($requested) {
                $types = $requested;
            }
        }
    }
}

$allowedTypes = ['posts', 'avatars', 'community', 'settings'];
$types = array_values(array_intersect($allowedTypes, $types));
if (!$types) {
    fwrite(STDERR, "No valid --types selected. Allowed: " . implode(',', $allowedTypes) . "\n");
    exit(1);
}

if (!$dryRun && !blog_cloudinary_is_configured()) {
    fwrite(STDERR, "Cloudinary is not configured. Set CLOUDINARY_CLOUD_NAME/API_KEY/API_SECRET first.\n");
    exit(1);
}

function migration_table_exists(PDO $conn, string $table): bool
{
    $stmt = $conn->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$table]);
    return ((int)$stmt->fetchColumn()) > 0;
}

function migration_column_exists(PDO $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return ((int)$stmt->fetchColumn()) > 0;
}

function migration_is_external(string $value): bool
{
    return (bool)preg_match('/^(https?:)?\/\//i', trim($value));
}

function migration_is_data_image(string $value): bool
{
    return stripos(trim($value), 'data:image/') === 0;
}

function migration_is_binary_blob(string $value): bool
{
    if ($value === '') {
        return false;
    }

    if (strpos($value, "\0") !== false) {
        return true;
    }

    // Heuristic: raw binary often contains control bytes outside typical text separators.
    return (bool)preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value);
}

function migration_normalize_relative(string $value): string
{
    $value = str_replace('\\', '/', trim($value));
    $value = preg_replace('#^(?:\./|\.\./)+#', '', $value);

    $pos = stripos((string)$value, 'uploaded_img/');
    if ($pos !== false) {
        $value = substr((string)$value, $pos + strlen('uploaded_img/'));
    }

    $value = ltrim((string)$value, '/');
    return (string)$value;
}

function migration_resolve_local_path(string $value): ?string
{
    $raw = trim($value);
    if ($raw === '' || migration_is_external($raw) || migration_is_data_image($raw) || migration_is_binary_blob($raw)) {
        return null;
    }

    $candidates = [];

    if (is_file($raw)) {
        $candidates[] = $raw;
    }

    $normalized = migration_normalize_relative($raw);
    if ($normalized !== '') {
        $candidates[] = __DIR__ . '/../uploaded_img/' . $normalized;
    }

    $basename = basename($raw);
    if ($basename !== '' && $basename !== '.' && $basename !== '..') {
        $candidates[] = __DIR__ . '/../uploaded_img/' . $basename;
    }

    foreach ($candidates as $candidate) {
        $real = realpath($candidate);
        if ($real !== false && is_file($real)) {
            return $real;
        }
    }

    return null;
}

function migration_detect_mime(string $path): string
{
    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $detected = finfo_file($finfo, $path);
            finfo_close($finfo);
            if (is_string($detected)) {
                $mime = $detected;
            }
        }
    }

    if ($mime === '' && function_exists('mime_content_type')) {
        $detected = mime_content_type($path);
        if (is_string($detected)) {
            $mime = $detected;
        }
    }

    if ($mime === '') {
        $ext = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));
        $map = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'avif' => 'image/avif',
            'bmp' => 'image/bmp',
            'svg' => 'image/svg+xml',
        ];
        $mime = $map[$ext] ?? 'application/octet-stream';
    }

    return $mime;
}

function migration_upload_local_to_cloudinary(string $localPath, string $folder): array
{
    $payload = [
        'name' => basename($localPath),
        'type' => migration_detect_mime($localPath),
        'tmp_name' => $localPath,
        'error' => UPLOAD_ERR_OK,
        'size' => (int)filesize($localPath),
    ];

    return blog_cloudinary_upload($payload, $folder);
}

function migration_ext_from_mime(string $mime): string
{
    $map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/avif' => 'avif',
        'image/bmp' => 'bmp',
        'image/svg+xml' => 'svg',
    ];
    return $map[strtolower(trim($mime))] ?? 'bin';
}

function migration_upload_binary_blob_to_cloudinary(string $binary, string $folder, string $namePrefix): array
{
    $tmpPath = tempnam(sys_get_temp_dir(), 'avatar_blob_');
    if ($tmpPath === false) {
        return ['ok' => false, 'error' => 'Khong tao duoc tep tam de migrate blob'];
    }

    $bytes = @file_put_contents($tmpPath, $binary);
    if ($bytes === false || (int)$bytes <= 0) {
        @unlink($tmpPath);
        return ['ok' => false, 'error' => 'Khong ghi duoc tep tam de migrate blob'];
    }

    $mime = migration_detect_mime($tmpPath);
    if (strpos(strtolower($mime), 'image/') !== 0) {
        @unlink($tmpPath);
        return ['ok' => false, 'error' => 'Blob khong phai du lieu anh hop le'];
    }

    $ext = migration_ext_from_mime($mime);
    $payload = [
        'name' => $namePrefix . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext,
        'type' => $mime,
        'tmp_name' => $tmpPath,
        'error' => UPLOAD_ERR_OK,
        'size' => (int)$bytes,
    ];

    $upload = blog_cloudinary_upload($payload, $folder);
    @unlink($tmpPath);
    return $upload;
}

function migration_process_rows(PDO $conn, array $config, bool $dryRun, int $limit): array
{
    $table = $config['table'];
    $idColumn = $config['id'];
    $valueColumn = $config['value'];
    $folder = $config['folder'];
    $allowBinaryBlob = !empty($config['allow_binary_blob']);
    $whereSql = $config['where'] ?? '';
    $whereParams = $config['params'] ?? [];

    if (!migration_table_exists($conn, $table) || !migration_column_exists($conn, $table, $valueColumn)) {
        return ['scanned' => 0, 'migrated' => 0, 'skipped' => 0, 'missing' => 0, 'errors' => 0, 'messages' => ["SKIP {$table}.{$valueColumn}: table/column not found"]];
    }

    $sql = "SELECT {$idColumn} AS _id, {$valueColumn} AS _value FROM {$table}";
    if ($whereSql !== '') {
        $sql .= " WHERE {$whereSql}";
    }
    $sql .= " ORDER BY {$idColumn} ASC";
    if ($limit > 0) {
        $sql .= " LIMIT {$limit}";
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($whereParams);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $out = ['scanned' => 0, 'migrated' => 0, 'skipped' => 0, 'missing' => 0, 'errors' => 0, 'messages' => []];

    foreach ($rows as $row) {
        $out['scanned']++;
        $id = (string)($row['_id'] ?? '');
        $value = trim((string)($row['_value'] ?? ''));

        if ($value === '' || migration_is_external($value) || migration_is_data_image($value)) {
            $out['skipped']++;
            continue;
        }

        if (migration_is_binary_blob($value)) {
            if (!$allowBinaryBlob) {
                $out['skipped']++;
                $out['messages'][] = "SKIP {$table}#{$id}: binary/blob value";
                continue;
            }

            if ($dryRun) {
                $out['migrated']++;
                $out['messages'][] = "PLAN {$table}#{$id}: binary/blob -> cloudinary";
                continue;
            }

            $upload = migration_upload_binary_blob_to_cloudinary($value, $folder, 'avatar_' . $id);
            if (!($upload['ok'] ?? false)) {
                $out['errors']++;
                $out['messages'][] = "FAIL {$table}#{$id}: " . (string)($upload['error'] ?? 'upload failed');
                continue;
            }

            $newUrl = (string)$upload['secure_url'];
            $update = $conn->prepare("UPDATE {$table} SET {$valueColumn} = ? WHERE {$idColumn} = ?");
            $update->execute([$newUrl, $id]);

            $out['migrated']++;
            $out['messages'][] = "DONE {$table}#{$id}: {$newUrl} (from blob)";
            continue;
        }

        $localPath = migration_resolve_local_path($value);
        if ($localPath === null) {
            $out['missing']++;
            $out['messages'][] = "MISS {$table}#{$id}: {$value}";
            continue;
        }

        if ($dryRun) {
            $out['migrated']++;
            $out['messages'][] = "PLAN {$table}#{$id}: {$value} -> {$localPath}";
            continue;
        }

        $upload = migration_upload_local_to_cloudinary($localPath, $folder);
        if (!($upload['ok'] ?? false)) {
            $out['errors']++;
            $out['messages'][] = "FAIL {$table}#{$id}: " . (string)($upload['error'] ?? 'upload failed');
            continue;
        }

        $newUrl = (string)$upload['secure_url'];
        $update = $conn->prepare("UPDATE {$table} SET {$valueColumn} = ? WHERE {$idColumn} = ?");
        $update->execute([$newUrl, $id]);

        $out['migrated']++;
        $out['messages'][] = "DONE {$table}#{$id}: {$newUrl}";
    }

    return $out;
}

$configs = [
    'posts' => [
        'table' => 'posts',
        'id' => 'id',
        'value' => 'image',
        'folder' => blog_cloudinary_default_folder() . '/posts',
    ],
    'avatars' => [
        'table' => 'users',
        'id' => 'id',
        'value' => 'avatar',
        'folder' => blog_cloudinary_default_folder() . '/avatars',
        'allow_binary_blob' => true,
    ],
    'community' => [
        'table' => 'community_post_media',
        'id' => 'id',
        'value' => 'file_path',
        'folder' => blog_cloudinary_default_folder() . '/community',
    ],
    'settings' => [
        'table' => 'settings',
        'id' => 'setting_key',
        'value' => 'setting_value',
        'where' => 'setting_key IN (?, ?)',
        'params' => ['logo', 'lienhe_image'],
        'folder' => blog_cloudinary_default_folder() . '/settings',
    ],
];

$mode = $dryRun ? 'DRY-RUN' : 'APPLY';
echo "Legacy image migration mode: {$mode}\n";
echo "Selected types: " . implode(', ', $types) . "\n";
if ($limit > 0) {
    echo "Limit per type: {$limit}\n";
}

$global = ['scanned' => 0, 'migrated' => 0, 'skipped' => 0, 'missing' => 0, 'errors' => 0];

foreach ($types as $type) {
    if (!isset($configs[$type])) {
        continue;
    }

    echo "\n=== {$type} ===\n";
    $result = migration_process_rows($conn, $configs[$type], $dryRun, $limit);

    foreach (['scanned', 'migrated', 'skipped', 'missing', 'errors'] as $k) {
        $global[$k] += (int)$result[$k];
    }

    foreach ((array)$result['messages'] as $line) {
        echo $line . "\n";
    }

    echo "Summary {$type}: scanned={$result['scanned']} migrated={$result['migrated']} skipped={$result['skipped']} missing={$result['missing']} errors={$result['errors']}\n";
}

echo "\n=== TOTAL ===\n";
echo "scanned={$global['scanned']} migrated={$global['migrated']} skipped={$global['skipped']} missing={$global['missing']} errors={$global['errors']}\n";

if ($dryRun) {
    echo "\nDry run only. Re-run with --apply to write DB updates.\n";
}

exit($global['errors'] > 0 ? 2 : 0);

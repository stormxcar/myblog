<?php

declare(strict_types=1);

function deleteOldFiles(string $dir, int $olderThanSeconds): array
{
    $result = [
        'deleted' => 0,
        'kept' => 0,
        'errors' => 0,
    ];

    if (!is_dir($dir)) {
        return $result;
    }

    $now = time();
    $files = scandir($dir);
    if ($files === false) {
        $result['errors']++;
        return $result;
    }

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $file;
        if (!is_file($path)) {
            continue;
        }

        $mtime = filemtime($path);
        if ($mtime === false) {
            $result['errors']++;
            continue;
        }

        if (($now - $mtime) > $olderThanSeconds) {
            if (@unlink($path)) {
                $result['deleted']++;
            } else {
                $result['errors']++;
            }
        } else {
            $result['kept']++;
        }
    }

    return $result;
}

$baseDataDir = __DIR__ . '/../data';
$summaryCacheDir = $baseDataDir . '/ai_summary_cache';
$rateLimitDir = $baseDataDir . '/ai_rate_limit';
$modelDiscoveryDir = $baseDataDir . '/ai_model_discovery';

$summaryTtl = 6 * 3600;      // keep summary cache for 6 hours
$rateLimitTtl = 24 * 3600;   // keep rate-limit trackers for 1 day
$modelTtl = 24 * 3600;       // keep model discovery cache for 1 day

$summaryStats = deleteOldFiles($summaryCacheDir, $summaryTtl);
$rateStats = deleteOldFiles($rateLimitDir, $rateLimitTtl);
$modelStats = deleteOldFiles($modelDiscoveryDir, $modelTtl);

echo "[AI Maintenance] " . date('Y-m-d H:i:s') . PHP_EOL;
echo "- Summary cache: deleted={$summaryStats['deleted']}, kept={$summaryStats['kept']}, errors={$summaryStats['errors']}" . PHP_EOL;
echo "- Rate limit logs: deleted={$rateStats['deleted']}, kept={$rateStats['kept']}, errors={$rateStats['errors']}" . PHP_EOL;
echo "- Model discovery cache: deleted={$modelStats['deleted']}, kept={$modelStats['kept']}, errors={$modelStats['errors']}" . PHP_EOL;

<?php

declare(strict_types=1);

require __DIR__ . '/../components/connect.php';

use App\Services\IndexerService;

$indexer = new IndexerService($conn);

if (!$indexer->isReady()) {
    fwrite(STDERR, "Elasticsearch client is not ready. Check SEARCH_ENGINE, ES_HOSTS, and composer dependencies.\n");
    exit(1);
}

$indexer->ensureIndex();
$count = $indexer->reindexAllActivePosts();

echo "Indexed {$count} active posts into Elasticsearch index.\n";

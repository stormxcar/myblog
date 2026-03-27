<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\PostDocument;
use App\Domain\Tag;
use PDO;

final class IndexerService
{
    private PDO $conn;
    private ?object $client;
    private string $indexName;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
        $this->indexName = (string)(getenv('ES_INDEX_POSTS') ?: 'blog_posts');
        $this->client = $this->createClient();
    }

    public function isReady(): bool
    {
        return $this->client !== null;
    }

    public function ensureIndex(): bool
    {
        if ($this->client === null) {
            return false;
        }

        $exists = $this->client->indices()->exists(['index' => $this->indexName])->asBool();
        if ($exists) {
            return true;
        }

        $this->client->indices()->create([
            'index' => $this->indexName,
            'body' => [
                'mappings' => [
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'title' => ['type' => 'text'],
                        'content' => ['type' => 'text'],
                        'category' => ['type' => 'text'],
                        'tags' => ['type' => 'text'],
                        'tag_slugs' => ['type' => 'keyword'],
                        'status' => ['type' => 'keyword'],
                        'author' => ['type' => 'text'],
                        'date' => ['type' => 'date', 'format' => 'yyyy-MM-dd HH:mm:ss||strict_date_optional_time||epoch_millis'],
                    ],
                ],
            ],
        ]);

        return true;
    }

    public function indexPostById(int $postId): bool
    {
        if ($this->client === null || $postId <= 0) {
            return false;
        }

        $post = $this->fetchPostRow($postId);
        if (!$post) {
            return false;
        }

        $tags = $this->fetchTags($postId);
        $doc = PostDocument::fromDatabaseRow($post, $tags);

        $this->client->index([
            'index' => $this->indexName,
            'id' => (string)$doc->id,
            'body' => $doc->toSearchDocument(),
            'refresh' => true,
        ]);

        return true;
    }

    public function deletePost(int $postId): bool
    {
        if ($this->client === null || $postId <= 0) {
            return false;
        }

        $this->client->delete([
            'index' => $this->indexName,
            'id' => (string)$postId,
            'refresh' => true,
        ]);

        return true;
    }

    public function reindexAllActivePosts(int $batchSize = 200): int
    {
        if ($this->client === null) {
            return 0;
        }

        $this->ensureIndex();

        $totalIndexed = 0;
        $offset = 0;

        while (true) {
            $stmt = $this->conn->prepare("SELECT p.* FROM posts p WHERE p.status = 'active' ORDER BY p.id ASC LIMIT ? OFFSET ?");
            $stmt->bindValue(1, $batchSize, PDO::PARAM_INT);
            $stmt->bindValue(2, $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$rows) {
                break;
            }

            $bulkBody = [];
            foreach ($rows as $row) {
                $postId = (int)($row['id'] ?? 0);
                if ($postId <= 0) {
                    continue;
                }
                $doc = PostDocument::fromDatabaseRow($row, $this->fetchTags($postId));
                $bulkBody[] = ['index' => ['_index' => $this->indexName, '_id' => (string)$postId]];
                $bulkBody[] = $doc->toSearchDocument();
                $totalIndexed++;
            }

            if ($bulkBody) {
                $this->client->bulk(['refresh' => false, 'body' => $bulkBody]);
            }

            $offset += $batchSize;
        }

        $this->client->indices()->refresh(['index' => $this->indexName]);

        return $totalIndexed;
    }

    private function createClient(): ?object
    {
        $builderClass = 'Elastic\\Elasticsearch\\ClientBuilder';
        if (!class_exists($builderClass)) {
            return null;
        }

        $hostsRaw = trim((string)(getenv('ES_HOSTS') ?: ''));
        if ($hostsRaw === '') {
            return null;
        }

        $hosts = array_values(array_filter(array_map('trim', explode(',', $hostsRaw))));
        if (!$hosts) {
            return null;
        }

        return $builderClass::create()->setHosts($hosts)->build();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchPostRow(int $postId): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM posts WHERE id = ? LIMIT 1');
        $stmt->execute([$postId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * @return Tag[]
     */
    private function fetchTags(int $postId): array
    {
        $stmt = $this->conn->prepare('SELECT t.id, t.name, t.slug FROM tags t INNER JOIN post_tags pt ON pt.tag_id = t.id WHERE pt.post_id = ?');
        $stmt->execute([$postId]);

        $tags = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tags[] = new Tag((int)($row['id'] ?? 0), (string)($row['name'] ?? ''), (string)($row['slug'] ?? ''));
        }

        return $tags;
    }
}

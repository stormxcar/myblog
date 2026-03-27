<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;

final class SearchService
{
    private PDO $conn;
    private ?object $client = null;
    private bool $elasticEnabled;
    private string $indexName;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
        $this->indexName = (string)(getenv('ES_INDEX_POSTS') ?: 'blog_posts');
        $this->elasticEnabled = strtolower((string)(getenv('SEARCH_ENGINE') ?: 'mysql')) === 'elasticsearch';
        $this->client = $this->createClient();
    }

    /**
     * @return array{engine:string,total:int,ids:array<int,int>}
     */
    public function searchPosts(string $query, string $tagSlug, int $page, int $size): array
    {
        if ($this->elasticEnabled && $this->client !== null) {
            try {
                $elasticResult = $this->searchWithElastic($query, $tagSlug, $page, $size);
                if ($elasticResult['total'] > 0 || $query !== '' || $tagSlug !== '') {
                    return $elasticResult;
                }
            } catch (Throwable $e) {
                // Fall back to MySQL when Elasticsearch is unavailable.
            }
        }

        return $this->searchWithMySql($query, $tagSlug, $page, $size);
    }

    private function createClient(): ?object
    {
        $builderClass = 'Elastic\\Elasticsearch\\ClientBuilder';
        if (!$this->elasticEnabled || !class_exists($builderClass)) {
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
     * @return array{engine:string,total:int,ids:array<int,int>}
     */
    private function searchWithElastic(string $query, string $tagSlug, int $page, int $size): array
    {
        $from = max(0, ($page - 1) * $size);
        $must = [];
        $filter = [
            ['term' => ['status' => 'active']],
        ];

        if ($tagSlug !== '') {
            $filter[] = ['term' => ['tag_slugs' => $tagSlug]];
        }

        if ($query !== '') {
            $must[] = [
                'multi_match' => [
                    'query' => $query,
                    'fields' => ['title^4', 'category^2', 'tags^3', 'content'],
                    'fuzziness' => 'AUTO',
                    'operator' => 'and',
                ],
            ];
        }

        $params = [
            'index' => $this->indexName,
            'body' => [
                'from' => $from,
                'size' => $size,
                'query' => [
                    'bool' => [
                        'must' => $must,
                        'filter' => $filter,
                    ],
                ],
                'sort' => [
                    ['_score' => ['order' => 'desc']],
                    ['date' => ['order' => 'desc']],
                ],
                '_source' => ['id'],
            ],
        ];

        $response = $this->client->search($params)->asArray();
        $hits = $response['hits']['hits'] ?? [];
        $ids = [];
        foreach ($hits as $hit) {
            $id = (int)($hit['_source']['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        $total = (int)($response['hits']['total']['value'] ?? 0);

        return [
            'engine' => 'elasticsearch',
            'total' => $total,
            'ids' => $ids,
        ];
    }

    /**
     * @return array{engine:string,total:int,ids:array<int,int>}
     */
    private function searchWithMySql(string $query, string $tagSlug, int $page, int $size): array
    {
        $searchTerm = '%' . $query . '%';

        $countSql = "SELECT COUNT(DISTINCT p.id)
            FROM posts p
            LEFT JOIN post_tags pt ON pt.post_id = p.id
            LEFT JOIN tags t ON t.id = pt.tag_id
            WHERE p.status = 'active'";

        $countParams = [];
        if ($tagSlug !== '') {
            $countSql .= ' AND t.slug = ?';
            $countParams[] = $tagSlug;
        }

        if ($query !== '') {
            $countSql .= ' AND (p.title LIKE ? OR p.category LIKE ? OR p.content LIKE ? OR t.name LIKE ?)';
            $countParams[] = $searchTerm;
            $countParams[] = $searchTerm;
            $countParams[] = $searchTerm;
            $countParams[] = $searchTerm;
        }

        $countStmt = $this->conn->prepare($countSql);
        $countStmt->execute($countParams);
        $total = (int)$countStmt->fetchColumn();

        if ($total <= 0) {
            return [
                'engine' => 'mysql',
                'total' => 0,
                'ids' => [],
            ];
        }

        $offset = max(0, ($page - 1) * $size);

        $sql = "SELECT DISTINCT p.id
            FROM posts p
            LEFT JOIN post_tags pt ON pt.post_id = p.id
            LEFT JOIN tags t ON t.id = pt.tag_id
            WHERE p.status = 'active'";

        $params = [];
        if ($tagSlug !== '') {
            $sql .= ' AND t.slug = ?';
            $params[] = $tagSlug;
        }

        if ($query !== '') {
            $sql .= ' AND (p.title LIKE ? OR p.category LIKE ? OR p.content LIKE ? OR t.name LIKE ?)';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $sql .= ' ORDER BY p.date DESC LIMIT ? OFFSET ?';

        $stmt = $this->conn->prepare($sql);
        $bindIndex = 1;
        foreach ($params as $value) {
            $stmt->bindValue($bindIndex++, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue($bindIndex++, $size, PDO::PARAM_INT);
        $stmt->bindValue($bindIndex, $offset, PDO::PARAM_INT);
        $stmt->execute();

        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        return [
            'engine' => 'mysql',
            'total' => $total,
            'ids' => $ids,
        ];
    }
}

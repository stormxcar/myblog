<?php

declare(strict_types=1);

namespace App\Domain;

final class PostDocument
{
    public int $id;
    public string $title;
    public string $content;
    public Category $category;
    /** @var Tag[] */
    public array $tags;
    public string $status;
    public string $author;
    public string $date;

    /**
     * @param Tag[] $tags
     */
    public function __construct(
        int $id,
        string $title,
        string $content,
        Category $category,
        array $tags,
        string $status,
        string $author,
        string $date
    ) {
        $this->id = $id;
        $this->title = trim($title);
        $this->content = trim($content);
        $this->category = $category;
        $this->tags = $tags;
        $this->status = trim($status);
        $this->author = trim($author);
        $this->date = trim($date);
    }

    /**
     * @param array<string, mixed> $row
     * @param Tag[] $tags
     */
    public static function fromDatabaseRow(array $row, array $tags): self
    {
        return new self(
            (int)($row['id'] ?? 0),
            (string)($row['title'] ?? ''),
            (string)($row['content'] ?? ''),
            new Category((string)($row['category'] ?? '')),
            $tags,
            (string)($row['status'] ?? 'active'),
            (string)($row['name'] ?? ''),
            (string)($row['date'] ?? '')
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchDocument(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => strip_tags($this->content),
            'category' => $this->category->toSearchValue(),
            'tags' => array_map(static fn(Tag $tag) => $tag->name, $this->tags),
            'tag_slugs' => array_map(static fn(Tag $tag) => $tag->slug, $this->tags),
            'status' => $this->status,
            'author' => $this->author,
            'date' => $this->date,
        ];
    }
}

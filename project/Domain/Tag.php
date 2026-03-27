<?php

declare(strict_types=1);

namespace App\Domain;

final class Tag
{
    public int $id;
    public string $name;
    public string $slug;

    public function __construct(int $id, string $name, string $slug)
    {
        $this->id = $id;
        $this->name = trim($name);
        $this->slug = trim($slug);
    }

    public function toSearchArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
        ];
    }
}

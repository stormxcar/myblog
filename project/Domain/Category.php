<?php

declare(strict_types=1);

namespace App\Domain;

final class Category
{
    public string $name;

    public function __construct(string $name)
    {
        $this->name = trim($name);
    }

    public function toSearchValue(): string
    {
        return $this->name;
    }
}

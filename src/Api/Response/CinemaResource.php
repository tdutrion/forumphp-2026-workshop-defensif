<?php

declare(strict_types=1);

namespace App\Api\Response;

final readonly class CinemaResource
{
    public function __construct(
        public string $slug,
        public string $name,
    ) {
    }

    /**
     * @param array{slug: string, name: string} $row
     */
    public static function fromRow(array $row): self
    {
        return new self($row['slug'], $row['name']);
    }
}

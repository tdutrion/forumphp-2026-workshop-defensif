<?php

declare(strict_types=1);

namespace App\Api\Response;

final readonly class FilmSummaryResource
{
    public function __construct(
        public string $slug,
        public string $title,
    ) {
    }

    /**
     * @param array<string, mixed> $row a film of FilmRepository::findBySlugs()
     */
    public static function fromRow(array $row): self
    {
        return new self($row['slug'], $row['title']);
    }
}

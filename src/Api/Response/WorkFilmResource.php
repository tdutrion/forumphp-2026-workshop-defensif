<?php

declare(strict_types=1);

namespace App\Api\Response;

use App\Catalog\Entity\Film;

final readonly class WorkFilmResource
{
    public function __construct(
        public string $chain,
        public string $slug,
        public string $title,
    ) {
    }

    public static function fromFilm(Film $film): self
    {
        return new self($film->getChain(), $film->getSlug(), $film->getTitle());
    }
}

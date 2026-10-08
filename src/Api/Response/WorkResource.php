<?php

declare(strict_types=1);

namespace App\Api\Response;

use App\Catalog\Entity\Film;
use App\Catalog\Entity\Work;

final readonly class WorkResource
{
    /**
     * @param list<WorkFilmResource> $films the films of the work, by chain
     */
    public function __construct(
        public string $id,
        public string $originalTitle,
        public ?int $year,
        public ?string $wikidataId,
        public ?string $imdbId,
        public ?string $tmdbId,
        public array $films,
    ) {
    }

    /**
     * @param list<Film> $films
     */
    public static function fromWork(Work $work, array $films): self
    {
        return new self(
            $work->getId()->toRfc4122(),
            $work->getOriginalTitle(),
            $work->getYear(),
            $work->getWikidataId(),
            $work->getImdbId(),
            $work->getTmdbId(),
            array_map(WorkFilmResource::fromFilm(...), $films),
        );
    }
}

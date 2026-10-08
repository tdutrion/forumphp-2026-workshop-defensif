<?php

declare(strict_types=1);

namespace App\Api\Response;

use App\Catalog\Entity\Film;

final readonly class FilmResource
{
    /**
     * @param list<string> $genres
     * @param bool         $seen     already seen by the user
     * @param bool         $unwanted not for the user
     */
    public function __construct(
        public string $slug,
        public string $title,
        public ?int $duration,
        public ?string $releaseDate,
        public array $genres,
        public ?string $posterUrl,
        public ?string $contentRating,
        public bool $seen,
        public bool $unwanted,
        public WorkIdsResource $work,
    ) {
    }

    public static function fromFilm(Film $film, bool $seen, bool $unwanted): self
    {
        return new self(
            $film->getSlug(),
            $film->getTitle(),
            $film->getDuration(),
            $film->getReleaseDate()?->format('Y-m-d'),
            $film->getGenres(),
            $film->getPosterUrl(),
            $film->getContentRating(),
            $seen,
            $unwanted,
            WorkIdsResource::fromWork($film->getWork()),
        );
    }
}

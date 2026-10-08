<?php

declare(strict_types=1);

namespace App\Api\Response;

use App\Catalog\Entity\Work;

/**
 * A work, common to every chain, with its open data ids (null when unknown).
 */
final readonly class WorkIdsResource
{
    public function __construct(
        public string $id,
        public ?string $wikidataId,
        public ?string $imdbId,
        public ?string $tmdbId,
    ) {
    }

    public static function fromWork(Work $work): self
    {
        return new self($work->getId()->toRfc4122(), $work->getWikidataId(), $work->getImdbId(), $work->getTmdbId());
    }
}

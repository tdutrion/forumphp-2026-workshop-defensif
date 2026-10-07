<?php

declare(strict_types=1);

namespace App\Planner;

/**
 * An open cinema around the place of a search, as the planner page lists it.
 */
final readonly class NearbyCinema
{
    /**
     * @param float $distance km from the place, one decimal
     */
    public function __construct(
        public string $slug,
        public string $name,
        public float $distance,
        public bool $excluded,
    ) {
    }
}

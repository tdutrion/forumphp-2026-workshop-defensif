<?php

declare(strict_types=1);

namespace App\Catalog;

/**
 * A chain with an unlimited pass that is not supported yet: the landing page announces it.
 */
final readonly class PlannedCinemaChain
{
    /**
     * @param list<CountryCode> $countries
     */
    public function __construct(
        public string $name,
        public array $countries,
    ) {
    }
}

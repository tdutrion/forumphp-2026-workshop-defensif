<?php

declare(strict_types=1);

namespace App\Api\Response;

use App\Planner\PlanFailure;

/**
 * The answer to a plan when no showtime matches the place and the date: nothing was drawn, so no seed.
 */
final readonly class NoShowtimeResource
{
    /**
     * @param list<ProgrammeResource> $programmes always empty
     */
    public function __construct(
        public array $programmes = [],
        public string $reason = PlanFailure::NoShowtime->value,
    ) {
    }
}

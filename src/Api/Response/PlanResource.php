<?php

declare(strict_types=1);

namespace App\Api\Response;

use App\Planner\PlanResult;

/**
 * The answer to a plan that offers programmes, or that went as far as drawing them.
 */
final readonly class PlanResource
{
    /**
     * @param list<ProgrammeResource> $programmes at most 3
     * @param string|null             $reason     why there are fewer than asked (fewer_films, not_enough_programmes), or no_programme
     * @param int|null                $films      the number of films per programme (fewer than asked when reason is fewer_films)
     * @param int|null                $seed       the seed of the draw: the same seed gives the same programmes
     */
    public function __construct(
        public array $programmes,
        public ?string $reason,
        public ?int $films,
        public ?int $seed,
    ) {
    }

    public static function fromResult(PlanResult $result): self
    {
        return new self(
            array_map(ProgrammeResource::fromProgramme(...), $result->programmes->toArray()),
            $result->reason(),
            $result->films,
            $result->seed,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Api\Response;

use App\Planner\Programme;

final readonly class ProgrammeResource
{
    /**
     * @param int                    $wait      minutes really lost waiting (breaks minus the 10-minute margins and the travel): the ranking score
     * @param float                  $distance  km travelled between cinemas
     * @param list<ShowtimeResource> $showtimes
     */
    public function __construct(
        public int $wait,
        public int $breakMinutes,
        public int $travelMinutes,
        public float $distance,
        public array $showtimes,
    ) {
    }

    public static function fromProgramme(Programme $programme): self
    {
        return new self(
            $programme->wait,
            $programme->breakMinutes,
            $programme->travelMinutes,
            $programme->distance,
            array_map(ShowtimeResource::fromShowtime(...), $programme->showtimes->toArray()),
        );
    }
}

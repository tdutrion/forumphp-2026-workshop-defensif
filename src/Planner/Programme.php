<?php

namespace App\Planner;

/**
 * A marathon: showtimes that chain, and what it costs to follow them.
 */
final readonly class Programme
{
    /**
     * @param ScheduledShowtimeList $showtimes     at least one, in the order they are watched
     * @param int                   $wait          minutes really lost waiting (the breaks minus the margins and the travel): the ranking score
     * @param float                 $distance      km travelled between cinemas
     * @param int                   $breakMinutes  the sum of the breaks between the showtimes
     * @param int                   $travelMinutes the travel they include
     */
    public function __construct(
        public ScheduledShowtimeList $showtimes,
        public int $wait,
        public float $distance,
        public int $breakMinutes,
        public int $travelMinutes,
    ) {
        if ($showtimes->isEmpty()) {
            throw new \InvalidArgumentException('A programme has at least one showtime.');
        }
    }

    /**
     * @return list<string>
     */
    public function filmSlugs(): array
    {
        return $this->showtimes->filmSlugs();
    }

    /**
     * @return list<string>
     */
    public function workIds(): array
    {
        return $this->showtimes->workIds();
    }
}

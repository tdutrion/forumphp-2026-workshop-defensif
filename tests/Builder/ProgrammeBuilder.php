<?php

declare(strict_types=1);

namespace App\Tests\Builder;

use App\Planner\Programme;
use App\Planner\ScheduledShowtime;
use App\Planner\ScheduledShowtimeList;

/**
 * A programme as ChainBuilder produces it.
 */
final class ProgrammeBuilder
{
    /** @var list<string> */
    private array $films = ['film-1', 'film-2'];
    private int $wait = 0;
    private float $distance = 0.0;

    public static function aProgramme(): self
    {
        return new self();
    }

    public function ofFilms(string ...$filmSlugs): self
    {
        $clone = clone $this;
        $clone->films = $filmSlugs;

        return $clone;
    }

    public function waiting(int $minutes): self
    {
        $clone = clone $this;
        $clone->wait = $minutes;

        return $clone;
    }

    public function travelling(float $km): self
    {
        $clone = clone $this;
        $clone->distance = $km;

        return $clone;
    }

    public function build(): Programme
    {
        return new Programme(
            showtimes: new ScheduledShowtimeList(...array_map(
                static fn (string $film): ScheduledShowtime => ScreeningBuilder::aScreening('showtime-of-'.$film)->ofFilm($film)->build(),
                $this->films,
            )),
            wait: $this->wait,
            distance: $this->distance,
            breakMinutes: 0,
            travelMinutes: 0,
        );
    }
}

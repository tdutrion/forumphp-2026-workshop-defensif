<?php

declare(strict_types=1);

namespace App\Tests\Builder;

use App\Catalog\Coordinates;
use App\Catalog\ShowtimeVersion;
use App\Planner\ScheduledShowtime;
use App\Planner\ScreeningTime;
use Time\Duration;

/**
 * A showtime as ChainBuilder receives it. Times are read in UTC, like every instant of the application;
 * the end includes the 20 minutes of ads, as Pathé computes it.
 */
final class ScreeningBuilder
{
    private string $film = 'film-1';
    private ?string $work = null;
    private string $cinema = 'A';
    private float $latitude = 0.0;
    private float $longitude = 0.0;
    private string $day = '2026-10-08';
    private string $time = '14:00';
    private int $duration = 100;

    private function __construct(private string $id)
    {
    }

    public static function aScreening(string $id): self
    {
        return new self($id);
    }

    public function ofFilm(string $filmSlug): self
    {
        $clone = clone $this;
        $clone->film = $filmSlug;

        return $clone;
    }

    /**
     * The work of the film (common to every chain); the film slug by default.
     */
    public function ofWork(string $workId): self
    {
        $clone = clone $this;
        $clone->work = $workId;

        return $clone;
    }

    public function inCinema(string $cinemaSlug, float $latitude, float $longitude): self
    {
        $clone = clone $this;
        $clone->cinema = $cinemaSlug;
        $clone->latitude = $latitude;
        $clone->longitude = $longitude;

        return $clone;
    }

    public function startingAt(string $time, string $day = '2026-10-08'): self
    {
        $clone = clone $this;
        $clone->time = $time;
        $clone->day = $day;

        return $clone;
    }

    public function lasting(int $minutes): self
    {
        $clone = clone $this;
        $clone->duration = $minutes;

        return $clone;
    }

    public function build(): ScheduledShowtime
    {
        $start = ScreeningTime::fromUtc($this->day.' '.$this->time);

        return new ScheduledShowtime(
            id: $this->id,
            filmSlug: $this->film,
            filmTitle: 'Film '.$this->film,
            workId: $this->work ?? $this->film,
            cinemaSlug: $this->cinema,
            cinemaName: 'Cinema '.$this->cinema,
            position: new Coordinates($this->latitude, $this->longitude),
            start: $start,
            end: $start->plus(Duration::fromMinutes($this->duration + 20)),
            timezone: new \DateTimeZone('UTC'),
            version: ShowtimeVersion::Vf,
            bookingUrl: 'https://s.pathe.fr/fr/'.$this->id.'/booking',
        );
    }
}

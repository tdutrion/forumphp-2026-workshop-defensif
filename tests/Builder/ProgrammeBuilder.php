<?php

namespace App\Tests\Builder;

/**
 * A programme as ChainBuilder produces it.
 */
final class ProgrammeBuilder
{
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

    public function build(): array
    {
        return [
            'showtimes' => array_map(static fn (string $film) => ['filmSlug' => $film, 'lateMinutes' => 0], $this->films),
            'wait' => $this->wait,
            'distance' => $this->distance,
        ];
    }
}

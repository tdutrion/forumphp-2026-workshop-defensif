<?php

namespace App\Tests\Builder;

/**
 * Builds the responses of a fake Pathé API with the shapes of the real one (checked on
 * October 4, 2026): latitude in "x", longitude in "y", contentRating [] when unrated,
 * naive local times, film end = start + running time + 20 minutes of ads.
 */
final class PatheApiBuilder
{
    private array $cities = [];
    private array $cinemas = [];
    private array $shows = [];
    private array $programmes = [];
    private array $showtimes = [];
    /** @var array<string, array{0: string, 1: int}> */
    private array $overrides = [];

    public static function aPatheApi(): self
    {
        return new self();
    }

    public function withCity(string $slug, string $name): self
    {
        $clone = clone $this;
        $clone->cities[] = [
            'id' => \count($this->cities) + 1,
            'slug' => $slug,
            'name' => $name,
            'departement' => '21',
            'isIdf' => false,
            // Pathé's city positions are unreliable: the application never uses them.
            'gpsPosition' => ['x' => 0.0, 'y' => 0.0],
            'cinemas' => [],
        ];

        return $clone;
    }

    public function withCinema(string $slug, string $citySlug, float $latitude, float $longitude, bool $open = true): self
    {
        $clone = clone $this;
        $clone->cinemas[] = [
            'slug' => $slug,
            'citySlug' => $citySlug,
            'name' => 'Cinema '.$slug,
            'status' => $open,
            'theaters' => [[
                'name' => 'Cinema '.$slug,
                'addressLine1' => '1 Main Street',
                'addressLine2' => '',
                'addressZip' => '21000',
                'addressCity' => 'Dijon',
                // Pathé puts the latitude in "x" and the longitude in "y".
                'gpsPosition' => ['x' => $latitude, 'y' => $longitude],
            ]],
            'hallCount' => 9,
        ];
        $clone->programmes[$slug] = ['days' => [], 'shows' => []];

        return $clone;
    }

    public function withFilm(string $slug, string $title, int $duration, ?string $posterUrl = null): self
    {
        $clone = clone $this;
        $clone->shows[$slug] = [
            'slug' => $slug,
            'title' => $title,
            'duration' => $duration,
            'releaseAt' => ['FR_FR' => '2026-09-30'],
            'genres' => ['Action'],
            'posterPath' => null === $posterUrl ? null : ['md' => $posterUrl],
            // Pathé sends an empty array, not null, while a film has no rating yet.
            'contentRating' => [],
            'isMovie' => true,
        ];

        return $clone;
    }

    public function withEvent(string $slug, string $title): self
    {
        $clone = $this->withFilm($slug, $title, 40);
        $clone->shows[$slug]['isMovie'] = false;

        return $clone;
    }

    /**
     * @param string      $localTime  session start as Pathé sends it: naive local time, e.g. '2026-10-04 21:40:00'
     * @param string      $sessionRef Pathé session reference, e.g. 'V3345S85501'
     * @param string|null $bookingUrl overrides the booking link built from $sessionRef
     */
    public function withShowtime(string $filmSlug, string $cinemaSlug, string $localTime, string $sessionRef, ?string $bookingUrl = null): self
    {
        if (!isset($this->shows[$filmSlug], $this->programmes[$cinemaSlug])) {
            throw new \LogicException('Declare the film and the cinema before their showtimes.');
        }

        $clone = clone $this;
        $start = new \DateTimeImmutable($localTime, new \DateTimeZone('Europe/Paris'));
        $day = $start->format('Y-m-d');
        $clone->programmes[$cinemaSlug]['days'][$day] = ['upsell' => null, 'highlightedShowtime' => null];
        $clone->programmes[$cinemaSlug]['shows'][$filmSlug]['days'][$day] = ['tags' => ['DEFAULT'], 'bookable' => true, 'versions' => ['vf']];
        $clone->showtimes[$filmSlug.'|'.$cinemaSlug][$day][] = [
            'time' => $start->format('Y-m-d H:i:s'),
            'version' => 'vf',
            'tags' => ['DEFAULT'],
            'status' => 'available',
            'reservabilityEnd' => $start->modify('+20 minutes')->format(\DATE_ATOM),
            'isMovie' => true,
            'refCmd' => $bookingUrl ?? 'https://s.pathe.fr/fr/'.$sessionRef.'/booking',
            'auditoriumName' => '1',
            'auditoriumCapacity' => '244',
            'endTime' => $start->modify('+'.($this->shows[$filmSlug]['duration'] + 20).' minutes')->format('Y-m-d H:i:s'),
        ];

        return $clone;
    }

    public function failing(string $path, int $status, string $body = '"error"'): self
    {
        $clone = clone $this;
        $clone->overrides[$path] = [$body, $status];

        return $clone;
    }

    /**
     * @return array<string, array{0: string, 1: int}> response body and HTTP status, keyed by API path
     */
    public function build(): array
    {
        $responses = [
            'cities' => [json_encode($this->cities, \JSON_THROW_ON_ERROR), 200],
            'cinemas' => [json_encode($this->cinemas, \JSON_THROW_ON_ERROR), 200],
            'shows' => [json_encode(['shows' => array_values($this->shows), 'labels' => [], 'contentratings' => []], \JSON_THROW_ON_ERROR), 200],
        ];
        foreach ($this->programmes as $cinemaSlug => $programme) {
            $responses['cinema/'.$cinemaSlug.'/shows'] = [json_encode($programme, \JSON_THROW_ON_ERROR), 200];
        }
        foreach ($this->showtimes as $pair => $byDay) {
            [$filmSlug, $cinemaSlug] = explode('|', $pair);
            $responses['show/'.$filmSlug.'/showtimes/'.$cinemaSlug] = [json_encode($byDay, \JSON_THROW_ON_ERROR), 200];
        }

        return array_replace($responses, $this->overrides);
    }
}

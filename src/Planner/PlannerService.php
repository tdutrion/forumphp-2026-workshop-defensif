<?php

namespace App\Planner;

use App\Account\ExcludedCinemaService;
use App\Account\SeenFilmService;
use App\Account\UnwantedFilmService;
use App\Catalog\Repository\CinemaRepository;
use App\Catalog\Repository\ShowtimeRepository;

/**
 * The "plan a marathon" use case, shared by the website and the API.
 */
class PlannerService
{
    public const DEFAULT_RADIUS_KM = 10;
    public const MAX_SEED = 999_999_999;

    public function __construct(
        private CinemaRepository $cinemaRepository,
        private ShowtimeRepository $showtimeRepository,
        private SeenFilmService $seenFilmService,
        private UnwantedFilmService $unwantedFilmService,
        private ExcludedCinemaService $excludedCinemaService,
        private ChainBuilder $chainBuilder,
        private ProgrammeSelector $programmeSelector,
        private LocationResolver $locationResolver,
    ) {
    }

    /**
     * @param PlanRequest $request a validated request (see PlanType and Api\Controller\PlanController)
     * @param string      $userId  user identifier (the films they have already seen are excluded)
     *
     * @return array|false ['programmes' => [...], 'reason' => null|'not_enough_programmes'|'fewer_films'|'no_programme'|'unknown_location',
     *                     'films' => number of films per programme (fewer than asked with 'fewer_films'),
     *                     'seed' => the seed of the draw, to get the same programmes again]
     *                     (only 'programmes' and 'reason' with 'unknown_location'),
     *                     or false if no showtime matches the place and date
     */
    public function plan(PlanRequest $request, string $userId): array|false
    {
        $location = $this->location($request);
        if (false === $location) {
            return ['programmes' => [], 'reason' => 'unknown_location'];
        }
        $seed = $request->seed ?? random_int(0, self::MAX_SEED);

        $cinemaSlugs = [];
        $excludedCinemas = $this->excludedCinemaService->getExcludedCinemaSlugs($userId);
        foreach ($this->cinemaRepository->findOpenWithCoordinates() as $cinema) {
            if (in_array($cinema['slug'], $excludedCinemas, true)) {
                continue;
            }
            // Fixed search radius around the city or the position (the form has no radius field).
            if (Geo::distanceKm($location['latitude'], $location['longitude'], $cinema['latitude'], $cinema['longitude']) <= self::DEFAULT_RADIUS_KM) {
                $cinemaSlugs[] = $cinema['slug'];
            }
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $rows = $this->showtimeRepository->findCandidates(
            $request->date,
            $cinemaSlugs,
            // Films already seen and films the user does not want to see are never offered.
            array_merge($this->seenFilmService->getSeenFilmSlugs($userId), $this->unwantedFilmService->getUnwantedFilmSlugs($userId)),
            $request->version,
            $now->format('Y-m-d H:i:s'),
        );
        if ([] === $rows) {
            return false;
        }

        $timeRange = $request->timeRange();
        $showtimes = [];
        foreach ($rows as $row) {
            // The database stores UTC instants: the planner compares them, the time zone of the cinema only shows them.
            $row['start'] = ScreeningTime::fromUtc($row['startsAt']);
            $row['end'] = ScreeningTime::fromUtc($row['endsAt']);
            $row['timezone'] = new \DateTimeZone($row['timezone']);
            if ($timeRange->contains($row['start'], $row['end'], $request->date, $row['timezone'])) {
                $showtimes[] = $row;
            }
        }

        // No marathon with that many films: offer programmes with fewer films, down to single films.
        $films = $requested = $request->filmCount();
        $programmes = $this->select($showtimes, $films, $request, $seed);
        while ([] === $programmes && !$films->isSingle()) {
            $films = $films->fewer();
            $programmes = $this->select($showtimes, $films, $request, $seed);
        }

        $reason = null;
        if ([] === $programmes) {
            $reason = 'no_programme';
        } elseif ($films->isFewerThan($requested)) {
            $reason = 'fewer_films';
        } elseif (\count($programmes) < 3) {
            $reason = 'not_enough_programmes';
        }

        return [
            'programmes' => array_map([$this, 'format'], $programmes),
            'reason' => $reason,
            'films' => $films->value,
            'seed' => $seed,
        ];
    }

    /**
     * The open cinemas around the place of a search (same radius as plan()), excluded ones included
     * with a flag, so that the user can exclude or reactivate them from the results.
     *
     * @param PlanRequest $request only the city and the position are read
     *
     * @return array list of ['slug', 'name', 'distance' (km), 'excluded' (bool)], nearest first;
     *               [] if the place is unknown
     */
    public function nearbyCinemas(PlanRequest $request, string $userId): array
    {
        $location = $this->location($request);
        if (false === $location) {
            return [];
        }

        $excluded = $this->excludedCinemaService->getExcludedCinemaSlugs($userId);
        $cinemas = [];
        foreach ($this->cinemaRepository->findOpenWithCoordinates() as $cinema) {
            $distance = Geo::distanceKm($location['latitude'], $location['longitude'], $cinema['latitude'], $cinema['longitude']);
            if ($distance <= self::DEFAULT_RADIUS_KM) {
                $cinemas[] = [
                    'slug' => $cinema['slug'],
                    'name' => $cinema['name'],
                    'distance' => round($distance, 1),
                    'excluded' => in_array($cinema['slug'], $excluded, true),
                ];
            }
        }
        usort($cinemas, static fn (array $a, array $b) => $a['distance'] <=> $b['distance']);

        return $cinemas;
    }

    /**
     * @return array|false ['latitude', 'longitude'] of the city or of the browser position, false if unknown
     */
    private function location(PlanRequest $request): array|false
    {
        if (null !== $request->city) {
            return $this->locationResolver->fromCity($request->city);
        }
        if (null !== $request->position) {
            return $this->locationResolver->fromPosition($request->position);
        }

        return false;
    }

    /**
     * @return array the best programmes of $films films among the showtimes, drawn with $seed
     */
    private function select(array $showtimes, FilmCount $films, PlanRequest $request, int $seed): array
    {
        return $this->programmeSelector->select(
            $this->chainBuilder->build($showtimes, $films->value, $request->acceptAds, $request->travelMode),
            seed: $seed,
        );
    }

    /**
     * Times are shown in the local time of each cinema (its chain's time zone).
     */
    private function format(array $programme): array
    {
        foreach ($programme['showtimes'] as $i => $showtime) {
            $programme['showtimes'][$i]['startTime'] = $showtime['start']->localTime($showtime['timezone'])->format('H:i');
            $programme['showtimes'][$i]['endTime'] = $showtime['end']->localTime($showtime['timezone'])->format('H:i');
        }
        $programme['filmSlugs'] = array_column($programme['showtimes'], 'filmSlug');

        return $programme;
    }
}

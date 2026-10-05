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
     * @param array  $criteria 'date' (Y-m-d, local day of the cinemas), 'latitude', 'longitude', 'radius' (km), 'films' (number, 1 to 8; 2 by default),
     *                         'version' (or null), 'acceptAds' (bool),
     *                         'travelMode' ('walking', 'cycling', 'transit' or 'car'; 'transit' by default),
     *                         'from' and 'until' (local 'H:i' time range, each optional)
     * @param string $userId   user identifier (the films they have already seen are excluded)
     *
     * @return array|false ['programmes' => [...], 'reason' => null|'not_enough_programmes'|'fewer_films'|'no_programme',
     *                     'films' => number of films per programme (fewer than asked with 'fewer_films')],
     *                     or false if no showtime matches the place and date
     */
    public function plan(array $criteria, string $userId): array|false
    {
        // Fixed search radius around the city or the position (the form has no radius field).
        $radius = $criteria['radius'] ?? self::DEFAULT_RADIUS_KM;
        $films = $criteria['films'] ?? 2;

        $cinemaSlugs = [];
        $excludedCinemas = $this->excludedCinemaService->getExcludedCinemaSlugs($userId);
        foreach ($this->cinemaRepository->findOpenWithCoordinates() as $cinema) {
            if (in_array($cinema['slug'], $excludedCinemas, true)) {
                continue;
            }
            if (Geo::distanceKm($criteria['latitude'], $criteria['longitude'], $cinema['latitude'], $cinema['longitude']) <= $radius) {
                $cinemaSlugs[] = $cinema['slug'];
            }
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $rows = $this->showtimeRepository->findCandidates(
            $criteria['date'],
            $cinemaSlugs,
            // Films already seen and films the user does not want to see are never offered.
            array_merge($this->seenFilmService->getSeenFilmSlugs($userId), $this->unwantedFilmService->getUnwantedFilmSlugs($userId)),
            $criteria['version'] ?? null,
            $now->format('Y-m-d H:i:s'),
        );
        if ([] === $rows) {
            return false;
        }

        $showtimes = [];
        foreach ($rows as $row) {
            // The database stores UTC instants: the planner only compares timestamps.
            $row['start'] = (new \DateTimeImmutable($row['startsAt'], new \DateTimeZone('UTC')))->getTimestamp();
            $row['end'] = (new \DateTimeImmutable($row['endsAt'], new \DateTimeZone('UTC')))->getTimestamp();
            // An empty field of the form means "no limit".
            if ($this->isWithinTimeRange($row, $criteria['date'], ($criteria['from'] ?? null) ?: null, ($criteria['until'] ?? null) ?: null)) {
                $showtimes[] = $row;
            }
        }

        // No marathon with that many films: offer programmes with fewer films, down to single films.
        $requestedFilms = $films;
        do {
            $programmes = $this->programmeSelector->select(
                $this->chainBuilder->build($showtimes, $films, $criteria['acceptAds'] ?? false, $criteria['travelMode'] ?? 'transit'),
            );
        } while ([] === $programmes && --$films >= 1);
        $films = max($films, 1);

        $reason = null;
        if ([] === $programmes) {
            $reason = 'no_programme';
        } elseif ($films < $requestedFilms) {
            $reason = 'fewer_films';
        } elseif (\count($programmes) < 3) {
            $reason = 'not_enough_programmes';
        }

        return [
            'programmes' => array_map([$this, 'format'], $programmes),
            'reason' => $reason,
            'films' => $films,
        ];
    }

    /**
     * Plans from the PlanType form data (website or API).
     *
     * @param array $data 'date', 'city' (slug or null), 'position' (JSON or null), 'films', 'version', 'acceptAds', 'travelMode', 'from', 'until'
     *
     * @return array|false like plan(), with the additional reason 'unknown_location'
     */
    public function planFromForm(array $data, string $userId): array|false
    {
        $location = $this->locationFromForm($data);
        if (false === $location) {
            return ['programmes' => [], 'reason' => 'unknown_location'];
        }

        return $this->plan([
            'date' => $data['date'],
            'latitude' => $location['latitude'],
            'longitude' => $location['longitude'],
            'films' => $data['films'] ?? null,
            'version' => $data['version'] ?? null,
            'acceptAds' => $data['acceptAds'] ?? false,
            'travelMode' => $data['travelMode'] ?? null,
            'from' => $data['from'] ?? null,
            'until' => $data['until'] ?? null,
        ], $userId);
    }

    /**
     * The open cinemas around the place of a search (same radius as plan()), excluded ones included
     * with a flag, so that the user can exclude or reactivate them from the results.
     *
     * @param array $data the PlanType data (only 'city' and 'position' are read)
     *
     * @return array list of ['slug', 'name', 'distance' (km), 'excluded' (bool)], nearest first;
     *               [] if the place is unknown
     */
    public function nearbyCinemas(array $data, string $userId): array
    {
        $location = $this->locationFromForm($data);
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
    private function locationFromForm(array $data): array|false
    {
        if (!empty($data['city'])) {
            return $this->locationResolver->fromCity($data['city']);
        }
        if (!empty($data['position'])) {
            return $this->locationResolver->fromPosition($data['position']);
        }

        return false;
    }

    /**
     * Time range of the search, in the local time of the cinema: the showtime starts at or after $from
     * and ends at or before $until ('H:i', each optional, on the same day; PlanType refuses an $until
     * before $from).
     */
    private function isWithinTimeRange(array $row, string $date, ?string $from, ?string $until): bool
    {
        if (null === $from && null === $until) {
            return true;
        }

        $midnight = (new \DateTimeImmutable($date.' 00:00:00', new \DateTimeZone($row['timezone'])))->getTimestamp();
        $toMinutes = static fn (string $time): int => (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
        $fromMinutes = null === $from ? 0 : $toMinutes($from);
        $startMinutes = intdiv($row['start'] - $midnight, 60);
        if ($startMinutes < $fromMinutes) {
            return false;
        }
        if (null === $until) {
            return true;
        }

        return intdiv($row['end'] - $midnight, 60) <= $toMinutes($until);
    }

    /**
     * Times are shown in the local time of each cinema (its chain's time zone).
     */
    private function format(array $programme): array
    {
        foreach ($programme['showtimes'] as $i => $showtime) {
            $timezone = new \DateTimeZone($showtime['timezone']);
            $programme['showtimes'][$i]['startTime'] = (new \DateTimeImmutable('@'.$showtime['start']))->setTimezone($timezone)->format('H:i');
            $programme['showtimes'][$i]['endTime'] = (new \DateTimeImmutable('@'.$showtime['end']))->setTimezone($timezone)->format('H:i');
        }
        $programme['filmSlugs'] = array_column($programme['showtimes'], 'filmSlug');

        return $programme;
    }
}

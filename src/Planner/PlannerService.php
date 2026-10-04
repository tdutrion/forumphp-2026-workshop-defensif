<?php

namespace App\Planner;

use App\Account\SeenFilmService;
use App\Account\UnwantedFilmService;
use App\Catalog\Repository\CinemaRepository;
use App\Catalog\Repository\ShowtimeRepository;

/**
 * The "plan a marathon" use case, shared by the website and the API.
 */
class PlannerService
{
    public function __construct(
        private CinemaRepository $cinemaRepository,
        private ShowtimeRepository $showtimeRepository,
        private SeenFilmService $seenFilmService,
        private UnwantedFilmService $unwantedFilmService,
        private ChainBuilder $chainBuilder,
        private ProgrammeSelector $programmeSelector,
        private LocationResolver $locationResolver,
    ) {
    }

    /**
     * @param array  $criteria 'date' (Y-m-d, local day of the cinemas), 'latitude', 'longitude', 'radius' (km), 'films' (number),
     *                         'version' (or null), 'acceptAds' (bool),
     *                         'travelMode' ('walking', 'cycling', 'transit' or 'car'; 'transit' by default)
     * @param string $userId   user identifier (the films they have already seen are excluded)
     *
     * @return array|false ['programmes' => [...], 'reason' => null|'not_enough_programmes'|'no_programme'],
     *                     or false if no showtime matches the place and date
     */
    public function plan(array $criteria, string $userId): array|false
    {
        $radius = $criteria['radius'] ?? 10;
        $films = $criteria['films'] ?? 3;

        $cinemaSlugs = [];
        foreach ($this->cinemaRepository->findOpenWithCoordinates() as $cinema) {
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
            $showtimes[] = $row;
        }

        $programmes = $this->programmeSelector->select(
            $this->chainBuilder->build($showtimes, $films, $criteria['acceptAds'] ?? false, $criteria['travelMode'] ?? 'transit'),
        );

        $reason = null;
        if ([] === $programmes) {
            $reason = 'no_programme';
        } elseif (\count($programmes) < 3) {
            $reason = 'not_enough_programmes';
        }

        return [
            'programmes' => array_map([$this, 'format'], $programmes),
            'reason' => $reason,
        ];
    }

    /**
     * Plans from the PlanType form data (website or API).
     *
     * @param array $data 'date', 'city' (slug or null), 'position' (JSON or null), 'radius', 'films', 'version', 'acceptAds', 'travelMode'
     *
     * @return array|false like plan(), with the additional reason 'unknown_location'
     */
    public function planFromForm(array $data, string $userId): array|false
    {
        $location = false;
        if (!empty($data['city'])) {
            $location = $this->locationResolver->fromCity($data['city']);
        } elseif (!empty($data['position'])) {
            $location = $this->locationResolver->fromPosition($data['position']);
        }

        if (false === $location) {
            return ['programmes' => [], 'reason' => 'unknown_location'];
        }

        return $this->plan([
            'date' => $data['date'],
            'latitude' => $location['latitude'],
            'longitude' => $location['longitude'],
            'radius' => $data['radius'] ?? null,
            'films' => $data['films'] ?? null,
            'version' => $data['version'] ?? null,
            'acceptAds' => $data['acceptAds'] ?? false,
            'travelMode' => $data['travelMode'] ?? null,
        ], $userId);
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

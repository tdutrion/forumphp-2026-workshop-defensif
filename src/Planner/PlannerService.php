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
     * @return PlanResult programmes (a PlanNotice says when they are not quite what was asked), or a PlanFailure
     */
    #[\NoDiscard('A plan that is not read is a search wasted.')]
    public function plan(PlanRequest $request, string $userId): PlanResult
    {
        $location = $this->location($request);
        if (false === $location) {
            return PlanResult::failure(PlanFailure::UnknownLocation);
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
            return PlanResult::failure(PlanFailure::NoShowtime);
        }

        $timeRange = $request->timeRange();
        // The database stores UTC instants: the planner compares them, the time zone of the cinema only shows them.
        $showtimes = new ScheduledShowtimeList(...array_filter(
            array_map(ScheduledShowtime::fromRow(...), $rows),
            static fn (ScheduledShowtime $showtime): bool => $timeRange->contains($showtime->start, $showtime->end, $request->date, $showtime->timezone),
        ));

        // No marathon with that many films: offer programmes with fewer films, down to single films.
        $films = $requested = $request->filmCount();
        $programmes = $this->select($showtimes, $films, $request, $seed);
        while ($programmes->isEmpty() && !$films->isSingle()) {
            $films = $films->fewer();
            $programmes = $this->select($showtimes, $films, $request, $seed);
        }

        if ($programmes->isEmpty()) {
            return PlanResult::failure(PlanFailure::NoProgramme, $films->value, $seed);
        }

        $notice = match (true) {
            $films->isFewerThan($requested) => PlanNotice::FewerFilms,
            \count($programmes) < 3 => PlanNotice::NotEnoughProgrammes,
            default => null,
        };

        return PlanResult::success($programmes, $films->value, $seed, $notice);
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
     * @return ProgrammeList the best programmes of $films films among the showtimes, drawn with $seed
     */
    private function select(ScheduledShowtimeList $showtimes, FilmCount $films, PlanRequest $request, int $seed): ProgrammeList
    {
        return $this->programmeSelector->select(
            $this->chainBuilder->build($showtimes, $films->value, $request->acceptAds, $request->travelMode),
            seed: $seed,
        );
    }
}

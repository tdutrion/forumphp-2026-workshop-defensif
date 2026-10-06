<?php

namespace App\Planner;

/**
 * Builds the sequences of showtimes that chain together.
 *
 * A showtime occupies the range [start, end]: start is the beginning of the ads, end is the end of the film.
 */
class ChainBuilder
{
    public const MARGIN_MINUTES = 10;
    /**
     * Straight-line speed (km/h) and fixed minutes (waiting, parking) of each travel mode.
     * No routing service: the workshop must work without a network.
     */
    public const TRAVEL_MODES = [
        'walking' => [5, 0],
        'cycling' => [15, 0],
        'transit' => [20, 10],
        'car' => [30, 15],
    ];
    public const ADS_MINUTES = 15;

    public function __construct(private int $maxNodes = 20000)
    {
    }

    /**
     * @param array  $showtimes  candidate showtimes: id, filmSlug, workId (common to every chain), cinemaSlug, latitude, longitude,
     *                           start and end (timestamps), plus any display keys
     * @param int    $count      number of films per programme
     * @param bool   $acceptAds  accept arriving up to 15 minutes after the showtime starts
     * @param string $travelMode 'walking', 'cycling', 'transit' or 'car' (see TRAVEL_MODES)
     *
     * @return array programmes: ['showtimes' => [...], 'wait' => minutes, 'distance' => km]
     */
    public function build(array $showtimes, int $count, bool $acceptAds, string $travelMode = 'transit'): array
    {
        usort($showtimes, static fn (array $a, array $b) => $a['start'] <=> $b['start']);

        $programmes = [];
        $nodes = 0;
        $roots = \count($showtimes);
        foreach (array_keys($showtimes) as $index) {
            // Each starting showtime gets its share of the remaining budget: the first showtimes
            // of the day must not use it all. What a short subtree leaves goes to the next ones.
            $limit = $nodes + intdiv($this->maxNodes - $nodes, $roots - $index);
            $this->explore([$showtimes[$index]], $index, $showtimes, $count, $acceptAds, $travelMode, $programmes, $nodes, $limit);
            if ($nodes >= $this->maxNodes) {
                break;
            }
        }

        return $programmes;
    }

    private function explore(array $path, int $lastIndex, array $showtimes, int $count, bool $acceptAds, string $travelMode, array &$programmes, int &$nodes, int $limit): void
    {
        ++$nodes;
        if (\count($path) === $count) {
            $programmes[] = $this->summarize($path, $travelMode);

            return;
        }

        $last = $path[\count($path) - 1];
        // One showtime per work: the same film shown by two chains is still one film.
        $works = array_column($path, 'workId');
        $total = \count($showtimes);
        for ($next = $lastIndex + 1; $next < $total; ++$next) {
            if ($nodes >= $limit) {
                return;
            }
            $candidate = $showtimes[$next];
            if (in_array($candidate['workId'], $works, true) || !$this->canChain($last, $candidate, $acceptAds, $travelMode)) {
                continue;
            }
            $this->explore([...$path, $candidate], $next, $showtimes, $count, $acceptAds, $travelMode, $programmes, $nodes, $limit);
        }
    }

    private function canChain(array $previous, array $next, bool $acceptAds, string $travelMode): bool
    {
        $latestArrival = $next['start'] + ($acceptAds ? self::ADS_MINUTES * 60 : 0);

        return $this->arrival($previous, $next, $travelMode) <= $latestArrival;
    }

    /**
     * Earliest arrival time (timestamp) at the next showtime.
     */
    private function arrival(array $previous, array $next, string $travelMode): int
    {
        $arrival = $previous['end'] + self::MARGIN_MINUTES * 60;
        if ($previous['cinemaSlug'] !== $next['cinemaSlug']) {
            $arrival += $this->travelMinutes($previous, $next, $travelMode) * 60;
        }

        return $arrival;
    }

    private function travelMinutes(array $from, array $to, string $travelMode): int
    {
        $distance = Geo::distanceKm($from['latitude'], $from['longitude'], $to['latitude'], $to['longitude']);
        [$speedKmh, $fixedMinutes] = self::TRAVEL_MODES[$travelMode] ?? self::TRAVEL_MODES['transit'];

        return (int) ceil($distance / $speedKmh * 60) + $fixedMinutes;
    }

    private function summarize(array $path, string $travelMode): array
    {
        $wait = 0;
        $distance = 0.0;
        $path[0]['lateMinutes'] = 0;
        $path[0]['breakMinutes'] = 0;
        $path[0]['travelMinutes'] = 0;
        for ($i = 1, $n = \count($path); $i < $n; ++$i) {
            $arrival = $this->arrival($path[$i - 1], $path[$i], $travelMode);
            // Shown between two showtimes: time from the end of the previous film to the next start, and its travel.
            $path[$i]['breakMinutes'] = intdiv(max(0, $path[$i]['start'] - $path[$i - 1]['end']), 60);
            $path[$i]['travelMinutes'] = $path[$i - 1]['cinemaSlug'] === $path[$i]['cinemaSlug']
                ? 0
                : $this->travelMinutes($path[$i - 1], $path[$i], $travelMode);
            $wait += max(0, $path[$i]['start'] - $arrival);
            $path[$i]['lateMinutes'] = intdiv(max(0, $arrival - $path[$i]['start']), 60);
            if ($path[$i - 1]['cinemaSlug'] !== $path[$i]['cinemaSlug']) {
                $distance += Geo::distanceKm($path[$i - 1]['latitude'], $path[$i - 1]['longitude'], $path[$i]['latitude'], $path[$i]['longitude']);
            }
        }

        return [
            'showtimes' => $path,
            // Score: time really lost waiting (the breaks minus the 10-minute margins and the travel).
            'wait' => intdiv($wait, 60),
            'distance' => round($distance, 2),
            // Shown to the user: the sum of the breaks between the showtimes, and the travel they include.
            'breakMinutes' => array_sum(array_column($path, 'breakMinutes')),
            'travelMinutes' => array_sum(array_column($path, 'travelMinutes')),
        ];
    }
}

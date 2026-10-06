<?php

namespace App\Planner;

use Time\Duration;

/**
 * Builds the sequences of showtimes that chain together.
 *
 * A showtime occupies the range [start, end]: start is the beginning of the ads, end is the end of the film.
 */
class ChainBuilder
{
    public function __construct(private int $maxNodes = 20000)
    {
    }

    /**
     * @param array $showtimes candidate showtimes: id, filmSlug, workId (common to every chain), cinemaSlug, latitude, longitude,
     *                         start and end (ScreeningTime), plus any display keys
     * @param int   $count     number of films per programme
     * @param bool  $acceptAds accept arriving up to 15 minutes after the showtime starts
     *
     * @return array programmes: ['showtimes' => [...], 'wait' => minutes, 'distance' => km]
     */
    public function build(array $showtimes, int $count, bool $acceptAds, TravelMode $travelMode = TravelMode::Transit): array
    {
        usort($showtimes, static fn (array $a, array $b) => ScreeningTime::compare($a['start'], $b['start']));

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

    private function explore(array $path, int $lastIndex, array $showtimes, int $count, bool $acceptAds, TravelMode $travelMode, array &$programmes, int &$nodes, int $limit): void
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

    private function canChain(array $previous, array $next, bool $acceptAds, TravelMode $travelMode): bool
    {
        $latestArrival = $acceptAds ? $next['start']->plus($this->ads()) : $next['start'];

        return !$this->arrival($previous, $next, $travelMode)->isAfter($latestArrival);
    }

    /**
     * Margin between the end of a film and the next showtime, to leave the hall.
     */
    private function margin(): Duration
    {
        return Duration::fromMinutes(10);
    }

    /**
     * How late one may arrive when accepting to miss the ads.
     */
    private function ads(): Duration
    {
        return Duration::fromMinutes(15);
    }

    /**
     * Earliest arrival time at the next showtime.
     */
    private function arrival(array $previous, array $next, TravelMode $travelMode): ScreeningTime
    {
        $arrival = $previous['end']->plus($this->margin());
        if ($previous['cinemaSlug'] !== $next['cinemaSlug']) {
            $arrival = $arrival->plus($this->travel($previous, $next, $travelMode));
        }

        return $arrival;
    }

    private function travel(array $from, array $to, TravelMode $travelMode): Duration
    {
        $distance = Geo::distanceKm($from['latitude'], $from['longitude'], $to['latitude'], $to['longitude']);

        return Duration::fromMinutes((int) ceil($distance / $travelMode->speedKmh() * 60) + $travelMode->fixedMinutes());
    }

    /**
     * Whole minutes shown to the user; 0 for a negative duration.
     */
    private function minutes(Duration $duration): int
    {
        return $duration->negative ? 0 : intdiv($duration->seconds, 60);
    }

    private function summarize(array $path, TravelMode $travelMode): array
    {
        $wait = Duration::fromSeconds(0);
        $distance = 0.0;
        $path[0]['lateMinutes'] = 0;
        $path[0]['breakMinutes'] = 0;
        $path[0]['travelMinutes'] = 0;
        for ($i = 1, $n = \count($path); $i < $n; ++$i) {
            $arrival = $this->arrival($path[$i - 1], $path[$i], $travelMode);
            // Shown between two showtimes: time from the end of the previous film to the next start, and its travel.
            $path[$i]['breakMinutes'] = $this->minutes($path[$i - 1]['end']->until($path[$i]['start']));
            $path[$i]['travelMinutes'] = $path[$i - 1]['cinemaSlug'] === $path[$i]['cinemaSlug']
                ? 0
                : $this->minutes($this->travel($path[$i - 1], $path[$i], $travelMode));
            $early = $arrival->until($path[$i]['start']);
            if (!$early->negative) {
                $wait = $wait->add($early);
            }
            $path[$i]['lateMinutes'] = $this->minutes($path[$i]['start']->until($arrival));
            if ($path[$i - 1]['cinemaSlug'] !== $path[$i]['cinemaSlug']) {
                $distance += Geo::distanceKm($path[$i - 1]['latitude'], $path[$i - 1]['longitude'], $path[$i]['latitude'], $path[$i]['longitude']);
            }
        }

        return [
            'showtimes' => $path,
            // Score: time really lost waiting (the breaks minus the 10-minute margins and the travel).
            'wait' => $this->minutes($wait),
            'distance' => round($distance, 2),
            // Shown to the user: the sum of the breaks between the showtimes, and the travel they include.
            'breakMinutes' => array_sum(array_column($path, 'breakMinutes')),
            'travelMinutes' => array_sum(array_column($path, 'travelMinutes')),
        ];
    }
}

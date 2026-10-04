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
    public const SPEED_KMH = 15;
    public const ADS_MINUTES = 15;

    public function __construct(private int $maxNodes = 20000)
    {
    }

    /**
     * @param array $showtimes candidate showtimes: id, filmSlug, cinemaSlug, latitude, longitude,
     *                         start and end (timestamps), plus any display keys
     * @param int   $count     number of films per programme
     * @param bool  $acceptAds accept arriving up to 15 minutes after the showtime starts
     *
     * @return array programmes: ['showtimes' => [...], 'wait' => minutes, 'distance' => km]
     */
    public function build(array $showtimes, int $count, bool $acceptAds): array
    {
        usort($showtimes, static fn (array $a, array $b) => $a['start'] <=> $b['start']);

        $programmes = [];
        $nodes = 0;
        foreach (array_keys($showtimes) as $index) {
            $this->explore([$showtimes[$index]], $index, $showtimes, $count, $acceptAds, $programmes, $nodes);
            if ($nodes >= $this->maxNodes) {
                break;
            }
        }

        return $programmes;
    }

    private function explore(array $path, int $lastIndex, array $showtimes, int $count, bool $acceptAds, array &$programmes, int &$nodes): void
    {
        ++$nodes;
        if (\count($path) === $count) {
            $programmes[] = $this->summarize($path);

            return;
        }

        $last = $path[\count($path) - 1];
        $films = array_column($path, 'filmSlug');
        $total = \count($showtimes);
        for ($next = $lastIndex + 1; $next < $total; ++$next) {
            if ($nodes >= $this->maxNodes) {
                return;
            }
            $candidate = $showtimes[$next];
            if (in_array($candidate['filmSlug'], $films, true) || !$this->canChain($last, $candidate, $acceptAds)) {
                continue;
            }
            $this->explore([...$path, $candidate], $next, $showtimes, $count, $acceptAds, $programmes, $nodes);
        }
    }

    private function canChain(array $previous, array $next, bool $acceptAds): bool
    {
        $latestArrival = $next['start'] + ($acceptAds ? self::ADS_MINUTES * 60 : 0);

        return $this->arrival($previous, $next) <= $latestArrival;
    }

    /**
     * Earliest arrival time (timestamp) at the next showtime.
     */
    private function arrival(array $previous, array $next): int
    {
        $arrival = $previous['end'] + self::MARGIN_MINUTES * 60;
        if ($previous['cinemaSlug'] !== $next['cinemaSlug']) {
            $arrival += $this->travelMinutes($previous, $next) * 60;
        }

        return $arrival;
    }

    private function travelMinutes(array $from, array $to): int
    {
        $distance = Geo::distanceKm($from['latitude'], $from['longitude'], $to['latitude'], $to['longitude']);

        return (int) ceil($distance / self::SPEED_KMH * 60);
    }

    private function summarize(array $path): array
    {
        $wait = 0;
        $distance = 0.0;
        $path[0]['lateMinutes'] = 0;
        for ($i = 1, $n = \count($path); $i < $n; ++$i) {
            $arrival = $this->arrival($path[$i - 1], $path[$i]);
            $wait += max(0, $path[$i]['start'] - $arrival);
            $path[$i]['lateMinutes'] = intdiv(max(0, $arrival - $path[$i]['start']), 60);
            if ($path[$i - 1]['cinemaSlug'] !== $path[$i]['cinemaSlug']) {
                $distance += Geo::distanceKm($path[$i - 1]['latitude'], $path[$i - 1]['longitude'], $path[$i]['latitude'], $path[$i]['longitude']);
            }
        }

        return [
            'showtimes' => $path,
            'wait' => intdiv($wait, 60),
            'distance' => round($distance, 2),
        ];
    }
}

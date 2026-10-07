<?php

declare(strict_types=1);

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
     * @param ScheduledShowtimeList $showtimes candidate showtimes
     * @param int                   $count     number of films per programme
     * @param bool                  $acceptAds accept arriving up to 15 minutes after the showtime starts
     */
    public function build(ScheduledShowtimeList $showtimes, int $count, bool $acceptAds, TravelMode $travelMode = TravelMode::Transit): ProgrammeList
    {
        $candidates = $showtimes->sortedByStart()->toArray();

        $programmes = [];
        $nodes = 0;
        $roots = \count($candidates);
        foreach (array_keys($candidates) as $index) {
            // Each starting showtime gets its share of the remaining budget: the first showtimes
            // of the day must not use it all. What a short subtree leaves goes to the next ones.
            $limit = $nodes + intdiv($this->maxNodes - $nodes, $roots - $index);
            $this->explore(new ScheduledShowtimeList($candidates[$index]), $index, $candidates, $count, $acceptAds, $travelMode, $programmes, $nodes, $limit);
            if ($nodes >= $this->maxNodes) {
                break;
            }
        }

        return new ProgrammeList(...$programmes);
    }

    /**
     * @param list<ScheduledShowtime> $showtimes  the candidates, in order of start
     * @param list<Programme>         $programmes
     */
    private function explore(ScheduledShowtimeList $path, int $lastIndex, array $showtimes, int $count, bool $acceptAds, TravelMode $travelMode, array &$programmes, int &$nodes, int $limit): void
    {
        ++$nodes;
        if (\count($path) === $count) {
            $programmes[] = $this->summarize($path, $travelMode);

            return;
        }

        $last = $path->last();
        \assert(null !== $last, 'A path always holds the showtime it starts with.');
        $total = \count($showtimes);
        for ($next = $lastIndex + 1; $next < $total; ++$next) {
            if ($nodes >= $limit) {
                return;
            }
            $candidate = $showtimes[$next];
            // One showtime per work: the same film shown by two chains is still one film.
            if ($path->hasWork($candidate->workId) || !$this->canChain($last, $candidate, $acceptAds, $travelMode)) {
                continue;
            }
            $this->explore($path->with($candidate), $next, $showtimes, $count, $acceptAds, $travelMode, $programmes, $nodes, $limit);
        }
    }

    private function canChain(ScheduledShowtime $previous, ScheduledShowtime $next, bool $acceptAds, TravelMode $travelMode): bool
    {
        $latestArrival = $acceptAds ? $next->start->plus($this->ads()) : $next->start;

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
    private function arrival(ScheduledShowtime $previous, ScheduledShowtime $next, TravelMode $travelMode): ScreeningTime
    {
        $arrival = $previous->end->plus($this->margin());
        if ($previous->cinemaSlug !== $next->cinemaSlug) {
            $arrival = $arrival->plus($this->travel($previous, $next, $travelMode));
        }

        return $arrival;
    }

    private function travel(ScheduledShowtime $from, ScheduledShowtime $to, TravelMode $travelMode): Duration
    {
        $distance = Geo::distanceKm($from->position->latitude, $from->position->longitude, $to->position->latitude, $to->position->longitude);

        return Duration::fromMinutes((int) ceil($distance / $travelMode->speedKmh() * 60) + $travelMode->fixedMinutes());
    }

    /**
     * Whole minutes shown to the user; 0 for a negative duration.
     */
    private function minutes(Duration $duration): int
    {
        return $duration->negative ? 0 : intdiv($duration->seconds, 60);
    }

    private function summarize(ScheduledShowtimeList $path, TravelMode $travelMode): Programme
    {
        $steps = $path->toArray();
        $wait = Duration::fromSeconds(0);
        $distance = 0.0;
        $showtimes = [$steps[0]];
        for ($i = 1, $n = \count($steps); $i < $n; ++$i) {
            $previous = $steps[$i - 1];
            $current = $steps[$i];
            $arrival = $this->arrival($previous, $current, $travelMode);
            $early = $arrival->until($current->start);
            if (!$early->negative) {
                $wait = $wait->add($early);
            }
            $changesCinema = $previous->cinemaSlug !== $current->cinemaSlug;
            if ($changesCinema) {
                $distance += Geo::distanceKm($previous->position->latitude, $previous->position->longitude, $current->position->latitude, $current->position->longitude);
            }
            // Shown between two showtimes: time from the end of the previous film to the next start, and its travel.
            $showtimes[] = $current->withTransition(
                lateMinutes: $this->minutes($current->start->until($arrival)),
                breakMinutes: $this->minutes($previous->end->until($current->start)),
                travelMinutes: $changesCinema ? $this->minutes($this->travel($previous, $current, $travelMode)) : 0,
            );
        }

        $showtimes = new ScheduledShowtimeList(...$showtimes);

        return new Programme(
            $showtimes,
            // Score: time really lost waiting (the breaks minus the 10-minute margins and the travel).
            wait: $this->minutes($wait),
            distance: round($distance, 2),
            // Shown to the user: the sum of the breaks between the showtimes, and the travel they include.
            breakMinutes: $showtimes->sum(static fn (ScheduledShowtime $showtime): int => $showtime->breakMinutes),
            travelMinutes: $showtimes->sum(static fn (ScheduledShowtime $showtime): int => $showtime->travelMinutes),
        );
    }
}

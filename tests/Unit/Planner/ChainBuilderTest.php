<?php

namespace App\Tests\Unit\Planner;

use App\Planner\ChainBuilder;
use App\Planner\Programme;
use App\Planner\ProgrammeList;
use App\Planner\ScheduledShowtime;
use App\Planner\ScheduledShowtimeList;
use App\Planner\TravelMode;
use App\Tests\Builder\ScreeningBuilder;
use PHPUnit\Framework\TestCase;

final class ChainBuilderTest extends TestCase
{
    private function inCinemaA(string $id): ScreeningBuilder
    {
        return ScreeningBuilder::aScreening($id)->inCinema('A', 0.0, 0.0);
    }

    // On the equator, 2.99 km away from cinema A: 12 minutes of travel by bike (15 km/h).
    private function inCinemaB(string $id): ScreeningBuilder
    {
        return ScreeningBuilder::aScreening($id)->inCinema('B', 0.0, 0.0269);
    }

    private function ids(ProgrammeList $programmes): array
    {
        return $programmes->map(static fn (Programme $programme): string => implode('>', array_map(static fn (ScheduledShowtime $showtime): string => $showtime->id, $programme->showtimes->toArray())));
    }

    private function build(array $showtimes, int $count, bool $acceptAds, TravelMode $travelMode = TravelMode::Transit, int $maxNodes = 20000): ProgrammeList
    {
        return (new ChainBuilder($maxNodes))->build(new ScheduledShowtimeList(...$showtimes), $count, $acceptAds, $travelMode);
    }

    public function testTheNextShowtimeLeavesTimeForTheMarginAndTheTravel(): void
    {
        // Arrange: the first film ends at 16:00 (14:00 + 100 min + 20 min of ads).
        $showtimes = [
            $this->inCinemaA('first')->ofFilm('film-1')->startingAt('14:00')->lasting(100)->build(),
            $this->inCinemaA('same-cinema-ok')->ofFilm('film-2')->startingAt('16:10')->lasting(90)->build(),
            $this->inCinemaA('same-cinema-too-early')->ofFilm('film-3')->startingAt('16:09')->lasting(90)->build(),
            $this->inCinemaB('other-cinema-ok')->ofFilm('film-4')->startingAt('16:22')->lasting(90)->build(),
            $this->inCinemaB('other-cinema-too-early')->ofFilm('film-5')->startingAt('16:21')->lasting(90)->build(),
        ];

        // Act
        $programmes = $this->build($showtimes, 2, false, TravelMode::Cycling);

        // Assert: 10 minutes of margin, plus the travel time when changing cinema.
        self::assertEqualsCanonicalizing(['first>same-cinema-ok', 'first>other-cinema-ok'], $this->ids($programmes));
    }

    public function testTheTravelModeSetsTheTimeNeededToChangeCinema(): void
    {
        // Arrange: the first film ends at 16:00 in cinema A; cinema B is 2.99 km away.
        $showtimes = [
            $this->inCinemaA('first')->ofFilm('film-1')->startingAt('14:00')->lasting(100)->build(),
            $this->inCinemaB('at-16-25')->ofFilm('film-2')->startingAt('16:25')->lasting(90)->build(),
            $this->inCinemaB('at-16-50')->ofFilm('film-3')->startingAt('16:50')->lasting(90)->build(),
        ];
        $reachable = [];

        // Act
        foreach (TravelMode::cases() as $mode) {
            $reachable[$mode->value] = $this->ids($this->build($showtimes, 2, false, $mode));
        }

        // Assert: 10 min of margin, then walking 36 min, cycling 12, transit 9 + 10 of waiting, car 6 + 15 of parking.
        self::assertEqualsCanonicalizing(['first>at-16-50'], $reachable['walking']);
        self::assertEqualsCanonicalizing(['first>at-16-25', 'first>at-16-50'], $reachable['cycling']);
        self::assertEqualsCanonicalizing(['first>at-16-50'], $reachable['transit']);
        self::assertEqualsCanonicalizing(['first>at-16-50'], $reachable['car']);
    }

    public function testAcceptingAdsAllowsArrivingUpToFifteenMinutesLate(): void
    {
        // Arrange
        $showtimes = [
            $this->inCinemaA('s1')->ofFilm('film-1')->startingAt('14:00')->lasting(100)->build(),
            $this->inCinemaB('s2')->ofFilm('film-2')->startingAt('16:07')->lasting(90)->build(),
        ];

        // Act
        $withoutAds = $this->build($showtimes, 2, false, TravelMode::Cycling);
        $withAds = $this->build($showtimes, 2, true, TravelMode::Cycling);

        // Assert
        self::assertTrue($withoutAds->isEmpty());
        self::assertSame(['s1>s2'], $this->ids($withAds));
        self::assertSame(15, $withAds->first()->showtimes->toArray()[1]->lateMinutes);
        self::assertSame(0, $withAds->first()->wait);
    }

    public function testNeverProposesTheSameFilmTwice(): void
    {
        // Arrange
        $showtimes = [
            $this->inCinemaA('s1')->ofFilm('film-1')->startingAt('14:00')->lasting(100)->build(),
            $this->inCinemaA('s2')->ofFilm('film-1')->startingAt('16:30')->lasting(100)->build(),
        ];

        // Act
        $programmes = $this->build($showtimes, 2, false, TravelMode::Cycling);

        // Assert
        self::assertTrue($programmes->isEmpty());
    }

    public function testChainsAcrossMidnight(): void
    {
        // Arrange
        $showtimes = [
            $this->inCinemaA('early')->ofFilm('film-1')->startingAt('19:10')->lasting(129)->build(), // ends at 21:39
            $this->inCinemaA('late')->ofFilm('film-2')->startingAt('21:55')->lasting(129)->build(),  // ends at 00:24 the next day
            $this->inCinemaA('after')->ofFilm('film-3')->startingAt('23:50')->lasting(90)->build(),
        ];

        // Act
        $twoFilms = $this->build($showtimes, 2, false, TravelMode::Cycling);
        $threeFilms = $this->build($showtimes, 3, false);

        // Assert
        self::assertSame(['early>late', 'early>after'], $this->ids($twoFilms));
        self::assertTrue($threeFilms->isEmpty(), 'the 23:50 showtime starts before the 21:55 one ends');
    }

    public function testMeasuresTheWaitAndTheTravel(): void
    {
        // Arrange
        $showtimes = [
            $this->inCinemaA('s1')->ofFilm('film-1')->startingAt('14:00')->lasting(100)->build(),
            $this->inCinemaB('s2')->ofFilm('film-2')->startingAt('16:40')->lasting(90)->build(),
        ];

        // Act
        $programme = $this->build($showtimes, 2, false, TravelMode::Cycling)->first();

        // Assert: earliest arrival 16:00 + 10 min of margin + 12 min of travel = 16:22; the showtime starts at 16:40.
        self::assertSame(18, $programme->wait);
        self::assertEqualsWithDelta(2.99, $programme->distance, 0.01);
        self::assertSame(0, $programme->showtimes->toArray()[1]->lateMinutes);
        self::assertSame(40, $programme->showtimes->toArray()[1]->breakMinutes, 'from the end of the first film (16:00) to the next showtime (16:40)');
        self::assertSame(12, $programme->showtimes->toArray()[1]->travelMinutes);
        self::assertSame(40, $programme->breakMinutes, 'sum of the breaks shown between the showtimes');
        self::assertSame(12, $programme->travelMinutes);
    }

    public function testTheSearchBudgetIsSharedAcrossTheDay(): void
    {
        // Arrange: a morning full of short films (thousands of chains) and a perfect evening chain.
        $showtimes = [$this->inCinemaA('early')->ofFilm('film-early')->startingAt('09:00')->lasting(10)->build()];
        for ($i = 0; $i < 20; ++$i) {
            $minutes = 600 + 25 * $i;
            $showtimes[] = $this->inCinemaA('morning-'.$i)->ofFilm('film-morning-'.$i)
                ->startingAt(sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60))->lasting(10)->build();
        }
        $showtimes[] = $this->inCinemaA('evening-1')->ofFilm('film-evening-1')->startingAt('20:00')->lasting(10)->build();
        $showtimes[] = $this->inCinemaA('evening-2')->ofFilm('film-evening-2')->startingAt('20:40')->lasting(10)->build();
        $showtimes[] = $this->inCinemaA('evening-3')->ofFilm('film-evening-3')->startingAt('21:20')->lasting(10)->build();

        // Act
        $programmes = $this->build($showtimes, 3, false, maxNodes: 120);

        // Assert: the cap must not be spent on the first showtime of the day alone.
        self::assertContains('evening-1>evening-2>evening-3', $this->ids($programmes));
    }

    public function testABusyDayDoesNotMakeTheSearchEndless(): void
    {
        // Arrange: 30 short films one after the other, thousands of possible chains.
        $showtimes = [];
        for ($i = 0; $i < 30; ++$i) {
            $showtimes[] = $this->inCinemaA('s'.$i)->ofFilm('film-'.$i)
                ->startingAt(sprintf('%02d:%02d', 10 + intdiv($i * 20, 60), ($i * 20) % 60))->lasting(10)->build();
        }

        // Act
        $unbounded = $this->build($showtimes, 3, false);
        $bounded = $this->build($showtimes, 3, false, maxNodes: 5);

        // Assert
        self::assertGreaterThan(100, \count($unbounded));
        self::assertLessThan(5, \count($bounded));
    }

    public function testTwoChainsShowingTheSameWorkNeverFillOneProgramme(): void
    {
        // Arrange: the same work, at one chain at 14:00 and at another chain, next door, at 17:00.
        $showtimes = [
            $this->inCinemaA('a')->ofFilm('digger-51293')->ofWork('W1')->startingAt('14:00')->build(),
            $this->inCinemaB('b')->ofFilm('other-digger')->ofWork('W1')->startingAt('17:00')->build(),
        ];

        // Act
        $programmes = $this->build($showtimes, 2, false);

        // Assert
        self::assertSame([], $this->ids($programmes));
    }
}

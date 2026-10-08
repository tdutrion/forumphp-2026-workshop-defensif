<?php

namespace App\Tests\Unit\Planner;

use App\Planner\ScheduledShowtimeList;
use App\Tests\Builder\ScreeningBuilder;
use PHPUnit\Framework\TestCase;

final class ScheduledShowtimeListTest extends TestCase
{
    private function list(): ScheduledShowtimeList
    {
        return new ScheduledShowtimeList(
            ScreeningBuilder::aScreening('late')->ofFilm('film-2')->startingAt('18:00')->build(),
            ScreeningBuilder::aScreening('early')->ofFilm('film-1')->startingAt('14:00')->ofWork('work-1')->build(),
        );
    }

    public function testIsEmptyOrNot(): void
    {
        // Assert
        self::assertTrue((new ScheduledShowtimeList())->isEmpty());
        self::assertNull((new ScheduledShowtimeList())->first());
        self::assertNull((new ScheduledShowtimeList())->last());
        self::assertFalse($this->list()->isEmpty());
        self::assertCount(2, $this->list());
    }

    public function testKnowsItsFirstAndItsLastShowtime(): void
    {
        // Act
        $list = $this->list();

        // Assert
        self::assertSame('late', $list->first()?->id);
        self::assertSame('early', $list->last()?->id);
    }

    public function testAddingAShowtimeGivesAnotherListAndLeavesTheFirstOneAlone(): void
    {
        // Arrange
        $list = $this->list();

        // Act
        $longer = $list->with(ScreeningBuilder::aScreening('third')->build());

        // Assert
        self::assertCount(2, $list);
        self::assertCount(3, $longer);
        self::assertSame('third', $longer->last()?->id);
    }

    public function testKnowsWhetherAWorkIsAlreadyThere(): void
    {
        // Act
        $list = $this->list();

        // Assert
        self::assertTrue($list->hasWork('work-1'));
        self::assertTrue($list->hasWork('film-2'), 'the film slug is the work by default in the builder');
        self::assertFalse($list->hasWork('work-9'));
    }

    public function testSortsByStartWithoutChangingTheOriginal(): void
    {
        // Arrange
        $list = $this->list();

        // Act
        $sorted = $list->sortedByStart();

        // Assert
        self::assertSame(['early', 'late'], array_map(static fn ($showtime) => $showtime->id, $sorted->toArray()));
        self::assertSame('late', $list->first()?->id);
    }

    public function testListsItsFilmsAndWorksAndSumsAValue(): void
    {
        // Arrange
        $list = $this->list();

        // Assert
        self::assertSame(['film-2', 'film-1'], $list->filmSlugs());
        self::assertSame(['film-2', 'work-1'], $list->workIds());
        self::assertSame(2, $list->sum(static fn () => 1));
    }

    public function testIsIterable(): void
    {
        // Act
        $ids = [];
        foreach ($this->list() as $showtime) {
            $ids[] = $showtime->id;
        }

        // Assert
        self::assertSame(['late', 'early'], $ids);
    }

    public function testOnlyAcceptsShowtimes(): void
    {
        // Assert
        $this->expectException(\TypeError::class);

        // Act
        $list = new ScheduledShowtimeList(['id' => 'not a showtime']); // @phpstan-ignore argument.type
        self::assertCount(1, $list);
    }
}

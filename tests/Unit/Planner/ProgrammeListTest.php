<?php

declare(strict_types=1);

namespace App\Tests\Unit\Planner;

use App\Planner\Programme;
use App\Planner\ProgrammeList;
use App\Tests\Builder\ProgrammeBuilder;
use PHPUnit\Framework\TestCase;

final class ProgrammeListTest extends TestCase
{
    private function list(): ProgrammeList
    {
        return new ProgrammeList(
            ProgrammeBuilder::aProgramme()->ofFilms('a', 'b')->waiting(30)->build(),
            ProgrammeBuilder::aProgramme()->ofFilms('c', 'd')->waiting(5)->build(),
            ProgrammeBuilder::aProgramme()->ofFilms('e', 'f')->waiting(20)->build(),
        );
    }

    public function testIsEmptyOrNot(): void
    {
        // Assert
        self::assertTrue((new ProgrammeList())->isEmpty());
        self::assertNull((new ProgrammeList())->first());
        self::assertCount(3, $this->list());
    }

    public function testSortsAndTakesWithoutChangingTheOriginal(): void
    {
        // Arrange
        $list = $this->list();

        // Act
        $best = $list->sortedBy(static fn (Programme $a, Programme $b): int => $a->wait <=> $b->wait)->take(2);

        // Assert
        self::assertSame([5, 20], $best->map(static fn (Programme $programme): int => $programme->wait));
        self::assertSame(30, $list->first()?->wait);
        self::assertCount(3, $list);
    }

    public function testFilters(): void
    {
        // Act
        $short = $this->list()->filter(static fn (Programme $programme): bool => $programme->wait < 25);

        // Assert
        self::assertSame([5, 20], $short->map(static fn (Programme $programme): int => $programme->wait));
    }

    public function testAddingAProgrammeGivesAnotherList(): void
    {
        // Arrange
        $list = $this->list();

        // Act
        $longer = $list->with(ProgrammeBuilder::aProgramme()->build());

        // Assert
        self::assertCount(3, $list);
        self::assertCount(4, $longer);
    }
}

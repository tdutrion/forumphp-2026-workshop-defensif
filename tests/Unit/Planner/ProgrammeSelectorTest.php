<?php

declare(strict_types=1);

namespace App\Tests\Unit\Planner;

use App\Planner\Programme;
use App\Planner\ProgrammeList;
use App\Planner\ProgrammeSelector;
use App\Tests\Builder\ProgrammeBuilder;
use PHPUnit\Framework\TestCase;

final class ProgrammeSelectorTest extends TestCase
{
    public function testKeepsTheThreeBestProgrammesWithDifferentFilms(): void
    {
        // Arrange
        $programmes = new ProgrammeList(
            ProgrammeBuilder::aProgramme()->ofFilms('a', 'b')->waiting(30)->build(),
            ProgrammeBuilder::aProgramme()->ofFilms('b', 'a')->waiting(5)->build(),   // same films as the previous one, but better
            ProgrammeBuilder::aProgramme()->ofFilms('a', 'c')->waiting(5)->travelling(2.0)->build(),
            ProgrammeBuilder::aProgramme()->ofFilms('c', 'd')->waiting(40)->build(),
            ProgrammeBuilder::aProgramme()->ofFilms('b', 'c')->waiting(20)->build(),
        );

        // Act
        $selected = (new ProgrammeSelector())->select($programmes);

        // Assert
        self::assertSame(
            [['b', 'a'], ['a', 'c'], ['b', 'c']],
            $selected->map(static fn (Programme $programme): array => $programme->filmSlugs()),
        );
    }

    /**
     * Twelve programmes with different films, waiting 1 to 12 minutes.
     */
    private function twelveProgrammes(): ProgrammeList
    {
        $programmes = [];
        for ($i = 1; $i <= 12; ++$i) {
            $programmes[] = ProgrammeBuilder::aProgramme()->ofFilms('a'.$i, 'b'.$i)->waiting($i)->build();
        }

        return new ProgrammeList(...$programmes);
    }

    /**
     * @return list<int>
     */
    private function waits(ProgrammeList $selected): array
    {
        return $selected->map(static fn (Programme $programme): int => $programme->wait);
    }

    public function testASeedDrawsThreeOfTheTenBestProgrammesBestFirst(): void
    {
        // Arrange
        $selector = new ProgrammeSelector();

        // Act
        $draws = [];
        for ($seed = 1; $seed <= 30; ++$seed) {
            $draws[] = $this->waits($selector->select($this->twelveProgrammes(), 3, $seed));
        }

        // Assert
        self::assertGreaterThan(1, \count(array_unique(array_map('serialize', $draws))), 'different seeds, different programmes');
        foreach ($draws as $waits) {
            $bestFirst = $waits;
            sort($bestFirst);
            self::assertCount(3, $waits);
            self::assertLessThanOrEqual(10, max($waits), 'only among the ten best');
            self::assertSame($bestFirst, $waits);
        }
    }

    public function testTheSameSeedDrawsTheSameProgrammes(): void
    {
        // Arrange
        $selector = new ProgrammeSelector();

        // Act
        $first = $selector->select($this->twelveProgrammes(), 3, 42);
        $second = $selector->select($this->twelveProgrammes(), 3, 42);

        // Assert
        self::assertSame($this->waits($first), $this->waits($second));
    }

    public function testAskingForNoProgrammeIsABugOfTheCaller(): void
    {
        // Assert: the internal invariant is an assertion, active in development and tests only
        $this->expectException(\AssertionError::class);

        // Act
        (new ProgrammeSelector())->select(new ProgrammeList(), 0);
    }
}

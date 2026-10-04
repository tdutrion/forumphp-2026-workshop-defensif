<?php

namespace App\Tests\Unit\Planner;

use App\Planner\ProgrammeSelector;
use App\Tests\Builder\ProgrammeBuilder;
use PHPUnit\Framework\TestCase;

final class ProgrammeSelectorTest extends TestCase
{
    public function testKeepsTheThreeBestProgrammesWithDifferentFilms(): void
    {
        // Arrange
        $programmes = [
            ProgrammeBuilder::aProgramme()->ofFilms('a', 'b')->waiting(30)->build(),
            ProgrammeBuilder::aProgramme()->ofFilms('b', 'a')->waiting(5)->build(),   // same films as the previous one, but better
            ProgrammeBuilder::aProgramme()->ofFilms('a', 'c')->waiting(5)->travelling(2.0)->build(),
            ProgrammeBuilder::aProgramme()->ofFilms('c', 'd')->waiting(40)->build(),
            ProgrammeBuilder::aProgramme()->ofFilms('b', 'c')->waiting(20)->build(),
        ];

        // Act
        $selected = (new ProgrammeSelector())->select($programmes);

        // Assert
        self::assertSame(
            [['b', 'a'], ['a', 'c'], ['b', 'c']],
            array_map(static fn (array $programme) => array_column($programme['showtimes'], 'filmSlug'), $selected),
        );
    }
}

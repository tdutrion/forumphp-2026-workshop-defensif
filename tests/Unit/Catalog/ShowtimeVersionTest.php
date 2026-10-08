<?php

namespace App\Tests\Unit\Catalog;

use App\Catalog\ShowtimeVersion;
use PHPUnit\Framework\TestCase;

final class ShowtimeVersionTest extends TestCase
{
    public function testEveryVersionHasTheLabelOfTheForms(): void
    {
        // Act
        $labels = array_map(static fn (ShowtimeVersion $version) => $version->label(), ShowtimeVersion::cases());

        // Assert
        self::assertSame(['planner.form.version.vf', 'planner.form.version.vost', 'planner.form.version.vo', 'planner.form.version.vfst'], $labels);
    }

    public function testOnlyTheOriginalVersionsIncludeTheFilmsMadeInTheLanguageOfTheCinema(): void
    {
        // Act
        $original = array_filter(ShowtimeVersion::cases(), static fn (ShowtimeVersion $version) => $version->isOriginal());

        // Assert
        self::assertSame([ShowtimeVersion::Vost, ShowtimeVersion::Vo], array_values($original));
    }
}

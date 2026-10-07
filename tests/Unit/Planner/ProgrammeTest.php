<?php

declare(strict_types=1);

namespace App\Tests\Unit\Planner;

use App\Planner\Programme;
use App\Planner\ScheduledShowtimeList;
use App\Tests\Builder\ProgrammeBuilder;
use PHPUnit\Framework\TestCase;

final class ProgrammeTest extends TestCase
{
    public function testListsItsFilmsAndItsWorksInOrder(): void
    {
        // Act
        $programme = ProgrammeBuilder::aProgramme()->ofFilms('b', 'a')->build();

        // Assert
        self::assertSame(['b', 'a'], $programme->filmSlugs());
        self::assertSame(['b', 'a'], $programme->workIds());
    }

    public function testHasAtLeastOneShowtime(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        new Programme(new ScheduledShowtimeList(), wait: 0, distance: 0.0, breakMinutes: 0, travelMinutes: 0);
    }
}

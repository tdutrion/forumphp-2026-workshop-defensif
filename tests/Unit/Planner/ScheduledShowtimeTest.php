<?php

declare(strict_types=1);

namespace App\Tests\Unit\Planner;

use App\Catalog\ShowtimeVersion;
use App\Planner\ScheduledShowtime;
use App\Tests\Builder\ScreeningBuilder;
use PHPUnit\Framework\TestCase;

final class ScheduledShowtimeTest extends TestCase
{
    public function testBuildsFromARowOfTheRepository(): void
    {
        // Arrange
        $row = [
            'id' => 'V1S1', 'filmSlug' => 'cars', 'filmTitle' => 'Cars', 'workId' => 'ABCD', 'cinemaSlug' => 'cinema-pathe-dijon',
            'cinemaName' => 'Pathé Dijon', 'timezone' => 'Europe/Paris', 'latitude' => 47.318031, 'longitude' => 5.029935,
            'startsAt' => '2030-01-10 15:40:00', 'endsAt' => '2030-01-10 17:40:00', 'version' => 'vost', 'bookingUrl' => 'https://s.pathe.fr/x',
        ];

        // Act
        $showtime = ScheduledShowtime::fromRow($row);

        // Assert
        self::assertSame(ShowtimeVersion::Vost, $showtime->version);
        self::assertSame('16:40', $showtime->startTime(), 'stored in UTC, shown in the time zone of the cinema');
        self::assertSame('18:40', $showtime->endTime());
        self::assertSame(47.318031, $showtime->position->latitude);
    }

    public function testAnUnknownVersionIsRefused(): void
    {
        // Assert
        $this->expectException(\ValueError::class);

        // Act
        ScheduledShowtime::fromRow([
            'id' => 'V1S1', 'filmSlug' => 'cars', 'filmTitle' => 'Cars', 'workId' => 'ABCD', 'cinemaSlug' => 'c', 'cinemaName' => 'C',
            'timezone' => 'UTC', 'latitude' => 0.0, 'longitude' => 0.0, 'startsAt' => '2030-01-10 15:40:00', 'endsAt' => '2030-01-10 17:40:00',
            'version' => 'dubbed', 'bookingUrl' => 'https://s.pathe.fr/x',
        ]);
    }

    public function testAWitherReturnsAnotherShowtimeAndLeavesTheFirstOneAlone(): void
    {
        // Arrange
        $first = ScreeningBuilder::aScreening('s1')->build();

        // Act
        $second = $first->withTransition(lateMinutes: 5, breakMinutes: 40, travelMinutes: 12);

        // Assert
        self::assertNotSame($first, $second);
        self::assertSame([0, 0, 0], [$first->lateMinutes, $first->breakMinutes, $first->travelMinutes]);
        self::assertSame([5, 40, 12], [$second->lateMinutes, $second->breakMinutes, $second->travelMinutes]);
        self::assertSame($first->id, $second->id);
    }

    public function testAShowtimeCannotBeChangedFromTheOutside(): void
    {
        // Arrange
        $showtime = ScreeningBuilder::aScreening('s1')->build();

        // Assert
        $this->expectException(\Error::class);

        // Act
        // @phpstan-ignore assign.propertyProtectedSet
        $showtime->lateMinutes = 5;
    }

    public function testIgnoringTheResultOfAWitherIsReported(): void
    {
        // Arrange
        $warnings = [];
        set_error_handler(static function (int $level, string $message) use (&$warnings): bool {
            $warnings[] = $message;

            return true;
        }, \E_USER_WARNING);

        try {
            // Act
            \call_user_func([ScreeningBuilder::aScreening('s1')->build(), 'withTransition'], 1, 2, 3);
        } finally {
            restore_error_handler();
        }

        // Assert
        self::assertCount(1, $warnings);
    }
}

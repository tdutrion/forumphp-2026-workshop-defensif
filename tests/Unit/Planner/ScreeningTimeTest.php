<?php

declare(strict_types=1);

namespace App\Tests\Unit\Planner;

use App\Planner\ScreeningTime;
use PHPUnit\Framework\TestCase;
use Time\Duration;

final class ScreeningTimeTest extends TestCase
{
    public function testIsHeldInUtcWhateverTheTimeZoneItIsBuiltIn(): void
    {
        // Arrange
        $paris = new \DateTimeImmutable('2026-10-08 21:40', new \DateTimeZone('Europe/Paris'));

        // Act
        $time = new ScreeningTime($paris);

        // Assert
        self::assertSame(0, ScreeningTime::compare(ScreeningTime::fromUtc('2026-10-08 19:40'), $time));
    }

    public function testShowsTheLocalTimeOfACinema(): void
    {
        // Arrange
        $time = ScreeningTime::fromUtc('2026-10-08 19:40');

        // Act
        $local = $time->localTime(new \DateTimeZone('Europe/Paris'));

        // Assert
        self::assertSame('2026-10-08T21:40:00+02:00', $local->format(\DATE_ATOM));
    }

    public function testKeepsTheDurationAcrossAClockChange(): void
    {
        // Arrange: in Paris, clocks go back from 03:00 to 02:00 on 2026-10-25.
        $time = new ScreeningTime(new \DateTimeImmutable('2026-10-25 01:30', new \DateTimeZone('Europe/Paris')));

        // Act
        $later = $time->plus(Duration::fromMinutes(120));

        // Assert: two hours later, but only one hour later on the wall clock.
        self::assertSame('02:30', $later->localTime(new \DateTimeZone('Europe/Paris'))->format('H:i'));
    }

    public function testMeasuresTheDurationUntilAnotherInstant(): void
    {
        // Arrange
        $start = ScreeningTime::fromUtc('2026-10-08 14:00');
        $end = ScreeningTime::fromUtc('2026-10-08 16:00');

        // Act
        $forward = $start->until($end);
        $backward = $end->until($start);

        // Assert
        self::assertSame(0, Duration::compare(Duration::fromHours(2), $forward));
        self::assertSame(0, Duration::compare(Duration::fromHours(2)->negate(), $backward));
    }

    public function testComparesTwoInstants(): void
    {
        // Arrange
        $earlier = ScreeningTime::fromUtc('2026-10-08 14:00');
        $later = ScreeningTime::fromUtc('2026-10-08 14:01');

        // Act and assert
        self::assertTrue($later->isAfter($earlier));
        self::assertFalse($earlier->isAfter($later));
        self::assertFalse($earlier->isAfter($earlier));
    }

    public function testRefusesAMalformedInstant(): void
    {
        // Assert
        $this->expectException(\DateMalformedStringException::class);

        // Act
        ScreeningTime::fromUtc('tomorrow evening at the cinema');
    }
}

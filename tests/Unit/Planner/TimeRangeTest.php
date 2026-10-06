<?php

namespace App\Tests\Unit\Planner;

use App\Planner\ScreeningTime;
use App\Planner\TimeRange;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class TimeRangeTest extends TestCase
{
    #[TestWith(['20:00', '19:00'])]
    #[TestWith(['20:00', '20:00'])]
    #[TestWith(['9:00', '10:00'], 'compared as text, "9:00" would come after "10:00"')]
    #[TestWith(['25:99', null])]
    #[TestWith([null, '24:00'])]
    #[TestWith(['', null])]
    public function testRefusesAMalformedOrReversedRange(?string $from, ?string $until): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        new TimeRange($from, $until);
    }

    public function testKeepsTheShowtimesThatStartAndEndWithinTheRange(): void
    {
        // Arrange: in Paris (UTC+1 in January), a showtime 16:30–18:30 local time.
        $paris = new \DateTimeZone('Europe/Paris');
        $start = ScreeningTime::fromUtc('2030-01-10 15:30');
        $end = ScreeningTime::fromUtc('2030-01-10 17:30');

        // Act
        $contains = static fn (TimeRange $range): bool => $range->contains($start, $end, '2030-01-10', $paris);

        // Assert
        self::assertTrue($contains(new TimeRange()));
        self::assertTrue($contains(new TimeRange('16:30', '18:30')));
        self::assertFalse($contains(new TimeRange(from: '16:31')));
        self::assertFalse($contains(new TimeRange(until: '18:29')));
    }

    public function testReadsItsLimitsInLocalTimeOnTheDayTheClocksChange(): void
    {
        // Arrange: on 2030-10-27, Paris goes back from UTC+2 to UTC+1 at 3:00; a showtime 20:00–22:00 local time.
        $paris = new \DateTimeZone('Europe/Paris');
        $start = ScreeningTime::fromUtc('2030-10-27 19:00');
        $end = ScreeningTime::fromUtc('2030-10-27 21:00');

        // Act
        $contains = new TimeRange('20:00', '22:00')->contains($start, $end, '2030-10-27', $paris);

        // Assert
        self::assertTrue($contains);
    }
}

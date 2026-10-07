<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\BookingStatus;
use App\Catalog\Entity\Showtime;
use App\Catalog\ShowtimeVersion;
use App\Tests\Builder\CinemaBuilder;
use App\Tests\Builder\CityBuilder;
use App\Tests\Builder\FilmBuilder;
use App\Tests\Builder\ShowtimeBuilder;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class ShowtimeTest extends TestCase
{
    private function showtime(): Showtime
    {
        $cinema = CinemaBuilder::aCinema()->in(CityBuilder::aCity()->build())->build();

        return ShowtimeBuilder::aShowtime()->of(FilmBuilder::aFilm()->withSlug('f1')->build())->at($cinema)->startingAt('2030-01-10 14:00:00')->build();
    }

    private function utc(string $moment): \DateTimeImmutable
    {
        return new \DateTimeImmutable($moment, new \DateTimeZone('UTC'));
    }

    public function testIsBookableWhileTheBookingIsOpen(): void
    {
        // Arrange: bookable until 13:40 UTC (20 minutes after the start)
        $showtime = $this->showtime();

        // Act
        $before = $showtime->isBookableAt($this->utc('2030-01-10 13:00:00'));
        $after = $showtime->isBookableAt($this->utc('2030-01-10 13:40:01'));

        // Assert
        self::assertTrue($before);
        self::assertFalse($after, 'the booking is closed');
    }

    public function testIsNotBookableWhenTheStatusSaysSo(): void
    {
        // Arrange
        $showtime = $this->showtime();
        $showtime->updateBooking(BookingStatus::SoldOut, $showtime->bookingUrl, $showtime->reservableUntil);

        // Act
        $bookable = $showtime->isBookableAt($this->utc('2030-01-10 13:00:00'));

        // Assert
        self::assertFalse($bookable);
    }

    public function testIsBookableUntilTheStartWhenPatheGivesNoLimit(): void
    {
        // Arrange
        $showtime = $this->showtime();
        $showtime->updateBooking(BookingStatus::Available, $showtime->bookingUrl, null);

        // Act
        $bookable = $showtime->isBookableAt($this->utc('2031-01-01 00:00:00'));

        // Assert
        self::assertTrue($bookable);
    }

    public function testCannotEndBeforeItStarts(): void
    {
        // Arrange
        $showtime = $this->showtime();

        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        $showtime->reschedule($this->utc('2030-01-10 15:00:00'), $this->utc('2030-01-10 14:00:00'), $this->utc('2030-01-10 00:00:00'));
    }

    public function testRescheduleMovesTheWholeSession(): void
    {
        // Arrange
        $showtime = $this->showtime();

        // Act
        $showtime->reschedule($this->utc('2030-01-11 14:00:00'), $this->utc('2030-01-11 16:00:00'), $this->utc('2030-01-11 00:00:00'));

        // Assert
        self::assertSame('2030-01-11 14:00:00', $showtime->startsAt->format('Y-m-d H:i:s'));
        self::assertSame('2030-01-11', $showtime->localDate->format('Y-m-d'));
    }

    #[TestWith(['244', 244])]
    #[TestWith([244, 244])]
    #[TestWith([null, null])]
    #[TestWith(['', null])]
    #[TestWith(['n/a', null])]
    public function testTheCapacityIsANumberOfSeatsOrUnknown(int|string|null $given, ?int $expected): void
    {
        // Arrange
        $showtime = $this->showtime();

        // Act
        $showtime->describeScreening(ShowtimeVersion::Vf, 'Salle 1', $given);

        // Assert
        self::assertSame($expected, $showtime->capacity);
    }

    public function testAHallCannotHaveNegativeSeats(): void
    {
        // Arrange
        $showtime = $this->showtime();

        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        $showtime->describeScreening(ShowtimeVersion::Vf, null, -1);
    }

    public function testTheStateCannotBeChangedFromTheOutside(): void
    {
        // Arrange
        $showtime = $this->showtime();

        // Assert
        $this->expectException(\Error::class);

        // Act
        // @phpstan-ignore assign.propertyPrivateSet
        $showtime->status = BookingStatus::SoldOut;
    }
}

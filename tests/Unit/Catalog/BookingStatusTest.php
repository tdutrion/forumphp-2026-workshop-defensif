<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\BookingStatus;
use PHPUnit\Framework\TestCase;

final class BookingStatusTest extends TestCase
{
    public function testOnlyAnAvailableShowtimeIsBookable(): void
    {
        // Act
        $bookable = array_filter(BookingStatus::cases(), static fn (BookingStatus $status) => $status->isBookable());

        // Assert
        self::assertSame([BookingStatus::Available], array_values($bookable));
    }
}

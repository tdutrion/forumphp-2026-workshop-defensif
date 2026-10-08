<?php

declare(strict_types=1);

namespace App\Catalog;

/**
 * Booking status of a showtime. Pathé documents only 'available' as observed; 'soldout' was found in a
 * synchronized catalog, 'cancelled' is still a guess. An unknown value is refused at the boundary
 * (CatalogSynchronizer).
 */
enum BookingStatus: string
{
    case Available = 'available';
    case SoldOut = 'soldout';
    case Cancelled = 'cancelled';

    public function isBookable(): bool
    {
        return match ($this) {
            self::Available => true,
            self::SoldOut, self::Cancelled => false,
        };
    }
}

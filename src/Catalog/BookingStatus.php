<?php

namespace App\Catalog;

/**
 * Booking status of a showtime. Pathé documents only 'available' as observed: the other values are
 * expected but unconfirmed, and an unknown one is refused at the boundary (CatalogSynchronizer).
 */
enum BookingStatus: string
{
    case Available = 'available';
    case SoldOut = 'sold_out';
    case Cancelled = 'cancelled';

    public function isBookable(): bool
    {
        return match ($this) {
            self::Available => true,
            self::SoldOut, self::Cancelled => false,
        };
    }
}

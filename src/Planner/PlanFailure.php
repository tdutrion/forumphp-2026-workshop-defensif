<?php

namespace App\Planner;

/**
 * Why a plan offers no programme at all.
 */
enum PlanFailure: string
{
    /** The city is unknown (or has no open cinema left), or the position could not be read. */
    case UnknownLocation = 'unknown_location';
    /** No showtime matches the place and the date (or every film on offer has been seen). */
    case NoShowtime = 'no_showtime';
    /** Showtimes exist, but none of them chains into a programme with these criteria. */
    case NoProgramme = 'no_programme';

    /**
     * Translation key of the message shown to the user.
     */
    public function messageKey(): string
    {
        return match ($this) {
            self::UnknownLocation => 'planner.result.unknown_place',
            self::NoShowtime => 'planner.result.no_showtime',
            self::NoProgramme => 'planner.result.no_programme',
        };
    }
}

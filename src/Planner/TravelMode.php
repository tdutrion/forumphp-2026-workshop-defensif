<?php

namespace App\Planner;

/**
 * How the user gets from one cinema to the next. No routing service: the workshop must work
 * without a network, so the travel time is a straight-line speed plus fixed minutes.
 */
enum TravelMode: string
{
    case Walking = 'walking';
    case Cycling = 'cycling';
    case Transit = 'transit';
    case Car = 'car';

    /**
     * Straight-line speed, in km/h.
     */
    public function speedKmh(): int
    {
        return match ($this) {
            self::Walking => 5,
            self::Cycling => 15,
            self::Transit => 20,
            self::Car => 30,
        };
    }

    /**
     * Minutes added to every trip: waiting for public transport, parking.
     */
    public function fixedMinutes(): int
    {
        return match ($this) {
            self::Walking, self::Cycling => 0,
            self::Transit => 10,
            self::Car => 15,
        };
    }
}

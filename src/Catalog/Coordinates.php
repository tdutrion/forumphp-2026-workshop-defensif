<?php

namespace App\Catalog;

/**
 * A position on Earth, in decimal degrees.
 */
final readonly class Coordinates
{
    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {
        if ($latitude < -90 || $latitude > 90) {
            throw new \InvalidArgumentException(\sprintf('Latitude %F is out of [-90, 90].', $latitude));
        }
        if ($longitude < -180 || $longitude > 180) {
            throw new \InvalidArgumentException(\sprintf('Longitude %F is out of [-180, 180].', $longitude));
        }
    }
}

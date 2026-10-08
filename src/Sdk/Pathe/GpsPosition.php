<?php

declare(strict_types=1);

namespace App\Sdk\Pathe;

/**
 * Position of a cinema, in decimal degrees.
 */
final readonly class GpsPosition
{
    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {
        if ($latitude < -90 || $latitude > 90) {
            throw new \InvalidArgumentException(sprintf('Latitude %F is out of [-90, 90].', $latitude));
        }
        if ($longitude < -180 || $longitude > 180) {
            throw new \InvalidArgumentException(sprintf('Longitude %F is out of [-180, 180].', $longitude));
        }
    }

    /**
     * @param array<string, mixed> $raw a Pathé "gpsPosition": the latitude in "x" and the longitude in "y"
     *
     * @return self|null null when Pathé gives no position
     */
    public static function fromApi(array $raw): ?self
    {
        if (!isset($raw['x'], $raw['y'])) {
            return null;
        }

        return new self(latitude: (float) $raw['x'], longitude: (float) $raw['y']);
    }
}

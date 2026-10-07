<?php

namespace App\Planner;

use App\Catalog\Repository\CinemaRepository;

/**
 * Turns the chosen place (city or browser position) into coordinates.
 */
class LocationResolver
{
    public function __construct(private CinemaRepository $cinemaRepository)
    {
    }

    /**
     * The GPS position that Pathé gives for a city is not reliable (Dijon is placed 45 km
     * from its center): we take the barycenter of the city's cinemas.
     *
     * @return array|false ['latitude' => ..., 'longitude' => ...] or false if the city has no located cinema
     */
    public function fromCity(string $citySlug): array|false
    {
        $cinemas = array_filter(
            $this->cinemaRepository->findByCity($citySlug),
            static fn (array $cinema) => null !== $cinema['latitude'] && null !== $cinema['longitude'],
        );
        if ([] === $cinemas) {
            return false;
        }

        return [
            'latitude' => array_sum(array_column($cinemas, 'latitude')) / \count($cinemas),
            'longitude' => array_sum(array_column($cinemas, 'longitude')) / \count($cinemas),
        ];
    }

    /**
     * @param string $json position sent by the browser, e.g. {"lat": 47.32, "lng": 5.04}
     *
     * @return array|false ['latitude' => ..., 'longitude' => ...] or false if the position is invalid
     */
    public function fromPosition(string $json): array|false
    {
        // What the browser sends is untrusted: a position that is not JSON is an ordinary answer, not an exception.
        if (!json_validate($json)) {
            return false;
        }
        $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        if (!is_array($data) || !isset($data['lat'], $data['lng']) || !is_numeric($data['lat']) || !is_numeric($data['lng'])) {
            return false;
        }

        $latitude = (float) $data['lat'];
        $longitude = (float) $data['lng'];
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return false;
        }

        return ['latitude' => $latitude, 'longitude' => $longitude];
    }
}

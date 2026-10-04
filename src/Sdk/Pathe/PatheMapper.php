<?php

namespace App\Sdk\Pathe;

/**
 * Turns raw Pathé responses into arrays ready to save.
 */
class PatheMapper
{
    public function mapCity(array $raw): array
    {
        return [
            'slug' => $raw['slug'],
            'name' => $raw['name'],
        ];
    }

    public function mapCinema(array $raw): array
    {
        $theater = $raw['theaters'][0] ?? [];

        return [
            'slug' => $raw['slug'],
            'name' => $raw['name'],
            'citySlug' => $raw['citySlug'],
            'open' => true === ($raw['status'] ?? false),
            'address' => $theater['addressLine1'] ?? null,
            'postalCode' => $theater['addressZip'] ?? null,
            'town' => $theater['addressCity'] ?? null,
            // Pathé calls the latitude "x" and the longitude "y".
            'latitude' => isset($theater['gpsPosition']['x']) ? (float) $theater['gpsPosition']['x'] : null,
            'longitude' => isset($theater['gpsPosition']['y']) ? (float) $theater['gpsPosition']['y'] : null,
            'hallCount' => $raw['hallCount'] ?? null,
        ];
    }

    /**
     * @return array|false the film's fields, or false if it is an event (no usable showtimes)
     */
    public function mapFilm(array $raw): array|false
    {
        if (true !== ($raw['isMovie'] ?? false)) {
            return false;
        }

        // contentRating is an object when the film is rated, and [] otherwise.
        $contentRating = null;
        if (!empty($raw['contentRating']) && isset($raw['contentRating']['description'])) {
            $contentRating = $raw['contentRating']['description'];
        }

        return [
            'slug' => $raw['slug'],
            'title' => $raw['title'],
            'duration' => $raw['duration'] ?? null,
            'releaseDate' => $raw['releaseAt']['FR_FR'] ?? null,
            'genres' => $raw['genres'] ?? [],
            'posterUrl' => $raw['posterPath']['md'] ?? null,
            'contentRating' => $contentRating,
        ];
    }

    /**
     * Pathé sends naive local times ('2026-10-04 21:40:00'): they are converted to UTC here,
     * with the time zone of the chain, and the local calendar day is kept for day-based searches.
     *
     * @param array  $raw      response from /show/{slug}/showtimes/{cinema}: [] or showtimes indexed by date
     * @param string $timezone IANA time zone of the chain, e.g. 'Europe/Paris'
     *
     * @return array list of showtimes, instants in UTC ('Y-m-d H:i:s')
     */
    public function mapShowtimes(array $raw, string $timezone): array
    {
        $local = new \DateTimeZone($timezone);
        $utc = new \DateTimeZone('UTC');
        $showtimes = [];
        foreach ($raw as $items) {
            foreach ($items as $item) {
                // The booking link ends up in an href: only Pathé HTTPS links are accepted.
                if (1 !== preg_match('#^https://s\.pathe\.fr/.*/(V\d+S\d+)/#', $item['refCmd'] ?? '', $matches)) {
                    continue;
                }

                $reservableUntil = null;
                if (isset($item['reservabilityEnd'])) {
                    // This field carries its own offset, unlike "time" and "endTime".
                    $reservableUntil = (new \DateTimeImmutable($item['reservabilityEnd']))->setTimezone($utc)->format('Y-m-d H:i:s');
                }

                $start = new \DateTimeImmutable($item['time'], $local);
                $showtimes[] = [
                    'id' => $matches[1],
                    'startsAt' => $start->setTimezone($utc)->format('Y-m-d H:i:s'),
                    'endsAt' => (new \DateTimeImmutable($item['endTime'], $local))->setTimezone($utc)->format('Y-m-d H:i:s'),
                    'localDate' => $start->format('Y-m-d'),
                    'version' => $item['version'],
                    'status' => $item['status'],
                    'bookingUrl' => $item['refCmd'],
                    'reservableUntil' => $reservableUntil,
                    'auditorium' => $item['auditoriumName'] ?? null,
                    'capacity' => $item['auditoriumCapacity'] ?? null,
                ];
            }
        }

        return $showtimes;
    }

    /**
     * Slugs of the films scheduled on at least one day between $from and $to (dates 'Y-m-d', inclusive).
     */
    public function showSlugsPlayingBetween(array $programme, string $from, string $to): array
    {
        $slugs = [];
        foreach ($programme['shows'] ?? [] as $slug => $show) {
            foreach (array_keys($show['days'] ?? []) as $day) {
                if ($day >= $from && $day <= $to) {
                    $slugs[] = $slug;
                    break;
                }
            }
        }

        return $slugs;
    }
}

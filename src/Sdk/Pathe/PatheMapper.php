<?php

namespace App\Sdk\Pathe;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Turns raw Pathé responses into arrays ready to save.
 */
class PatheMapper
{
    public function __construct(private LoggerInterface $logger = new NullLogger())
    {
    }

    public function mapCity(array $raw): array
    {
        return [
            'slug' => $raw['slug'],
            'name' => $raw['name'],
        ];
    }

    /**
     * @return array the cinema's fields, its 'position' a GpsPosition or null
     */
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
            'position' => GpsPosition::fromApi($theater['gpsPosition'] ?? []),
            'hallCount' => $raw['hallCount'] ?? null,
        ];
    }

    /**
     * Languages of the countries Pathé writes in "nationality" (in French), first country first.
     */
    private const LANGUAGE_BY_NATIONALITY = [
        'France' => 'fr',
        'Etats-Unis' => 'en',
        'Royaume-Uni' => 'en',
        'Irlande' => 'en',
        'Australie' => 'en',
        'Nouvelle-Zélande' => 'en',
        'Espagne' => 'es',
        'Italie' => 'it',
        'Allemagne' => 'de',
        'Japon' => 'ja',
        'Corée du Sud' => 'ko',
    ];

    /**
     * Original language of a film, guessed from the first country of its nationality
     * ("France, Belgique" gives 'fr'). Null when unknown or ambiguous (Belgique, Canada, Suisse...).
     *
     * @param array $rawShow a film page (/show/{slug})
     */
    public function mapOriginalLanguage(array $rawShow): ?string
    {
        $nationality = $rawShow['nationality'] ?? null;
        if (!is_string($nationality) || '' === trim($nationality)) {
            return null;
        }
        $firstCountry = trim(explode(',', $nationality)[0]);

        return self::LANGUAGE_BY_NATIONALITY[$firstCountry] ?? null;
    }

    /**
     * Synopsis of a film as plain text: Pathé may send markup, and the text ends up in the pages.
     *
     * @param array $rawShow a film page (/show/{slug})
     */
    public function mapSynopsis(array $rawShow): ?string
    {
        $synopsis = $rawShow['synopsis'] ?? null;
        if (!is_string($synopsis)) {
            return null;
        }
        $text = trim(html_entity_decode(strip_tags($synopsis), \ENT_QUOTES | \ENT_HTML5, 'UTF-8'));

        return '' === $text ? null : $text;
    }

    /**
     * What identifies the film as a work, from its film page: original title, release year and
     * directors (Pathé gives them as one comma-separated string).
     *
     * @param array $rawShow a film page (/show/{slug})
     *
     * @return array ['originalTitle' => ?string, 'year' => ?int, 'directors' => list of names]
     */
    public function mapFilmDetails(array $rawShow): array
    {
        $title = $rawShow['originalTitle'] ?? $rawShow['title'] ?? null;
        $date = $rawShow['releaseAt']['FR_FR'] ?? null;
        $directors = is_string($rawShow['directors'] ?? null) ? $rawShow['directors'] : '';

        return [
            'originalTitle' => is_string($title) && '' !== trim($title) ? trim($title) : null,
            'year' => is_string($date) && 1 === preg_match('/^(\d{4})-/', $date, $matches) ? (int) $matches[1] : null,
            'directors' => array_values(array_filter(array_map('trim', explode(',', $directors)))),
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

        // Only HTTPS images: a poster link is shown as is in the pages.
        $posterUrl = $raw['posterPath']['md'] ?? null;
        if (!is_string($posterUrl) || !str_starts_with($posterUrl, 'https://')) {
            $posterUrl = null;
        }

        return [
            'slug' => $raw['slug'],
            'title' => $raw['title'],
            'duration' => $raw['duration'] ?? null,
            'releaseDate' => $raw['releaseAt']['FR_FR'] ?? null,
            'genres' => $raw['genres'] ?? [],
            'posterUrl' => $posterUrl,
            'contentRating' => $contentRating,
        ];
    }

    /**
     * Pathé sends naive local times ('2026-10-04 21:40:00'): they are converted to UTC here,
     * with the time zone of the chain, and the local calendar day is kept for day-based searches.
     * A showtime with a malformed date is skipped (and logged): the other ones are still mapped.
     *
     * @param \DateTimeZone $timezone time zone of the chain, e.g. Europe/Paris
     *
     * @return array list of showtimes, instants in UTC ('Y-m-d H:i:s'), 'version' and 'status' as Pathé sends them
     */
    public function mapShowtimes(PatheShowtimes $showtimes, \DateTimeZone $timezone): array
    {
        $utc = new \DateTimeZone('UTC');
        $mapped = [];
        foreach ($showtimes->items as $item) {
            // The booking link ends up in an href: only Pathé HTTPS links are accepted.
            if (1 !== preg_match('#^https://s\.pathe\.fr/.*/(V\d+S\d+)/#', $item['refCmd'] ?? '', $matches)) {
                continue;
            }

            try {
                $reservableUntil = null;
                if (isset($item['reservabilityEnd'])) {
                    // This field carries its own offset, unlike "time" and "endTime".
                    $reservableUntil = (new \DateTimeImmutable($item['reservabilityEnd']))->setTimezone($utc)->format('Y-m-d H:i:s');
                }
                $start = new \DateTimeImmutable($item['time'], $timezone);
                $end = new \DateTimeImmutable($item['endTime'], $timezone);
            } catch (\DateMalformedStringException $e) {
                $this->logger->warning('Malformed Pathé date, showtime skipped', ['showtime' => $matches[1], 'error' => $e->getMessage()]);
                continue;
            }

            $mapped[] = [
                'id' => $matches[1],
                'startsAt' => $start->setTimezone($utc)->format('Y-m-d H:i:s'),
                'endsAt' => $end->setTimezone($utc)->format('Y-m-d H:i:s'),
                'localDate' => $start->format('Y-m-d'),
                'version' => $item['version'],
                'status' => $item['status'],
                'bookingUrl' => $item['refCmd'],
                'reservableUntil' => $reservableUntil,
                'auditorium' => $item['auditoriumName'] ?? null,
                'capacity' => $item['auditoriumCapacity'] ?? null,
            ];
        }

        return $mapped;
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

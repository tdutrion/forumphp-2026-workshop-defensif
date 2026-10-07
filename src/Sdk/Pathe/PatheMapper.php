<?php

declare(strict_types=1);

namespace App\Sdk\Pathe;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Uri\Rfc3986\Uri;

/**
 * Turns raw Pathé responses into arrays ready to save.
 */
class PatheMapper
{
    public function __construct(private LoggerInterface $logger = new NullLogger())
    {
    }

    /**
     * @param array<string, mixed> $raw
     *
     * @return array{slug: string, name: string}
     */
    public function mapCity(array $raw): array
    {
        return [
            'slug' => $raw['slug'],
            'name' => $raw['name'],
        ];
    }

    /**
     * @param array<string, mixed> $raw
     *
     * @return array{slug: string, name: string, citySlug: string, open: bool, address: ?string, postalCode: ?string, town: ?string, position: ?GpsPosition, hallCount: ?int}
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
    private const array LANGUAGE_BY_NATIONALITY = [
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
     * @param array<string, mixed> $rawShow a film page (/show/{slug})
     */
    public function mapOriginalLanguage(array $rawShow): ?string
    {
        $nationality = $rawShow['nationality'] ?? null;
        if (!is_string($nationality) || '' === trim($nationality)) {
            return null;
        }
        $firstCountry = $nationality
            |> (static fn (string $countries): array => explode(',', $countries))
            |> array_first(...)
            |> trim(...);

        return self::LANGUAGE_BY_NATIONALITY[$firstCountry] ?? null;
    }

    /**
     * Synopsis of a film as plain text: Pathé may send markup, and the text ends up in the pages.
     *
     * @param array<string, mixed> $rawShow a film page (/show/{slug})
     */
    public function mapSynopsis(array $rawShow): ?string
    {
        $synopsis = $rawShow['synopsis'] ?? null;
        if (!is_string($synopsis)) {
            return null;
        }
        $text = $synopsis
            |> strip_tags(...)
            |> (static fn (string $html): string => html_entity_decode($html, \ENT_QUOTES | \ENT_HTML5, 'UTF-8'))
            |> trim(...);

        return '' === $text ? null : $text;
    }

    /**
     * What identifies the film as a work, from its film page: original title, release year and
     * directors (Pathé gives them as one comma-separated string).
     *
     * @param array<string, mixed> $rawShow a film page (/show/{slug})
     *
     * @return array{originalTitle: ?string, year: ?int, directors: list<string>}
     */
    public function mapFilmDetails(array $rawShow): array
    {
        $title = $rawShow['originalTitle'] ?? $rawShow['title'] ?? null;
        $date = $rawShow['releaseAt']['FR_FR'] ?? null;
        $directors = is_string($rawShow['directors'] ?? null) ? $rawShow['directors'] : '';

        return [
            'originalTitle' => is_string($title) && '' !== trim($title) ? trim($title) : null,
            'year' => is_string($date) && 1 === preg_match('/^(\d{4})-/', $date, $matches) ? (int) $matches[1] : null,
            'directors' => $directors
                |> (static fn (string $names): array => explode(',', $names))
                |> (static fn (array $names): array => array_map(trim(...), $names))
                |> array_filter(...)
                |> array_values(...),
        ];
    }

    /**
     * @param array<string, mixed> $raw
     *
     * @return array{slug: string, title: string, duration: ?int, releaseDate: ?string, genres: list<string>, posterUrl: ?string, contentRating: ?string}|false
     *                                                                                                                                                          the film's fields, or false if it is an event (no usable showtimes)
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
        if (!is_string($posterUrl) || !$this->isHttpsUrl($posterUrl)) {
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
     * @return list<array{id: string, startsAt: string, endsAt: string, localDate: string, version: string, status: string, bookingUrl: string, reservableUntil: ?string, auditorium: ?string, capacity: int|string|null}>
     *                                                                                                                                                                                                                     list of showtimes, instants in UTC ('Y-m-d H:i:s'), 'version' and 'status' as Pathé sends them
     */
    public function mapShowtimes(PatheShowtimes $showtimes, \DateTimeZone $timezone): array
    {
        $utc = new \DateTimeZone('UTC');
        $mapped = [];
        foreach ($showtimes->items as $item) {
            // The booking link ends up in an href: only Pathé HTTPS links are accepted.
            $showtimeId = $this->showtimeIdOfBookingLink($item['refCmd'] ?? null);
            if (null === $showtimeId) {
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
                $this->logger->warning('Malformed Pathé date, showtime skipped', ['showtime' => $showtimeId, 'error' => $e->getMessage()]);
                continue;
            }

            $mapped[] = [
                'id' => $showtimeId,
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
     *
     * @param array<string, mixed> $programme the programme of a cinema
     *
     * @return list<string>
     */
    public function showSlugsPlayingBetween(array $programme, string $from, string $to): array
    {
        $slugs = [];
        foreach ($programme['shows'] ?? [] as $slug => $show) {
            foreach (array_keys($show['days'] ?? []) as $day) {
                if ($day >= $from && $day <= $to) {
                    // A key that looks like a number is an int in a PHP array.
                    $slugs[] = (string) $slug;
                    break;
                }
            }
        }

        return $slugs;
    }

    /**
     * The showtime id (e.g. "V3345S85470") of a booking link: a segment of the path of an https link of
     * s.pathe.fr that is followed by another one.
     */
    private function showtimeIdOfBookingLink(mixed $link): ?string
    {
        if (!\is_string($link)) {
            return null;
        }
        $uri = Uri::parse($link);
        if (null === $uri || 'https' !== $uri->getScheme() || 's.pathe.fr' !== $uri->getHost()) {
            return null;
        }

        $segments = explode('/', ltrim($uri->getPath(), '/'));

        return array_find(\array_slice($segments, 0, -1), static fn (string $segment): bool => 1 === preg_match('/^V\d+S\d+$/', $segment));
    }

    private function isHttpsUrl(string $link): bool
    {
        $uri = Uri::parse($link);

        return 'https' === $uri?->getScheme() && !\in_array($uri->getHost(), [null, ''], true);
    }
}

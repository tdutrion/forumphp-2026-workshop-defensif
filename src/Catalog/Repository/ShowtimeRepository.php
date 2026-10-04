<?php

namespace App\Catalog\Repository;

use App\Catalog\Entity\Showtime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Showtime>
 */
class ShowtimeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Showtime::class);
    }

    /**
     * Local days (format 'Y-m-d', in each cinema's time zone) that have at least one showtime, from $from inclusive.
     */
    public function findAvailableDates(string $from): array
    {
        return $this->getEntityManager()->getConnection()->fetchFirstColumn(
            'SELECT DISTINCT local_date FROM showtime WHERE local_date >= :from ORDER BY local_date',
            ['from' => $from],
        );
    }

    /**
     * Showtimes of one day in the given cinemas, bookable at instant $now, excluding the excluded films.
     *
     * @param string      $date              local day of the cinemas, format 'Y-m-d'
     * @param array       $cinemaSlugs       slugs of the cinemas to keep
     * @param array       $excludedFilmSlugs slugs of the films to exclude (already seen)
     * @param string|null $version           'vf', 'vost', 'vo', 'vfst' or null for any
     * @param string      $now               current instant in UTC, format 'Y-m-d H:i:s'
     *
     * @return array rows ['id', 'filmSlug', 'filmTitle', 'duration', 'cinemaSlug', 'cinemaName', 'timezone',
     *               'latitude', 'longitude', 'startsAt', 'endsAt', 'version', 'bookingUrl'] (instants in UTC)
     */
    public function findCandidates(string $date, array $cinemaSlugs, array $excludedFilmSlugs, ?string $version, string $now): array
    {
        if ([] === $cinemaSlugs) {
            return [];
        }

        $sql = 'SELECT s.id, s.film_slug AS filmSlug, f.title AS filmTitle, f.duration, s.cinema_slug AS cinemaSlug,
                       c.name AS cinemaName, c.timezone, c.latitude, c.longitude, s.starts_at AS startsAt, s.ends_at AS endsAt,
                       s.version, s.booking_url AS bookingUrl
                FROM showtime s
                INNER JOIN film f ON f.slug = s.film_slug
                INNER JOIN cinema c ON c.slug = s.cinema_slug
                WHERE s.local_date = :date
                  AND s.cinema_slug IN (:cinemas)
                  AND s.status = :status
                  AND (s.reservable_until IS NULL OR s.reservable_until > :now)';
        $params = [
            'date' => $date,
            'cinemas' => $cinemaSlugs,
            'status' => 'available',
            'now' => $now,
        ];
        $types = ['cinemas' => ArrayParameterType::STRING];

        if ([] !== $excludedFilmSlugs) {
            $sql .= ' AND s.film_slug NOT IN (:excluded)';
            $params['excluded'] = $excludedFilmSlugs;
            $types['excluded'] = ArrayParameterType::STRING;
        }
        if (null !== $version) {
            $sql .= ' AND s.version = :version';
            $params['version'] = $version;
        }

        return $this->getEntityManager()->getConnection()->fetchAllAssociative($sql.' ORDER BY s.starts_at, s.id', $params, $types);
    }

    /**
     * Deletes the showtimes of the given cinemas whose local day is between $from and $to (inclusive),
     * except those whose identifier is kept.
     *
     * @return int number of showtimes deleted
     */
    public function deleteForCinemasBetween(array $cinemaSlugs, string $from, string $to, array $keepIds): int
    {
        if ([] === $cinemaSlugs) {
            return 0;
        }

        $sql = 'DELETE FROM showtime WHERE cinema_slug IN (:cinemas) AND local_date BETWEEN :from AND :to';
        $params = ['cinemas' => $cinemaSlugs, 'from' => $from, 'to' => $to];
        $types = ['cinemas' => ArrayParameterType::STRING];
        if ([] !== $keepIds) {
            $sql .= ' AND id NOT IN (:keep)';
            $params['keep'] = $keepIds;
            $types['keep'] = ArrayParameterType::STRING;
        }

        return (int) $this->getEntityManager()->getConnection()->executeStatement($sql, $params, $types);
    }
}

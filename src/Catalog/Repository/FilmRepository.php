<?php

namespace App\Catalog\Repository;

use App\Catalog\Entity\Film;
use App\Catalog\FilmCatalogQuery;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Film>
 */
class FilmRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Film::class);
    }

    /**
     * @return array|null ['slug', 'title', 'duration', 'releaseDate', 'genres', 'posterUrl', 'contentRating', 'synopsis', 'workId', 'wikidataId', 'imdbId', 'tmdbId'] or null
     */
    public function findBySlug(string $slug): ?array
    {
        $rows = $this->findBySlugs([$slug]);

        return $rows[0] ?? null;
    }

    /**
     * @param array  $slugs     list of slugs
     * @param string $direction 'ASC' or 'DESC' (sorted by title)
     *
     * @return array list of films (same shape as findBySlug)
     */
    public function findBySlugs(array $slugs, string $direction = 'ASC'): array
    {
        if ([] === $slugs) {
            return [];
        }
        if ('ASC' !== $direction && 'DESC' !== $direction) {
            throw new \InvalidArgumentException('Invalid sort direction: '.$direction);
        }

        $rows = $this->createQueryBuilder('f')
            ->select('f.slug', 'f.title', 'f.duration', 'f.releaseDate', 'f.genres', 'f.posterUrl', 'f.contentRating', 'f.synopsis',
                'w.id AS workId', 'w.wikidataId', 'w.imdbId', 'w.tmdbId')
            ->join('f.work', 'w')
            ->where('f.slug IN (:slugs)')
            ->setParameter('slugs', $slugs)
            ->orderBy('f.title', $direction)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => ['workId' => $row['workId']->toRfc4122()] + $row, $rows);
    }

    /**
     * Films that can still be booked (open cinemas), with their number of showtimes and cinemas.
     *
     * @param array  $excludedSlugs films left out (already seen, not for the user)
     * @param string $now           UTC instant ('Y-m-d H:i:s')
     *
     * @return array rows ['slug', 'title', 'duration', 'releaseDate', 'genres' (JSON), 'posterUrl', 'showtimes', 'cinemas']
     */
    public function findShowing(FilmCatalogQuery $query, array $excludedSlugs, string $now, int $offset, int $limit): array
    {
        [$where, $params, $types] = $this->showingConditions($query, $excludedSlugs, $now);
        $order = match ($query->sort) {
            'showtimes' => 'showtimes DESC, f.title',
            'release' => 'f.release_date IS NULL, f.release_date DESC, f.title',
            'duration' => 'f.duration IS NULL, f.duration, f.title',
            default => 'f.title',
        };

        return $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT f.slug, f.title, f.duration, f.release_date AS releaseDate, f.genres, f.poster_url AS posterUrl,
                    COUNT(s.id) AS showtimes, COUNT(DISTINCT s.cinema_slug) AS cinemas
             FROM film f
             INNER JOIN showtime s ON s.film_slug = f.slug
             INNER JOIN cinema c ON c.slug = s.cinema_slug
             WHERE '.$where.'
             GROUP BY f.slug, f.title, f.duration, f.release_date, f.genres, f.poster_url
             ORDER BY '.$order.'
             LIMIT '.$limit.' OFFSET '.$offset,
            $params,
            $types,
        );
    }

    public function countShowing(FilmCatalogQuery $query, array $excludedSlugs, string $now): int
    {
        [$where, $params, $types] = $this->showingConditions($query, $excludedSlugs, $now);

        return (int) $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT COUNT(DISTINCT f.slug)
             FROM film f
             INNER JOIN showtime s ON s.film_slug = f.slug
             INNER JOIN cinema c ON c.slug = s.cinema_slug
             WHERE '.$where,
            $params,
            $types,
        );
    }

    /**
     * @return array genres of the films that can still be booked, sorted
     */
    public function findShowingGenres(string $now): array
    {
        $genres = [];
        $rows = $this->getEntityManager()->getConnection()->fetchFirstColumn(
            'SELECT DISTINCT f.genres FROM film f
             WHERE EXISTS (SELECT 1 FROM showtime s INNER JOIN cinema c ON c.slug = s.cinema_slug
                           WHERE s.film_slug = f.slug AND c.open = 1 AND s.status = :status
                             AND (s.reservable_until IS NULL OR s.reservable_until > :now))',
            ['status' => 'available', 'now' => $now],
        );
        foreach ($rows as $json) {
            foreach (json_decode((string) $json, true) ?: [] as $genre) {
                $genres[$genre] = true;
            }
        }
        $genres = array_keys($genres);
        sort($genres);

        return $genres;
    }

    /**
     * Cinema weeks (Wednesday to Tuesday, local days) holding showtimes that can still be booked.
     *
     * @return list<string> the Wednesday starting each week (Y-m-d), in order
     */
    public function findShowingWeeks(string $now): array
    {
        // WEEKDAY(): Monday = 0, so (WEEKDAY + 5) % 7 is the number of days since the last Wednesday.
        return $this->getEntityManager()->getConnection()->fetchFirstColumn(
            'SELECT DISTINCT DATE_FORMAT(DATE_SUB(s.local_date, INTERVAL (WEEKDAY(s.local_date) + 5) % 7 DAY), \'%Y-%m-%d\') AS week
             FROM showtime s INNER JOIN cinema c ON c.slug = s.cinema_slug
             WHERE c.open = 1 AND s.status = :status AND (s.reservable_until IS NULL OR s.reservable_until > :now)
             ORDER BY week',
            ['status' => 'available', 'now' => $now],
        );
    }

    /**
     * @return array [SQL conditions, parameters, parameter types]
     */
    private function showingConditions(FilmCatalogQuery $query, array $excludedSlugs, string $now): array
    {
        $where = ['c.open = 1', 's.status = :status', '(s.reservable_until IS NULL OR s.reservable_until > :now)'];
        $params = ['status' => 'available', 'now' => $now];
        $types = [];
        if (null !== $query->q && '' !== trim($query->q)) {
            $where[] = 'f.title LIKE :q';
            $params['q'] = '%'.addcslashes(trim($query->q), '%_\\').'%';
        }
        if (null !== $query->genre && '' !== $query->genre) {
            $where[] = 'JSON_CONTAINS(f.genres, JSON_QUOTE(:genre))';
            $params['genre'] = $query->genre;
        }
        if (null !== $query->city && '' !== $query->city) {
            $where[] = 'c.city_slug = :city';
            $params['city'] = $query->city;
        }
        if ('vost' === $query->version || 'vo' === $query->version) {
            // Same rule as the planner: a film made in the language of the cinema is in original version.
            $where[] = '(s.version = :version OR f.original_language = c.language)';
            $params['version'] = $query->version;
        } elseif (null !== $query->version && '' !== $query->version) {
            $where[] = 's.version = :version';
            $params['version'] = $query->version;
        }
        if (null !== $query->week) {
            $where[] = 's.local_date BETWEEN :weekStart AND DATE_ADD(:weekStart, INTERVAL 6 DAY)';
            $params['weekStart'] = $query->week;
        }
        if ([] !== $excludedSlugs) {
            $where[] = 'f.slug NOT IN (:excluded)';
            $params['excluded'] = $excludedSlugs;
            $types['excluded'] = ArrayParameterType::STRING;
        }

        return [implode(' AND ', $where), $params, $types];
    }
}

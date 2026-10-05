<?php

namespace App\Catalog\Repository;

use App\Catalog\Entity\Film;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
     * @return array|null ['slug', 'title', 'duration', 'releaseDate', 'genres', 'posterUrl', 'contentRating', 'synopsis'] or null
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

        return $this->createQueryBuilder('f')
            ->select('f.slug', 'f.title', 'f.duration', 'f.releaseDate', 'f.genres', 'f.posterUrl', 'f.contentRating', 'f.synopsis')
            ->where('f.slug IN (:slugs)')
            ->setParameter('slugs', $slugs)
            ->orderBy('f.title', $direction)
            ->getQuery()
            ->getArrayResult();
    }
}
